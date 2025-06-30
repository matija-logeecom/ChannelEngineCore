<?php

namespace ChannelEngineCore\Business\Service;

use Address;
use Carrier;
use ChannelEngine\BusinessLogic\Orders\OrdersService;
use ChannelEngine\BusinessLogic\API\Orders\DTO\Order as ChannelEngineOrder;
use ChannelEngine\BusinessLogic\Orders\Domain\CreateResponse;
use ChannelEngine\Infrastructure\Logger\Logger;
use Cart;
use Configuration;
use Context;
use Country;
use Currency;
use Customer;
use Order as PrestaShopOrder;
use OrderDetail;
use OrderHistory;
use PrestaShopDatabaseException;
use PrestaShopException;
use Product;
use State;
use Tools;
use Validate;
/**
 * PrestaShop OrdersService implementation
 */
class PrestaShopOrdersService extends OrdersService
{
    /**
     * Creates new orders in the shop system and returns CreateResponse.
     *
     * @param ChannelEngineOrder $order
     * @return CreateResponse
     */
    public function create(ChannelEngineOrder $order): CreateResponse
    {
        try {
            // Check if order already exists
            $existingOrder = $this->findExistingOrder($order);

            if ($existingOrder) {
                return $this->updateExistingOrder($existingOrder, $order);
            } else {
                return $this->createNewOrder($order);
            }
        } catch (\Exception $e) {
            Logger::logError(
                "Failed to create/update order {$order->getId()}: " . $e->getMessage(),
                'PrestaShopOrdersService',
                [
                    'channelengine_order_id' => $order->getId(),
                    'channel_order_no' => $order->getChannelOrderNo(),
                    'exception' => $e->getMessage()
                ]
            );

            return new CreateResponse(false, $e->getMessage());
        }
    }

    /**
     * Find existing PrestaShop order by ChannelEngine order data
     *
     * @param ChannelEngineOrder $channelEngineOrder
     * @return PrestaShopOrder|null
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function findExistingOrder(ChannelEngineOrder $channelEngineOrder): ?PrestaShopOrder
    {
        // Convert to short reference format for searching
        $shortReference = $this->getShortReference($channelEngineOrder->getChannelOrderNo());

        // Try to find by short reference
        $orders = PrestaShopOrder::getByReference($shortReference);

        if ($orders && $orders->count() > 0) {
            $firstOrder = $orders->getFirst();
            return new PrestaShopOrder($firstOrder['id_order']);
        }

        return null;
    }

    /**
     * Create new PrestaShop order from ChannelEngine order
     *
     * @param ChannelEngineOrder $channelEngineOrder
     * @return CreateResponse
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createNewOrder(ChannelEngineOrder $channelEngineOrder): CreateResponse
    {
        $context = Context::getContext();

        // 1. Find or create customer
        $customer = $this->findOrCreateCustomer($channelEngineOrder);

        // 2. Create cart
        $cart = $this->createCartFromOrder($channelEngineOrder, $customer);

        // 3. Get payment method
        $paymentMethod = $channelEngineOrder->getPaymentMethod() ?: 'ChannelEngine';

        // 4. Get currency
        $currency = Currency::getIdByIsoCode($channelEngineOrder->getCurrencyCode());
        if (!$currency) {
            $currency = (int)Configuration::get('PS_CURRENCY_DEFAULT');
        }

        // 5. Get order state
        $orderState = $this->getOrderStateFromChannelEngineStatus($channelEngineOrder->getStatus());

        // 6. Calculate totals
        $totalPaid = $channelEngineOrder->getTotalInclVat();
        $totalShipping = $channelEngineOrder->getShippingCostsInclVat();

        // 7. Create addresses
        $billingAddressId = $this->createAddress($channelEngineOrder->getBillingAddress(), $customer);
        $shippingAddressId = $this->createAddress($channelEngineOrder->getShippingAddress(), $customer);

        $defaultCarrierId = (int)Configuration::get('PS_CARRIER_DEFAULT');
        if (!$defaultCarrierId) {
            // Fallback: get any active carrier
            $carriers = Carrier::getCarriers((int)$context->language->id, true);
            $defaultCarrierId = !empty($carriers) ? (int)$carriers[0]['id_carrier'] : 1;
        }

        $channelOrderNo = $channelEngineOrder->getChannelOrderNo(); // "CE-TEST-52254"
        $orderNumber = str_replace('CE-TEST-', '', $channelOrderNo); // "52254"

        // 8. Create the PrestaShop order
        $order = new PrestaShopOrder();
        $order->id_cart = $cart->id;
        $order->id_customer = $customer->id;
        $order->id_currency = $currency;
        $order->id_lang = $context->language->id;
        $order->id_shop = $context->shop->id;
        $order->id_address_delivery = $shippingAddressId;
        $order->id_address_invoice = $billingAddressId;
        $order->id_carrier = $defaultCarrierId;
        $order->module = 'channelenginecore';
        $order->reference = $this->getShortReference($channelEngineOrder->getChannelOrderNo());        $order->payment = $paymentMethod;
        $order->total_paid = $totalPaid;
        $order->total_paid_tax_incl = $totalPaid;
        $order->total_paid_tax_excl = $totalPaid - ($channelEngineOrder->getTotalVat() ?: 0);
        $order->total_paid_real = $totalPaid;
        $order->total_products = $channelEngineOrder->getSubTotalInclVat() ?: 0;
        $order->total_products_wt = $channelEngineOrder->getSubTotalInclVat() ?: 0;
        $order->total_shipping = $totalShipping;
        $order->total_shipping_tax_incl = $totalShipping;
        $order->total_shipping_tax_excl = $totalShipping - ($channelEngineOrder->getShippingCostsVat() ?: 0);
        $order->conversion_rate = 1;
        $order->date_add = $channelEngineOrder->getOrderDate()->format('Y-m-d H:i:s');
        $order->current_state = $orderState;
        $order->secure_key = md5(uniqid(rand(), true));

        if ($order->add()) {
            // 9. Add order details (line items)
            $this->addOrderDetails($order, $channelEngineOrder);

            // 10. Set order state history
            $orderHistory = new OrderHistory();
            $orderHistory->id_order = $order->id;
            $orderHistory->changeIdOrderState($orderState, $order->id);
            $orderHistory->addWithemail();

            Logger::logInfo(
                "Created PrestaShop order {$order->id} from ChannelEngine order {$channelEngineOrder->getId()}",
                'PrestaShopOrdersService'
            );

            $createResponse = new CreateResponse();
            $createResponse->setSuccess(true);
            $createResponse->setMessage("Order created successfully");
            $createResponse->setShopOrderId($order->reference);

            return $createResponse;
        } else {
            $createResponse = new CreateResponse();
            $createResponse->setSuccess(false);
            $createResponse->setMessage("Failed to create order in PrestaShop");
            $createResponse->setShopOrderId($order->reference);

            return $createResponse;
        }
    }

    /**
     * Find or create customer from ChannelEngine order
     *
     * @param ChannelEngineOrder $channelEngineOrder
     *
     * @return Customer
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function findOrCreateCustomer(ChannelEngineOrder $channelEngineOrder): Customer
    {
        $email = $channelEngineOrder->getEmail();
        $customer = new Customer();
        $customer->getByEmail($email);

        if (!Validate::isLoadedObject($customer)) {
            // Create new customer
            $billingAddress = $channelEngineOrder->getBillingAddress();

            $customer->email = $email;
            $customer->firstname = $billingAddress->getFirstName() ?: 'ChannelEngine';
            $customer->lastname = $billingAddress->getLastName() ?: 'Customer';

            // FIX: Generate a stronger password and hash it properly
            $plainPassword = Tools::passwdGen(12); // Longer password
            $customer->passwd = Tools::hash($plainPassword); // Hash the password

            $customer->active = 1;
            $customer->add();
        }

        return $customer;
    }

    /**
     * Create address from ChannelEngine address data
     *
     * @param $addressData
     * @param Customer $customer
     * @return int
     */
    private function createAddress($addressData, Customer $customer): int
    {
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->firstname = $addressData->getFirstName() ?: 'ChannelEngine';
        $address->lastname = $addressData->getLastName() ?: 'Customer';
        $address->company = $addressData->getCompanyName() ?: '';

        // FIX: Use correct method names
        $address->address1 = $addressData->getStreetName() . ' ' . $addressData->getHouseNumber();
        $address->address2 = $addressData->getHouseNumberAddition() ?: '';

        $address->postcode = $addressData->getZipCode() ?: '';
        $address->city = $addressData->getCity() ?: '';
        $address->phone = ''; // Phone is not in the Address DTO, it's in the Order

        // Try to find country by ISO code
        $countryId = Country::getByIso($addressData->getCountryIso());
        $address->id_country = $countryId ?: (int)Configuration::get('PS_COUNTRY_DEFAULT');

        // Try to find state if provided
        if ($addressData->getRegion()) {
            $stateId = State::getIdByName($addressData->getRegion());
            $address->id_state = $stateId ?: 0;
        }

        $address->alias = 'ChannelEngine';
        $address->add();

        return $address->id;
    }

    /**
     * Create cart from ChannelEngine order
     *
     * @param ChannelEngineOrder $channelEngineOrder
     * @param Customer $customer
     * @return Cart
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function createCartFromOrder(ChannelEngineOrder $channelEngineOrder, Customer $customer): Cart
    {
        $context = Context::getContext();

        $cart = new Cart();
        $cart->id_customer = $customer->id;
        $cart->id_currency = Currency::getIdByIsoCode($channelEngineOrder->getCurrencyCode()) ?: (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = $context->language->id;
        $cart->id_shop = $context->shop->id;
        $cart->add();

        // Add products to cart
        foreach ($channelEngineOrder->getLines() as $lineItem) {
            $productId = $this->findProductByMerchantProductNo($lineItem->getMerchantProductNo());
            if ($productId) {
                $cart->updateQty(
                    $lineItem->getQuantity(),
                    $productId,
                    null, // id_product_attribute
                    false, // add/subtract
                    'up' // type
                );
            }
        }

        $cart->update();
        return $cart;
    }

    /**
     * Find PrestaShop product ID by merchant product number
     *
     * @param string $merchantProductNo
     * @return int|null
     */
    private function findProductByMerchantProductNo(string $merchantProductNo): ?int
    {
        // Assuming merchant product number matches PrestaShop product ID
        $productId = (int)$merchantProductNo;
        $product = new Product($productId);

        if (Validate::isLoadedObject($product)) {
            return $productId;
        }

        return null;
    }

    /**
     * Map ChannelEngine status to PrestaShop order state
     *
     * @param string $channelEngineStatus
     * @return int
     */
    private function getOrderStateFromChannelEngineStatus(string $channelEngineStatus): int
    {
        return match($channelEngineStatus) {
            'IN_PROGRESS' => Configuration::get('PS_OS_PREPARATION'),
            'SHIPPED' => Configuration::get('PS_OS_SHIPPING'),
            'CLOSED' => Configuration::get('PS_OS_DELIVERED'),
            'CANCELED' => Configuration::get('PS_OS_CANCELED'),
            'RETURNED' => Configuration::get('PS_OS_REFUND'),
            default => Configuration::get('PS_OS_PAYMENT')
        };
    }

    /**
     * Add order details (line items) to PrestaShop order
     *
     * @param PrestaShopOrder $order
     * @param ChannelEngineOrder $channelEngineOrder
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function addOrderDetails(PrestaShopOrder $order, ChannelEngineOrder $channelEngineOrder): void
    {
        $context = Context::getContext();

        foreach ($channelEngineOrder->getLines() as $lineItem) {
            $productId = $this->findProductByMerchantProductNo($lineItem->getMerchantProductNo());

            if ($productId) {
                $product = new Product($productId);

                $orderDetail = new OrderDetail();
                $orderDetail->id_order = $order->id;
                $orderDetail->product_id = $productId;
                $orderDetail->id_warehouse = 0;
                $orderDetail->id_shop = $context->shop->id;
                $orderDetail->product_name = $product->name[Context::getContext()->language->id] ?: $lineItem->getDescription();
                $orderDetail->product_quantity = $lineItem->getQuantity();
                $orderDetail->unit_price_tax_incl = $lineItem->getUnitPriceInclVat();
                $orderDetail->unit_price_tax_excl = $lineItem->getUnitPriceInclVat() - ($lineItem->getLineVat() / $lineItem->getQuantity());
                $orderDetail->total_price_tax_incl = $lineItem->getLineTotalInclVat();
                $orderDetail->total_price_tax_excl = $lineItem->getLineTotalInclVat() - $lineItem->getLineVat();

                $orderDetail->product_price = $lineItem->getUnitPriceInclVat() - ($lineItem->getLineVat() / $lineItem->getQuantity()); // Excl VAT
                $orderDetail->original_product_price = $orderDetail->product_price;
                $orderDetail->unit_price_tax_incl = $lineItem->getUnitPriceInclVat();
                $orderDetail->unit_price_tax_excl = $orderDetail->product_price;
                $orderDetail->total_price_tax_incl = $lineItem->getLineTotalInclVat();
                $orderDetail->total_price_tax_excl = $lineItem->getLineTotalInclVat() - $lineItem->getLineVat();
                $orderDetail->product_reference = $product->reference ?: '';
                $orderDetail->product_supplier_reference = $product->supplier_reference ?: '';
                $orderDetail->product_weight = $product->weight ?: 0;
                $orderDetail->id_customization = 0;
                $orderDetail->product_quantity_discount = 0;
                $orderDetail->product_ean13 = $product->ean13 ?: '';
                $orderDetail->product_isbn = $product->isbn ?: '';
                $orderDetail->product_upc = $product->upc ?: '';
                $orderDetail->product_mpn = $product->mpn ?: '';
                $orderDetail->add();
            }
        }
    }

    /**
     * Update existing PrestaShop order with ChannelEngine data
     *
     * @param PrestaShopOrder $prestaShopOrder
     * @param ChannelEngineOrder $channelEngineOrder
     *
     * @return CreateResponse
     *
     * @throws PrestaShopException
     */
    private function updateExistingOrder(PrestaShopOrder $prestaShopOrder, ChannelEngineOrder $channelEngineOrder): CreateResponse
    {
        // Update order status if changed
        $newState = $this->getOrderStateFromChannelEngineStatus($channelEngineOrder->getStatus());

        if ($prestaShopOrder->current_state != $newState) {
            $orderHistory = new OrderHistory();
            $orderHistory->id_order = $prestaShopOrder->id;
            $orderHistory->changeIdOrderState($newState, $prestaShopOrder->id);
            $orderHistory->addWithemail();

            Logger::logInfo(
                "Updated order {$prestaShopOrder->id} status to {$channelEngineOrder->getStatus()}",
                'PrestaShopOrdersService'
            );
        }

        return new CreateResponse(true, "Order updated successfully", $prestaShopOrder->reference);
    }

    /**
     * Convert ChannelEngine order number to short reference format
     *
     * @param string $channelOrderNo
     * @return string
     */
    private function getShortReference(string $channelOrderNo): string
    {
        // Extract just the number part from "CE-TEST-52254" -> "52254"
        $orderNumber = str_replace('CE-TEST-', '', $channelOrderNo);

        // Create short reference "CE-52254" (8 chars, fits in VARCHAR(9))
        return 'CE-' . $orderNumber;
    }
}