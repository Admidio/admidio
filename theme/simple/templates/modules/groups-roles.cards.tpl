{$category = $cards[0].category}
<h2>{$cards[0].category}</h2>
<div class="row admidio-margin-bottom">
    {foreach $cards as $card}
        {if $category != $card.category}
            </div>
            <h2>{$card.category}</h2>
            <div class="row admidio-margin-bottom">
            {$category = $card.category}
        {/if}
        {include file='sys-template-parts/card.information.button.tpl'}
    {/foreach}
</div>
