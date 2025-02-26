<div class="table-responsive">
    <table id="adm_table_item_fields" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th>
                    {$l10n->get('SYS_FIELD')}
                    {assign var="data" value=['helpTextId' => 'ORG_FIELD_DESCRIPTION']}
                    {include 'sys-template-parts/parts/form.part.iconhelp.tpl' data=$data}
                </th>
                <th>&nbsp;</th>
                <th>{$l10n->get('ORG_DATATYPE')}</th>
                <th>{$l10n->get('SYS_REQUIRED_INPUT')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        {foreach $list as $itemFieldsCategory}
            <tbody>
            <tr class="admidio-group-heading">
                <td id="adm_item_fields_category_{$itemFieldsCategory.id}" colspan="10">
                    <a id="adm_item_fields_caret_{$itemFieldsCategory.id}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_item_fields_{$itemFieldsCategory.id}">
                        <i class="bi bi-caret-down-fill"></i>
                    </a> {$itemFieldsCategory.name}
                </td>
            </tr>
            </tbody>
            <tbody id="adm_item_fields_{$itemFieldsCategory.id}" class="admidio-sortable">
                {foreach $itemFieldsCategory.entries as $itemField}
                    <tr id="adm_item_field_{$itemField.id}" data-uuid="{$itemField.id}">
                        <td>
                            <a href="{$itemField.urlEdit}">{$itemField.name}</a>
                            {assign var="data" value=['helpTextId' => $itemField.description]}
                            {include 'sys-template-parts/parts/form.part.iconhelp.tpl' data=$data}
                        </td>
                        <td>
                            <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="{$itemField.id}"
                               data-direction="UP" data-target="adm_item_field_{$itemField.id}">
                                <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('SYS_PROFILE_FIELD'))}"></i></a>
                            <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-uuid="{$itemField.id}"
                               data-direction="DOWN" data-target="adm_item_field_{$itemField.id}">
                                <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('SYS_PROFILE_FIELD'))}"></i></a>
                            <a class="admidio-icon-link">
                                <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_VAR', array('SYS_PROFILE_FIELD'))}"></i></a>
                        </td>
                        <td>{$itemField.dataType}</td>
                        <td>{$itemField.mandatory}</td>
                        <td class="text-end">
                           {foreach $itemField.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                            {/foreach}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        {/foreach}
    </table>
</div>
