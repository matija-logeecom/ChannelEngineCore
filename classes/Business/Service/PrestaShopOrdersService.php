<?php

namespace ChannelEngineCore\Business\Service;

use Address;
use Carrier;
use ChannelEngine\BusinessLogic\Orders\OrdersService;
use ChannelEngine\BusinessLogic\API\Orders\DTO\Order as ChannelEngineOrder;
use ChannelEngine\BusinessLogic\Orders\Domain\CreateResponse;
use ChannelEngine\Infrastructure\Logger\Logger;
use ChannelEngineCore\Business\DTO\PrestaShopOrder;
use ChannelEngineCore\Business\DTO\PrestaShopOrderDetail;
use Cart;
use Configuration;
use Context;
use Country;
use Currency;
use Customer;
use Exception;
use Order as PrestaShopOrderEntity;
use OrderHistory;
use PrestaShopDatabaseException;
use PrestaShopException;
use Product;
use State;
use Tools;
use Validate;

class PrestaShopOrdersService extends OrdersService
{
    /**
     * Creates new orders in the shop system and returns CreateResponse.
     *
     * @param ChannelEngineOrder $order
     *
     * @return CreateResponse
     */
    public function create(ChannelEngineOrder $order): CreateResponse
    {
        try {
            $prestaShopOrder = PrestaShopOrder::fromChannelEngineOrder($order);

            $validationErrors = $prestaShopOrder->validate();
            if (!empty($validationErrors)) {
                return $this->createErrorResponse('Order validation failed: ' . implode(', ', $validationErrors));
            }

            $existingOrder = $this->findExistingOrder($prestaShopOrder);
            if ($existingOrder) {
                return $this->updateExistingOrder($existingOrder, $prestaShopOrder);
            }

            return $this->createNewOrder($prestaShopOrder);

        } catch (Exception $e) {
            Logger::logError(
                "Failed to create/update order {$order->getId()}: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channelengine_order_id' => $order->getId(),
                    'channel_order_no' => $order->getChannelOrderNo(),
                    'exception_type' => get_class($e),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Find existing PrestaShop order by ChannelEngine order data
     *
     * @param PrestaShopOrder $prestaShopOrder
     *
     * @return PrestaShopOrderEntity|null
     */
    private function findExistingOrder(PrestaShopOrder $prestaShopOrder): ?PrestaShopOrderEntity
    {
        try {
            $shortReference = $prestaShopOrder->getShortReference();
            $orders = PrestaShopOrderEntity::getByReference($shortReference);

            if ($orders && $orders->count() > 0) {
                $firstOrder = $orders->getFirst();
                if (!$firstOrder || !isset($firstOrder['id_order'])) {
                    return null;
                }
                return new PrestaShopOrderEntity($firstOrder['id_order']);
            }

            return null;
        } catch (Exception $e) {
            Logger::logError(
                "Error finding existing order: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channel_order_no' => $prestaShopOrder->getChannelOrderNo(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return null;
        }
    }

    /**
     * Create new PrestaShop order from ChannelEngine order
     *
     * @param PrestaShopOrder $prestaShopOrder
     *
     * @return CreateResponse
     */
    private function createNewOrder(PrestaShopOrder $prestaShopOrder): CreateResponse
    {
        try {
            $customer = $this->findOrCreateCustomer($prestaShopOrder);
            $cart = $this->createCartFromOrder($prestaShopOrder, $customer);
            $order = $this->buildPrestaShopOrder($prestaShopOrder, $customer, $cart);

            if (!$order->add()) {
                throw new Exception("Failed to create order in PrestaShop");
            }

            $this->addOrderDetails($order, $prestaShopOrder);
            $this->setOrderState($order, $prestaShopOrder);

            Logger::logInfo(
                "Created PrestaShop order {$order->id} " .
                "from ChannelEngine order {$prestaShopOrder->getChannelEngineOrderId()}",
                'PrestaShopOrdersService'
            );

            return $this->createSuccessResponse("Order created successfully", $order->reference);
        } catch (Exception $e) {
            Logger::logError(
                "Failed to create new order: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channelengine_order_id' => $prestaShopOrder->getChannelEngineOrderId(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Build PrestaShop order object with all required fields
     *
     * @param PrestaShopOrder $prestaShopOrder
     * @param Customer $customer
     * @param Cart $cart
     *
     * @return PrestaShopOrderEntity
     *
     * @throws Exception
     */
    private function buildPrestaShopOrder(PrestaShopOrder $prestaShopOrder,
                                          Customer $customer, Cart $cart): PrestaShopOrderEntity
    {
        try {
            $context = Context::getContext();

            $billingAddressId = $this->createAddress($prestaShopOrder->getBillingAddress(), $customer);
            $shippingAddressId = $this->createAddress($prestaShopOrder->getShippingAddress(), $customer);
            $currencyId = $this->getCurrencyId($prestaShopOrder->getCurrencyCode());
            $carrierId = $this->getDefaultCarrierId($context);
            $orderStateId = $this->getOrderStateFromChannelEngineStatus($prestaShopOrder->getStatus());

            return $prestaShopOrder->toPrestaShopOrderEntity(
                $cart->id,
                $customer->id,
                $currencyId,
                $context->language->id,
                $context->shop->id,
                $shippingAddressId,
                $billingAddressId,
                $carrierId,
                $orderStateId
            );
        } catch (Exception $e) {
            Logger::logError(
                "Failed to build PrestaShop order: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channelengine_order_id' => $prestaShopOrder->getChannelEngineOrderId(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    /**
     * Get currency ID by ISO code
     *
     * @param string $currencyCode
     *
     * @return int
     */
    private function getCurrencyId(string $currencyCode): int
    {
        try {
            $currency = Currency::getIdByIsoCode($currencyCode);
            if (!$currency) {
                $currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
            }

            return $currency;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to get currency ID: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'currency_code' => $currencyCode,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return (int)Configuration::get('PS_CURRENCY_DEFAULT');
        }
    }

    /**
     * Get default carrier ID
     *
     * @param Context $context
     *
     * @return int
     */
    private function getDefaultCarrierId(Context $context): int
    {
        try {
            $defaultCarrierId = (int)Configuration::get('PS_CARRIER_DEFAULT');
            if (!$defaultCarrierId) {
                $carriers = Carrier::getCarriers((int)$context->language->id, true);
                $defaultCarrierId = !empty($carriers) ? (int)$carriers[0]['id_carrier'] : 1;
            }

            return $defaultCarrierId;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to get default carrier ID: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return 1;
        }
    }

    /**
     * Set order state history
     *
     * @param PrestaShopOrderEntity $order
     * @param PrestaShopOrder $prestaShopOrder
     */
    private function setOrderState(PrestaShopOrderEntity $order, PrestaShopOrder $prestaShopOrder): void
    {
        try {
            $orderState = $this->getOrderStateFromChannelEngineStatus($prestaShopOrder->getStatus());
            $orderHistory = new OrderHistory();
            $orderHistory->id_order = $order->id;
            $orderHistory->changeIdOrderState($orderState, $order->id);
            $orderHistory->addWithemail();
        } catch (Exception $e) {
            Logger::logError(
                "Failed to set order state: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'order_id' => $order->id,
                    'target_state' => $prestaShopOrder->getStatus(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
        }
    }

    /**
     * Find or create customer from ChannelEngine order
     *
     * @param PrestaShopOrder $prestaShopOrder
     *
     * @return Customer
     *
     * @throws Exception
     */
    private function findOrCreateCustomer(PrestaShopOrder $prestaShopOrder): Customer
    {
        try {
            $email = $prestaShopOrder->getEmail();
            $customer = new Customer();
            $customer->getByEmail($email);

            if (!Validate::isLoadedObject($customer)) {
                $customer = $this->createNewCustomer($prestaShopOrder, $email);
            }

            return $customer;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to find or create customer: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'email' => $prestaShopOrder->getEmail(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    /**
     * Create new customer
     *
     * @param PrestaShopOrder $prestaShopOrder
     * @param string $email
     *
     * @return Customer
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createNewCustomer(PrestaShopOrder $prestaShopOrder, string $email): Customer
    {
        try {
            $billingAddress = $prestaShopOrder->getBillingAddress();

            $customer = new Customer();
            $customer->email = $email;
            $customer->firstname = $billingAddress['firstName'];
            $customer->lastname = $billingAddress['lastName'];
            $customer->passwd = Tools::hash(Tools::passwdGen(12));
            $customer->active = 1;

            if (!$customer->add()) {
                throw new Exception("Failed to create customer for email: $email");
            }

            return $customer;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to create new customer: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'email' => $email,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    /**
     * Create address from address data array
     *
     * @param array $addressData
     * @param Customer $customer
     *
     * @return int
     *
     * @throws Exception
     */
    private function createAddress(array $addressData, Customer $customer): int
    {
        try {
            $address = new Address();
            $address->id_customer = $customer->id;
            $address->firstname = $addressData['firstName'];
            $address->lastname = $addressData['lastName'];
            $address->company = $addressData['companyName'];
            $address->address1 = $addressData['streetName'] . ' ' . $addressData['houseNumber'];
            $address->address2 = $addressData['houseNumberAddition'];
            $address->postcode = $addressData['zipCode'];
            $address->city = $addressData['city'];
            $address->phone = '';
            $address->id_country = $this->getCountryId($addressData['countryIso']);
            $address->id_state = $this->getStateId($addressData['region']);
            $address->alias = 'ChannelEngine';

            if (!$address->add()) {
                throw new Exception("Failed to create address for customer {$customer->id}");
            }

            return $address->id;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to create address: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'customer_id' => $customer->id,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    /**
     * Get country ID by ISO code
     *
     * @param string $countryIso
     *
     * @return int
     */
    private function getCountryId(string $countryIso): int
    {
        try {
            $countryId = Country::getByIso($countryIso);

            return $countryId ?: (int)Configuration::get('PS_COUNTRY_DEFAULT');
        } catch (Exception $e) {
            Logger::logError(
                "Failed to get country ID: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'country_iso' => $countryIso,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return (int)Configuration::get('PS_COUNTRY_DEFAULT');
        }
    }

    /**
     * Get state ID by name
     *
     * @param string|null $region
     *
     * @return int
     */
    private function getStateId(?string $region): int
    {
        if (!$region) {
            return 0;
        }

        try {
            $stateId = State::getIdByName($region);

            return $stateId ?: 0;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to get state ID: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'region' => $region,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return 0;
        }
    }

    /**
     * Create cart from ChannelEngine order
     *
     * @param PrestaShopOrder $prestaShopOrder
     * @param Customer $customer
     *
     * @return Cart
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createCartFromOrder(PrestaShopOrder $prestaShopOrder, Customer $customer): Cart
    {
        try {
            $context = Context::getContext();

            $cart = new Cart();
            $cart->id_customer = $customer->id;
            $cart->id_currency = Currency::getIdByIsoCode($prestaShopOrder->getCurrencyCode()) ?:
                (int)Configuration::get('PS_CURRENCY_DEFAULT');
            $cart->id_lang = $context->language->id;
            $cart->id_shop = $context->shop->id;

            if (!$cart->add()) {
                throw new Exception("Failed to create cart for customer {$customer->id}");
            }

            $this->addProductsToCart($cart, $prestaShopOrder);
            $cart->update();

            return $cart;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to create cart: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'customer_id' => $customer->id,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
            throw $e;
        }
    }

    /**
     * Add products to cart
     *
     * @param Cart $cart
     * @param PrestaShopOrder $prestaShopOrder
     */
    private function addProductsToCart(Cart $cart, PrestaShopOrder $prestaShopOrder): void
    {
        foreach ($prestaShopOrder->getLineItems() as $lineItem) {
            try {
                $productId = $this->findProductByMerchantProductNo($lineItem->getMerchantProductNo());
                if ($productId) {
                    $cart->updateQty(
                        $lineItem->getQuantity(),
                        $productId,
                        null,
                        false,
                        'up'
                    );
                } else {
                    Logger::logWarning(
                        "Product not found for merchant product no: {$lineItem->getMerchantProductNo()}",
                        'PrestaShopOrdersService',
                        ['cart_id' => $cart->id]
                    );
                }
            } catch (Exception $e) {
                Logger::logError(
                    "Failed to add product to cart: " . $e->getMessage(),
                    'PrestaShopOrdersService',
                    [
                        'cart_id' => $cart->id,
                        'merchant_product_no' => $lineItem->getMerchantProductNo(),
                        'stack_trace' => $e->getTraceAsString()
                    ]
                );
            }
        }
    }

    /**
     * Find PrestaShop product ID by merchant product number
     *
     * @param string $merchantProductNo
     *
     * @return int|null
     */
    private function findProductByMerchantProductNo(string $merchantProductNo): ?int
    {
        try {
            $productId = (int)$merchantProductNo;
            $product = new Product($productId);

            if (Validate::isLoadedObject($product)) {
                return $productId;
            }

            return null;
        } catch (Exception $e) {
            Logger::logError(
                "Failed to find product: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'merchant_product_no' => $merchantProductNo,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return null;
        }
    }

    /**
     * Map ChannelEngine status to PrestaShop order state
     *
     * @param string $channelEngineStatus
     *
     * @return int
     */
    private function getOrderStateFromChannelEngineStatus(string $channelEngineStatus): int
    {
        try {
            return match($channelEngineStatus) {
                'IN_PROGRESS' => Configuration::get('PS_OS_PREPARATION'),
                'SHIPPED' => Configuration::get('PS_OS_SHIPPING'),
                'CLOSED' => Configuration::get('PS_OS_DELIVERED'),
                'CANCELED' => Configuration::get('PS_OS_CANCELED'),
                'RETURNED' => Configuration::get('PS_OS_REFUND'),
                default => Configuration::get('PS_OS_PAYMENT')
            };
        } catch (Exception $e) {
            Logger::logError(
                "Failed to get order state: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channel_engine_status' => $channelEngineStatus,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return Configuration::get('PS_OS_PAYMENT');
        }
    }

    /**
     * Add order details (line items) to PrestaShop order
     *
     * @param PrestaShopOrderEntity $order
     * @param PrestaShopOrder $prestaShopOrder
     */
    private function addOrderDetails(PrestaShopOrderEntity $order, PrestaShopOrder $prestaShopOrder): void
    {
        try {
            $context = Context::getContext();

            foreach ($prestaShopOrder->getLineItems() as $lineItem) {
                $this->createOrderDetail($order, $lineItem, $context);
            }
        } catch (Exception $e) {
            Logger::logError(
                "Failed to add order details: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'order_id' => $order->id,
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
        }
    }

    /**
     * Create single order detail
     *
     * @param PrestaShopOrderEntity $order
     * @param PrestaShopOrderDetail $lineItem
     * @param Context $context
     */
    private function createOrderDetail(PrestaShopOrderEntity $order,
                                       PrestaShopOrderDetail $lineItem, Context $context): void
    {
        try {
            $productId = $this->findProductByMerchantProductNo($lineItem->getMerchantProductNo());

            if (!$productId) {
                Logger::logWarning(
                    "Product not found for merchant product no: {$lineItem->getMerchantProductNo()}",
                    'PrestaShopOrdersService',
                    ['order_id' => $order->id]
                );

                return;
            }

            $product = new Product($productId);
            $orderDetail = $lineItem->toPrestaShopOrderDetail($order->id, $product, $context);

            if (!$orderDetail->add()) {
                Logger::logError(
                    "Failed to add order detail for product {$productId}",
                    'PrestaShopOrdersService',
                    ['order_id' => $order->id, 'product_id' => $productId]
                );
            }
        } catch (Exception $e) {
            Logger::logError(
                "Failed to create order detail: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'order_id' => $order->id,
                    'merchant_product_no' => $lineItem->getMerchantProductNo(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );
        }
    }

    /**
     * Update existing PrestaShop order with ChannelEngine data
     *
     * @param PrestaShopOrderEntity $prestaShopOrderEntity
     * @param PrestaShopOrder $prestaShopOrder
     *
     * @return CreateResponse
     */
    private function updateExistingOrder(PrestaShopOrderEntity $prestaShopOrderEntity,
                                         PrestaShopOrder $prestaShopOrder): CreateResponse
    {
        try {
            $newState = $this->getOrderStateFromChannelEngineStatus($prestaShopOrder->getStatus());

            if ($prestaShopOrderEntity->current_state != $newState) {
                $orderHistory = new OrderHistory();
                $orderHistory->id_order = $prestaShopOrderEntity->id;
                $orderHistory->changeIdOrderState($newState, $prestaShopOrderEntity->id);
                $orderHistory->addWithemail();

                Logger::logInfo(
                    "Updated order {$prestaShopOrderEntity->id} status to {$prestaShopOrder->getStatus()}",
                    'PrestaShopOrdersService'
                );
            }

            return $this->createSuccessResponse("Order updated successfully",
                $prestaShopOrderEntity->reference);
        } catch (Exception $e) {
            Logger::logError(
                "Failed to update existing order: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'prestashop_order_id' => $prestaShopOrderEntity->id,
                    'channelengine_order_id' => $prestaShopOrder->getChannelEngineOrderId(),
                    'stack_trace' => $e->getTraceAsString()
                ]
            );

            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Create success response
     *
     * @param string $message
     * @param string $shopOrderId
     *
     * @return CreateResponse
     */
    private function createSuccessResponse(string $message, string $shopOrderId): CreateResponse
    {
        $response = new CreateResponse();
        $response->setSuccess(true);
        $response->setMessage($message);
        $response->setShopOrderId($shopOrderId);

        return $response;
    }

    /**
     * Create error response
     *
     * @param string $message
     *
     * @return CreateResponse
     */
    private function createErrorResponse(string $message): CreateResponse
    {
        $response = new CreateResponse();
        $response->setSuccess(false);
        $response->setMessage($message);

        return $response;
    }
}