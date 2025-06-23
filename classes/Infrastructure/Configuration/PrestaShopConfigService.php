<?php

namespace ChannelEngineCore\Infrastructure\Configuration;

use ChannelEngine\BusinessLogic\Configuration\ConfigService;
use ChannelEngine\BusinessLogic\Configuration\DTO\SystemInfo;
use Context;
use Tools;

class PrestaShopConfigService extends ConfigService
{
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;

    /**
     * Retrieves integration name.
     *
     * @return string Integration name.
     */
    public function getIntegrationName(): string
    {
        return 'ChannelEngine';
    }

    /**
     * Returns async process starter url.
     *
     * @param string $guid Process identifier.
     *
     * @return string Formatted URL of async process starter endpoint.
     */
    public function getAsyncProcessUrl($guid): string
    {
        return Context::getContext()->link->getAdminLink(
            'AdminChannelEngine',
            true,
            [],
            ['action' => 'processAsync', 'guid' => $guid]
        );
    }

    /**
     * Provides information about the system.
     *
     * @return SystemInfo
     */
    public function getSystemInfo(): SystemInfo
    {
        $context = Context::getContext();

        return new SystemInfo(
            'PrestaShop',
            _PS_VERSION_,
            Tools::getShopDomainSsl(true, true),
            '1.0.0',
            [
                'php_version' => PHP_VERSION,
                'shop_id' => $context->shop->id ?? null,
                'language_id' => $context->language->id ?? null,
            ]
        );
    }
}