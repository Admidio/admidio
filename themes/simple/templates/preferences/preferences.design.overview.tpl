<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    <div class="card admidio-tabbed-field-group">
        <div class="card-header"><i class="bi bi-house-door-fill me-1"></i>{$l10n->get('SYS_OVERVIEW')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
            {include 'sys-template-parts/form.description.tpl' data=$elements['overview_design_description']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_birthday_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_calendar_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_random_photo_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_latest_documents_files_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_announcement_list_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_event_list_enabled']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['overview_plugin_who_is_online_enabled']}

            {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_overview_design']}
            <div class="form-alert" style="display: none;">&nbsp;</div>
        </div>
    </div>
</form>
