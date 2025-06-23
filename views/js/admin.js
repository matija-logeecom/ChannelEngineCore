/**
 * Simple ChannelEngine Admin Interface
 */
var ChannelEngine = {
    ajax: null,
    modal: null,

    init: function() {
        console.log('ChannelEngine: Initializing...');

        this.ajax = new ChannelEngineAjax();
        this.findModal();
        this.bindEvents();

        console.log('ChannelEngine: Initialization complete');
    },

    findModal: function() {
        this.modal = document.getElementById('channelengine-modal');

        if (this.modal) {
            console.log('ChannelEngine: Modal found');
        } else {
            console.error('ChannelEngine: Modal NOT found');
            var elements = document.querySelectorAll('[id*="channelengine"]');
            console.log('Elements with channelengine in ID:', elements);
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
            syncButton.textContent = 'Synchronizing...';
            syncButton.disabled = true;
        }

        this.displaySyncStatus({
            status: 'in_progress'
        });

        var self = this;

        this.ajax.sync(
            function(response) {
                self.resetSyncButton();

                if (response && response.success) {
                    self.displaySyncStatus({
                        status: 'done'
                    });

                    var message = response.message || 'Products synchronized successfully';
                    if (response.total_products && response.synced_products) {
                        message += ' (' + response.synced_products + '/' + response.total_products + ' products)';
                    }
                } else {
                    self.displaySyncStatus({
                        status: 'error',
                        error_message: response.message || 'Unknown error'
                    });
                    alert('Synchronization failed: ' + (response.message || 'Unknown error'));
                }
            },
            function(error) {
                self.resetSyncButton();
                self.displaySyncStatus({
                    status: 'error',
                    error_message: error
                });
                alert('Synchronization failed: ' + error);
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

    displaySyncStatus: function(statusData) {
        let status;
        if (statusData.status) {
            status = statusData.status;
        } else if (statusData.success) {
            status = 'done';
        } else {
            status = 'error';
        }

        var statusValue = document.getElementById('sync-status-value');
        if (statusValue) {
            statusValue.className = '';

            if (status === 'in_progress') {
                statusValue.textContent = 'In progress';
                statusValue.classList.add('status-in_progress');
            } else if (status === 'error') {
                statusValue.textContent = 'Error';
                statusValue.classList.add('status-error');
            } else {
                statusValue.textContent = 'Done';
                statusValue.classList.add('status-done');
            }
        }

        var errorElement = document.querySelector('.sync-error-message');
        if (errorElement) {
            if (status === 'error' && statusData.error_message) {
                errorElement.style.display = 'block';
                errorElement.textContent = 'Error: ' + statusData.error_message;
            } else {
                errorElement.style.display = 'none';
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