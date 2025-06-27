<div class="channelengine-container">
    <div class="channelengine-header">
        <div class="channelengine-logo"></div>
    </div>

    <div class="channelengine-sync-section">
        <div class="sync-status-container">
            <div class="sync-status-row">
                <span class="sync-status-label">Sync Status:</span>
                <span id="sync-status-value" class="status-none">Loading...</span>
            </div>
        </div>

        <button class="sync-button" onclick="ChannelEngine.handleSync()">Synchronize</button>

        <div class="sync-progress" style="display: none;"></div>
        <div class="sync-error-message" style="display: none;"></div>
        <div class="sync-last-run" style="display: none;"></div>
    </div>
</div>

<script type="text/javascript">
    {if isset($sync_status_json)}
    window.channelEngineInitialSyncStatus = {$sync_status_json|escape:'javascript':'UTF-8'|json_decode:true|json_encode nofilter};
    {else}
    window.channelEngineInitialSyncStatus = {literal}{"status": "none", "message": "No sync data available"}{/literal};
    {/if}
</script>