{$javascript}
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['changelog_enable_module']}

<div class="admidio-form-group admidio-form-changelog-tables
    {if $formType neq "vertical" and $formType neq "navbar"}row{/if}
    {if $formType neq "navbar"} mb-3{/if}">
    <label class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
        {$elements['changelog_tables'].label}
    </label>
    {if $formType neq "vertical" and $formType neq "navbar"}<div class="col-sm-9">{/if}
    <div id="{$elements.changelog_tables.id}"{if $elements.changelog_tables.class neq ""} class="{$elements.changelog_tables.class}"{/if}>{$elements.changelog_tables.content}</div>
{foreach $elements.changelog_tables.tables as $tablegroup}
        <div id="{$tablegroup.id}" class="col-sm-9 changelog-tableselect-header">{$tablegroup.title}</div>
        <div class="col-sm-9">
        <ul class="changelog-tableselect-list">
            {foreach $tablegroup.tables as $table}
            <li><input id="changelog_table_{$table}" name="changelog_table_{$table}" class="form-check-input focus-ring {$elements['changelog_table_'|cat:$table].class}" type="checkbox" value="1"
                {foreach $elements['changelog_table_'|cat:$table].attributes as $itemvar}
                    {$itemvar@key}="{$itemvar}"
                {/foreach}
            >
            <label class="changelog-tableselect-label" for="changelog_table_{$table}">
                {$elements['changelog_table_'|cat:$table].label}
            </label>
            </li>
            {/foreach}
            </ul>
        </div>
{/foreach}
    {if $formType neq "vertical" and $formType neq "navbar"}</div>{/if}
</div>


{*    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['changelog_allow_deletion']} *}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_changelog']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
