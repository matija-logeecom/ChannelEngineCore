/**
 * ChannelEngine Polling Service
 * Handles periodic status polling with adaptive intervals
 */
function ChannelEnginePollingService(ajaxService) {
    this.ajaxService = ajaxService;
    this.intervalId = null;
    this.currentInterval = 5000; // Default 5 seconds
    this.callbacks = {
        success: [],
        error: []
    };
    this.isPolling = false;
}

/**
 * Start polling with optional custom interval
 */
ChannelEnginePollingService.prototype.start = function(interval) {
    if (this.isPolling) {
        console.warn('ChannelEngine: Polling already active, stopping previous instance');
        this.stop();
    }

    this.currentInterval = interval || this.currentInterval;
    this.isPolling = true;

    console.log('ChannelEngine: Starting status polling (every ' + this.currentInterval + 'ms)');

    // Poll immediately, then set up interval
    this.poll();

    var self = this;
    this.intervalId = setInterval(function() {
        self.poll();
    }, this.currentInterval);
};

/**
 * Stop polling
 */
ChannelEnginePollingService.prototype.stop = function() {
    if (this.intervalId) {
        clearInterval(this.intervalId);
        this.intervalId = null;
    }

    this.isPolling = false;
    console.log('ChannelEngine: Stopped status polling');
};

/**
 * Perform a single poll
 */
ChannelEnginePollingService.prototype.poll = function() {
    var self = this;

    this.ajaxService.getSyncStatus()
        .then(function(response) {
            if (response && response.success && response.data) {
                self.triggerSuccessCallbacks(response.data);
                self.adjustInterval(response.data.status);
            } else {
                console.error('ChannelEngine: Invalid sync status response:', response);
                self.triggerErrorCallbacks('Invalid response format');
            }
        })
        .catch(function(error) {
            console.error('ChannelEngine: Polling error:', error);
            self.triggerErrorCallbacks(error.message);
        });
};

/**
 * Adjust polling interval based on sync status
 */
ChannelEnginePollingService.prototype.adjustInterval = function(status) {
    var newInterval = this.getIntervalForStatus(status);

    if (newInterval !== this.currentInterval) {
        console.log('ChannelEngine: Adjusting polling interval from ' + this.currentInterval + 'ms to ' + newInterval + 'ms');
        this.currentInterval = newInterval;

        if (this.isPolling) {
            this.stop();
            this.start(newInterval);
        }
    }
};

/**
 * Get appropriate polling interval for status
 */
ChannelEnginePollingService.prototype.getIntervalForStatus = function(status) {
    switch (status) {
        case 'in_progress':
        case 'queued':
            return 2000;
        case 'completed':
        case 'failed':
        case 'aborted':
            return 10000;
        default:
            return 5000;
    }
};

/**
 * Add success callback
 */
ChannelEnginePollingService.prototype.onSuccess = function(callback) {
    if (typeof callback === 'function') {
        this.callbacks.success.push(callback);
    }
};

/**
 * Add error callback
 */
ChannelEnginePollingService.prototype.onError = function(callback) {
    if (typeof callback === 'function') {
        this.callbacks.error.push(callback);
    }
};

/**
 * Remove success callback
 */
ChannelEnginePollingService.prototype.offSuccess = function(callback) {
    var index = this.callbacks.success.indexOf(callback);
    if (index > -1) {
        this.callbacks.success.splice(index, 1);
    }
};

/**
 * Remove error callback
 */
ChannelEnginePollingService.prototype.offError = function(callback) {
    var index = this.callbacks.error.indexOf(callback);
    if (index > -1) {
        this.callbacks.error.splice(index, 1);
    }
};

/**
 * Clear all callbacks
 */
ChannelEnginePollingService.prototype.clearCallbacks = function() {
    this.callbacks.success = [];
    this.callbacks.error = [];
};

/**
 * Trigger success callbacks
 */
ChannelEnginePollingService.prototype.triggerSuccessCallbacks = function(data) {
    this.callbacks.success.forEach(function(callback) {
        try {
            callback(data);
        } catch (error) {
            console.error('ChannelEngine: Error in success callback:', error);
        }
    });
};

/**
 * Trigger error callbacks
 */
ChannelEnginePollingService.prototype.triggerErrorCallbacks = function(error) {
    this.callbacks.error.forEach(function(callback) {
        try {
            callback(error);
        } catch (callbackError) {
            console.error('ChannelEngine: Error in error callback:', callbackError);
        }
    });
};

/**
 * Check if currently polling
 */
ChannelEnginePollingService.prototype.isActive = function() {
    return this.isPolling;
};

/**
 * Get current polling interval
 */
ChannelEnginePollingService.prototype.getCurrentInterval = function() {
    return this.currentInterval;
};