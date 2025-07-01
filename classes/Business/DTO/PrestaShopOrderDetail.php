<?php

namespace ChannelEngineCore\Business\DTO;

use Context;
use OrderDetail;
use Product;

class PrestaShopOrderDetail
{
    private string $merchantProductNo;
    private string $description;
    private int $quantity;
    private float $unitPriceInclVat;
    private float $lineTotalInclVat;
    private float $lineVat;

    public function __construct(
        string $merchantProductNo,
        string $description,
        int $quantity,
        float $unitPriceInclVat,
        float $lineTotalInclVat,
        float $lineVat
    ) {
        $this->merchantProductNo = $merchantProductNo;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPriceInclVat = $unitPriceInclVat;
        $this->lineTotalInclVat = $lineTotalInclVat;
        $this->lineVat = $lineVat;
    }

    /**
     * Creates PrestaShopOrderDetail from ChannelEngine line item
     *
     * @param $lineItem
     *
     * @return self
     */
    public static function fromChannelEngineLineItem($lineItem): self
    {
        return new self(
            $lineItem->getMerchantProductNo(),
            $lineItem->getDescription() ?: '',
            $lineItem->getQuantity(),
            $lineItem->getUnitPriceInclVat(),
            $lineItem->getLineTotalInclVat(),
            $lineItem->getLineVat() ?: 0.0
        );
    }

    /**
     * Creates multiple PrestaShopOrderDetail objects from ChannelEngine line items
     *
     * @param array $lineItems
     *
     * @return array
     */
    public static function fromChannelEngineLineItems(array $lineItems): array
    {
        $orderDetails = [];
        foreach ($lineItems as $lineItem) {
            $orderDetails[] = self::fromChannelEngineLineItem($lineItem);
        }
        return $orderDetails;
    }

    /**
     * Validates the order detail
     *
     * @returns string[]
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->merchantProductNo)) {
            $errors[] = 'Merchant product number is required';
        }

        if ($this->quantity <= 0) {
            $errors[] = 'Quantity must be greater than zero';
        }

        if ($this->unitPriceInclVat < 0) {
            $errors[] = 'Unit price cannot be negative';
        }

        if ($this->lineTotalInclVat < 0) {
            $errors[] = 'Line total cannot be negative';
        }

        if ($this->lineVat < 0) {
            $errors[] = 'Line VAT cannot be negative';
        }

        $expectedTotal = $this->unitPriceInclVat * $this->quantity;
        if (abs($expectedTotal - $this->lineTotalInclVat) > 0.01) {
            $errors[] = 'Line total does not match unit price × quantity';
        }

        return $errors;
    }

    /**
     * @return float
     */
    public function getUnitPriceExclVat(): float
    {
        if ($this->quantity <= 0) {
            return 0.0;
        }

        return $this->unitPriceInclVat - ($this->lineVat / $this->quantity);
    }

    /**
     * @return float
     */
    public function getLineTotalExclVat(): float
    {
        return $this->lineTotalInclVat - $this->lineVat;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'merchantProductNo' => $this->merchantProductNo,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unitPriceInclVat' => $this->unitPriceInclVat,
            'lineTotalInclVat' => $this->lineTotalInclVat,
            'lineVat' => $this->lineVat,
        ];
    }

    /**
     * @param int $orderId
     * @param Product $product
     * @param Context $context
     *
     * @return OrderDetail
     */
    public function toPrestaShopOrderDetail(int $orderId, Product $product, Context $context): OrderDetail
    {
        $orderDetail = new OrderDetail();
        $orderDetail->id_order = $orderId;
        $orderDetail->product_id = $product->id;
        $orderDetail->id_warehouse = 0;
        $orderDetail->id_shop = $context->shop->id;
        $orderDetail->product_name = $product->name[$context->language->id] ?: $this->description;
        $orderDetail->product_quantity = $this->quantity;
        $orderDetail->unit_price_tax_incl = $this->unitPriceInclVat;
        $orderDetail->unit_price_tax_excl = $this->getUnitPriceExclVat();
        $orderDetail->total_price_tax_incl = $this->lineTotalInclVat;
        $orderDetail->total_price_tax_excl = $this->getLineTotalExclVat();
        $orderDetail->product_price = $this->getUnitPriceExclVat();
        $orderDetail->original_product_price = $this->getUnitPriceExclVat();
        $orderDetail->product_reference = $product->reference ?: '';
        $orderDetail->product_supplier_reference = $product->supplier_reference ?: '';
        $orderDetail->product_weight = $product->weight ?: 0;
        $orderDetail->id_customization = 0;
        $orderDetail->product_quantity_discount = 0;
        $orderDetail->product_ean13 = $product->ean13 ?: '';
        $orderDetail->product_isbn = $product->isbn ?: '';
        $orderDetail->product_upc = $product->upc ?: '';
        $orderDetail->product_mpn = $product->mpn ?: '';

        return $orderDetail;
    }

    /**
     * @return string
     */
    public function getMerchantProductNo(): string
    {
        return $this->merchantProductNo;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return float
     */
    public function getUnitPriceInclVat(): float
    {
        return $this->unitPriceInclVat;
    }

    /**
     * @return float
     */
    public function getLineTotalInclVat(): float
    {
        return $this->lineTotalInclVat;
    }

    /**
     * @return float
     */
    public function getLineVat(): float
    {
        return $this->lineVat;
    }
}