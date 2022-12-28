{if $helpTextIdLabel}
    {if Language::isTranslationStringId($helpTextIdLabel)}
        {$helpTextIdLabel = $l10n->get($helpTextIdLabel)}
    {/if}
    <i class="fas fa-info-circle admidio-info-icon" data-toggle="popover"
    data-html="true" data-trigger="hover click" data-placement="auto"
    title="{$l10n->get('SYS_NOTE')}" data-content="{SecurityUtils::encodeHTML($helpTextIdLabel)}"></i>
{/if}