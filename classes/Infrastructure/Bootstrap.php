<?php

namespace ChannelEngineCore\Infrastructure;

use ChannelEngine\BusinessLogic\BootstrapComponent as BusinessLogicBootstrap;
use ChannelEngine\BusinessLogic\Orders\ChannelSupport\OrdersChannelSupportEntity;
use ChannelEngine\BusinessLogic\Orders\Configuration\OrdersConfigEntity;
use ChannelEngine\BusinessLogic\Orders\Configuration\OrderSyncConfig;
use ChannelEngine\BusinessLogic\Orders\Contracts\OrdersService;
use ChannelEngine\BusinessLogic\Products\Contracts\ProductsService;
use ChannelEngine\BusinessLogic\Products\Entities\ProductEvent;
use ChannelEngine\BusinessLogic\Products\Listeners\TickEventListener;
use ChannelEngine\BusinessLogic\TransactionLog\Entities\Details;
use ChannelEngine\BusinessLogic\TransactionLog\Entities\TransactionLog;
use ChannelEngine\BusinessLogic\Webhooks\Contracts\WebhooksService;
use ChannelEngine\Infrastructure\Configuration\ConfigEntity;
use ChannelEngine\Infrastructure\Configuration\Configuration;
use ChannelEngine\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use ChannelEngine\Infrastructure\ORM\Exceptions\RepositoryClassException;
use ChannelEngine\Infrastructure\ORM\RepositoryRegistry;
use ChannelEngine\Infrastructure\Serializer\Concrete\JsonSerializer;
use ChannelEngine\Infrastructure\Serializer\Serializer;
use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngine\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException;
use ChannelEngine\Infrastructure\TaskExecution\Process;
use ChannelEngine\Infrastructure\TaskExecution\QueueItem;
use ChannelEngine\Infrastructure\TaskExecution\TaskEvents\TickEvent;
use ChannelEngine\Infrastructure\Utility\Events\EventBus;
use ChannelEngineCore\Business\Service\PrestaShopOrdersService;
use ChannelEngineCore\Business\Service\PrestaShopProductsService;
use ChannelEngineCore\Business\Service\PrestaShopWebhooksService;
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
            parent::init();
            static::initPrestaShopServices();
    }

    /**
     * Initializes services and utilities.
     */
    protected static function initServices(): void
    {
        parent::initServices();

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
        ServiceRegister::registerService(
            WebhooksService::class,
            function() {
                return new PrestaShopWebhooksService();
            }
        );
        ServiceRegister::registerService(
            OrdersService::class,
            function() {
                return new PrestaShopOrdersService();
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
        RepositoryRegistry::registerRepository(
            ProductEvent::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            OrderSyncConfig::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            OrdersConfigEntity::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(
            OrdersChannelSupportEntity::CLASS_NAME,
            GenericEntityRepository::getClassName()
        );
    }

    /**
     * Initializes events.
     */
    protected static function initEvents(): void
    {
        parent::initEvents();

        $eventBus = ServiceRegister::getService(EventBus::CLASS_NAME);

        try {
            $eventBus->when(TickEvent::class, function() {
                TickEventListener::handle();
            });
        } catch (QueueStorageUnavailableException $e) {
            PrestaShopLogger::addLog(
                'TickEventListener failed to initialize ' . $e->getMessage(),
                4,
                null,
                'ChannelEngine'
            );
            throw $e;
        }
    }
}