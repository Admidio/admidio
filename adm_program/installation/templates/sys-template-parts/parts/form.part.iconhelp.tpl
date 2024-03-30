{if $helpTextId}
    {if {is_translation_string_id string=$helpTextId}}
        {$helpTextId = $l10n->get($helpTextId)}
    {/if}
    <i class="fas fa-info-circle admidio-info-icon" data-bs-toggle="popover"
    data-html="true" data-trigger="hover click" data-placement="auto"
    title="{$l10n->get('SYS_NOTE')}" data-content="{$helpTextId|escape:'html'}"></i>
{/if}
