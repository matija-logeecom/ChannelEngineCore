<?php

namespace ChannelEngineCore\Infrastructure\DI;

use Exception;

/*
 * Class for storing and retrieving services
 */

class ServiceRegistry
{
    private static array $services = [];

    /**
     * Adds service to services array
     *
     * @param string $name
     * @param $service
     *
     * @return void
     */
    public static function set(string $name, $service): void
    {
        self::$services[$name] = $service;
    }

    /**
     * Returns service with provided name
     *
     * @param string $name
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function get(string $name): mixed
    {
        if (!isset(self::$services[$name])) {
            throw new Exception("Service not found: $name");
        }

        return self::$services[$name];
    }
}
