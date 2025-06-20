<?php

namespace ChannelEngineCore\Infrastructure;

/*
 * Responsible for initializing dependencies
 */

use PrestaShopLogger;

class Bootstrap
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
            
        } catch (\Throwable $e) {
            PrestaShopLogger::addLog(
                'Critical error: Bootstrap failed! ' . $e->getMessage(),
                4,
                null,
                'ChannelEngine'
            );

            exit;
        }
    }
}
