<div id="{$card.id}" class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card admidio-card">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">{$card.title}</h5>
            <ul class="list-group list-group-flush">
                {if array_key_exists('actions', $card) && count($card.actions) > 0}
                    <li class="list-group-item">
                        {foreach $card.actions as $actionItem}
                            <a {if isset($actionItem.dataHref)} class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                                    {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                <i class="{$actionItem.icon}" data-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                        {/foreach}
                    </li>
                {/if}

                {if array_key_exists('information', $card) && count($card.information) > 0}
                    {foreach $card.information as $informationItem}
                        <li class="list-group-item">{$informationItem}</li>
                    {/foreach}
                {/if}
            </ul>
            {if array_key_exists('buttons', $card) && count($card.buttons) > 0}
                {foreach $card.buttons as $buttonItem}
                    <a class="btn btn-primary mt-auto" href="{$buttonItem.url}">{$buttonItem.name}</a>
                {/foreach}
            {/if}
        </div>
    </div>
</div>
