/**
 * Enhanced ChannelEngine Admin Interface with Sync Status Polling
 */
var ChannelEngine = {
    ajax: null,
    modal: null,
    syncStatusInterval: null,

    init: function() {
        console.log('ChannelEngine: Initializing...');

        this.ajax = new ChannelEngineAjax();
        this.findModal();
        this.bindEvents();

        if (this.isSyncPage()) {
            if (typeof window.channelEngineInitialSyncStatus !== 'undefined') {
                console.log('Initial sync status:', window.channelEngineInitialSyncStatus);
                this.handleSyncStatusUpdate(window.channelEngineInitialSyncStatus);
            }

            this.startSyncStatusPolling();
        }

        console.log('ChannelEngine: Initialization complete');
    },

    isSyncPage: function() {
        return document.querySelector('.sync-button') !== null;
    },

    findModal: function() {
        this.modal = document.getElementById('channelengine-modal');

        if (this.modal) {
            console.log('ChannelEngine: Modal found');
        } else {
            console.log('ChannelEngine: Modal not found (this is normal on sync page)');
        }
    },

    bindEvents: function() {
        var self = this;

        document.addEventListener('click', function(event) {
            if (event.target === self.modal) {
                self.closeModal();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && self.modal && self.modal.classList.contains('show')) {
                self.closeModal();
            }
        });
    },

    handleConnect: function() {
        console.log('ChannelEngine: handleConnect called');

        if (!this.modal) {
            console.error('ChannelEngine: No modal found, cannot open');
            alert('Error: Modal not found. Please refresh the page.');
            return;
        }

        this.openModal();
    },

    openModal: function() {
        console.log('ChannelEngine: Opening modal');

        if (this.modal) {
            this.modal.classList.add('show');

            var accountInput = document.getElementById('account_name');
            if (accountInput) {
                accountInput.focus();
            }
        }
    },

    closeModal: function() {
        console.log('ChannelEngine: Closing modal');

        if (this.modal) {
            this.modal.classList.remove('show');
            this.clearForm();
        }
    },

    clearForm: function() {
        var accountInput = document.getElementById('account_name');
        var apiKeyInput = document.getElementById('api_key');

        if (accountInput) accountInput.value = '';
        if (apiKeyInput) apiKeyInput.value = '';
    },

    handleLogin: function() {
        console.log('ChannelEngine: handleLogin called');

        var accountInput = document.getElementById('account_name');
        var apiKeyInput = document.getElementById('api_key');
        var connectBtn = this.modal ? this.modal.querySelector('.channelengine-btn-primary') : null;

        if (!accountInput || !apiKeyInput) {
            alert('Form inputs not found');
            return;
        }

        var accountName = accountInput.value.trim();
        var apiKey = apiKeyInput.value.trim();

        if (!accountName || !apiKey) {
            alert('Please fill in all fields');
            return;
        }

        if (connectBtn) {
            connectBtn.textContent = 'Connecting...';
            connectBtn.disabled = true;
        }

        var self = this;

        this.ajax.connect(accountName, apiKey,
            function(response) {
                if (response && response.success) {
                    self.closeModal();

                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Connection failed: ' + (response.message || 'Unknown error'));
                }

                if (connectBtn) {
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                }
            },
            function(error) {
                alert('Connection failed: ' + error);

                if (connectBtn) {
                    connectBtn.textContent = 'Connect';
                    connectBtn.disabled = false;
                }
            }
        );
    },

    handleSync: function() {
        console.log('ChannelEngine: handleSync called');

        var syncButton = document.querySelector('.sync-button');
        if (syncButton) {
            syncButton.textContent = 'Starting...';
            syncButton.disabled = true;
        }

        var self = this;

        this.ajax.sync(
            function(response) {
                console.log('Sync response:', response);

                if (response && response.success) {
                    self.displaySyncStatus({
                        status: 'queued',
                        progress: 0
                    });

                    self.startSyncStatusPolling(2000);
                } else {
                    self.resetSyncButton();
                    self.displaySyncStatus({
                        status: 'error',
                        error_message: response.message || 'Failed to start synchronization'
                    });
                    alert('Failed to start synchronization: ' + (response.message || 'Unknown error'));
                }
            },
            function(error) {
                self.resetSyncButton();
                self.displaySyncStatus({
                    status: 'error',
                    error_message: error
                });
                alert('Failed to start synchronization: ' + error);
            }
        );
    },

    resetSyncButton: function() {
        var syncButton = document.querySelector('.sync-button');
        if (syncButton) {
            syncButton.textContent = 'Synchronize';
            syncButton.disabled = false;
        }
    },

    handleDisconnect: function() {
        if (!confirm('Are you sure you want to disconnect from ChannelEngine?')) {
            return;
        }

        var self = this;

        this.ajax.disconnect(
            function(response) {
                if (response && response.success) {
                    alert('Disconnected successfully!');
                    window.location.reload();
                } else {
                    alert('Disconnect failed: ' + (response.message || 'Unknown error'));
                }
            },
            function(error) {
                alert('Disconnect failed: ' + error);
            }
        );
    },

    /**
     * Helper method to get user-friendly status message
     */
    getStatusMessage: function(statusData) {
        if (!statusData || !statusData.status) {
            return 'Invalid status data';
        }

        var status = statusData.status;
        var messages = {
            'none': 'Not Started',
            'queued': 'Queued',
            'in_progress': 'In Progress',
            'completed': 'Completed',
            'failed': 'Failed',
            'aborted': 'Aborted',
            'error': 'Error'
        };

        var message = messages[status] || 'Unknown status';

        // Handle special cases
        switch (status) {
            case 'in_progress':
                var progress = statusData.progress || 0;
                message = 'Synchronization in progress (' + Math.round(progress) + '%)';
                break;

            case 'failed':
                if (statusData.failure_description) {
                    message = 'Synchronization failed: ' + statusData.failure_description;
                }
                break;

            case 'error':
                if (statusData.error_message) {
                    message = statusData.error_message;
                }
                break;

            case 'completed':
                if (statusData.finished_at) {
                    var finishedDate = new Date(statusData.finished_at * 1000);
                    message += ' at ' + finishedDate.toLocaleString();
                }
                break;
        }

        return message;
    },

    /**
     * Helper method to get CSS class for status
     */
    getStatusClass: function(status) {
        var classes = {
            'none': 'status-none',
            'queued': 'status-queued',
            'in_progress': 'status-in_progress',
            'completed': 'status-done',
            'failed': 'status-error',
            'aborted': 'status-error',
            'error': 'status-error'
        };

        return classes[status] || 'status-none';
    },

    /**
     * Helper method to update sync button based on status
     */
    updateSyncButton: function(statusData) {
        var syncButton = document.querySelector('.sync-button');
        if (!syncButton) return;

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
    },

    /**
     * Start polling for sync status updates
     */
    startSyncStatusPolling: function(interval) {
        interval = interval || 5000;

        var self = this;

        if (this.syncStatusInterval) {
            clearInterval(this.syncStatusInterval);
        }

        this.updateSyncStatus();

        this.syncStatusInterval = setInterval(function() {
            self.updateSyncStatus();
        }, interval);

        console.log('ChannelEngine: Started sync status polling (every ' + interval + 'ms)');
    },

    /**
     * Stop polling for sync status
     */
    stopSyncStatusPolling: function() {
        if (this.syncStatusInterval) {
            clearInterval(this.syncStatusInterval);
            this.syncStatusInterval = null;
            console.log('ChannelEngine: Stopped sync status polling');
        }
    },

    /**
     * Fetch and update current sync status
     */
    updateSyncStatus: function() {
        var self = this;

        this.ajax.getSyncStatus(
            function(response) {
                if (response && response.success && response.data) {
                    self.handleSyncStatusUpdate(response.data);
                } else {
                    console.error('Failed to get sync status:', response);
                }
            },
            function(error) {
                console.error('Error getting sync status:', error);
            }
        );
    },

    /**
     * Handle sync status update from server - simplified
     */
    handleSyncStatusUpdate: function(statusData) {
        console.log('Sync status update received:', statusData);

        // Handle null or undefined statusData
        if (!statusData) {
            console.warn('statusData is null or undefined, using default');
            statusData = { status: 'none', progress: 0 };
        }

        // Validate status data
        if (!statusData.status) {
            console.warn('statusData.status is missing, using default');
            statusData = { status: 'none', progress: 0 };
        }

        this.displaySyncStatus(statusData);
        this.updateSyncButton(statusData);

        // Adjust polling frequency based on status
        this.adjustPollingFrequency(statusData.status);
    },

    /**
     * Adjust polling frequency based on status
     */
    adjustPollingFrequency: function(status) {
        var interval = 5000; // default

        switch (status) {
            case 'in_progress':
            case 'queued':
                interval = 2000; // Poll more frequently during active sync
                break;
            case 'completed':
            case 'failed':
            case 'aborted':
                interval = 10000; // Poll less frequently when done
                break;
        }

        if (this.syncStatusInterval) {
            clearInterval(this.syncStatusInterval);
            this.syncStatusInterval = setInterval(() => {
                this.updateSyncStatus();
            }, interval);
        }
    },

    /**
     * Display sync status in the UI - now uses helper methods
     */
    displaySyncStatus: function(statusData) {
        console.log('Displaying sync status:', statusData);

        var statusValue = document.getElementById('sync-status-value');
        if (!statusValue) {
            console.warn('sync-status-value element not found');
            return;
        }

        var message = this.getStatusMessage(statusData);
        var cssClass = this.getStatusClass(statusData.status);

        statusValue.className = '';
        statusValue.textContent = message;
        statusValue.classList.add(cssClass);

        // Handle progress display
        var progressElement = document.querySelector('.sync-progress');
        if (progressElement) {
            if (statusData.status === 'in_progress' && statusData.progress !== undefined) {
                progressElement.style.display = 'block';
                progressElement.textContent = 'Progress: ' + Math.round(statusData.progress) + '%';
            } else {
                progressElement.style.display = 'none';
            }
        }

        // Handle error messages
        var errorElement = document.querySelector('.sync-error-message');
        if (errorElement) {
            if ((statusData.status === 'failed' || statusData.status === 'error')) {
                errorElement.style.display = 'block';
                var errorMessage = statusData.failure_description || statusData.error_message || 'Unknown error occurred';
                errorElement.textContent = errorMessage;
            } else {
                errorElement.style.display = 'none';
            }
        }
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        ChannelEngine.init();
    });
} else {
    ChannelEngine.init();
}