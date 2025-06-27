/**
 * ChannelEngine Modal Controller
 * Handles modal dialog interactions
 */
function ChannelEngineModalController() {
    this.modal = null;
    this.isInitialized = false;
    this.init();
}

/**
 * Initialize modal controller
 */
ChannelEngineModalController.prototype.init = function() {
    if (this.isInitialized) {
        return;
    }

    this.findModal();
    this.isInitialized = true;

    console.log('ChannelEngine: Modal controller initialized');
};

/**
 * Find modal element in DOM
 */
ChannelEngineModalController.prototype.findModal = function() {
    this.modal = document.getElementById('channelengine-modal');

    if (this.modal) {
        console.log('ChannelEngine: Modal found');
    } else {
        console.log('ChannelEngine: Modal not found (this is normal on sync page)');
    }
};

/**
 * Open the modal
 */
ChannelEngineModalController.prototype.open = function() {
    if (!this.modal) {
        console.error('ChannelEngine: No modal found, cannot open');
        this.showFallbackAlert('Error: Modal not found. Please refresh the page.');
        return false;
    }

    console.log('ChannelEngine: Opening modal');

    this.modal.classList.add('show');
    this.focusFirstInput();

    return true;
};

/**
 * Close the modal
 */
ChannelEngineModalController.prototype.close = function() {
    if (!this.modal) {
        return;
    }

    console.log('ChannelEngine: Closing modal');

    this.modal.classList.remove('show');
    this.clearForm();
};

/**
 * Handle escape key press
 */
ChannelEngineModalController.prototype.handleEscapeKey = function() {
    if (this.isOpen()) {
        this.close();
    }
};

/**
 * Handle backdrop click
 */
ChannelEngineModalController.prototype.handleBackdropClick = function(event) {
    if (event.target === this.modal && this.isOpen()) {
        this.close();
    }
};

/**
 * Check if modal is open
 */
ChannelEngineModalController.prototype.isOpen = function() {
    return this.modal && this.modal.classList.contains('show');
};

/**
 * Focus first input in modal
 */
ChannelEngineModalController.prototype.focusFirstInput = function() {
    if (!this.modal) {
        return;
    }

    var accountInput = this.modal.querySelector('#account_name');
    if (accountInput) {
        setTimeout(function() {
            accountInput.focus();
        }, 100);
    }
};

/**
 * Clear form inputs
 */
ChannelEngineModalController.prototype.clearForm = function() {
    if (!this.modal) {
        return;
    }

    var accountInput = this.modal.querySelector('#account_name');
    var apiKeyInput = this.modal.querySelector('#api_key');

    if (accountInput) accountInput.value = '';
    if (apiKeyInput) apiKeyInput.value = '';
};

/**
 * Get form data
 */
ChannelEngineModalController.prototype.getFormData = function() {
    if (!this.modal) {
        return null;
    }

    var accountInput = this.modal.querySelector('#account_name');
    var apiKeyInput = this.modal.querySelector('#api_key');

    if (!accountInput || !apiKeyInput) {
        return null;
    }

    return {
        accountName: accountInput.value.trim(),
        apiKey: apiKeyInput.value.trim()
    };
};

/**
 * Validate form data
 */
ChannelEngineModalController.prototype.validateForm = function() {
    var formData = this.getFormData();

    if (!formData) {
        return {
            valid: false,
            message: 'Form inputs not found'
        };
    }

    if (!formData.accountName || !formData.apiKey) {
        return {
            valid: false,
            message: 'Please fill in all fields'
        };
    }

    return {
        valid: true,
        data: formData
    };
};

/**
 * Set loading state on connect button
 */
ChannelEngineModalController.prototype.setConnectButtonLoading = function(loading) {
    if (!this.modal) {
        return;
    }

    var connectBtn = this.modal.querySelector('.channelengine-btn-primary');
    if (!connectBtn) {
        return;
    }

    if (loading) {
        connectBtn.textContent = 'Connecting...';
        connectBtn.disabled = true;
    } else {
        connectBtn.textContent = 'Connect';
        connectBtn.disabled = false;
    }
};

/**
 * Show alert when modal is not available
 */
ChannelEngineModalController.prototype.showFallbackAlert = function(message) {
    alert(message);
};

/**
 * Show success message and close modal
 */
ChannelEngineModalController.prototype.showSuccessAndClose = function(message, delay) {
    delay = delay || 1000;

    if (message) {
        console.log('ChannelEngine: ' + message);
    }

    this.close();

    var self = this;
    setTimeout(function() {
        window.location.reload();
    }, delay);
};

/**
 * Show error message
 */
ChannelEngineModalController.prototype.showError = function(message) {
    console.error('ChannelEngine: ' + message);
    alert(message);
};