<?php

use ChannelEngine\BusinessLogic\Authorization\Contracts\AuthorizationService;
use ChannelEngine\BusinessLogic\Authorization\DTO\AuthInfo;
use ChannelEngine\BusinessLogic\Authorization\Exceptions\CurrencyMismatchException;
use ChannelEngine\BusinessLogic\InitialSync\ProductSync;
use ChannelEngine\Infrastructure\Logger\Logger;
use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;
use ChannelEngine\Infrastructure\TaskExecution\QueueService;
use ChannelEngine\Infrastructure\TaskExecution\QueueItem;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\Priority;

class AdminChannelEngineController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';
        $this->meta_title = 'Channel Engine';

        parent::__construct();
    }

    /**
     * Handle AJAX requests
     *
     * @throws PrestaShopException
     */
    public function ajaxProcess(): void
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            $action = $data['action'] ?? Tools::getValue('action', 'status');

            $response = match($action) {
                'connect' => $this->processConnection($data),
                'disconnect' => $this->processDisconnection(),
                'status' => $this->getConnectionStatus(),
                'sync' => $this->startProductSync(),
                'sync_status' => $this->getSyncStatus(),
                default => ['success' => false, 'message' => 'Unknown action']
            };
        } catch (Throwable $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        $this->ajaxRender(json_encode($response));
        exit;
    }

    /**
     * Main render method
     *
     * @return string
     *
     * @throws SmartyException
     */
    public function renderView(): string
    {
        if ($this->isConnected()) {
            return $this->renderSyncPage();
        }

        return $this->renderWelcomePage();
    }

    /**
     * Render welcome page
     *
     * @return string
     *
     * @throws SmartyException
     */
    private function renderWelcomePage(): string
    {
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
        $this->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');

        $this->context->smarty->assign([
            'module_dir' => $this->module->getPathUri(),
        ]);

        return $this->context->smarty->fetch(
            $this->module->getLocalPath() . 'views/templates/admin/welcome.tpl'
        );
    }

    /**
     * Render sync page
     *
     * @return string
     *
     * @throws SmartyException
     */
    private function renderSyncPage(): string
    {
        try {
            ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME)->wakeup();

            $this->addCSS($this->module->getPathUri() . 'views/css/sync.css');
            $this->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
            $this->addJS($this->module->getPathUri() . 'views/js/admin.js');

            $accountName = $this->getAccountName();
            $syncStatus = $this->getCurrentSyncStatus();

            $this->context->smarty->assign([
                'module_dir' => $this->module->getPathUri(),
                'account_name' => $accountName,
                'is_connected' => true,
                'sync_status' => $syncStatus,
                'sync_status_json' => json_encode($syncStatus),
            ]);

            return $this->context->smarty->fetch(
                $this->module->getLocalPath() . 'views/templates/admin/sync.tpl'
            );
        } catch (Throwable $e) {
            Logger::logError('Failed to get sync status: ' . $e->getMessage(),
            'AdminController');

            $this->context->smarty->assign([
                'module_dir' => $this->module->getPathUri(),
                'error_message' => 'Failed to load synchronization status',
                'is_connected' => true,
            ]);

            return $this->context->smarty->fetch(
                $this->module->getLocalPath() . 'views/templates/admin/sync.tpl'
            );
        }
    }

    /**
     * Handle connect request
     *
     * @param array $data
     *
     * @return array
     */
    private function processConnection(array $data = []): array
    {
        $accountName = $data['account_name'] ?? Tools::getValue('account_name');
        $apiKey = $data['api_key'] ?? Tools::getValue('api_key');

        if (empty($accountName) || empty($apiKey)) {
            return ['success' => false, 'message' => 'Account name and API key are required'];
        }

        try {
            $authService = ServiceRegister::getService(AuthorizationService::CLASS_NAME);

            $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));

            $authService->validateAccountInfo($apiKey, $accountName, $currency->iso_code);
            $authService->setAuthInfo(new AuthInfo($accountName, $apiKey));
            ServiceRegister::getService(TaskRunnerWakeup::CLASS_NAME)->wakeup();

            return ['success' => true, 'message' => 'Connected successfully'];
        } catch (CurrencyMismatchException $e) {
            Logger::logError('Currency mismatch: ' . $e->getMessage(),
                'AdminController');

            return ['success' => false, 'message' =>
                'Currency mismatch - ' . 'your shop currency must match your ChannelEngine account currency'];

        } catch (HttpRequestException $e) {
            Logger::logError('Invalid credentials: ' . $e->getMessage(),
                'AdminController');

            return ['success' => false, 'message' =>
                'Invalid credentials - please check your account name and API key'];

        } catch (Throwable $e) {
            Logger::logError('ChannelEngine connection error: ' . $e->getMessage(),
                'AdminController');

            return ['success' => false,
                'message' => 'Connection failed - please check your credentials and try again'];
        }
    }

    /**
     * Handle disconnect request
     *
     * @return array
     */
    private function processDisconnection(): array
    {
        try {
            $authService = ServiceRegister::getService(AuthorizationService::CLASS_NAME);
            $authService->setAuthInfo(null);

            return ['success' => true, 'message' => 'Disconnected successfully'];
        } catch (Throwable $e) {
            Logger::logError('Failed to disconnect: ' . $e->getMessage(),
            'AdminController');

            return ['success' => false, 'message' => 'Disconnect failed: ' . $e->getMessage()];
        }
    }

    /**
     * Handle status request
     *
     * @return array
     */
    private function getConnectionStatus(): array
    {
        try {
            return [
                'success' => true,
                'data' => [
                    'is_connected' => $this->isConnected(),
                    'account_name' => $this->getAccountName(),
                    'sync_status' => $this->getCurrentSyncStatus()
                ]
            ];
        } catch (Throwable $e) {
            Logger::logError('Failed to get connection status: ' . $e->getMessage(),
                'AdminController');

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Handle manual sync request - This is where we enqueue the sync task
     *
     * @return array
     */
    private function startProductSync(): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'message' => 'Not connected to ChannelEngine'];
        }

        try {
            $existingSyncItem = $this->getActiveSyncTask();
            if ($existingSyncItem && $existingSyncItem->getStatus() === QueueItem::IN_PROGRESS) {
                return [
                    'success' => false,
                    'message' => 'Product synchronization is already in progress'
                ];
            }

            $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
            $productSyncTask = new ProductSync();
            $queueItem = $queueService->enqueue(
                'manual-sync',
                $productSyncTask,
                '',
                Priority::HIGH
            );

            Logger::logInfo(
                'Manual product sync initiated. Queue Item ID: ' . $queueItem->getId(),
                'AdminController'
            );

            return [
                'success' => true,
                'message' => 'Product synchronization started successfully',
                'queue_item_id' => $queueItem->getId()
            ];

        } catch (Throwable $e) {
            Logger::logError(
                'Failed to start manual sync: ' . $e->getMessage(),
                'AdminController'
            );

            return [
                'success' => false,
                'message' => 'Failed to start synchronization: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check current status of sync tasks
     *
     * @return array
     */
    private function getSyncStatus(): array
    {
        try {
            $syncStatus = $this->getCurrentSyncStatus();

            return [
                'success' => true,
                'data' => $syncStatus
            ];
        } catch (Throwable $e) {
            Logger::logError('Failed to get sync status: ' . $e->getMessage(),
                'AdminController');

            return [
                'success' => false,
                'message' => 'Failed to get sync status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get the current synchronization status by checking queue items
     *
     * @return array
     *
     * @throws Throwable
     */
    private function getCurrentSyncStatus(): array
    {
        try {
            $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
            $syncItem = $queueService->findLatestByType(ProductSync::getClassName());

            if (!$syncItem) {
                return [
                    'status' => 'none',
                    'progress' => 0
                ];
            }

            $data = [
                'status' => $syncItem->getStatus(),
                'progress' => $syncItem->getProgressFormatted(),
                'retries' => $syncItem->getRetries()
            ];

            if ($syncItem->getFinishTimestamp()) {
                $data['finished_at'] = $syncItem->getFinishTimestamp();
            }

            if ($syncItem->getStartTimestamp()) {
                $data['started_at'] = $syncItem->getStartTimestamp();
            }

            if ($syncItem->getStatus() === QueueItem::FAILED && $syncItem->getFailureDescription()) {
                $data['failure_description'] = $syncItem->getFailureDescription();
            }

            return $data;
        } catch (Throwable $e) {
            Logger::logError(
                'Failed to get sync status: ' . $e->getMessage(),
                'AdminController'
            );

            return [
                'status' => 'error',
                'error_message' => 'Failed to retrieve sync status'
            ];
        }
    }

    /**
     * Get active sync task if any
     *
     * @return QueueItem|null
     */
    private function getActiveSyncTask(): ?QueueItem
    {
        try {
            $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
            return $queueService->findLatestByType(ProductSync::getClassName());
        } catch (Throwable $e) {
            Logger::logWarning('Failed to get active sync status: ' . $e->getMessage(),
                'AdminController');

            return null;
        }
    }

    /**
     * Check if connected
     *
     * @return bool
     */
    private function isConnected(): bool
    {
        try {
            $authService = ServiceRegister::getService(AuthorizationService::CLASS_NAME);

            return $authService->getAuthInfo() !== null;
        } catch (Throwable $e) {
            Logger::logWarning('Failed to check connection: ' . $e->getMessage(),
            'AdminController');

            return false;
        }
    }

    /**
     * Get account name
     *
     * @return string|null
     */
    private function getAccountName(): ?string
    {
        try {
            $authService = ServiceRegister::getService(AuthorizationService::CLASS_NAME);

            return $authService->getAuthInfo()->getAccountName();
        } catch (Throwable $e) {
            Logger::logWarning('Failed to get account name: ' . $e->getMessage(),
                'AdminController');

            return null;
        }
    }
}