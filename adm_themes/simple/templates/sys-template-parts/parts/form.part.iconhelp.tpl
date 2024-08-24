{if $data.helpTextId}
    {if is_array($data.helpTextId)}
        {$helpTextId = $l10n->get($data.helpTextId[0],$data.helpTextId[1])}
    {else}
        {if {is_translation_string_id string=$data.helpTextId}}
            {$data.helpTextId = $l10n->get($data.helpTextId)}
        {/if}
    {/if}
    <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
    data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
    data-bs-content="{$data.helpTextId|escape:"html"}"></i>
{/if}
