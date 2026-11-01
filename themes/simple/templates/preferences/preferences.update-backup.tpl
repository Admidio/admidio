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
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_CORE_UPDATE_TITLE')}
    </label>
    <div class="col-sm-9">
        <div>
            <a class="btn btn-primary" id="adm_start_core_update" href="{$coreUpdateUrl}">
                <i class="bi bi-arrow-repeat"></i>{$l10n->get('SYS_CORE_UPDATE_START')}</a>
            <div class="form-text">{$l10n->get('SYS_CORE_UPDATE_START_DESC')}</div>
        </div>
    </div>
</div>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_update_check']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_backup_path']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_update_dump_password']}
    {if isset($elements['system_update_dump_password_remove'])}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_update_dump_password_remove']}
    {/if}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_update_backup']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

<div class="admidio-form-group admidio-form-custom-content row mb-3 mt-4">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DATABASE_BACKUP')}
    </label>
    <div class="col-sm-9">
        <div>
            {if $backupPossible}
                <a class="btn btn-secondary admidio-send-csrf-token" id="adm_create_database_backup" data-url="{$backupUrl}" data-csrf-token="{$csrfToken}">
                    <i class="bi bi-database-down"></i>{$l10n->get('SYS_DATABASE_BACKUP_CREATE')}</a>
                <div class="form-text">{$l10n->get('SYS_DATABASE_BACKUP_CREATE_DESC')}</div>
            {else}
                <div class="form-text">{$l10n->get('SYS_DATABASE_BACKUP_NOT_POSSIBLE')}</div>
            {/if}
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_BACKUPS')}
    </label>
    <div class="col-sm-9">
        {if count($backups) > 0}
            <table class="table table-sm table-hover mb-1" id="adm_backups">
                <thead>
                    <tr>
                        <th>{$l10n->get('SYS_TYPE')}</th>
                        <th>{$l10n->get('SYS_DATE')}</th>
                        <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $backups as $backup}
                        <tr>
                            <td title="{$backup.name}">{$backup.kind}</td>
                            <td>{$backup.date}</td>
                            <td class="text-end">{$backup.size}</td>
                            <td class="text-end text-nowrap">
                                <a class="admidio-icon-link" href="{$backup.downloadUrl}" title="{$l10n->get('SYS_DOWNLOAD_FILE')}">
                                    <i class="bi bi-download"></i></a>
                                <form class="d-inline" method="post" action="{$backup.deleteUrl}"
                                    onsubmit="return window.confirm(this.dataset.question)" data-question="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($backup.name))|strip_tags|escape}">
                                    <input type="hidden" name="adm_csrf_token" value="{$csrfToken}">
                                    <button type="submit" class="btn btn-link admidio-icon-link p-0 align-baseline" title="{$l10n->get('SYS_DELETE')}">
                                        <i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="form-control-plaintext">{$l10n->get('SYS_NO_ENTRIES')}</div>
        {/if}
        <div class="form-text">{$l10n->get('SYS_BACKUPS_DESC')}</div>
    </div>
</div>
