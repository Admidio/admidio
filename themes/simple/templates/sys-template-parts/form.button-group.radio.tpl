<div class="admidio-form-group {if $formType eq "navbar"} form-floating{else} mb-3{/if}">
    <div id="{$data.id}" class="btn-group {if $formType eq "navbar"}h-100 align-items-center{/if}
        {$data.class}" role="group" aria-label="Basic radio toggle button group"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >
        {foreach $data.values as $item}
            <input type="radio" class="btn-check" name="btnradio" id="{$item.id}" autocomplete="off"{if $item.default eq true} checked{/if}>
            <label class="btn btn-outline-primary" for="{$item.id}">{$item.value}</label>
        {/foreach}
    </div>
</div>

