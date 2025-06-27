<?php

namespace ChannelEngineCore\Business\Service;

use ChannelEngine\BusinessLogic\Products\Domain\Product;
use ChannelEngineCore\Business\DTO\PrestaShopProduct as ProductDTO;
use ChannelEngine\BusinessLogic\Products\Contracts\ProductsService;
use ChannelEngine\Infrastructure\Logger\Logger;
use Configuration as PrestaShopConfiguration;
use Context;
use Exception;
use Image;
use Link;
use PrestaShopCollection;
use PrestaShopDatabaseException;
use PrestaShopException;
use Product as PrestaShopProduct;
use StockAvailable;
use Validate;

class PrestaShopProductsService implements ProductsService
{

    /**
     * Gets all product IDs
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function getProductIds($page, $limit = 5000): array
    {
        $collection = new PrestaShopCollection('Product');
        $collection->where('active', '=', 1);
        $collection->setPageSize($limit);
        $collection->setPageNumber($page + 1);
        $collection->orderBy('id_product', 'ASC');

        $products = $collection->getResults();

        $ids = [];
        foreach ($products as $product) {
            $ids[] = $product->id;
        }

        return $ids;
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
                $prestaShopProductDTO = $this->loadProductById($id, $context);
                if ($prestaShopProductDTO !== null) {
                    $products[] = $prestaShopProductDTO->toCoreProduct();
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
     * @return ProductDTO|null
     */
    private function loadProductById($id, array $context): ?ProductDTO
    {
        $prestashopProduct = new PrestaShopProduct((int)$id, false, $context['idLang'], $context['idShop']);

        if (!Validate::isLoadedObject($prestashopProduct) || !$prestashopProduct->active) {
            return null;
        }

        $productData = $this->getProductDataFromPrestaShop($prestashopProduct, $context);
        $mainImageUrl = $this->getMainImageUrl($prestashopProduct, $context);

        return ProductDTO::fromPrestaShopData($productData, $mainImageUrl);
    }

    /**
     * Extracts basic product data from PrestaShop product
     *
     * @param PrestaShopProduct $prestashopProduct
     * @param array $context
     *
     * @return array
     */
    private function getProductDataFromPrestaShop(PrestaShopProduct $prestashopProduct, array $context): array
    {
        $productId = (int)$prestashopProduct->id;

        return [
            'id' => $productId,
            'price' => (float)$prestashopProduct->getPrice(true, null, 6),
            'stock' => StockAvailable::getQuantityAvailableByProduct(
                $productId, null, $context['idShop']),
            'name' => $prestashopProduct->name,
            'description' => $prestashopProduct->description,
            'ean' => !empty($prestashopProduct->ean13) ? $prestashopProduct->ean13 : null,
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
        Logger::logError(
            "Error processing product {$id}: " . $e->getMessage(),
            'PrestaShopProductsService'
        );
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