<div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card admidio-card" id="{$card.id}">
        <div class="card-body d-flex flex-column">
            <h5 class="card-title">{$card.title}</h5>
            <ul class="list-group list-group-flush">
                {if count($card.actions) > 0}
                    <li class="list-group-item">
                        {foreach $card.actions as $actionItem}
                            <a {if isset($actionItem.dataHref)} class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                                    {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                <i class="{$actionItem.icon}" data-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                        {/foreach}
                    </li>
                {/if}

                {foreach $card.information as $informationItem}
                    <li class="list-group-item">{$informationItem}</li>
                {/foreach}
            </ul>
            {foreach $card.buttons as $buttonItem}
                <a class="btn btn-primary mt-auto" href="{$buttonItem.url}">{$buttonItem.name}</a>
            {/foreach}
        </div>
    </div>
</div>
