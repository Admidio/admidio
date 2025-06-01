<div class="{$data.id}_head subinfo subinfo_head">
{foreach from=$data.data key=label item=info name=staticInfoLoop}
    <div class="admidio-form-group admidio-form-custom-content row mb-3 subinfo-row{if !$smarty.foreach.staticInfoLoop.first} subinfo-sub-row{/if}">
        <label class="col-sm-3 col-form-label{if !$smarty.foreach.staticInfoLoop.first} col-form-sublabel{/if}">
        {if $smarty.foreach.staticInfoLoop.first}
            <a id="{$data.id}_caret" class=" admidio-open-close-caret" data-target="{$data.id}_contents">
                <i class="bi bi-caret-right-fill" style="margin-right: 0"></i>
            </a>
        {/if}
        {$label}:&nbsp;</label>
        <div class="col-sm-9 form-control-plaintext">
            <div class="copy-container {if isset($info.class)}{$info.class}{/if}" id="{$info.id}" 
                {if isset($info.style) and $info.style neq ''} style="{$info.style}"{/if}
                >{$info.value}</div>
        </div>
    </div>
    {if $smarty.foreach.staticInfoLoop.first}
        <div id="{$data.id}_contents" style="display: none">
    {/if}
{foreachelse}
        <div id="{$data.id}_contents" style="display: none">
{/foreach}
</div>
</div>
