<div style="max-width: 500px">
    <form {foreach $attributes as $attribute}
            {$attribute@key}="{$attribute}"
        {/foreach}>

        {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['new_password']}
        {include 'sys-template-parts/form.input.tpl' data=$elements['new_password_confirm']}
        {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
        <div class="form-alert" style="display: none;">&nbsp;</div>
    </form>
</div>
