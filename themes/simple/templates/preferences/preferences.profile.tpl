<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['editProfileFields']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['profile_show_map_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['profile_show_empty_fields']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['profile_show_roles']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['profile_show_former_roles']}
    {if {array_key_exists array=$elements key='profile_show_extern_roles'}}
        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['profile_show_extern_roles']}
    {/if}
    {include 'sys-template-parts/form.select.tpl' data=$elements['profile_photo_storage']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_profile']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
