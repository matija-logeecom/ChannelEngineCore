<?php

namespace ChannelEngineCore\Infrastructure\Configuration;

use ChannelEngine\BusinessLogic\Configuration\ConfigService;
use ChannelEngine\BusinessLogic\Configuration\DTO\SystemInfo;
use ChannelEngine\Infrastructure\Logger\Logger;
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
     * @return string
     */
    public function getIntegrationName(): string
    {
        return 'ChannelEngine';
    }

    /**
     * Returns async process starter url.
     *
     * @param string $guid
     *
     * @return string
     */
    public function getAsyncProcessUrl($guid): string
    {
        $context = Context::getContext();

        $url = $context->link->getModuleLink(
            'channelenginecore',
            'asyncprocess',
            ['guid' => $guid]
        );

        Logger::logInfo(
            'Generated async process URL: ' . $url,
            'PrestaShopConfigService'
        );

        return $url;
    }

    /**
     * Override async process call HTTP method to use GET instead of POST
     * This matches the working Packlink configuration
     *
     * @return string
     */
    public function getAsyncProcessCallHttpMethod(): string
    {
        return 'GET';
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