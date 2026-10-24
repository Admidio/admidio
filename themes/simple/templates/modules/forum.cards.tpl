{if count($cards) == 0}
<div class="alert alert-info" role="alert">
    {$l10n->get('SYS_NO_ENTRIES')}
</div>
{else}
    {foreach $cards as $forumTopic}
        <div class="card container admidio-blog admidio-forum-topic" id="adm_topic_{$forumTopic.uuid}">
            <div class="row">
                <div class="col d-flex flex-column">
                    <div class="card-header">
                        <i class="bi bi-chat-dots-fill"></i> <a href="{$forumTopic.url}">{$forumTopic.title}</a>
                    </div>
                    <div class="card-body flex-grow-1">
                        {$forumTopic.text}
                    </div>
                    <div class="card-footer container">
                        <div class="row align-items-center">
                            {if $forumTopic.repliesCount > 0}
                                <div class="col-lg-6 col-12 text-bg-secondary bg-opacity-25 text-dark rounded">
                                    <span class="d-block">{$l10n->get('SYS_REPLIES_VAR', array($forumTopic.repliesCount))}</span>
                                    {$forumTopic.lastReplyInfo}
                                </div>
                            {/if}
                            <div class="col">
                                <span class="d-block">{$l10n->get('SYS_VIEWS_VAR', array($forumTopic.views))}</span>
                                {if $showCategories}
                                    <span class="d-block">{$l10n->get('SYS_CATEGORY')} <span class="fw-bold">{$forumTopic.category}</span></span>
                                {/if}
                            </div>
                            {if $forumTopic.editable && {array_key_exists array=$forumTopic key="actions"} && count($forumTopic.actions) > 0}
                                <div class="col-auto ms-auto text-nowrap">
                                    {foreach $forumTopic.actions as $actionItem}
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
                <div class="col-lg-2 col-sm-3 col-4">
                    <div class="card-body admidio-forum-entry-info">
                        <img class="rounded-circle d-block pb-1" src="{$forumTopic.userProfilePhotoUrl}" />
                        <a class="d-block pb-1" href="{$urlAdmidio}/modules/profile/profile.php?user_uuid={$forumTopic.userUUID}">{$forumTopic.userName}</a>
                        <span class="d-block">{$l10n->get('SYS_CREATED_AT_VAR', array($forumTopic.timestamp))}</span>
                    </div>
                </div>
            </div>
        </div>
    {/foreach}
{/if}

{$pagination}
