<?php

use ChannelEngine\BusinessLogic\Authorization\Contracts\AuthorizationService;
use ChannelEngine\BusinessLogic\Authorization\DTO\AuthInfo;
use ChannelEngine\BusinessLogic\Authorization\Exceptions\CurrencyMismatchException;
use ChannelEngine\Infrastructure\ServiceRegister;

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
        $this->addCSS($this->module->getPathUri() . 'views/css/sync.css');
        $this->addJS($this->module->getPathUri() . 'views/js/ChannelEngineAjax.js');
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js');

        $accountName = $this->getAccountName();

        $this->context->smarty->assign([
            'module_dir' => $this->module->getPathUri(),
            'account_name' => $accountName,
            'is_connected' => true,
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
                'account_name' => $this->getAccountName()
            ]
        ];
    }

    /**
     * Handle sync request
     *
     * @return array
     */
    private function handleSync(): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'message' => 'Not connected to ChannelEngine'];
        }

        return ['success' => true, 'message' => 'Sync started successfully'];
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