{foreach $cards as $post}
    <div class="card container admidio-blog admidio-forum-post" id="adm_post_{$post.post_uuid}">
        <div class="row">
            <div class="col d-flex flex-column">
                <div class="card-body">
                    {$post.text}
                </div>
                {if {array_key_exists array=$post key='timestampChanged'} || $post.editable}
                    <div class="card-footer">
                        {if {array_key_exists array=$post key='timestampChanged'}}
                            {$l10n->get('SYS_LAST_EDITED_AT', array($post.timestampChanged))}
                        {/if}
                        {if {array_key_exists array=$post key="actions"} && count($post.actions) > 0}
                            {foreach $post.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                            {/foreach}
                        {/if}
                    </div>
                {/if}
            </div>
            <div class="col-lg-2 col-sm-3 col-4">
                <div class="card-body admidio-forum-entry-info">
                    <img class="rounded-circle d-block pb-1" src="{$post.userProfilePhotoUrl}" />
                    <a class="d-block pb-1" href="{$urlAdmidio}/adm_program/modules/profile/profile.php?user_uuid={$post.userUUID}">{$post.userName}</a>
                    <span class="d-block">{$l10n->get('SYS_CREATED_AT_VAR', array($post.timestampCreated))}</span>
                </div>
            </div>
        </div>
    </div>
{/foreach}

{$pagination}
