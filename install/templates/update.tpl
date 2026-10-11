
{if $coreUpdateFailed}
{* the database update of an automatic core update failed: retry it here, or restore in the updater *}
<h3>{$l10n->get('SYS_CORE_UPDATE_DATABASE_FAILED_TITLE')}</h3>

<div class="alert alert-warning" role="alert">
    <i class="bi bi-exclamation-triangle-fill"></i>
    {$coreUpdateFailedText}
</div>

<p>{$l10n->get('SYS_CORE_UPDATE_DATABASE_RETRY_DESC')}</p>
{else}
<h3>{$l10n->get('INS_WELCOME_TO_UPDATE')}</h3>

<p>{$l10n->get(
                'INS_WELCOME_TEXT_UPDATE',
                array(ADMIDIO_VERSION_TEXT,
                    $installedDbVersion,
                    '<a href="https://www.admidio.org/dokuwiki/doku.php?id=en:2.0:update" target="_blank">',
                    '</a>',
                    '<a href="https://www.admidio.org/forum" target="_blank">',
                    '</a>'
                )
            )}</p>

<p class="fw-bolder">{$l10n->get('SYS_CURRENT_DATABASE_VERSION')}: {$installedDbVersion}</p>
<p class="fw-bolder">{$l10n->get('SYS_DATABASE_VERSION_AFTER_UPDATE')}: {ADMIDIO_VERSION_TEXT}</p>

{* if this is a beta version then show a warning message *}
{if ADMIDIO_VERSION_BETA > 0}
    <div class="alert alert-warning alert-small" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        {$l10n->get('INS_WARNING_BETA_VERSION')}
    </div>
{/if}
{/if}

<form {foreach $attributes as $attribute}
{$attribute@key}="{$attribute}"
{/foreach}>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {if {array_key_exists array=$elements key='adm_login_name'}}
        <p>{$l10n->get('INS_ADMINISTRATOR_LOGIN_DESC')}</p>
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_login_name']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['adm_password']}
        {if $settings->has('two_factor_authentication_enabled') && $settings->getBool('two_factor_authentication_enabled')}
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_totp_code']}
        {/if}
    {/if}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_next_page']}
    {if $coreUpdateFailed}
        <a class="btn btn-secondary" href="{$coreUpdateRestoreUrl}"><i class="bi bi-arrow-counterclockwise"></i>{$coreUpdateRestoreText}</a>
    {/if}
</form>
