<link rel="stylesheet" type="text/css" href="{$module_dir}views/css/admin.css">

<div class="channelengine-container">
    <div class="channelengine-header">
        <div class="channelengine-logo with-image">
            <!-- Placeholder for ChannelEngine logo -->
        </div>
    </div>

    <div class="channelengine-welcome-card">
        <div class="channelengine-icon with-image">
        </div>

        <h2 class="channelengine-welcome-title">Welcome to ChannelEngine</h2>

        <p class="channelengine-welcome-description">
            Connect, sync product data to ChannelEngine and orders to your shop.
        </p>

        <button class="channelengine-connect-btn" onclick="ChannelEngine.handleConnect()">
            Connect
        </button>
    </div>
</div>

{* Include the modal template directly *}
{include file="./modal.tpl"}

{* Include the required JavaScript files *}
<script src="{$module_dir}views/js/ChannelEngineAjax.js"></script>
<script src="{$module_dir}views/js/admin.js"></script>