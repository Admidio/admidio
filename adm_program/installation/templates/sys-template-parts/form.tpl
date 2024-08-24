<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    {if $hasRequiredFields}
        <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {/if}

    {foreach $elements as $element}
        {include $element.template data=$element}
    {/foreach}
</form>
