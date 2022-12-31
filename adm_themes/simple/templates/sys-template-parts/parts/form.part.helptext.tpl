{if $helpTextIdInline}
    {if is_array($helpTextIdInline)}
        {$helpTextIdInline = $l10n->get($helpTextIdInline[0],$helpTextIdInline[1])}
    {else}
        {if Language::isTranslationStringId($helpTextIdInline)}
            {$helpTextIdInline = $l10n->get($helpTextIdInline)}
        {/if}
    {/if}
    <div class="help-block">{$helpTextIdInline}</div>
{/if}
