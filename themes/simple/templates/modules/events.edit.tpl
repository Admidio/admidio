<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {$eventSections = ['basic' => 'SYS_BASIC_DATA', 'participation' => 'SYS_REGISTRATION', 'recurrence' => 'SYS_REPEAT']}
    <div class="tabs-x tabs-above tab-bordered admidio-event-tabs" data-max-title-length="-1">
        <ul class="nav nav-tabs admidio-tabs d-none d-md-flex" role="tablist">
            {foreach $eventSections as $section => $label}
                <li class="nav-item" role="presentation">
                    <button class="nav-link{if $section === 'basic'} active{/if}" id="adm_event_{$section}_tab"
                            data-bs-toggle="tab" data-bs-target="#adm_event_{$section}_pane" type="button" role="tab"
                            aria-controls="adm_event_{$section}_pane" aria-selected="{if $section === 'basic'}true{else}false{/if}"
                            {if $section === 'recurrence' && !isset($elements['event_recurrence_frequency'])}disabled{/if}>
                                                    {$l10n->get($label)}
                                                </button>
                                            </li>
                                        {/foreach}
                                    </ul>
                                    <div class="tab-content accordion admidio-margin-bottom" id="adm_event_accordion">
                                        {foreach $eventSections as $section => $label}
                                            <div class="tab-pane accordion-item{if $section === 'basic'} active{/if}" id="adm_event_{$section}_pane" role="tabpanel" aria-labelledby="adm_event_{$section}_tab">
                                                <h2 class="accordion-header d-md-none" id="adm_event_{$section}_heading">
                                                    <button class="accordion-button{if $section !== 'basic'} collapsed{/if}" type="button"
                                                            data-bs-toggle="collapse" data-bs-target="#adm_event_{$section}_collapse"
                                                            aria-expanded="{if $section === 'basic'}true{else}false{/if}" aria-controls="adm_event_{$section}_collapse"
                                                            {if $section === 'recurrence' && !isset($elements['event_recurrence_frequency'])}disabled{/if}>
                                                        {$l10n->get($label)}
                                                    </button>
                                                </h2>
                                                <div class="accordion-collapse collapse{if $section === 'basic'} show{/if}" id="adm_event_{$section}_collapse"
                                                     aria-labelledby="adm_event_{$section}_heading" data-bs-parent="#adm_event_accordion">
                                                    <div class="accordion-body">
                            {if $section === 'basic'}
                                <div class="card admidio-tabbed-field-group">
                                    <div class="card-header">{$l10n->get('SYS_TITLE')} &amp; {$l10n->get('SYS_VENUE')}</div>
                                    <div class="card-body">
                                        {include 'sys-template-parts/form.input.tpl' data=$elements['dat_headline']}
                                        {include 'sys-template-parts/form.input.tpl' data=$elements['dat_location']}
                                        {if {array_key_exists array=$elements key='dat_country'}}
                                            {include 'sys-template-parts/form.select.tpl' data=$elements['dat_country']}
                                        {/if}
                                        {if {array_key_exists array=$elements key='dat_room_id'}}
                                            {include 'sys-template-parts/form.select.tpl' data=$elements['dat_room_id']}
                                        {/if}
                                    </div>
                                </div>
                                <div class="card admidio-tabbed-field-group">
                                    <div class="card-header">{$l10n->get('SYS_PERIOD')} &amp; {$l10n->get('SYS_CALENDAR')}</div>
                                    <div class="card-body">
                                        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_all_day']}
                                        {include 'sys-template-parts/form.input.tpl' data=$elements['event_from']}
                                        {include 'sys-template-parts/form.input.tpl' data=$elements['event_to']}
                                        {include 'sys-template-parts/form.select.tpl' data=$elements['cat_uuid']}
                                        {include 'sys-template-parts/form.checkbox.tpl' data=$elements['dat_highlight']}
                                    </div>
                                </div>
                                <div class="card admidio-tabbed-field-group">
                                    <div class="card-header">{$l10n->get('SYS_DESCRIPTION')}</div>
                                    <div class="card-body">
                                        {include 'sys-template-parts/form.editor.tpl' data=$elements['dat_description']}
                                    </div>
                                </div>
                            {elseif $section === 'participation'}
                                <div class="card admidio-tabbed-field-group">
                                    <div class="card-header">{$l10n->get('SYS_REGISTRATION')}</div>
                                    <div class="card-body">
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
                            {else}
                                {if isset($elements['event_recurrence_frequency'])}
                                    <div class="card admidio-tabbed-field-group">
                                        <div class="card-header">{$l10n->get('SYS_REPEAT')}</div>
                                        <div class="card-body">
                                            {include 'sys-template-parts/form.select.tpl' data=$elements['event_recurrence_frequency']}
                                            {include 'sys-template-parts/form.input.tpl' data=$elements['event_recurrence_interval']}
                                            {include 'sys-template-parts/form.select.tpl' data=$elements['event_recurrence_weekdays']}
                                            {include 'sys-template-parts/form.select.tpl' data=$elements['event_recurrence_end_type']}
                                            {include 'sys-template-parts/form.input.tpl' data=$elements['event_recurrence_count']}
                                            {include 'sys-template-parts/form.input.tpl' data=$elements['event_recurrence_until']}
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
