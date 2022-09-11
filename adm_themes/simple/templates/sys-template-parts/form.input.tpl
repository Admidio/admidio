{if $data.property eq 4}
<input
    type="{$type}"
    name="{$id}"
    id="{$id}"
    value="{$value}"
    {foreach $data.attributes as $itemvar}
    {$itemvar@key}="{$itemvar}"
    {/foreach}>
{else}
<div
    id="{$id}_group"
    class="form-group row {if $property eq 1}admidio-form-group-required{/if}">    
    <label for="{$id}" class="col-sm-3 control-label">
        {if $icon}
            <i class="{$icon} fa-fw" data-toggle="tooltip" title="{$label}"></i>
        {/if}
        {$label}
        {if $helpTextIdLabel}
            {if Language::isTranslationStringId($helpTextIdLabel)}
                {$helpTextIdLabel = $l10n->get($helpTextIdLabel)}
            {/if}
            <i class="fas fa-info-circle admidio-info-icon" data-toggle="popover"
            data-html="true" data-trigger="hover click" data-placement="auto"
            title="{$l10n->get('SYS_NOTE')}" data-content="{SecurityUtils::encodeHTML($helpTextIdLabel)}"></i>
        {/if}
    </label>
{$type}
    <div class="col-sm-9">
        {if $type == 'datetime'}
            <input
            type="datetime-local"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {elseif $type == 'date' OR $type == 'birthday'}
            <input
            type="date"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {else}
        <input
            type="{$type}"
            name="{$id}"
            id="{$id}"
            value="{$value}"
            {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
            {/foreach}
            >
            {$htmlAfter}
        {/if}

        {if $helpTextIdInline}
            {if Language::isTranslationStringId($helpTextIdInline)}
            {$helpTextIdInline}
                {$helpTextIdInline = $l10n->get($helpTextIdInline)}
            {/if}
            <div class="help-block">{$helpTextIdInline}</div>
        {/if}
       

        {if $alertWarning}
        <div class="alert alert-warning mt-3" role="alert">
            <i class="fas fa-exclamation-triangle"></i>{$alertWarning}
        </div>
        {/if}
    </div>
</div>
{/if}