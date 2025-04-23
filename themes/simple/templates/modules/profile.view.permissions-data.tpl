<div class="row">
    {if count($userRights) > 0}
        {foreach $userRights as $userRight}
        <div class="col-sm-6 col-md-4 admidio-profile-user-right" data-bs-toggle="popover" data-bs-html="true"
            data-bs-trigger="hover click" data-bs-placement="auto" data-bs-content="{$l10n->get('SYS_ASSIGNED_BY_ROLES')}:
            <strong>{$userRight.roles}</strong>"><i class="bi {$userRight.icon}"></i>{$userRight.right}</div>
        {/foreach}
    {else}
        <div class="col-sm-12">{$l10n->get('SYS_NO_PERMISSIONS_ASSIGNED')}</div>
    {/if}
</div>