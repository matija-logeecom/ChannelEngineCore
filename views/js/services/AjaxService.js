/**
 * ChannelEngine AJAX Service
 * Handles all HTTP requests to the backend
 */
function ChannelEngineAjaxService() {
    this.baseUrl = this.getAjaxUrl();
    this.defaultHeaders = {
        'X-Requested-With': 'XMLHttpRequest'
    };
}

/**
 * Get the AJAX URL for requests
 */
ChannelEngineAjaxService.prototype.getAjaxUrl = function() {
    var currentUrl = window.location.href;
    var url = currentUrl.split('?')[0] + '?controller=AdminChannelEngine&ajax=1';

    var urlParts = currentUrl.split('token=');
    if (urlParts.length > 1) {
        var token = urlParts[1].split('&')[0];
        url += '&token=' + token;
    }

    console.log('ChannelEngine AJAX URL:', url);
    return url;
};

/**
 * Make a generic HTTP request
 */
ChannelEngineAjaxService.prototype.request = function(method, data, options) {
    options = options || {};

    return new Promise((resolve, reject) => {
        var url = this.baseUrl;
        var fetchOptions = {
            method: method,
            headers: Object.assign({}, this.defaultHeaders, options.headers || {})
        };

        if (method === 'GET' || method === 'DELETE') {
            if (data && Object.keys(data).length > 0) {
                var queryParams = new URLSearchParams(data).toString();
                url += '&' + queryParams;
            }
        } else {
            fetchOptions.headers['Content-Type'] = 'application/json';
            if (data) {
                fetchOptions.body = JSON.stringify(data);
            }
        }

        fetch(url, fetchOptions)
            .then(response => {
                return response.json().then(responseData => {
                    return {
                        ok: response.ok,
                        status: response.status,
                        statusText: response.statusText,
                        data: responseData
                    };
                }).catch(parseError => {
                    throw new Error('Invalid JSON response (HTTP ' + response.status + ')');
                });
            })
            .then(result => {
                if (result.ok) {
                    console.log('ChannelEngine ' + method + ' Response:', result.data);
                    resolve(result.data);
                } else {
                    console.error('ChannelEngine ' + method + ' Error:', result.data);
                    var errorMessage = this.extractErrorMessage(result);
                    reject(new Error(errorMessage));
                }
            })
            .catch(error => {
                console.error('ChannelEngine ' + method + ' request failed:', error);
                var errorMessage = this.formatNetworkError(error);
                reject(new Error(errorMessage));
            });
    });
};

/**
 * Extract error message from response
 */
ChannelEngineAjaxService.prototype.extractErrorMessage = function(result) {
    if (result.data && result.data.message) {
        return result.data.message;
    }
    if (result.data && result.data.error) {
        return result.data.error;
    }
    return 'HTTP ' + result.status + ': ' + result.statusText;
};

/**
 * Format network error messages
 */
ChannelEngineAjaxService.prototype.formatNetworkError = function(error) {
    var errorMessage = error.message || 'Request failed';

    if (error.name === 'TypeError' && error.message.includes('fetch')) {
        return 'Network error - please check your connection';
    }
    if (error.message.includes('JSON')) {
        return 'Server returned invalid response format';
    }
    return errorMessage;
};

/**
 * HTTP GET request
 */
ChannelEngineAjaxService.prototype.get = function(data, options) {
    return this.request('GET', data, options);
};

/**
 * HTTP POST request
 */
ChannelEngineAjaxService.prototype.post = function(data, options) {
    return this.request('POST', data, options);
};

/**
 * HTTP PUT request
 */
ChannelEngineAjaxService.prototype.put = function(data, options) {
    return this.request('PUT', data, options);
};

/**
 * HTTP DELETE request
 */
ChannelEngineAjaxService.prototype.delete = function(data, options) {
    return this.request('DELETE', data, options);
};

/**
 * Connect to ChannelEngine
 */
ChannelEngineAjaxService.prototype.connect = function(accountName, apiKey) {
    return this.post({
        action: 'connect',
        account_name: accountName,
        api_key: apiKey
    });
};

/**
 * Disconnect from ChannelEngine
 */
ChannelEngineAjaxService.prototype.disconnect = function() {
    return this.delete({
        action: 'disconnect'
    });
};

/**
 * Get connection status
 */
ChannelEngineAjaxService.prototype.getStatus = function() {
    return this.get({
        action: 'status'
    });
};

/**
 * Start synchronization
 */
ChannelEngineAjaxService.prototype.sync = function() {
    return this.post({
        action: 'sync'
    });
};

/**
 * Get synchronization status
 */
ChannelEngineAjaxService.prototype.getSyncStatus = function() {
    return this.get({
        action: 'sync_status'
    });
};