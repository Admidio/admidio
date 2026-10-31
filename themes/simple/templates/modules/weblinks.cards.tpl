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
                                    <div class="admidio-weblink-description text-break mt-3">
                                        {$link.description}
                                    </div>
                                {/if}
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <small class="text-body-secondary">{$l10n->get('SYS_COUNTER')}: {$link.counter}</small>
                                    {if $link.editable}
                                        <div>
                                            {assign var=linkActions value=[['url' => $link.editUrl, 'icon' => 'bi bi-pencil-square', 'tooltip' => $l10n->get('SYS_EDIT')], ['dataMessage' => $l10n->get('SYS_WANT_DELETE_ENTRY', [$link.name]), 'dataHref' => "callUrlHideElement('lnk_{$link.uuid}', '{$link.deleteUrl}', '{$csrfToken}')", 'icon' => 'bi bi-trash', 'tooltip' => $l10n->get('SYS_DELETE')]]}
                                            {$linkActions[] = ['class' => 'admidio-link-move', 'attributes' => ['data-uuid' => $link.uuid, 'data-direction' => 'UP', 'data-target' => "lnk_{$link.uuid}"], 'icon' => 'bi bi-arrow-left-circle-fill', 'tooltip' => $l10n->get('SYS_MOVE_LEFT', ['SYS_WEBLINK'])]}
                                            {$linkActions[] = ['class' => 'admidio-link-move', 'attributes' => ['data-uuid' => $link.uuid, 'data-direction' => 'DOWN', 'data-target' => "lnk_{$link.uuid}"], 'icon' => 'bi bi-arrow-right-circle-fill', 'tooltip' => $l10n->get('SYS_MOVE_RIGHT', ['SYS_WEBLINK'])]}
                                            {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $link.uuid, 'actions' => $linkActions]}
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
