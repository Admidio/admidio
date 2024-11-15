{if $data.helpTextId}
    {if is_array($data.helpTextId)}
        {$data.helpTextId = $l10n->get($data.helpTextId[0],$data.helpTextId[1])}
    {else}
        {if {is_translation_string_id string=$data.helpTextId}}
            {$data.helpTextId = $l10n->get($data.helpTextId)}
        {/if}
    {/if}
    <div class="form-text">{$data.helpTextId}</div>
{/if}
