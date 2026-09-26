<div id="links_overview">
    {if count($categories) === 0}
        <p>{$l10n->get($singleLink ? 'SYS_NO_ENTRY' : 'SYS_NO_ENTRIES')}</p>
    {else}
        {foreach $categories as $category}
            <h2>{$category.name}</h2>
            <div class="row admidio-margin-bottom admidio-weblinks-grid">
                {foreach $category.links as $link}
                    <div id="lnk_{$link.uuid}" class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="card admidio-card">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">
                                    <a href="{$link.url|escape:'html'}" target="{$target}" rel="noopener noreferrer" title="{$link.destination}">
                                        {$link.name}<i class="bi bi-box-arrow-up-right fs-6 ms-2" aria-hidden="true"></i>
                                    </a>
                                </h5>
                                <div class="small text-body-secondary text-break" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{$link.destination}">
                                    <span class="visually-hidden">{$l10n->get('SYS_LINK_ADDRESS')}: </span>{$link.destinationHost|escape:'html'}
                                </div>
                                {if $link.description !== ''}
                                    <div class="admidio-weblink-description mt-3">
                                        {$link.description}
                                    </div>
                                {/if}
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <small class="text-body-secondary">{$l10n->get('SYS_COUNTER')}: {$link.counter}</small>
                                    {if $link.editable}
                                        <div>
                                            <a class="admidio-icon-link admidio-link-move" href="javascript:void(0)" data-uuid="{$link.uuid}" data-direction="UP" data-target="lnk_{$link.uuid}" aria-label="{$l10n->get('SYS_MOVE_LEFT', array('SYS_WEBLINK'))}"{if $link@first} style="display: none;"{/if}><i class="bi bi-arrow-left-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_LEFT', array('SYS_WEBLINK'))}"></i></a>
                                            <a class="admidio-icon-link admidio-link-move" href="javascript:void(0)" data-uuid="{$link.uuid}" data-direction="DOWN" data-target="lnk_{$link.uuid}" aria-label="{$l10n->get('SYS_MOVE_RIGHT', array('SYS_WEBLINK'))}"{if $link@last} style="display: none;"{/if}><i class="bi bi-arrow-right-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_RIGHT', array('SYS_WEBLINK'))}"></i></a>
                                            <a class="admidio-icon-link" href="{$link.editUrl}"><i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT')}"></i></a>
                                            <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no" data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array($link.name))}" data-href="callUrlHideElement('lnk_{$link.uuid}', '{$link.deleteUrl}', '{$csrfToken}')"><i class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DELETE')}"></i></a>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                {/foreach}
            </div>
        {/foreach}
    {/if}
</div>
{$pagination}
