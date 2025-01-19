{foreach $cards as $post}
    <div class="card admidio-blog" id="adm_post_{$post.post_uuid}">
        <div class="card-header">
            {if $post.editable}
                <div class="dropdown float-end">
                    <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                    {if {array_key_exists array=$post key="actions"} && count($post.actions) > 0}
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            {foreach $post.actions as $actionItem}
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
            {$post.text}
        </div>
        <div class="card-footer">
            {$l10n->get('SYS_CREATED_BY_AND_AT', array($post.userName, $post.timestamp))}
        </div>
    </div>
{/foreach}
