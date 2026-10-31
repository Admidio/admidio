{* The tree is created by system/js/tree-view.js, the selected node is saved in the hidden field *}
<div id="{$data.id}_group" class="admidio-form-group
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if} mb-3
    {if $data.property eq 1} admidio-form-group-required{/if}">
    <label id="{$data.id}_label" for="{$data.id}_tree" class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {include file="sys-template-parts/parts/form.part.icon.tpl"}
        {$data.label}
    </label>
    {if $formType neq "vertical" and $formType neq "navbar"}<div class="col-sm-9">{/if}
    <input type="hidden" id="{$data.id}" name="{$data.id}" value="{$data.value}">
    <div class="admidio-tree-select {$data.class}" data-field="{$data.id}" data-summary="{$data.id}_summary"
        data-tree-options="{$data.treeOptions|escape}">
        <ul id="{$data.id}_tree" class="admidio-tree" role="tree" aria-labelledby="{$data.id}_label"
            {if $data.property eq 1}aria-required="true"{/if}></ul>
    </div>
    {if $data.emptyText neq ""}
        <div id="{$data.id}_summary" class="form-text admidio-tree-select-summary" aria-live="polite"
            data-text-empty="{$data.emptyText|escape}">{$data.emptyText}</div>
    {/if}
    {include file="sys-template-parts/parts/form.part.helptext.tpl"}
    {include file="sys-template-parts/parts/form.part.warning.tpl"}
    {if $formType neq "vertical" and $formType neq "navbar"}</div>{/if}
</div>
