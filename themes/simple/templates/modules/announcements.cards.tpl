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
            <div id="adm_announcement_description_{$announcement.uuid}" class="clamp-text" style="--admidio-clamp-text-lines: {$clampLines};">
                {$announcement.description}
            </div>
            <div class="clamp-button">
                <a id="adm_announcement_caret_description_{$announcement.uuid}" onclick="showHideMoreText($(this), ['{$l10n->get('SYS_SHOW_MORE')}', '{$l10n->get('SYS_SHOW_LESS')}']);" role="button" class="admidio-more-less-button" data-target="adm_announcement_description_{$announcement.uuid}">
                    {$l10n->get('SYS_SHOW_MORE')}
                </a>
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
