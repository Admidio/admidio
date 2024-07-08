<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_TITLE')} &amp; {$l10n->get('SYS_VENUE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.input.tpl' data=$elements['dat_headline']}
            {if {array_key_exists array=$elements key='dat_location'}}
                {include 'sys-template-parts/form.input.tpl' data=$elements['dat_location']}
            {/if}
            {if {array_key_exists array=$elements key='dat_country'}}
                {include 'sys-template-parts/form.input.tpl' data=$elements['dat_country']}
            {/if}
            {if {array_key_exists array=$elements key='dat_room_id'}}
                {include 'sys-template-parts/form.select.tpl' data=$elements['dat_room_id']}
            {/if}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PERIOD')} &amp; {$l10n->get('SYS_CALENDAR')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_all_day']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['event_from']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['event_to']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['cat_uuid']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_VISIBILITY')} &amp; {$l10n->get('SYS_REGISTRATION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_highlight']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_participation_possible']}
            {include 'sys-template-parts/form.select.tpl' data=$elements['adm_event_participation_right']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_current_user_assigned']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_allow_comments']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_additional_guests']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['dat_max_members']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['event_deadline']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_right_list_view']}
            {include 'sys-template-parts/form.checkbox.tpl' data=$elements['event_right_send_mail']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_DESCRIPTION')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.editor.tpl' data=$elements['dat_description']}
        </div>
    </div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['btn_save']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
