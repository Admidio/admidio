{if $data.property eq 4}
    <textarea style="display: none;" name="{$id}" id="{$id}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >{$value}</textarea>
{else}
    <div id="{$id}_group" class="form-control-group{if $data.formtype neq "navbar"} mb-4{/if}{if $property eq 1} admidio-form-group-required{/if}">
        <label for="{$id}" class="form-label">
            {include file='sys-template-parts/parts/form.part.icon.tpl'}
            {$label}
        </label>
        <div>
            <textarea id="{$id}" name="{$id}" class="form-control {$class}"
                {foreach $data.attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach}
                >{$value}</textarea>
            {if $maxLength > 0}
                <small class="characters-count">({$l10n->get('SYS_STILL_X_CHARACTERS', array('<span id="'|cat:$id|cat:'_counter" class="">255</span>'))})</small>
            {/if}
            {include file='sys-template-parts/parts/form.part.helptext.tpl'}
            {include file='sys-template-parts/parts/form.part.warning.tpl'}
        </div>
    </div>
{/if}
