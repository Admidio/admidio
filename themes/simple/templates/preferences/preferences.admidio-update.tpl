<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_ADMIDIO_VERSION')}
    </label>
    <div class="col-sm-9">
        <div>
            <span id="adm_version_content">{$admidioVersion}
                <a id="adm_link_check_update" href="#adm_link_check_update" title="{$l10n->get('SYS_CHECK_FOR_UPDATE')}">{$l10n->get('SYS_CHECK_FOR_UPDATE')}</a>
            </span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="lastUpdateStep" class="col-sm-3 col-form-label">
        {$l10n->get('ORG_LAST_UPDATE_STEP')}
    </label>
    <div class="col-sm-9">
        <div id="lastUpdateStep">
            <span class="{$updateStepColorClass}"><strong>{$updateStepText}</strong></span>
        </div>
    </div>
</div>
{if $databaseType == 'mysql' || $databaseType == 'mariadb'}
    <div class="admidio-form-group admidio-form-custom-content row mb-3">
        <label class="col-sm-3 col-form-label">
            {$l10n->get('SYS_DATABASE_BACKUP')}
        </label>
        <div class="col-sm-9">
            <div>
                <a class="btn btn-secondary" id="add_another_organization" href="{$backupUrl}">
                    <i class="bi bi-download"></i>{$l10n->get('SYS_DOWNLOAD_DATABASE_BACKUP')}</a>
                <div class="form-text">{$l10n->get('SYS_DATABASE_BACKUP_DESC')}</div>
            </div>
        </div>
    </div>
{/if}
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DONATE')}
    </label>
    <div class="col-sm-9">
        <div>
            <a id="donate" class="icon-link" href="{$admidioHomepage}donate.php" target="_blank">
                <i class="bi bi-heart-fill"></i>{$l10n->get('SYS_DONATE')}</a>
            <div class="form-text">{$l10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT')}</div>
        </div>
    </div>
</div>
