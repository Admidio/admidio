<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_shortname']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_longname']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['org_homepage']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['email_administrator']}
    {if {array_key_exists array=$elements key='org_org_id_parent'}}
        {include 'sys-template-parts/form.select.tpl' data=$elements['org_org_id_parent']}
    {/if}
    {if {array_key_exists array=$elements key='system_organization_select'}}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['system_organization_select']}
    {/if}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['new_organization']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save_organization']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
