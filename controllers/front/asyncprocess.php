<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\AsyncProcessService;
use ChannelEngine\Infrastructure\Logger\Logger;

class channelenginecoreasyncprocessModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Starts process asynchronously.
     *
     * @throws PrestaShopException
     */
    public function initContent(): void
    {
        $guid = trim(Tools::getValue('guid'));

        Logger::logDebug('Received async process request.', 'Integration', array('guid' => $guid));

        try {
            $asyncProcessService = ServiceRegister::getService(AsyncProcessService::CLASS_NAME);
            $asyncProcessService->runProcess($guid);

            $this->ajaxRender(json_encode(['success' => true]));
        } catch (Throwable $e) {
            Logger::logError(
                'Async process execution failed: ' . $e->getMessage(),
                'Integration',
                array(
                    'guid' => $guid,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                )
            );

            $this->ajaxRender(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }

        exit;
    }

    /**
     * Initializes AsyncProcess controller.
     */
    public function init(): void
    {
        try {
            parent::init();
        } catch (Exception $e) {
            Logger::logWarning(
                'Error initializing AsyncProcessController',
                'Integration',
                array(
                    'Message' => $e->getMessage(),
                    'Stack trace' => $e->getTraceAsString(),
                )
            );
        }
    }

    /**
     * Displays maintenance page if shop is closed.
     */
    public function displayMaintenancePage()
    {
        // Allow async process in maintenance mode
    }

    /**
     * Displays 'country restricted' page if user's country is not allowed.
     */
    protected function displayRestrictedCountryPage()
    {
        // Allow async process
    }
}