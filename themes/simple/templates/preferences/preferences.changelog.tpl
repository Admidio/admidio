{$javascript}
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['changelog_module_enabled']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['changelog_default_days']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['changelog_retention_days']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['changelog_purge']}

<div class="admidio-form-group admidio-form-changelog-areas
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">
    <label class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {$elements['changelog_areas'].label}
    </label>
    {if $formType neq "vertical" and $formType neq "navbar"}<div class="col-sm-9">{/if}
    <div id="{$elements.changelog_areas.id}"{if $elements.changelog_areas.class neq ""} class="{$elements.changelog_areas.class}"{/if}>{$elements.changelog_areas.content}</div>
{foreach $elements.changelog_areas.sections as $section}
        {if $section.title neq ""}<div id="{$section.id}" class="fw-bold">{$section.title}</div>{/if}
        <ul class="changelog-tableselect-list">
            {foreach $section.areas as $areaElement}
            <li><input id="{$elements[$areaElement].id}" name="{$elements[$areaElement].id}" class="form-check-input focus-ring {$elements[$areaElement].class}" type="checkbox" value="{$elements[$areaElement].value}"
                data-changelog-area="1" data-state="{$elements[$areaElement].state}" data-initial-state="{$elements[$areaElement].state}"
                {foreach $elements[$areaElement].attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach}
            >
            <label class="fw-normal" for="{$elements[$areaElement].id}">
                {$elements[$areaElement].label}
            </label>
            </li>
            {/foreach}
        </ul>
{/foreach}
    {if $formType neq "vertical" and $formType neq "navbar"}</div>{/if}
</div>

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_changelog']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
