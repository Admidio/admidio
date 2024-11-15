<p class="lead admidio-max-with">{$description}</p>

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['dest_folder_uuid']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_move']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
