<div id="channelengine-modal" class="channelengine-modal">
    <div class="channelengine-modal-content">
        <div class="channelengine-modal-header">
            <h3 class="channelengine-modal-title">Login to ChannelEngine</h3>
            <button type="button" class="channelengine-modal-close" onclick="ChannelEngineApp.controllers.modal.close()">&times;</button>
        </div>
        <div class="channelengine-modal-body">
            <p style="margin-bottom: 20px; color: #666;">Please enter account data:</p>
            <div class="channelengine-form-group">
                <label class="channelengine-form-label">Account name</label>
                <input type="text" id="account_name" class="channelengine-form-input">
            </div>
            <div class="channelengine-form-group">
                <label class="channelengine-form-label">Api key</label>
                <input type="password" id="api_key" class="channelengine-form-input">
            </div>
        </div>
        <div class="channelengine-modal-footer">
            <button type="button" class="channelengine-btn channelengine-btn-primary" onclick="handleChannelEngineLogin()">Connect</button>
        </div>
    </div>
</div>