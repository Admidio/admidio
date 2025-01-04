{foreach $cards as $forumTopic}
    <div class="card admidio-blog" id="adm_topic_{$forumTopic.uuid}">
        <div class="card-header">
            <i class="bi bi-chat-dots-fill"></i>{$forumTopic.title}

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

        <div class="card-body">
            {$forumTopic.text}
        </div>
    </div>
{/foreach}
