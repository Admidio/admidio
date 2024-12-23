<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_NAME')} &amp; {$l10n->get('SYS_CATEGORY')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['rol_name']}
            {include 'sys-template-parts/form.multiline.tpl' data=$elements['rol_description']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['rol_cat_id']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {if {array_key_exists array=$elements key='rol_mail_this_role'}}
                {include 'sys-template-parts/form.select.tpl' data=$elements['rol_mail_this_role']}
            {/if}
            {include 'sys-template-parts/form.select.tpl' data=$elements['rol_view_memberships']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['rol_view_members_profiles']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['rol_leader_rights']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['rol_lst_id']}

            {if $eventRole == false}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_default_registration']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_max_members']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_cost']}
                {include 'sys-template-parts/form.select.tpl' data=$elements['rol_cost_period']}
            {/if}
        </div>
    </div>
    {if $eventRole == false}
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_PERMISSIONS')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_assign_roles']}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_all_lists_view']}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_approve_users']}
                {if {array_key_exists array=$elements key='rol_mail_this_role'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_mail_to_all']}
                {/if}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_edit_user']}
                {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_profile']}
                {if {array_key_exists array=$elements key='rol_announcements'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_announcements']}
                {/if}
                {if {array_key_exists array=$elements key='rol_events'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_events']}
                {/if}
                {if {array_key_exists array=$elements key='rol_photo'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_photo']}
                {/if}
                {if {array_key_exists array=$elements key='rol_documents_files'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_documents_files']}
                {/if}
                {if {array_key_exists array=$elements key='rol_forum_admin'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_forum_admin']}
                {/if}
                {if {array_key_exists array=$elements key='rol_weblinks'}}
                    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['rol_weblinks']}
                {/if}
            </div>
        </div>
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_APPOINTMENTS')} / {$l10n->get('SYS_MEETINGS')}&nbsp;&nbsp;({$l10n->get('SYS_OPTIONAL')})</div>
            <div class="card-body">
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_start_date']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_end_date']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_start_time']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_end_time']}
                {include 'sys-template-parts/form.select.tpl' data=$elements['rol_weekday']}
                {include 'sys-template-parts/form.input.tpl' data=$elements['rol_location']}
            </div>
        </div>
        <div class="card admidio-field-group">
            <div class="card-header">{$l10n->get('SYS_DEPENDENCIES')}&nbsp;&nbsp;({$l10n->get('SYS_OPTIONAL')})</div>
            <div class="card-body">
                <p>{$l10n->get('SYS_ROLE_DEPENDENCIES_DESC', [$roleName])}</p>
                {include 'sys-template-parts/form.select.tpl' data=$elements['dependent_roles']}
            </div>
        </div>
    {/if}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
