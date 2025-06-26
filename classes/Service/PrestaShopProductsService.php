<?php

namespace ChannelEngineCore\Service;

use ChannelEngine\BusinessLogic\Products\Contracts\ProductsService;
use ChannelEngine\BusinessLogic\Products\Domain\Product;

use DbQuery;
use Exception;
use PrestaShopDatabaseException;
use PrestaShopLogger;
use Product as PrestaShopProduct;
use StockAvailable;
use Context;
use Link;
use Db;
use Configuration as PrestaShopConfiguration;
use Image;
use Validate;

class PrestaShopProductsService implements ProductsService
{
    /**
     * Gets all product IDs
     *
     * @throws PrestaShopDatabaseException
     */
    public function getProductIds($page, $limit = 5000): array
    {
        $offset = $page * $limit;
        $db = Db::getInstance();

        $query = new DbQuery();
        $query->select('p.id_product');
        $query->from('product', 'p');
        $query->where('p.active = 1');
        $query->limit($limit, $offset);

        $results = $db->executeS($query);

        if (!is_array($results)) {
            return [];
        }

        return array_column($results, 'id_product');
    }

    /**
     * Gets all products
     *
     * @param array $ids
     *
     * @return array|Product[]
     */
    public function getProducts(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $context = $this->getShopContext();
        $products = [];

        foreach ($ids as $id) {
            try {
                $product = $this->createProductFromId($id, $context);
                if ($product !== null) {
                    $products[] = $product;
                }
            } catch (Exception $e) {
                $this->logProductError($id, $e);
            }
        }

        return $products;
    }

    /**
     * Gets shop context data
     *
     * @return array
     */
    private function getShopContext(): array
    {
        $context = Context::getContext();

        return [
            'idLang' => $context->language->id ?? (int)PrestaShopConfiguration::get('PS_LANG_DEFAULT'),
            'idShop' => $context->shop->id ?? (int)PrestaShopConfiguration::get('PS_SHOP_DEFAULT'),
            'link' => $context->link ?: new Link()
        ];
    }

    /**
     * Creates a Product from PrestaShop product ID
     *
     * @param mixed $id
     * @param array $context
     *
     * @return Product|null
     */
    private function createProductFromId($id, array $context): ?Product
    {
        $prestashopProduct = new PrestaShopProduct((int)$id, false, $context['idLang'], $context['idShop']);

        if (!Validate::isLoadedObject($prestashopProduct) || !$prestashopProduct->active) {
            return null;
        }

        $productData = $this->extractProductData($prestashopProduct, $context);
        $mainImageUrl = $this->getMainImageUrl($prestashopProduct, $context);

        return new Product(
            $productData['id'],
            $productData['price'],
            $productData['stock'],
            $productData['name'],
            $productData['description'],
            null, // purchasePrice
            null, // msrp
            $productData['vatRateType'],
            null, // shippingCost
            null, // shippingTime
            $productData['ean'],
            null, // manufacturerProductNumber
            null, // url
            null, // brand
            null, // size
            null, // color
            $mainImageUrl,
            [], // additionalImageUrls
            [], // customAttributes
            '', // categoryTrail
            false, // hasThreeLevelSync
            [] // variants
        );
    }

    /**
     * Extracts basic product data from PrestaShop product
     *
     * @param PrestaShopProduct $prestashopProduct
     * @param array $context
     *
     * @return array
     */
    private function extractProductData(PrestaShopProduct $prestashopProduct, array $context): array
    {
        $productId = (int)$prestashopProduct->id;

        return [
            'id' => $productId,
            'price' => (float)$prestashopProduct->getPrice(true, null, 6),
            'stock' => StockAvailable::getQuantityAvailableByProduct($productId, null, $context['idShop']),
            'name' => $prestashopProduct->name,
            'description' => $prestashopProduct->description,
            'ean' => $this->getValidEan($prestashopProduct->ean13),
            'vatRateType' => $this->getVatRateType($prestashopProduct)
        ];
    }

    /**
     * Gets main image URL for product
     *
     * @param PrestaShopProduct $prestashopProduct
     * @param array $context
     *
     * @return string|null
     */
    private function getMainImageUrl(PrestaShopProduct $prestashopProduct, array $context): ?string
    {
        $images = Image::getImages($context['idLang'], (int)$prestashopProduct->id);

        if (empty($images)) {
            return null;
        }

        $coverImage = $this->findCoverImage($images);
        $imageType = 'home_default';

        return $context['link']->getImageLink(
            $prestashopProduct->link_rewrite[$context['idLang']],
            (string)$coverImage['id_image'],
            $imageType
        );
    }

    /**
     * Finds cover image or returns first image as fallback
     *
     * @param array $images
     *
     * @return array
     */
    private function findCoverImage(array $images): array
    {
        foreach ($images as $image) {
            if ($image['cover']) {
                return $image;
            }
        }

        return $images[0];
    }

    /**
     * Logs product processing error
     *
     * @param int $id
     * @param Exception $e
     */
    private function logProductError(int $id, Exception $e): void
    {
        PrestaShopLogger::addLog(
            "Error processing product {$id}: " . $e->getMessage(),
            3,
            null,
            'ChannelEngine'
        );
    }

    /**
     *  Get valid EAN or null if invalid
`    *
     * @param string|null $ean
     *
     * @return string|null
     */
    private function getValidEan(?string $ean): ?string
    {
        if (empty($ean)) {
            return null;
        }

        $ean = trim($ean);

        if (!preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $ean)) {
            return null;
        }

        if (strlen($ean) === 13) {
            $checksum = 0;
            for ($i = 0; $i < 12; $i++) {
                $checksum += (int)$ean[$i] * (($i % 2 === 0) ? 1 : 3);
            }
            $calculatedCheck = (10 - ($checksum % 10)) % 10;

            if ($calculatedCheck != (int)$ean[12]) {
                return null;
            }
        }

        return $ean;
    }

    /**
     * Get VAT rate type for a PrestaShop product
     *
     * @param PrestaShopProduct $product
     *
     * @return string
     */
    private function getVatRateType(PrestaShopProduct $product): string
    {
        try {
            $taxRate = \Tax::getProductTaxRate($product->id, null, Context::getContext());

            if ($taxRate === null) {
                return 'STANDARD';
            }

            return match (true) {
                $taxRate == 0 => 'EXEMPT',
                $taxRate <= 6 => 'SUPER_REDUCED',
                $taxRate <= 12 => 'REDUCED',
                default => 'STANDARD'
            };
        } catch (Exception $e) {
            return 'STANDARD';
        }
    }
}