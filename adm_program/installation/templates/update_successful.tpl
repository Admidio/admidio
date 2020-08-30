
<div id="installation-message">
    <h3>{$l10n->get("SYS_UPDATE_SUCCESSFUL")}</h3>

    <div class="alert alert-success form-alert">
        <i class="fas fa-check"></i>
        <strong>{$l10n->get('INS_UPDATING_WAS_SUCCESSFUL')}</strong>
    </div>

    <p>
        {$l10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', array(ADMIDIO_VERSION_TEXT))}<br /><br />
        {$l10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT')}
    </p>

    <p>{$content}</p>
</div>
