<?php

use ChannelEngine\BusinessLogic\Authorization\Contracts\AuthorizationService;
use ChannelEngine\BusinessLogic\Authorization\DTO\AuthInfo;
use ChannelEngine\BusinessLogic\Authorization\Exceptions\CurrencyMismatchException;
use ChannelEngine\BusinessLogic\InitialSync\ProductSync;
use ChannelEngine\Infrastructure\ServiceRegister;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup;
use ChannelEngine\Infrastructure\TaskExecution\QueueService;
use ChannelEngine\Infrastructure\TaskExecution\QueueItem;
use ChannelEngine\Infrastructure\TaskExecution\Interfaces\Priority;
use ChannelEngine\Infrastructure\TaskExecution\TaskRunnerWakeupService;

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
                'connect' => $this->handleConnect($data),
                'disconnect' => $this->handleDisconnect(),
                'status' => $this->handleStatus(),
                'sync' => $this->handleSync(),
                'sync_status' => $this->handleSyncStatus(),
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
        } else {
            return $this->renderWelcomePage();
        }
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
    }

    /**
     * Handle connect request
     *
     * @param array $data
     *
     * @return array
     */
    private function handleConnect(array $data = []): array
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
            return ['success' => false, 'message' =>
                'Currency mismatch - ' . 'your shop currency must match your ChannelEngine account currency'];

        } catch (HttpRequestException $e) {
            return ['success' => false, 'message' =>
                'Invalid credentials - please check your account name and API key'];

        } catch (Throwable $e) {
            PrestaShopLogger::addLog('ChannelEngine connection error: ' . $e->getMessage(), 3);
            return ['success' => false, 'message' => 'Connection failed - please check your credentials and try again'];
        }
    }

    /**
     * Handle disconnect request
     *
     * @return array
     */
    private function handleDisconnect(): array
    {
        try {
            $authService = ServiceRegister::getService(AuthorizationService::CLASS_NAME);
            $authService->setAuthInfo(null);

            return ['success' => true, 'message' => 'Disconnected successfully'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Disconnect failed: ' . $e->getMessage()];
        }
    }

    /**
     * Handle status request
     *
     * @return array
     */
    private function handleStatus(): array
    {
        return [
            'success' => true,
            'data' => [
                'is_connected' => $this->isConnected(),
                'account_name' => $this->getAccountName(),
                'sync_status' => $this->getCurrentSyncStatus()
            ]
        ];
    }

    /**
     * Handle manual sync request - This is where we enqueue the sync task
     *
     * @return array
     */
    private function handleSync(): array
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

            PrestaShopLogger::addLog(
                'Manual product sync initiated. Queue Item ID: ' . $queueItem->getId(),
                1,
                null,
                'ChannelEngine'
            );

            return [
                'success' => true,
                'message' => 'Product synchronization started successfully',
                'queue_item_id' => $queueItem->getId()
            ];

        } catch (Throwable $e) {
            PrestaShopLogger::addLog(
                'Failed to start manual sync: ' . $e->getMessage(),
                3,
                null,
                'ChannelEngine'
            );

            return [
                'success' => false,
                'message' => 'Failed to start synchronization: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle sync status request - Check current status of sync tasks
     *
     * @return array
     */
    private function handleSyncStatus(): array
    {
        try {
            $syncStatus = $this->getCurrentSyncStatus();

            return [
                'success' => true,
                'data' => $syncStatus
            ];
        } catch (Throwable $e) {
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
     */
    private function getCurrentSyncStatus(): array
    {
        try {
            $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
            $syncItem = $queueService->findLatestByType(ProductSync::getClassName());

            if (!$syncItem) {
                return [
                    'status' => 'none',
                    'message' => 'No synchronization has been performed yet'
                ];
            }

            $status = $syncItem->getStatus();
            $progress = $syncItem->getProgressFormatted();

            return match ($status) {
                QueueItem::QUEUED => [
                    'status' => 'queued',
                    'message' => 'Synchronization is queued and waiting to start',
                    'progress' => 0
                ],
                QueueItem::IN_PROGRESS => [
                    'status' => 'in_progress',
                    'message' => 'Synchronization in progress',
                    'progress' => $progress
                ],
                QueueItem::COMPLETED => [
                    'status' => 'completed',
                    'message' => 'Synchronization completed successfully',
                    'progress' => 100,
                    'finished_at' => $syncItem->getFinishTimestamp()
                ],
                QueueItem::FAILED => [
                    'status' => 'failed',
                    'message' => 'Synchronization failed: ' . $syncItem->getFailureDescription(),
                    'progress' => $progress
                ],
                QueueItem::ABORTED => [
                    'status' => 'aborted',
                    'message' => 'Synchronization was aborted',
                    'progress' => $progress
                ],
                default => [
                    'status' => 'unknown',
                    'message' => 'Unknown synchronization status',
                    'progress' => $progress
                ],
            };
        } catch (Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to check sync status: ' . $e->getMessage()
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
            return null;
        }
    }
}