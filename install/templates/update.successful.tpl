
<div id="adm_installation_message">
    <h3>{$l10n->get("SYS_UPDATE_SUCCESSFUL")}</h3>

    <div class="alert alert-success form-alert">
        <i class="bi bi-check-lg"></i>
        <strong>{$l10n->get('INS_UPDATING_WAS_SUCCESSFUL')}</strong>
    </div>

    {if $updateWarnings|@count > 0 || $updateInfos|@count > 0}
        <h4>{$l10n->get('SYS_NOTE')}</h4>
        {if $updateInfos|@count > 0}
            {foreach from=$updateInfos item=data}
                {include file="sys-template-parts/parts/form.part.info.tpl"}
            {/foreach}
        {/if}
        {if $updateWarnings|@count > 0}
            {foreach from=$updateWarnings item=data}
                {include file="sys-template-parts/parts/form.part.warning.tpl"}
            {/foreach}
        {/if}
    {/if}

    <p>
        {$l10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', array(ADMIDIO_VERSION_TEXT))}<br /><br />
        {$l10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT')}
    </p>

    <p>
        <a id="buttonLater" class="btn btn-secondary admidio-margin-bottom" href="{$urlAdmidio}/modules/overview.php">
            <i class="bi bi-house-door-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_LATER')}"></i>{$l10n->get('SYS_LATER')}
        </a>
        <a id="buttonDonate" class="btn btn-primary admidio-margin-bottom ms-3" href="https://www.admidio.org/donate.php">
            <i class="bi bi-heart-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DONATE')}"></i>{$l10n->get('SYS_DONATE')}
        </a>
    </p>
</div>
