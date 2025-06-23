function ChannelEngineAjax() {
    this.baseUrl = this.getAjaxUrl();
}

ChannelEngineAjax.prototype.getAjaxUrl = function() {
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

ChannelEngineAjax.prototype.request = function(method, data, callback, errorCallback) {
    var url = this.baseUrl;
    var options = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    if (method === 'GET' || method === 'DELETE') {
        if (data && Object.keys(data).length > 0) {
            var queryParams = new URLSearchParams(data).toString();
            url += '&' + queryParams;
        }
    } else {
        options.headers['Content-Type'] = 'application/json';
        if (data) {
            options.body = JSON.stringify(data);
        }
    }

    fetch(url, options)
        .then(function(response) {
            return response.json().then(function(responseData) {
                return {
                    ok: response.ok,
                    status: response.status,
                    statusText: response.statusText,
                    data: responseData
                };
            }).catch(function(parseError) {
                throw new Error('Invalid JSON response (HTTP ' + response.status + ')');
            });
        })
        .then(function(result) {
            if (result.ok) {
                console.log('ChannelEngine ' + method + ' Response:', result.data);
                if (callback) {
                    callback(result.data);
                }
            } else {
                console.error('ChannelEngine ' + method + ' Error:', result.data);
                var errorMessage = this.extractErrorMessage(result);
                if (errorCallback) {
                    errorCallback(errorMessage);
                }
            }
        }.bind(this))
        .catch(function(error) {
            console.error('ChannelEngine ' + method + ' request failed:', error);
            var errorMessage = this.formatNetworkError(error);
            if (errorCallback) {
                errorCallback(errorMessage);
            }
        }.bind(this));
};

ChannelEngineAjax.prototype.extractErrorMessage = function(result) {
    if (result.data && result.data.message) {
        return result.data.message;
    }
    if (result.data && result.data.error) {
        return result.data.error;
    }
    return 'HTTP ' + result.status + ': ' + result.statusText;
};

ChannelEngineAjax.prototype.formatNetworkError = function(error) {
    var errorMessage = error.message || 'Request failed';

    if (error.name === 'TypeError' && error.message.includes('fetch')) {
        return 'Network error - please check your connection';
    }
    if (error.message.includes('JSON')) {
        return 'Server returned invalid response format';
    }
    return errorMessage;
};

ChannelEngineAjax.prototype.get = function(data, callback, errorCallback) {
    this.request('GET', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.post = function(data, callback, errorCallback) {
    this.request('POST', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.put = function(data, callback, errorCallback) {
    this.request('PUT', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.delete = function(data, callback, errorCallback) {
    this.request('DELETE', data, callback, errorCallback);
};

ChannelEngineAjax.prototype.connect = function(accountName, apiKey, callback, errorCallback) {
    this.post({
        action: 'connect',
        account_name: accountName,
        api_key: apiKey
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.disconnect = function(callback, errorCallback) {
    this.delete({
        action: 'disconnect'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.getStatus = function(callback, errorCallback) {
    this.get({
        action: 'status'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.sync = function(callback, errorCallback) {
    this.post({
        action: 'sync'
    }, callback, errorCallback);
};

ChannelEngineAjax.prototype.getSyncStatus = function(callback, errorCallback) {
    this.get({
        action: 'sync_status'
    }, callback, errorCallback);
};