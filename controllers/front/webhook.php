<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

use ChannelEngine\BusinessLogic\Webhooks\DTO\Webhook;
use ChannelEngine\BusinessLogic\Webhooks\Handlers\OrderWebhookHandler;
use ChannelEngine\Infrastructure\Logger\Logger;

/**
 * Webhook handler controller for ChannelEngine webhooks
 */
class channelenginecorewebhookModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handles incoming webhook requests
     */
    public function initContent(): void
    {
        try {
            // Validate webhook token first
            $token = Tools::getValue('token');
            if (empty($token)) {
                $this->respondWithError('Missing webhook token', 401);
                return;
            }

            // Get webhook parameters from GET request
            $tenant = Tools::getValue('tenant', '');
            $event = Tools::getValue('event', 'ORDERS_CREATE');

            // Log the incoming webhook
            Logger::logInfo(
                'Received webhook from ChannelEngine',
                'Webhook',
                [
                    'token' => substr($token, 0, 8) . '...', // Log partial token for security
                    'tenant' => $tenant,
                    'event' => $event,
                    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                ]
            );

            $event = $event === 'ORDERS_CREATE' ? 'orders' : $event;

            $webhook = new Webhook(
                $tenant,
                $token,
                $event
            );

            $this->handleWebhook($webhook);

            $this->respondWithSuccess('Webhook processed successfully');

        } catch (Throwable $e) {
            Logger::logError(
                'Webhook processing failed: ' . $e->getMessage(),
                'Webhook',
                [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
                ]
            );

            $this->respondWithError('Webhook processing failed', 500);
        }
    }

    /**
     * Handle webhook based on event type
     *
     * @param Webhook $webhook
     *
     * @throws Exception
     */
    private function handleWebhook(Webhook $webhook): void
    {
        try {
            switch ($webhook->getEvent()) {
                case 'ORDERS_CREATE':
                case 'orders':
                    $handler = new OrderWebhookHandler();
                    $handler->handle($webhook);
                    break;

                default:
                    Logger::logWarning(
                        'Unknown webhook event type: ' . $webhook->getEvent(),
                        'Webhook'
                    );
                    break;
            }
        } catch (Throwable $e) {
            Logger::logError('Webhook processing failed: ' . $e->getMessage(), 'Webhook Controller');
        }
    }

    /**
     * Respond with success
     *
     * @param string $message
     */
    private function respondWithSuccess(string $message): void
    {
        header('Content-Type: application/json');
        http_response_code(200);

        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Respond with error
     *
     * @param string $message
     * @param int $httpCode
     */
    private function respondWithError(string $message, int $httpCode = 400): void
    {
        header('Content-Type: application/json');
        http_response_code($httpCode);

        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => date('c')
        ];

        echo json_encode($response);
        exit;
    }

    /**
     * Initializes webhook controller.
     */
    public function init(): void
    {
        try {
            parent::init();
        } catch (Exception $e) {
            Logger::logWarning(
                'Error initializing WebhookController',
                'Webhook',
                [
                    'Message' => $e->getMessage(),
                    'Stack trace' => $e->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * Displays maintenance page if shop is closed.
     */
    public function displayMaintenancePage()
    {
        // Allow webhook processing in maintenance mode
    }

    /**
     * Displays 'country restricted' page if user's country is not allowed.
     */
    protected function displayRestrictedCountryPage()
    {
        // Allow webhook processing
    }
}