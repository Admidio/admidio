<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('SYS_REGISTERED_AS')}</h3>

    <div class="admidio-form-group mb-3">
        <div>
            {$l10n->get('SYS_MEMBER')}
        </div>
        <div>
            <strong>
                <a href="{$urlAdmidio}/modules/profile/profile.php?user_uuid={$userUUID}" title="{$l10n->get('SYS_SHOW_PROFILE')}">{$userName}</a>
            </strong>
        </div>
    </div>
    <div class="admidio-form-group mb-3">
        <div>
            {$l10n->get('PLG_LOGIN_FORM_ACTIVE_SINCE')}
        </div>
        <div>
            <strong>{$loginActiveSince}</strong>
        </div>
    </div>
    <div class="admidio-form-group mb-3">
        <div>
            {$l10n->get('PLG_LOGIN_FORM_LAST_LOGIN')}
        </div>
        <div>
            <strong>{$lastLogin}</strong>
        </div>
    </div>
    <div class="admidio-form-group mb-3">
        <div>
            {$l10n->get('PLG_LOGIN_FORM_NUMBER_OF_LOGINS')}
        </div>
        <div>
            <strong>{$numberOfLogins}</strong>
        </div>
    </div>
    {if $showLogoutLink}
        <a id="adm_logout_link" class="icon-link" href="{$urlAdmidio}/system/logout.php"><i class="bi bi-box-arrow-right"></i>{$l10n->get('SYS_LOGOUT')}</a>
    {/if}
</div>
