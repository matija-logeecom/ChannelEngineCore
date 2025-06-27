<?php

use ChannelEngine\BusinessLogic\Authorization\Contracts\AuthorizationService;
use ChannelEngine\BusinessLogic\Products\Domain\ProductUpsert;
use ChannelEngine\BusinessLogic\Products\Handlers\ProductUpsertEventHandler;
use ChannelEngine\Infrastructure\Logger\Logger;
use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngineCore\Infrastructure\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

Bootstrap::init();

class ChannelEngineCore extends Module
{
    public function __construct()
    {
        $this->name = 'channelenginecore';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Matija Stankovic';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Channel Engine Core', [], 'Modules.ChannelEngineCore.Admin');
        $this->description = $this->trans('Channel Engine integration plugin.',
            [], 'Modules.ChannelEngine.Admin');

        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall?',
            [], 'Modules.ChannelEngineCore.Admin');
    }

    /**
     * Install the module and register necessary hooks
     *
     * @returns bool
     */
    public function install(): bool
    {
        return parent::install()
            && $this->installTab()
            && $this->installDatabaseTables()
            && $this->registerHooks();
    }

    /**
     * Uninstall the module and clean up
     *
     * @returns bool
     */
    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallDatabaseTables();
    }

    /**
     * Register hooks for product synchronization
     *
     * @return bool
     */
    private function registerHooks(): bool
    {
        return $this->registerHook('actionProductAdd') &&
            $this->registerHook('actionProductUpdate');
    }

    /**
     * Hook: Product added - sync to ChannelEngine
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionProductAdd(array $params): void
    {
        $this->handleProductUpsert($params);
    }

    /**
     * Hook: Product updated - sync to ChannelEngine
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionProductUpdate(array $params): void
    {
        $this->handleProductUpsert($params);
    }

    /**
     * Handle product upsert (add/update) events
     */
    private function handleProductUpsert($params): void
    {
        try {
            if (!$this->isConnectedToChannelEngine()) {
                return;
            }

            $productId = $this->extractProductId($params);

            if (!$productId || !$this->isValidProduct($productId)) {
                return;
            }

            $upsertEvent = new ProductUpsert($productId);
            $handler = new ProductUpsertEventHandler();
            $handler->handle($upsertEvent);

        } catch (Throwable $e) {
            Logger::logError(
                'Error handling product upsert event: ' . $e->getMessage(),
                'Integration'
            );
        }
    }

    /**
     * Install database tables required by ChannelEngine core
     *
     * @returns bool
     */
    private function installDatabaseTables(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'channelengine_entity` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(127) NOT NULL,
            `index_1` VARCHAR(127),
            `index_2` VARCHAR(127),
            `index_3` VARCHAR(127),
            `index_4` VARCHAR(127),
            `index_5` VARCHAR(127),
            `index_6` VARCHAR(127),
            `index_7` VARCHAR(127),
            `data` LONGTEXT,
            PRIMARY KEY (`id`),
            INDEX `type` (`type`),
            INDEX `index_1` (`index_1`),
            INDEX `index_2` (`index_2`),
            INDEX `index_3` (`index_3`),
            INDEX `index_4` (`index_4`),
            INDEX `index_5` (`index_5`),
            INDEX `index_6` (`index_6`),
            INDEX `index_7` (`index_7`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci';

        return Db::getInstance()->execute($sql);
    }

    /**
     * Uninstall database tables
     *
     * @returns bool
     */
    private function uninstallDatabaseTables(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'channelengine_entity`';
        return Db::getInstance()->execute($sql);
    }

    /**
     * Install admin tab for ChannelEngine management
     *
     * @returns bool
     */
    private function installTab(): bool
    {
        try {
            $parentTabCollection = new PrestaShopCollection('Tab');
            $parentTabCollection->where('class_name', '=', 'AdminParentOrders');
            $parentTab = $parentTabCollection->getFirst();

            if ($parentTab && Validate::isLoadedObject($parentTab)) {
                $parentTabId = (int)$parentTab->id;
            } else {
                $parentTabId = 2;
            }

            $tab = new Tab();
            $tab->active = 1;
            $tab->class_name = 'AdminChannelEngine';
            $tab->name = [];

            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $this->trans('Channel Engine', [], 'Modules.ChannelEngine.Admin');
            }

            $tab->id_parent = $parentTabId;
            $tab->module = $this->name;

            return $tab->add();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Uninstall admin tab
     *
     * @returns bool
     */
    private function uninstallTab(): bool
    {
        try {
            $tabCollection = new PrestaShopCollection('Tab');
            $tabCollection->where('class_name', '=', 'AdminChannelEngine');
            $tabCollection->where('module', '=', $this->name);
            $tab = $tabCollection->getFirst();

            if ($tab && Validate::isLoadedObject($tab)) {
                return $tab->delete();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Extract product ID from hook parameters
     *
     * @returns int|null
     */
    private function extractProductId($params): ?int
    {
        if (isset($params['id_product'])) {
            return (int)$params['id_product'];
        }

        if (isset($params['product']) && is_object($params['product'])) {
            return (int)$params['product']->id;
        }

        if (isset($params['object']) && is_object($params['object'])) {
            return (int)$params['object']->id;
        }

        return null;
    }

    /**
     * Check if product is valid for sync
     *
     * @returns bool
     */
    private function isValidProduct($productId): bool
    {
        try {
            $product = new Product($productId);

            return Validate::isLoadedObject($product) && $product->active;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check if module is connected to ChannelEngine
     *
     * @returns bool
     */
    private function isConnectedToChannelEngine(): bool
    {
        try {
            $authService = ServiceRegister::getService(
                AuthorizationService::CLASS_NAME
            );

            $authInfo = $authService->getAuthInfo();

            return $authInfo !== null && !empty($authInfo->getAccountName()) && !empty($authInfo->getApiKey());
        } catch (Throwable $e) {
            Logger::logDebug(
                'Could not check ChannelEngine connection status: ' . $e->getMessage(),
                'Integration'
            );

            return false;
        }
    }
}