<div class="admidio-form-group admidio-form-description
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">
    <div id="{$data.id}"{if $data.class neq ""} class="{$data.class}"{/if}>
        <p>{$data.content}</p>
    </div>
</div>
