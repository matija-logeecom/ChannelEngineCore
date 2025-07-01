<?php

namespace ChannelEngineCore\Business\DTO;

use ChannelEngine\BusinessLogic\API\Orders\DTO\Order as ChannelEngineOrder;
use Order as PrestaShopOrderEntity;
use DateTime;

/**
 * PrestaShop-specific Order DTO
 */
class PrestaShopOrder
{
    private int $channelEngineOrderId;
    private string $channelOrderNo;
    private string $email;
    private string $status;
    private float $totalInclVat;
    private string $currencyCode;
    private DateTime $orderDate;
    private array $billingAddress;
    private array $shippingAddress;
    private array $lineItems;
    private ?string $paymentMethod;
    private ?float $shippingCostsInclVat;
    private ?float $totalVat;
    private ?float $shippingCostsVat;
    private ?float $subTotalInclVat;

    public function __construct(
        int $channelEngineOrderId,
        string $channelOrderNo,
        string $email,
        string $status,
        float $totalInclVat,
        string $currencyCode,
        DateTime $orderDate,
        array $billingAddress,
        array $shippingAddress,
        array $lineItems,
        ?string $paymentMethod = null,
        ?float $shippingCostsInclVat = null,
        ?float $totalVat = null,
        ?float $shippingCostsVat = null,
        ?float $subTotalInclVat = null
    ) {
        $this->channelEngineOrderId = $channelEngineOrderId;
        $this->channelOrderNo = $channelOrderNo;
        $this->email = $email;
        $this->status = $status;
        $this->totalInclVat = $totalInclVat;
        $this->currencyCode = $currencyCode;
        $this->orderDate = $orderDate;
        $this->billingAddress = $billingAddress;
        $this->shippingAddress = $shippingAddress;
        $this->lineItems = $lineItems;
        $this->paymentMethod = $paymentMethod;
        $this->shippingCostsInclVat = $shippingCostsInclVat;
        $this->totalVat = $totalVat;
        $this->shippingCostsVat = $shippingCostsVat;
        $this->subTotalInclVat = $subTotalInclVat;
    }

    /**
     * Creates a PrestaShopOrder from ChannelEngine order data
     *
     * @param ChannelEngineOrder $channelEngineOrder
     *
     * @return self
     */
    public static function fromChannelEngineOrder(ChannelEngineOrder $channelEngineOrder): self
    {
        return new self(
            $channelEngineOrder->getId(),
            $channelEngineOrder->getChannelOrderNo(),
            $channelEngineOrder->getEmail(),
            $channelEngineOrder->getStatus(),
            $channelEngineOrder->getTotalInclVat(),
            $channelEngineOrder->getCurrencyCode(),
            $channelEngineOrder->getOrderDate(),
            self::extractAddressData($channelEngineOrder->getBillingAddress()),
            self::extractAddressData($channelEngineOrder->getShippingAddress()),
            PrestaShopOrderDetail::fromChannelEngineLineItems($channelEngineOrder->getLines()),
            $channelEngineOrder->getPaymentMethod(),
            $channelEngineOrder->getShippingCostsInclVat(),
            $channelEngineOrder->getTotalVat(),
            $channelEngineOrder->getShippingCostsVat(),
            $channelEngineOrder->getSubTotalInclVat()
        );
    }

    /**
     * Validates the order data before processing
     *
     * @returns string[]
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->email) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }

        if (empty($this->channelOrderNo)) {
            $errors[] = 'Channel order number is required';
        }

        if (empty($this->billingAddress)) {
            $errors[] = 'Billing address is required';
        }

        if (empty($this->shippingAddress)) {
            $errors[] = 'Shipping address is required';
        }

        if (empty($this->lineItems)) {
            $errors[] = 'Order must contain at least one line item';
        } else {
            foreach ($this->lineItems as $index => $lineItem) {
                $lineItemErrors = $lineItem->validate();
                foreach ($lineItemErrors as $lineItemError) {
                    $errors[] = "Line item {$index}: {$lineItemError}";
                }
            }
        }

        if ($this->totalInclVat <= 0) {
            $errors[] = 'Order total must be greater than zero';
        }

        return $errors;
    }

    /**
     * Converts the DTO to a PrestaShop Order entity.
     *
     * @param int $cartId
     * @param int $customerId
     * @param int $currencyId
     * @param int $langId
     * @param int $shopId
     * @param int $shippingAddressId
     * @param int $billingAddressId
     * @param int $carrierId
     * @param int $orderStateId
     *
     * @return PrestaShopOrderEntity
     */
    public function toPrestaShopOrderEntity(
        int $cartId,
        int $customerId,
        int $currencyId,
        int $langId,
        int $shopId,
        int $shippingAddressId,
        int $billingAddressId,
        int $carrierId,
        int $orderStateId
    ): PrestaShopOrderEntity
    {
        $order = new PrestaShopOrderEntity();
        $order->id_cart = $cartId;
        $order->id_customer = $customerId;
        $order->id_currency = $currencyId;
        $order->id_lang = $langId;
        $order->id_shop = $shopId;
        $order->id_address_delivery = $shippingAddressId;
        $order->id_address_invoice = $billingAddressId;
        $order->id_carrier = $carrierId;
        $order->module = 'channelenginecore';
        $order->reference = $this->getShortReference();
        $order->payment = $this->getPaymentMethod() ?: 'ChannelEngine';
        $order->total_paid = $this->getTotalInclVat();
        $order->total_paid_tax_incl = $this->getTotalInclVat();
        $order->total_paid_tax_excl = $this->getTotalInclVat() - ($this->getTotalVat() ?: 0);
        $order->total_paid_real = $this->getTotalInclVat();
        $order->total_products = $this->getSubTotalInclVat() ?: 0;
        $order->total_products_wt = $this->getSubTotalInclVat() ?: 0;
        $order->total_shipping = $this->getShippingCostsInclVat() ?: 0;
        $order->total_shipping_tax_incl = $this->getShippingCostsInclVat() ?: 0;
        $order->total_shipping_tax_excl = ($this->getShippingCostsInclVat() ?: 0) - ($this->getShippingCostsVat() ?: 0);
        $order->conversion_rate = 1;
        $order->date_add = $this->getOrderDate()->format('Y-m-d H:i:s');
        $order->current_state = $orderStateId;
        $order->secure_key = md5(uniqid(rand(), true));

        return $order;
    }

    /**
     * @return string
     */
    public function getShortReference(): string
    {
        $orderNumber = str_replace('CE-TEST-', '', $this->channelOrderNo);
        return 'CE-' . $orderNumber;
    }

    /**
     * @return int
     */
    public function getChannelEngineOrderId(): int
    {
        return $this->channelEngineOrderId;
    }

    /**
     * @return string
     */
    public function getChannelOrderNo(): string
    {
        return $this->channelOrderNo;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return float
     */
    public function getTotalInclVat(): float
    {
        return $this->totalInclVat;
    }

    /**
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @return DateTime
     */
    public function getOrderDate(): DateTime
    {
        return $this->orderDate;
    }

    /**
     * @return array
     */
    public function getBillingAddress(): array
    {
        return $this->billingAddress;
    }

    /**
     * @return array
     */
    public function getShippingAddress(): array
    {
        return $this->shippingAddress;
    }

    /**
     * @return array
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @return string|null
     */
    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    /**
     * @return float|null
     */
    public function getShippingCostsInclVat(): ?float
    {
        return $this->shippingCostsInclVat;
    }

    /**
     * @return float|null
     */
    public function getTotalVat(): ?float
    {
        return $this->totalVat;
    }

    /**
     * @return float|null
     */
    public function getShippingCostsVat(): ?float
    {
        return $this->shippingCostsVat;
    }

    /**
     * @return float|null
     */
    public function getSubTotalInclVat(): ?float
    {
        return $this->subTotalInclVat;
    }

    /**
     * Extract address data from ChannelEngine address object
     *
     * @param $addressObject
     *
     * @return array|string[]
     */
    private static function extractAddressData($addressObject): array
    {
        return [
            'firstName' => $addressObject->getFirstName() ?: 'ChannelEngine',
            'lastName' => $addressObject->getLastName() ?: 'Customer',
            'companyName' => $addressObject->getCompanyName() ?: '',
            'streetName' => $addressObject->getStreetName() ?: '',
            'houseNumber' => $addressObject->getHouseNumber() ?: '',
            'houseNumberAddition' => $addressObject->getHouseNumberAddition() ?: '',
            'zipCode' => $addressObject->getZipCode() ?: '',
            'city' => $addressObject->getCity() ?: '',
            'countryIso' => $addressObject->getCountryIso() ?: '',
            'region' => $addressObject->getRegion() ?: '',
        ];
    }
}