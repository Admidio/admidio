{if $helpTextId}
    {if is_array($helpTextId)}
        {$helpTextId = $l10n->get($helpTextId[0],$helpTextId[1])}
    {else}
        {if {is_translation_string_id string=$helpTextId}}
            {$helpTextId = $l10n->get($helpTextId)}
        {/if}
    {/if}
    <div class="form-text">{$helpTextId}</div>
{/if}
