{* The page an administrator gets for a page of a plugin that waits for its update. The button runs
   the update of the plugin manager; the script of the page then loads the page that was asked for. *}
<div class="message">
    <p class="lead">{$message}</p>
    <p class="text-muted">{$installedVersion} &rarr; {$fileVersion}</p>

    <div id="adm_plugin_update_result" class="alert d-none" role="alert"></div>

    <button id="adm_button_plugin_update" class="btn btn-primary admidio-margin-bottom" type="button">
        <i class="bi bi-arrow-clockwise"></i> {$l10n->get('SYS_PLUGIN_UPDATE')}
    </button>
    <button class="btn btn-secondary admidio-margin-bottom" type="button" onclick="history.back()">
        <i class="bi bi-arrow-left-circle-fill"></i> {$l10n->get('SYS_BACK')}
    </button>
</div>
