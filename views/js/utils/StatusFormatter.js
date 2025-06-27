/**
 * ChannelEngine Status Formatter
 * Utility class for formatting and displaying sync status information
 */
function ChannelEngineStatusFormatter() {
    this.statusMessages = {
        'none': 'Not Started',
        'queued': 'Queued',
        'in_progress': 'In Progress',
        'completed': 'Completed',
        'failed': 'Failed',
        'aborted': 'Aborted',
        'error': 'Error'
    };

    this.statusClasses = {
        'none': 'status-none',
        'queued': 'status-queued',
        'in_progress': 'status-in_progress',
        'completed': 'status-done',
        'failed': 'status-error',
        'aborted': 'status-error',
        'error': 'status-error'
    };
}

/**
 * Get user-friendly status message
 */
ChannelEngineStatusFormatter.prototype.getStatusMessage = function(statusData) {
    if (!statusData || !statusData.status) {
        return 'Invalid status data';
    }

    var status = statusData.status;
    var baseMessage = this.statusMessages[status] || 'Unknown status';

    // Handle special cases with additional information
    switch (status) {
        case 'in_progress':
            return this.formatInProgressMessage(statusData, baseMessage);

        case 'failed':
            return this.formatFailedMessage(statusData, baseMessage);

        case 'error':
            return this.formatErrorMessage(statusData, baseMessage);

        case 'completed':
            return this.formatCompletedMessage(statusData, baseMessage);

        default:
            return baseMessage;
    }
};

/**
 * Format in-progress status message
 */
ChannelEngineStatusFormatter.prototype.formatInProgressMessage = function(statusData, baseMessage) {
    var progress = statusData.progress || 0;
    return 'Synchronization in progress (' + Math.round(progress) + '%)';
};

/**
 * Format failed status message
 */
ChannelEngineStatusFormatter.prototype.formatFailedMessage = function(statusData, baseMessage) {
    if (statusData.failure_description) {
        return 'Synchronization failed: ' + statusData.failure_description;
    }
    return baseMessage;
};

/**
 * Format error status message
 */
ChannelEngineStatusFormatter.prototype.formatErrorMessage = function(statusData, baseMessage) {
    if (statusData.error_message) {
        return statusData.error_message;
    }
    return baseMessage;
};

/**
 * Format completed status message
 */
ChannelEngineStatusFormatter.prototype.formatCompletedMessage = function(statusData, baseMessage) {
    if (statusData.finished_at) {
        var finishedDate = new Date(statusData.finished_at * 1000);
        return baseMessage + ' at ' + finishedDate.toLocaleString();
    }
    return baseMessage;
};

/**
 * Get CSS class for status
 */
ChannelEngineStatusFormatter.prototype.getStatusClass = function(status) {
    return this.statusClasses[status] || 'status-none';
};

/**
 * Format progress percentage
 */
ChannelEngineStatusFormatter.prototype.formatProgress = function(progress) {
    if (progress === undefined || progress === null) {
        return '0%';
    }
    return Math.round(progress) + '%';
};

/**
 * Get status priority for sorting/comparison
 */
ChannelEngineStatusFormatter.prototype.getStatusPriority = function(status) {
    var priorities = {
        'error': 1,
        'failed': 2,
        'aborted': 3,
        'in_progress': 4,
        'queued': 5,
        'completed': 6,
        'none': 7
    };

    return priorities[status] || 999;
};

/**
 * Check if status indicates an active operation
 */
ChannelEngineStatusFormatter.prototype.isActiveStatus = function(status) {
    return status === 'in_progress' || status === 'queued';
};

/**
 * Check if status indicates completion (success or failure)
 */
ChannelEngineStatusFormatter.prototype.isCompletedStatus = function(status) {
    return status === 'completed' || status === 'failed' || status === 'aborted' || status === 'error';
};

/**
 * Check if status indicates an error condition
 */
ChannelEngineStatusFormatter.prototype.isErrorStatus = function(status) {
    return status === 'failed' || status === 'aborted' || status === 'error';
};

/**
 * Format timestamp to readable date
 */
ChannelEngineStatusFormatter.prototype.formatTimestamp = function(timestamp) {
    if (!timestamp) {
        return 'N/A';
    }

    var date = new Date(timestamp * 1000);
    return date.toLocaleString();
};

/**
 * Format duration between two timestamps
 */
ChannelEngineStatusFormatter.prototype.formatDuration = function(startTimestamp, endTimestamp) {
    if (!startTimestamp || !endTimestamp) {
        return 'N/A';
    }

    var duration = endTimestamp - startTimestamp;
    var seconds = Math.floor(duration);
    var minutes = Math.floor(seconds / 60);
    var hours = Math.floor(minutes / 60);

    if (hours > 0) {
        return hours + 'h ' + (minutes % 60) + 'm ' + (seconds % 60) + 's';
    } else if (minutes > 0) {
        return minutes + 'm ' + (seconds % 60) + 's';
    } else {
        return seconds + 's';
    }
};