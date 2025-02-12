{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if}

<div class="table-responsive">
    <table id="forum-table" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th>{$l10n->get('SYS_TOPIC')}</th>
                <th class="text-center">{$l10n->get('SYS_POSTS')}</th>
                <th class="text-center">{$l10n->get('SYS_VIEWS')}</th>
                <th>{$l10n->get('SYS_LAST_POST')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="adm_topic_{$row.uuid}">
                    <td class="row align-items-center">
                        <div class="col">
                            <span class="fs-5"><a href="{$row.url}">{$row.title}</a></span>
                            <span class="d-block" style="font-size: 0.75rem">{$l10n->get('SYS_CREATED_BY_AND_AT', array($row.userNameWithLink, $row.timestamp))}</span>
                        </div>
                        <div class="col-auto"><img class="rounded-circle" style="max-height: 40px; max-width: 40px;" src="{$row.userProfilePhotoUrl}" /></div>
                    </td>
                    <td class="text-center">{$row.repliesCount}</td>
                    <td class="text-center">{$row.views}</td>
                    <td>
                        {if $row.repliesCount > 0}
                            {$row.lastReplyInfo}
                        {/if}
                    </td>
                    <td class="text-end">
                        {if {array_key_exists array=$row key='actions'}}
                            {foreach $row.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-function-link admidio-messagebox" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$l10n->get('SYS_DELETE_ENTRY', array({$row.title}))}" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link admidio-function-link" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                            {/foreach}
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
