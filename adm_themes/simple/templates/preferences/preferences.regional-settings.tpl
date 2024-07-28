<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_timezone']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['system_language']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['default_country']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_date']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_time']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['system_currency']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save_regional_settings']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
