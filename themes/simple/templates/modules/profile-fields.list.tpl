<table id="adm_table_profile_fields" class="table table-hover" width="100%" style="width: 100%;">
    <thead>
        <tr>
            <th>
                {$l10n->get('SYS_FIELD')}
                {assign var="data" value=['helpTextId' => 'ORG_FIELD_DESCRIPTION']}
                {include 'sys-template-parts/parts/form.part.iconhelp.tpl' data=$data}
            </th>
            <th>&nbsp;</th>
            <th>{$l10n->get('ORG_DATATYPE')}</th>
            <th><i class="bi bi-eye-fill" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_NOT_HIDDEN')}"></i></th>
            <th><i class="bi bi-key-fill" data-bs-toggle="tooltip" data-bs-html="true" title="{$l10n->get('ORG_FIELD_DISABLED', [$l10n->get('SYS_RIGHT_EDIT_USER')])}"></i></th>
            <th><i class="bi bi-card-checklist" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_REGISTRATION')}"></i></th>
            <th class="d-none d-lg-table-cell">{$l10n->get('SYS_REQUIRED_INPUT')}</th>
            <th class="d-none d-lg-table-cell">{$l10n->get('SYS_DEFAULT_VALUE')}</th>
            <th class="d-none d-lg-table-cell">{$l10n->get('SYS_REGULAR_EXPRESSION')}</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    {foreach $list as $profileFieldsCategory}
        <tbody>
        <tr class="admidio-group-heading">
            <td id="adm_profile_fields_category_{$profileFieldsCategory.uuid}" colspan="10">
                <a id="adm_profile_fields_caret_{$profileFieldsCategory.uuid}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_profile_fields_{$profileFieldsCategory.uuid}">
                    <i class="bi bi-caret-down-fill"></i>
                </a> {$profileFieldsCategory.name}
            </td>
        </tr>
        </tbody>
        <tbody id="adm_profile_fields_{$profileFieldsCategory.uuid}" class="admidio-sortable">
            {foreach $profileFieldsCategory.entries as $profileField}
                <tr id="adm_profile_field_{$profileField.uuid}" data-uuid="{$profileField.uuid}">
                    <td>
                        <a href="{$profileField.urlEdit}">{$profileField.name}</a>
                        {assign var="data" value=['helpTextId' => $profileField.description]}
                        {include 'sys-template-parts/parts/form.part.iconhelp.tpl' data=$data}
                    </td>
                    <td>
                        <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="{$profileField.uuid}"
                           data-direction="UP" data-target="adm_profile_field_{$profileField.uuid}">
                            <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('SYS_PROFILE_FIELD'))}"></i></a>
                        <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="{$profileField.uuid}"
                           data-direction="DOWN" data-target="adm_profile_field_{$profileField.uuid}">
                            <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('SYS_PROFILE_FIELD'))}"></i></a>
                        <a class="admidio-icon-link">
                            <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_VAR', array('SYS_PROFILE_FIELD'))}"></i></a>
                    </td>
                    <td>{$profileField.dataType}</td>
                    <td>
                        {if $profileField.hidden}
                            <i class="bi bi-eye-fill admidio-opacity-reduced" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_HIDDEN')}"></i>
                        {else}
                            <i class="bi bi-eye-fill" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_NOT_HIDDEN')}"></i>
                        {/if}
                    </td>
                    <td>
                        {if $profileField.disabled}
                            <i class="bi bi-key-fill admidio-opacity-reduced" data-bs-toggle="tooltip" data-bs-html="true" title="{$l10n->get('ORG_FIELD_DISABLED', [$l10n->get('SYS_RIGHT_EDIT_USER')])}"></i>
                        {else}
                            <i class="bi bi-key-fill" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_NOT_DISABLED')}"></i>
                        {/if}
                    </td>
                    <td>
                        {if $profileField.registration}
                            <i class="bi bi-card-checklist" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_REGISTRATION')}"></i>
                        {else}
                            <i class="bi bi-card-checklist admidio-opacity-reduced" data-bs-toggle="tooltip" title="{$l10n->get('ORG_FIELD_NOT_REGISTRATION')}"></i>
                        {/if}
                    </td>
                    <td class="d-none d-lg-table-cell">{$profileField.mandatory}</td>
                    <td class="d-none d-lg-table-cell">{$profileField.defaultValue}</td>
                    <td class="d-none d-lg-table-cell">{$profileField.regex}</td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$profileField}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    {/foreach}
</table>
