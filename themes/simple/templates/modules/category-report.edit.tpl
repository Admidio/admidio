<form {foreach $attributes as $attribute}{$attribute@key}="{$attribute}" {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['name']}
    {include 'sys-template-parts/form.multiline.tpl' data=$elements['description']}
    <div class="admidio-form-group admidio-form-custom-content row mb-3">
        <label class="col-sm-3 col-form-label">{$l10n->get('SYS_COLUMN_SELECTION')}</label>
        <div class="col-sm-9">
            <div class="table-responsive">
                <table class="table table-condensed catreport-columns-table">
                    <thead><tr>
                        <th>{$l10n->get('SYS_ABR_NO')}</th>
                        <th>{$l10n->get('SYS_CONTENT')}</th>
                        <th>{$l10n->get('SYS_CONDITION')}
                            <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-class="modal-lg" data-href="{$conditionHelpUrl}"><i class="bi bi-info-circle-fill admidio-info-icon"></i></a>
                        </th>
                        <th></th>
                    </tr></thead>
                    <tbody id="category_report_columns"></tbody>
                </table>
            </div>
            <div class="form-text">{$l10n->get('SYS_COLUMN_SELECTION_DESC')}</div>
            {include 'sys-template-parts/form.button.tpl' data=$elements['category_report_add_column']}
        </div>
    </div>
    {include 'sys-template-parts/form.select.tpl' data=$elements['selection_role']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['selection_cat']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['number_col']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['report_action']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['source_id']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_category_report']}
</form>
<script>
{literal}
(function () {
    const data = {/literal}{$reportFormDataJson nofilter}{literal};
    const fields = data.fields;
    const roleProperties = data.roleProperties;
    const columns = data.columns;
    const $body = $('#category_report_columns');
    const roleIds = new Set(roleProperties.map(item => String(item.id)));

    function renumber() {
        $body.children('tr').each(function (index) {
            $(this).children('td:first').text((index + 1) + '.');
        });
    }

    function addColumn(value = '', condition = '') {
        const raw = String(value || '');
        const prefix = raw.charAt(0);
        const selectedField = roleIds.has(prefix) && raw !== 'ddummy' ? 'r' + raw.slice(1) : raw;
        const $row = $('<tr class="CategoryReportColumnDefinition">');
        $row.append($('<td>'));
        const $fieldCell = $('<td>');
        const $select = $('<select class="form-control ListProfileField" name="columns[]">');
        $select.append($('<option>').val('').text(''));
        let group = null;
        let category = null;
        fields.forEach(function (field) {
            const id = String(field.id);
            if (roleIds.has(id.charAt(0)) && id.charAt(0) !== 'r' && id !== 'ddummy') {
                return;
            }
            if (category !== field.cat_name) {
                category = field.cat_name;
                group = $('<optgroup>').attr('label', category || '');
                $select.append(group);
            }
            group.append($('<option>').val(id).text(field.data));
        });
        $select.val(selectedField);
        const $property = $('<select class="form-control ListProfileField" name="columnsRoleProp[]">');
        roleProperties.forEach(function (item) {
            $property.append($('<option>').val(item.id).text(item.data));
        });
        $property.val(roleIds.has(prefix) ? prefix : 'r');
        function toggleProperty() { $property.toggle(/^r\d+$/.test(String($select.val() || ''))); }
        $select.on('change', toggleProperty);
        $fieldCell.append($select, $property);
        $row.append($fieldCell);
        $row.append($('<td>').append($('<input type="text" class="form-control" name="conditions[]" maxlength="50">')
            .val(String(condition || '').replace(/{/g, '<').replace(/}/g, '>'))));
        const $move = $('<a class="admidio-icon-link admidio-move-row me-2 text-body" href="javascript:void(0);">')
            .append($('<i class="bi bi-arrows-move" data-bs-toggle="tooltip">').attr('title', data.moveLabel));
        const $remove = $('<a class="admidio-icon-link text-danger" href="javascript:void(0);">')
            .append($('<i class="bi bi-trash-fill" data-bs-toggle="tooltip">').attr('title', data.deleteLabel));
        $remove.on('click', function () { $row.remove(); renumber(); });
        $row.append($('<td>').append($move, $remove));
        $body.append($row);
        $row.find('[data-bs-toggle="tooltip"]').each(function () { new bootstrap.Tooltip(this); });
        toggleProperty();
        renumber();
    }

    columns.forEach(column => addColumn(column.field, column.condition));
    $body.sortable({ handle: '.admidio-move-row', items: 'tr', update: renumber });
    $('#category_report_add_column').on('click', function () { addColumn(); });
})();
{/literal}
</script>
