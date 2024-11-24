{foreach $cards as $card}
    <h2>{$card.name}</h2>
    <div class="row admidio-margin-bottom">
        {foreach $card.entries as $role}
            {include file='sys-template-parts/card.information.button.tpl' card=$role}
        {/foreach}
    </div>
{/foreach}
