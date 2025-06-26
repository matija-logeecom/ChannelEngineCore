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
                        message: 'Synchronization started, processing...'
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
     * Handle sync status update from server
     */
    handleSyncStatusUpdate: function(statusData) {
        console.log('Sync status update:', statusData);

        this.displaySyncStatus(statusData);

        var syncButton = document.querySelector('.sync-button');
        var status = statusData ? statusData.status : 'error';
        if (syncButton) {
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

                case 'completed':
                    syncButton.textContent = 'Synchronize';
                    syncButton.disabled = false;
                    this.startSyncStatusPolling(10000);
                    break;

                case 'failed':
                case 'aborted':
                case 'error':
                    syncButton.textContent = 'Synchronize';
                    syncButton.disabled = false;
                    this.startSyncStatusPolling(10000);
                    break;

                default:
                    syncButton.textContent = 'Synchronize';
                    syncButton.disabled = false;
            }
        }
    },

    /**
     * Display sync status in the UI
     */
    displaySyncStatus: function(statusData) {
        console.log('Displaying sync status:', statusData);

        var statusValue = document.getElementById('sync-status-value');
        if (!statusValue) {
            console.warn('sync-status-value element not found');
            return;
        }

        statusValue.className = '';

        var status = statusData ? statusData.status : 'none';
        var displayText = '';
        var cssClass = '';

        switch (status) {
            case 'none':
                displayText = 'Not started';
                cssClass = 'status-none';
                break;

            case 'queued':
                displayText = 'Queued';
                cssClass = 'status-queued';
                break;

            case 'in_progress':
                var progress = statusData.progress || 0;
                displayText = 'In progress (' + Math.round(progress) + '%)';
                cssClass = 'status-in_progress';
                break;

            case 'completed':
                displayText = 'Completed';
                cssClass = 'status-done';
                break;

            case 'failed':
                displayText = 'Failed';
                cssClass = 'status-error';
                break;

            case 'aborted':
                displayText = 'Aborted';
                cssClass = 'status-error';
                break;

            case 'error':
                displayText = 'Error';
                cssClass = 'status-error';
                break;

            default:
                displayText = 'Unknown';
                cssClass = 'status-none';
        }

        statusValue.textContent = displayText;
        statusValue.classList.add(cssClass);

        var errorElement = document.querySelector('.sync-error-message');
        if (errorElement) {
            if ((status === 'failed' || status === 'error') && statusData.message) {
                errorElement.style.display = 'block';
                errorElement.textContent = statusData.message;
            } else {
                errorElement.style.display = 'none';
            }
        }

        var progressElement = document.querySelector('.sync-progress');
        if (progressElement) {
            if (status === 'in_progress' && statusData.progress !== undefined) {
                progressElement.style.display = 'block';
                progressElement.textContent = 'Progress: ' + Math.round(statusData.progress) + '%';
            } else {
                progressElement.style.display = 'none';
            }
        }
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        ChannelEngine.init();
    });
} else {
    ChannelEngine.init();
}