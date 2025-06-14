{foreach $cards as $announcement}
    <div class="card admidio-blog" id="adm_announcement_{$announcement.uuid}">
        <div class="card-header">
            <i class="bi bi-newspaper"></i> {$announcement.title}

            {if $announcement.editable}
                <div class="dropdown float-end">
                    <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                    {if {array_key_exists array=$announcement key="actions"} && count($announcement.actions) > 0}
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            {foreach $announcement.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                            {/foreach}
                        </ul>
                    {/if}
                </div>
            {/if}
        </div>
        <div class="card-body">
        {if $enableClampLines}
            <div class="row">
                <div id="adm_announcement_caret_col_description_{$announcement.uuid}" class="col-auto clamp-caret">
                    <a id="adm_announcement_caret_description_{$announcement.uuid}" class="admidio-open-close-caret" data-target="adm_announcement_description_{$announcement.uuid}">
                        <i class="bi bi-caret-right-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_SHOW_MORE')}"></i>
                    </a>
                </div>
                <div class="col">
                    <div id="adm_announcement_description_{$announcement.uuid}" class="clamp-text">
                        {$announcement.description}
                    </div>
                </div>
            </div>
        {else}
            {$announcement.description}
        {/if}
        </div>
        <div class="card-footer container">
            <div class="row">
                <div class="col-auto">
                    <img class="rounded-circle" style="max-height: 40px; max-width: 40px;" src="{$announcement.userCreatedProfilePhotoUrl}" />
                </div>
                <div class="col admidio-info-category">
                    <span class="admidio-info-created">{$l10n->get('SYS_CREATED_BY_AND_AT', array($announcement.userCreatedName, $announcement.userCreatedTimestamp))}</span>
                    {$l10n->get('SYS_CATEGORY')} <a href="{$urlAdmidio}/modules/announcements.php?category_uuid={$announcement.categoryUUID}">{$announcement.category}</a>
                </div>
            </div>
        </div>
    </div>
{/foreach}

{$pagination}
