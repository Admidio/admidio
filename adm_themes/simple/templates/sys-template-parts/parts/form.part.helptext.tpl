{if $helpTextIdInline}
    {if Language::isTranslationStringId($helpTextIdInline)}
    {$helpTextIdInline}
        {$helpTextIdInline = $l10n->get($helpTextIdInline)}
    {/if}
    <div class="help-block">{$helpTextIdInline}</div>
{/if}