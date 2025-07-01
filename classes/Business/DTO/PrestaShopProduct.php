<?php

namespace ChannelEngineCore\Business\DTO;

use ChannelEngine\BusinessLogic\Products\Domain\Product;

class PrestaShopProduct
{
    private int $id;
    private float $price;
    private int $stock;
    private string $name;
    private ?string $description;
    private ?string $ean;
    private string $vatRateType;
    private ?string $mainImageUrl;
    private array $additionalImageUrls;
    private array $customAttributes;
    private string $categoryTrail;
    private array $variants;

    public function __construct(
        int $id,
        float $price,
        int $stock,
        string $name,
        ?string $description = null,
        ?string $ean = null,
        string $vatRateType = 'STANDARD',
        ?string $mainImageUrl = null,
        array $additionalImageUrls = [],
        array $customAttributes = [],
        string $categoryTrail = '',
        array $variants = []
    ) {
        $this->id = $id;
        $this->price = $price;
        $this->stock = $stock;
        $this->name = $name;
        $this->description = $description;
        $this->ean = $ean;
        $this->vatRateType = $vatRateType;
        $this->mainImageUrl = $mainImageUrl;
        $this->additionalImageUrls = $additionalImageUrls;
        $this->customAttributes = $customAttributes;
        $this->categoryTrail = $categoryTrail;
        $this->variants = $variants;
    }

    /**
     * Creates a PrestaShopProduct from PrestaShop product data
     *
     * @param array $productData
     * @param string|null $mainImageUrl
     * @param array $additionalImages
     * @param array $customAttributes
     * @param string $categoryTrail
     * @param array $variants
     *
     * @return self
     */
    public static function fromPrestaShopData(
        array $productData,
        ?string $mainImageUrl = null,
        array $additionalImages = [],
        array $customAttributes = [],
        string $categoryTrail = '',
        array $variants = []
    ): self {
        return new self(
            $productData['id'],
            $productData['price'],
            $productData['stock'],
            $productData['name'],
            $productData['description'] ?? null,
            $productData['ean'] ?? null,
            $productData['vatRateType'],
            $mainImageUrl,
            $additionalImages,
            $customAttributes,
            $categoryTrail,
            $variants
        );
    }

    /**
     * Convert to core ChannelEngine Product
     *
     * @return Product
     */
    public function toCoreProduct(): Product
    {
        return new Product(
            $this->id,
            $this->price,
            $this->stock,
            $this->name,
            $this->description,
            null, // purchasePrice
            null, // msrp
            $this->vatRateType,
            null, // shippingCost
            null, // shippingTime
            $this->ean,
            null, // manufacturerProductNumber
            null, // url
            null, // brand
            null, // size
            null, // color
            $this->mainImageUrl,
            $this->additionalImageUrls,
            $this->customAttributes,
            $this->categoryTrail,
            false, // hasThreeLevelSync
            $this->variants
        );
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @return int
     */
    public function getStock(): int
    {
        return $this->stock;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getEan(): ?string
    {
        return $this->ean;
    }

    /**
     * @return string
     */
    public function getVatRateType(): string
    {
        return $this->vatRateType;
    }

    /**
     * @return string|null
     */
    public function getMainImageUrl(): ?string
    {
        return $this->mainImageUrl;
    }

    /**
     * @return array
     */
    public function getAdditionalImageUrls(): array
    {
        return $this->additionalImageUrls;
    }

    /**
     * @return array
     */
    public function getCustomAttributes(): array
    {
        return $this->customAttributes;
    }

    /**
     * @return string
     */
    public function getCategoryTrail(): string
    {
        return $this->categoryTrail;
    }

    /**
     * @return array
     */
    public function getVariants(): array
    {
        return $this->variants;
    }
}