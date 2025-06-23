<?php

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
     */
    public function install(): bool
    {
        return parent::install()
            && $this->installTab()
            && $this->installDatabaseTables();
    }

    /**
     * Uninstall the module and clean up
     */
    public function uninstall(): bool
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && $this->uninstallDatabaseTables();
    }

    /**
     * Install database tables required by ChannelEngine core
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
     */
    private function uninstallDatabaseTables(): bool
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'channelengine_entity`';
        return Db::getInstance()->execute($sql);
    }

    /**
     * Install admin tab for ChannelEngine management
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
}