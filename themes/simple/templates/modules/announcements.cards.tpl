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
                            {include 'sys-template-parts/list.functions.tpl' cardActions=true data=['uuid' => $announcement.uuid, 'actions' => $announcement.actions]}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    {/foreach}
{/if}

{$pagination}
