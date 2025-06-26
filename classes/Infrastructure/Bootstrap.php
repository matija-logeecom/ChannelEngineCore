<?php

namespace ChannelEngineCore\Infrastructure;

use ChannelEngine\BusinessLogic\BootstrapComponent as BusinessLogicBootstrap;
use ChannelEngine\BusinessLogic\Products\Contracts\ProductsService;
use ChannelEngine\BusinessLogic\TransactionLog\Entities\Details;
use ChannelEngine\BusinessLogic\TransactionLog\Entities\TransactionLog;
use ChannelEngine\Infrastructure\Configuration\Configuration;
use ChannelEngine\Infrastructure\Configuration\ConfigEntity;
use ChannelEngine\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use ChannelEngine\Infrastructure\ORM\Exceptions\RepositoryClassException;
use ChannelEngine\Infrastructure\ORM\RepositoryRegistry;
use ChannelEngine\Infrastructure\Serializer\Concrete\JsonSerializer;
use ChannelEngine\Infrastructure\Serializer\Serializer;
use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngine\Infrastructure\TaskExecution\Process;
use ChannelEngine\Infrastructure\TaskExecution\QueueItem;
use ChannelEngineCore\Service\PrestaShopProductsService;
use ChannelEngineCore\Infrastructure\Configuration\PrestaShopConfigService;
use ChannelEngineCore\Infrastructure\Logger\PrestaShopLoggerAdapter;
use ChannelEngineCore\Infrastructure\ORM\GenericEntityRepository;
use ChannelEngineCore\Infrastructure\ORM\QueueItemRepository;

use PrestaShopLogger;

/**
 * PrestaShop Bootstrap - extends ChannelEngine Business Logic bootstrap
 */
class Bootstrap extends BusinessLogicBootstrap
{
    /**
     * Initializes dependencies
     */
    public static function init(): void
    {
        try {
            if (!defined('VIEWS_PATH')) {
                define('VIEWS_PATH', __DIR__ . '/views');
            }

            parent::init();

            static::initRepositories();
            static::initPrestaShopServices();
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'Bootstrap failed: ' . $e->getMessage(),
                4,
                null,
                'ChannelEngine'
            );
            throw $e;
        }
    }

    /**
     * Initializes services and utilities.
     */
    protected static function initServices(): void
    {
        parent::initServices();

        // Register the missing Serializer service
        ServiceRegister::registerService(
            Serializer::CLASS_NAME,
            function () {
                return new JsonSerializer();
            }
        );
    }


    /**
     * Initializes PrestaShop-specific services only
     *
     * @return void
     */
    protected static function initPrestaShopServices(): void
    {
        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function() {
                return PrestaShopConfigService::getInstance();
            }
        );
        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            function() {
                return new PrestaShopLoggerAdapter();
            }
        );
        ServiceRegister::registerService(
            ProductsService::CLASS,
            function() {
                return new PrestaShopProductsService();
            }
        );
    }

    /**
     * Initializes repositories for PrestaShop database
     *
     * @return void
     *
     * @throws RepositoryClassException
     */
    protected static function initRepositories(): void
    {
        RepositoryRegistry::registerRepository(
            ConfigEntity::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            QueueItem::CLASS_NAME,
            QueueItemRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            Process::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            TransactionLog::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            Details::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
    }
}