/**
 * ChannelEngine Sync Controller
 * Handles synchronization operations and status updates
 */
function ChannelEngineSyncController(ajaxService, pollingService) {
    this.ajaxService = ajaxService;
    this.pollingService = pollingService;
    this.isInitialized = false;
    this.statusFormatter = new ChannelEngineStatusFormatter();
    this.init();
}

/**
 * Initialize sync controller
 */
ChannelEngineSyncController.prototype.init = function() {
    if (this.isInitialized) {
        return;
    }

    this.setupPollingCallbacks();
    this.isInitialized = true;

    console.log('ChannelEngine: Sync controller initialized');
};

/**
 * Setup polling service callbacks
 */
ChannelEngineSyncController.prototype.setupPollingCallbacks = function() {
    var self = this;

    this.pollingService.onSuccess(function(statusData) {
        self.handleStatusUpdate(statusData);
    });

    this.pollingService.onError(function(error) {
        self.handlePollingError(error);
    });
};

/**
 * Handle sync button click
 */
ChannelEngineSyncController.prototype.handleSync = function() {
    console.log('ChannelEngine: Handle sync called');

    this.setSyncButtonLoading(true);

    var self = this;

    this.ajaxService.sync()
        .then(function(response) {
            self.handleSyncSuccess(response);
        })
        .catch(function(error) {
            self.handleSyncError(error);
        });
};

/**
 * Handle successful sync start
 */
ChannelEngineSyncController.prototype.handleSyncSuccess = function(response) {
    console.log('ChannelEngine: Sync start successful:', response);

    if (response && response.success) {
        this.displayStatus({
            status: 'queued',
            progress: 0
        });

        this.startStatusPolling(2000); // Poll more frequently during sync
    } else {
        this.handleSyncError(new Error(response.message || 'Failed to start synchronization'));
    }
};

/**
 * Handle sync start error
 */
ChannelEngineSyncController.prototype.handleSyncError = function(error) {
    console.error('ChannelEngine: Sync start failed:', error);

    this.resetSyncButton();
    this.displayStatus({
        status: 'error',
        error_message: error.message || error
    });

    alert('Failed to start synchronization: ' + (error.message || error));
};

/**
 * Start status polling
 */
ChannelEngineSyncController.prototype.startStatusPolling = function(interval) {
    console.log('ChannelEngine: Starting sync status polling');
    this.pollingService.start(interval);
};

/**
 * Stop status polling
 */
ChannelEngineSyncController.prototype.stopStatusPolling = function() {
    console.log('ChannelEngine: Stopping sync status polling');
    this.pollingService.stop();
};

/**
 * Handle status update from polling
 */
ChannelEngineSyncController.prototype.handleStatusUpdate = function(statusData) {
    console.log('ChannelEngine: Sync status update received:', statusData);

    // Handle null or undefined statusData
    if (!statusData) {
        console.warn('ChannelEngine: statusData is null or undefined, using default');
        statusData = { status: 'none', progress: 0 };
    }

    // Validate status data
    if (!statusData.status) {
        console.warn('ChannelEngine: statusData.status is missing, using default');
        statusData = { status: 'none', progress: 0 };
    }

    this.displayStatus(statusData);
    this.updateSyncButton(statusData);
};

/**
 * Handle polling error
 */
ChannelEngineSyncController.prototype.handlePollingError = function(error) {
    console.error('ChannelEngine: Polling error:', error);
    // Don't show alerts for polling errors as they can be frequent
};

/**
 * Display sync status in UI
 */
ChannelEngineSyncController.prototype.displayStatus = function(statusData) {
    console.log('ChannelEngine: Displaying sync status:', statusData);

    var statusElement = document.getElementById('sync-status-value');
    if (!statusElement) {
        console.warn('ChannelEngine: sync-status-value element not found');
        return;
    }

    var message = this.statusFormatter.getStatusMessage(statusData);
    var cssClass = this.statusFormatter.getStatusClass(statusData.status);

    statusElement.className = '';
    statusElement.textContent = message;
    statusElement.classList.add(cssClass);

    this.updateProgressDisplay(statusData);
    this.updateErrorDisplay(statusData);
};

/**
 * Update progress display
 */
ChannelEngineSyncController.prototype.updateProgressDisplay = function(statusData) {
    var progressElement = document.querySelector('.sync-progress');
    if (!progressElement) {
        return;
    }

    if (statusData.status === 'in_progress' && statusData.progress !== undefined) {
        progressElement.style.display = 'block';
        progressElement.textContent = 'Progress: ' + Math.round(statusData.progress) + '%';
    } else {
        progressElement.style.display = 'none';
    }
};

/**
 * Update error message display
 */
ChannelEngineSyncController.prototype.updateErrorDisplay = function(statusData) {
    var errorElement = document.querySelector('.sync-error-message');
    if (!errorElement) {
        return;
    }

    if (statusData.status === 'failed' || statusData.status === 'error') {
        errorElement.style.display = 'block';
        var errorMessage = statusData.failure_description || statusData.error_message || 'Unknown error occurred';
        errorElement.textContent = errorMessage;
    } else {
        errorElement.style.display = 'none';
    }
};

/**
 * Update sync button based on status
 */
ChannelEngineSyncController.prototype.updateSyncButton = function(statusData) {
    var syncButton = document.querySelector('.sync-button');
    if (!syncButton) {
        return;
    }

    var status = statusData.status;

    switch (status) {
        case 'queued':
            syncButton.textContent = 'Queued...';
            syncButton.disabled = true;
            break;

        case 'in_progress':
            var progress = statusData.progress || 0;
            syncButton.textContent = 'Synchronizing... (' + Math.round(progress) + '%)';
            syncButton.disabled = true;
            break;

        default:
            syncButton.textContent = 'Synchronize';
            syncButton.disabled = false;
    }
};

/**
 * Set sync button to loading state
 */
ChannelEngineSyncController.prototype.setSyncButtonLoading = function(loading) {
    var syncButton = document.querySelector('.sync-button');
    if (!syncButton) {
        return;
    }

    if (loading) {
        syncButton.textContent = 'Starting...';
        syncButton.disabled = true;
    } else {
        syncButton.textContent = 'Synchronize';
        syncButton.disabled = false;
    }
};

/**
 * Reset sync button to default state
 */
ChannelEngineSyncController.prototype.resetSyncButton = function() {
    this.setSyncButtonLoading(false);
};

/**
 * Get current sync status
 */
ChannelEngineSyncController.prototype.getCurrentStatus = function() {
    return this.ajaxService.getSyncStatus();
};