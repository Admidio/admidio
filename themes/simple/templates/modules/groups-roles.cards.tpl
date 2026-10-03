{foreach $cards as $card}
    <h2>{$card.name}</h2>
    <div class="row admidio-margin-bottom">
        {foreach $card.entries as $role}
            <div id="{$role.id}" class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card admidio-card">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><a href="{$role.url}">{$role.title}</a></h5>
                        {if isset($role.information) && count($role.information) > 0}
                            <ul class="list-group list-group-flush">
                                {foreach $role.information as $informationItem}
                                    <li class="list-group-item">{$informationItem}</li>
                                {/foreach}
                            </ul>
                        {/if}
                        {if isset($role.actions) && count($role.actions) > 0}
                            <div class="mt-auto pt-3 d-flex justify-content-end align-items-center">
                                {foreach $role.actions as $actionItem}
                                    <a
                                        {if isset($actionItem.dataHref)}class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                            data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else}class="admidio-icon-link" href="{$actionItem.url}"{/if} aria-label="{$actionItem.tooltip}">
                                        <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i>
                                    </a>
                                {/foreach}
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        {/foreach}
    </div>
{/foreach}
