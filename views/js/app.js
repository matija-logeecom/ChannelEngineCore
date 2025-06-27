/**
 * ChannelEngine Admin Application
 * Main application bootstrapper and coordinator
 */
var ChannelEngineApp = {
    services: {},
    controllers: {},
    initialized: false,

    /**
     * Initialize the application
     */
    init: function() {
        if (this.initialized) {
            console.warn('ChannelEngine: Application already initialized');
            return;
        }

        console.log('ChannelEngine: Initializing application...');

        try {
            this.initializeServices();
            this.initializeControllers();
            this.bindGlobalEvents();

            this.initializePage();

            this.initialized = true;
            console.log('ChannelEngine: Application initialization complete');
        } catch (error) {
            console.error('ChannelEngine: Application initialization failed:', error);
        }
    },

    /**
     * Initialize all services
     */
    initializeServices: function() {
        console.log('ChannelEngine: Initializing services...');

        this.services.ajax = new ChannelEngineAjaxService();
        this.services.polling = new ChannelEnginePollingService(this.services.ajax);

        console.log('ChannelEngine: Services initialized');
    },

    /**
     * Initialize all controllers
     */
    initializeControllers: function() {
        console.log('ChannelEngine: Initializing controllers...');

        this.controllers.modal = new ChannelEngineModalController();
        this.controllers.connection = new ChannelEngineConnectionController(
            this.services.ajax,
            this.controllers.modal
        );
        this.controllers.sync = new ChannelEngineSyncController(
            this.services.ajax,
            this.services.polling
        );

        console.log('ChannelEngine: Controllers initialized');
    },

    /**
     * Bind global application events
     */
    bindGlobalEvents: function() {
        var self = this;

        // Global keyboard events
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                self.controllers.modal.handleEscapeKey();
            }
        });

        // Global click events for modal backdrop
        document.addEventListener('click', function(event) {
            self.controllers.modal.handleBackdropClick(event);
        });

        console.log('ChannelEngine: Global events bound');
    },

    /**
     * Initialize page-specific functionality
     */
    initializePage: function() {
        // Check if this is a sync page and initialize accordingly
        if (this.isSyncPage()) {
            this.initializeSyncPage();
        }

        this.controllers.connection.checkInitialStatus();
    },

    /**
     * Initialize sync page specific functionality
     */
    initializeSyncPage: function() {
        console.log('ChannelEngine: Initializing sync page...');

        if (typeof window.channelEngineInitialSyncStatus !== 'undefined') {
            console.log('ChannelEngine: Loading initial sync status:', window.channelEngineInitialSyncStatus);
            this.controllers.sync.handleStatusUpdate(window.channelEngineInitialSyncStatus);
        }

        this.controllers.sync.startStatusPolling();
    },

    /**
     * Check if current page is a sync page
     */
    isSyncPage: function() {
        return document.querySelector('.sync-button') !== null;
    },

    /**
     * Get service instance
     */
    getService: function(name) {
        if (!this.services[name]) {
            console.error('ChannelEngine: Service not found:', name);
            return null;
        }
        return this.services[name];
    },

    /**
     * Get controller instance
     */
    getController: function(name) {
        if (!this.controllers[name]) {
            console.error('ChannelEngine: Controller not found:', name);
            return null;
        }
        return this.controllers[name];
    },

    /**
     * Cleanup when page is unloaded
     */
    cleanup: function() {
        console.log('ChannelEngine: Cleaning up application...');

        if (this.services.polling) {
            this.services.polling.stop();
        }

        this.initialized = false;
    }
};

// Global functions for backward compatibility and easy access
window.handleChannelEngineConnect = function() {
    if (ChannelEngineApp.controllers.connection) {
        ChannelEngineApp.controllers.connection.handleConnect();
    }
};

window.handleChannelEngineLogin = function() {
    if (ChannelEngineApp.controllers.connection) {
        ChannelEngineApp.controllers.connection.handleLogin();
    }
};

window.handleChannelEngineSync = function() {
    if (ChannelEngineApp.controllers.sync) {
        ChannelEngineApp.controllers.sync.handleSync();
    }
};

window.handleChannelEngineDisconnect = function() {
    if (ChannelEngineApp.controllers.connection) {
        ChannelEngineApp.controllers.connection.handleDisconnect();
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        ChannelEngineApp.init();
    });
} else {
    ChannelEngineApp.init();
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    ChannelEngineApp.cleanup();
});