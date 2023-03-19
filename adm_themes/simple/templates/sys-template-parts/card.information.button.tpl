<div class="card admidio-card" id="{$title}">
    <div class="card-body d-flex flex-column">
        <h5 class="card-title">{$title}</h5>
        <ul class="list-group list-group-flush">
            {if count($actions) > 0}
                <li class="list-group-item">
                    {foreach $actions as $actionItem}
                        <a class="admidio-icon-link" href="{$actionItem.url}">'.
                            '<i class="{$actionItem.icon}" data-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                    {/foreach}
                </li>
            {/if}

            {foreach $information as $informationItem}
                <li class="list-group-item">{$informationItem}</li>
            {/foreach}
            {foreach $buttons as $buttonItem}
                <a class="btn btn-primary {$actionItem.class} mt-auto" href="{$actionItem.url}">{$actionItem.name}</a>
            {/foreach}
        </ul>
    </div>
</div>
