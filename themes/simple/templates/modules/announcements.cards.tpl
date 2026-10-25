{if count($cards) == 0}
<div class="alert alert-info" role="alert">
    {$l10n->get('SYS_NO_ENTRIES')}
</div>
{else}
{foreach $cards as $announcement}
        <div class="card admidio-blog" id="adm_announcement_{$announcement.uuid}">
            <div class="card-header">
                <i class="bi bi-newspaper"></i> {$announcement.title}
            </div>
            <div class="card-body">
                {$announcement.description}
            </div>
            <div class="card-footer container">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <img class="rounded-circle admidio-small-photo" src="{$announcement.userCreatedProfilePhotoUrl}" />
                    </div>
                    <div class="col admidio-info-category">
                        <span class="admidio-info-created">{$l10n->get('SYS_CREATED_BY_AND_AT', array($announcement.userCreatedName, $announcement.userCreatedTimestamp))}</span>
                        {$l10n->get('SYS_CATEGORY')} <a href="{$urlAdmidio}/modules/announcements.php?category_uuid={$announcement.categoryUUID}">{$announcement.category}</a>
                    </div>
                    {if $announcement.editable && {array_key_exists array=$announcement key="actions"} && count($announcement.actions) > 0}
                        <div class="col-auto ms-auto text-nowrap">
                            {foreach $announcement.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox d-inline-flex p-1 fs-6" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage|escape:'html'}" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link d-inline-flex p-1 fs-6" href="{$actionItem.url}"{/if}
                                    aria-label="{$actionItem.tooltip|escape:'html'}">
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip|escape:'html'}" aria-hidden="true"></i></a>
                            {/foreach}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    {/foreach}
{/if}

{$pagination}
