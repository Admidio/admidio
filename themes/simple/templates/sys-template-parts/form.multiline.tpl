{if $data.property eq 4}
    <textarea style="display: none;" name="{$data.id}" id="{$data.id}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >{$value}</textarea>
{else}
    <div id="{$data.id}_group" class="admidio-form-group
        {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
        {if $formType neq "navbar"} mb-3{/if}
        {if $data.property eq 1} admidio-form-group-required{/if}">
        {include file="sys-template-parts/parts/form.part.fieldtoggle.tpl"}
        <div{if $formType neq "vertical" and $formType neq "navbar"} class="col-sm-9"{/if}>
            <textarea id="{$data.id}" name="{$data.id}" class="form-control focus-ring {$data.class}"
                {foreach $data.attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach}
                >{$data.value}</textarea>
            {if $data.maxLength > 0}
                <small class="characters-count">({$l10n->get("SYS_STILL_X_CHARACTERS", array('<span id="'|cat:$data.id|cat:'_counter" class="">255</span>'))})</small>
            {/if}
            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}
        </div>
    </div>
{/if}
