/**
 * ChannelEngine Connection Controller
 * Handles connection and disconnection operations
 */
function ChannelEngineConnectionController(ajaxService, modalController) {
    this.ajaxService = ajaxService;
    this.modalController = modalController;
    this.isInitialized = false;
    this.init();
}

/**
 * Initialize connection controller
 */
ChannelEngineConnectionController.prototype.init = function() {
    if (this.isInitialized) {
        return;
    }

    this.isInitialized = true;
    console.log('ChannelEngine: Connection controller initialized');
};

/**
 * Check initial connection status
 */
ChannelEngineConnectionController.prototype.checkInitialStatus = function() {
    console.log('ChannelEngine: Checking initial connection status');
};

/**
 * Handle connect button click
 */
ChannelEngineConnectionController.prototype.handleConnect = function() {
    console.log('ChannelEngine: Handle connect called');

    if (!this.modalController.open()) {
        return;
    }
};

/**
 * Handle login form submission
 */
ChannelEngineConnectionController.prototype.handleLogin = function() {
    console.log('ChannelEngine: Handle login called');

    var validation = this.modalController.validateForm();
    if (!validation.valid) {
        this.modalController.showError(validation.message);
        return;
    }

    var formData = validation.data;
    this.modalController.setConnectButtonLoading(true);

    var self = this;

    this.ajaxService.connect(formData.accountName, formData.apiKey)
        .then(function(response) {
            self.handleConnectSuccess(response);
        })
        .catch(function(error) {
            self.handleConnectError(error);
        })
        .finally(function() {
            self.modalController.setConnectButtonLoading(false);
        });
};

/**
 * Handle successful connection
 */
ChannelEngineConnectionController.prototype.handleConnectSuccess = function(response) {
    console.log('ChannelEngine: Connection successful:', response);

    if (response && response.success) {
        this.modalController.showSuccessAndClose('Connection successful!');
    } else {
        var errorMessage = 'Connection failed: ' + (response.message || 'Unknown error');
        this.modalController.showError(errorMessage);
    }
};

/**
 * Handle connection error
 */
ChannelEngineConnectionController.prototype.handleConnectError = function(error) {
    console.error('ChannelEngine: Connection failed:', error);

    var errorMessage = 'Connection failed: ' + (error.message || error);
    this.modalController.showError(errorMessage);
};

/**
 * Handle disconnect button click
 */
ChannelEngineConnectionController.prototype.handleDisconnect = function() {
    console.log('ChannelEngine: Handle disconnect called');

    if (!this.confirmDisconnect()) {
        return;
    }

    var self = this;

    this.ajaxService.disconnect()
        .then(function(response) {
            self.handleDisconnectSuccess(response);
        })
        .catch(function(error) {
            self.handleDisconnectError(error);
        });
};

/**
 * Confirm disconnect action
 */
ChannelEngineConnectionController.prototype.confirmDisconnect = function() {
    return confirm('Are you sure you want to disconnect from ChannelEngine?');
};

/**
 * Handle successful disconnection
 */
ChannelEngineConnectionController.prototype.handleDisconnectSuccess = function(response) {
    console.log('ChannelEngine: Disconnection successful:', response);

    if (response && response.success) {
        alert('Disconnected successfully!');
        window.location.reload();
    } else {
        var errorMessage = 'Disconnect failed: ' + (response.message || 'Unknown error');
        alert(errorMessage);
    }
};

/**
 * Handle disconnection error
 */
ChannelEngineConnectionController.prototype.handleDisconnectError = function(error) {
    console.error('ChannelEngine: Disconnection failed:', error);

    var errorMessage = 'Disconnect failed: ' + (error.message || error);
    alert(errorMessage);
};

/**
 * Get current connection status
 */
ChannelEngineConnectionController.prototype.getStatus = function() {
    return this.ajaxService.getStatus();
};