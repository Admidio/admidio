{if $helpTextId}
    {if is_array($helpTextId)}
        {$helpTextId = $l10n->get($helpTextId[0],$helpTextId[1])}
    {else}
        {if {is_translation_string_id string=$helpTextId}}
            {$helpTextId = $l10n->get($helpTextId)}
        {/if}
    {/if}
    <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
    data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
    title="{$l10n->get("SYS_NOTE")}" data-bs-content="{$helpTextId|escape:"html"}"></i>
{/if}
