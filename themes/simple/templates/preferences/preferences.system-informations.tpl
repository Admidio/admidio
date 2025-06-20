{foreach $cards as $card}
    <div class="card admidio-tabbed-field-group">
        <div class="card-header"><i class="bi {$card.icon} me-1"></i>{$card.title}</div>
        <div class="card-body">
            {include file=$card.templateFile}
        </div>
    </div>
{/foreach}