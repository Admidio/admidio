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
                <th class="text-center"><i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$l10n->get('SYS_FIELD')])}"></i></th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody id="adm_item_fields" class="admidio-sortable">
            {foreach $list as $itemField}
                <tr id="adm_item_field_{$itemField.uuid}" data-uuid="{$itemField.uuid}">
                    <td>
                        <a href="{$itemField.urlEdit}">{$itemField.name}</a>
                        {assign var="data" value=['helpTextId' => $itemField.description]}
                        {include 'sys-template-parts/parts/form.part.iconhelp.tpl' data=$data}
                    </td>
                    <td>
                        {include 'sys-template-parts/list.move.tpl' moveActions=[
                            ['direction' => 'UP', 'label' => $l10n->get('SYS_MOVE_UP', array('SYS_FIELD')), 'class' => 'admidio-field-move', 'attributes' => ['data-uuid' => "`$itemField.uuid`", 'data-target' => "adm_item_field_`$itemField.uuid`"]],
                            ['direction' => 'DOWN', 'label' => $l10n->get('SYS_MOVE_DOWN', array('SYS_FIELD')), 'class' => 'admidio-field-move', 'attributes' => ['data-uuid' => "`$itemField.uuid`", 'data-target' => "adm_item_field_`$itemField.uuid`"]],
                            ['direction' => 'FREE', 'label' => $l10n->get('SYS_MOVE_VAR', array('SYS_FIELD'))]
                        ]}
                    </td>
                    <td>{$itemField.dataType}</td>
                    <td>{$itemField.mandatory}</td>
                    <td class="text-center">
                        {if $itemField.system}
                            <i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$l10n->get('SYS_FIELD')])}"></i>
                        {/if}
                    </td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$itemField}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
