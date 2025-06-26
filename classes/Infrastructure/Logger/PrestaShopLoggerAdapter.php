<?php

namespace ChannelEngineCore\Infrastructure\Logger;

use ChannelEngine\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use ChannelEngine\Infrastructure\Logger\LogData;
use ChannelEngine\Infrastructure\Logger\Logger;
use PrestaShopLogger;

class PrestaShopLoggerAdapter implements ShopLoggerAdapter
{
    /**
     * Log message using PrestaShop's logging system
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data): void
    {
        $psLogLevel = $this->convertLogLevel($data->getLogLevel());
        $message = $this->formatMessage($data);

        PrestaShopLogger::addLog(
            $message,
            $psLogLevel,
            null,
            'ChannelEngine',
            null,
            true
        );
    }

    /**
     * Convert ChannelEngine log level to PrestaShop log level
     *
     * @param int $channelEngineLevel
     *
     * @return int
     */
    private function convertLogLevel(int $channelEngineLevel): int
    {
        return match($channelEngineLevel) {
            Logger::ERROR => 3,
            Logger::WARNING => 2,
            Logger::INFO => 1,
            LOGGER::DEBUG => 1,
            default => 1
        };
    }

    /**
     * Format log message with context
     *
     * @param LogData $data
     *
     * @return string
     */
    private function formatMessage(LogData $data): string
    {
        $message = '[' . $data->getComponent() . '] ' . $data->getMessage();

        $context = $data->getContext();
        if (!empty($context)) {
            $contextData = [];
            foreach ($context as $contextItem) {
                $contextData[$contextItem->getName()] = $contextItem->getValue();
            }

            if (!empty($contextData)) {
                $message .= ' | Context: ' . json_encode($contextData);
            }
        }

        return $message;
    }
}