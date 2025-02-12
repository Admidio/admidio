{foreach $cards as $forumTopic}
    <div class="card container admidio-blog admidio-forum-topic" id="adm_topic_{$forumTopic.uuid}">
        <div class="row">
            <div class="col d-flex flex-column">
                <div class="card-header">
                    <i class="bi bi-chat-dots-fill"></i> <a href="{$forumTopic.url}">{$forumTopic.title}</a>

                    {if $forumTopic.editable}
                        <div class="dropdown float-end">
                            <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                            {if {array_key_exists array=$forumTopic key="actions"} && count($forumTopic.actions) > 0}
                                <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                    {foreach $forumTopic.actions as $actionItem}
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
                <div class="card-body flex-grow-1">
                    {$forumTopic.text}
                </div>
                <div class="card-footer container">
                    <div class="row">
                        {if $forumTopic.repliesCount > 0}
                            <div class="col-lg-6 col-12 text-bg-secondary bg-opacity-25 text-dark rounded">
                                <span class="d-block">{$l10n->get('SYS_REPLIES_VAR', array($forumTopic.repliesCount))}</span>
                                <a href="{$forumTopic.lastReplyUrl}">{$l10n->get('SYS_LAST_REPLY_BY_AT', array($forumTopic.lastReplyUserName, $forumTopic.lastReplyTimestamp))}</a>
                            </div>
                        {/if}
                        <div class="col">
                            <span class="d-block">{$l10n->get('SYS_VIEWS_VAR', array($forumTopic.views))}</span>
                            {if strlen($forumTopic.category) > 0}
                                    <span class="d-block">{$l10n->get('SYS_CATEGORY')} {$forumTopic.category}</span>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-sm-3 col-4">
                <div class="card-body admidio-forum-entry-info">
                    <img class="rounded-circle d-block pb-1" src="{$forumTopic.userProfilePhotoUrl}" />
                    <a class="d-block pb-1" href="{$urlAdmidio}/adm_program/modules/profile/profile.php?user_uuid={$forumTopic.userUUID}">{$forumTopic.userName}</a>
                    <span class="d-block">{$l10n->get('SYS_CREATED_AT_VAR', array($forumTopic.timestamp))}</span>
                </div>
            </div>
        </div>
    </div>
{/foreach}

{$pagination}
