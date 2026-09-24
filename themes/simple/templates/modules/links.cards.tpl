<div id="links_overview">
{if count($categories) === 0}
    <p>{$l10n->get($singleLink ? 'SYS_NO_ENTRY' : 'SYS_NO_ENTRIES')}</p>
{else}
    {foreach $categories as $category}
        <div class="card admidio-blog">
            <div class="card-header">{$category.name}</div>
            <div class="card-body">
                {foreach $category.links as $link}
                    <div class="mb-3" id="lnk_{$link.uuid}">
                        <a class="icon-link" href="{$link.url}" target="{$target}"><i class="bi bi-link"></i>{$link.name}</a>
                        {if $link.editable}
                            <a class="admidio-icon-link" href="{$link.editUrl}"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT')}"></i></a>
                            <a class="admidio-icon-link admidio-link-move" href="javascript:void(0)" data-uuid="{$link.uuid}" data-direction="UP" data-target="lnk_{$link.uuid}"><i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('SYS_WEBLINK'))}"></i></a>
                            <a class="admidio-icon-link admidio-link-move" href="javascript:void(0)" data-uuid="{$link.uuid}" data-direction="DOWN" data-target="lnk_{$link.uuid}"><i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('SYS_WEBLINK'))}"></i></a>
                            <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no" data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($link.name))}" data-href="callUrlHideElement('lnk_{$link.uuid}', '{$link.deleteUrl}', '{$csrfToken}')"><i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE')}"></i></a>
                        {/if}
                        {if $link.description !== ''}<div class="admidio-weblink-description">{$link.description}</div>{/if}
                        <div class="weblink-counter"><small>{$l10n->get('SYS_COUNTER')}: {$link.counter}</small></div>
                    </div>
                {/foreach}
            </div>
        </div>
    {/foreach}
{/if}
</div>
{$pagination}
