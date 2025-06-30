<?php

namespace ChannelEngineCore\Business\Service;

use ChannelEngine\BusinessLogic\Webhooks\WebhooksService;
use ChannelEngine\Infrastructure\Configuration\ConfigurationManager;
use ChannelEngine\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Context;

class PrestaShopWebhooksService extends WebhooksService
{
    /**
     * Provides list of available events for order webhooks.
     *
     * @return array
     */
    protected function getEvents(): array
    {
        return ['ORDERS_CREATE'];
    }

    /**
     * Provides webhook name. This name will be used to identify webhook.
     * Uses a stored name or generates a new one.
     *
     * @return string
     *
     * @throws QueryFilterInvalidParamException
     */
    protected function getName(): string
    {
        $configManager = ConfigurationManager::getInstance();
        $storedName = $configManager->getConfigValue('CHANNELENGINE_WEBHOOK_NAME', '');

        if (empty($storedName)) {
            // Use shop domain and a fixed identifier to make it predictable
            $context = Context::getContext();
            $shopDomain = $context->shop->domain ?? 'prestashop';
            // Clean domain name for webhook name (remove dots, etc.)
            $cleanDomain = preg_replace('/[^a-zA-Z0-9]/', '', $shopDomain);

            $storedName = "prestashop_orders_{$cleanDomain}_webhook";
            $configManager->saveConfigValue('CHANNELENGINE_WEBHOOK_NAME', $storedName);
        }

        return $storedName;
    }

    /**
     * Webhook handling url.
     *
     * @return string
     */
    protected function getUrl(): string
    {
        $context = Context::getContext();

        return $context->link->getModuleLink(
            'channelenginecore',
            'webhook',
            [],
            true // Force SSL
        );
    }
}