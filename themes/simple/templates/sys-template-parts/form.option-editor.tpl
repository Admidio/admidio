{if $data.property eq 4}
    {* Nur als nicht editierbarer Text, z. B. im Hintergrund *}
    <textarea style="display: none;" name="{$data.id}" id="{$data.id}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >{$data.value}</textarea>

{else}
    {assign var="translationStrings" value=[
        'move_up'  => $l10n->get('SYS_MOVE_UP', array('SYS_ENTRY')),
        'move_down'=> $l10n->get('SYS_MOVE_DOWN', array('SYS_ENTRY')),
        'move_var' => $l10n->get('SYS_MOVE_VAR', array('SYS_ENTRY')),
        'restore'  => $l10n->get('SYS_RESTORE_ENTRY'),
        'delete'   => $l10n->get('SYS_DELETE')
    ]}
    <div id="{$data.id}_group" class="admidio-form-group
        {if $formType neq "vertical" and $formType neq "navbar"} row{/if}
        {if $formType neq "navbar"} mb-3{/if}
        {if $data.property eq 1} admidio-form-group-required{/if}">

        <label class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>

        <div{if $formType neq "vertical" and $formType neq "navbar"} class="col-sm-9"{/if}>

            {* --- Die Tabelle mit den editierbaren Optionen --- *}
            <table id="{$data.id}_table" class="table table-hover" width="100%" style="width: 100%;">
                <thead>
                    <tr>
                        <th>{$l10n->get('SYS_VALUE')}</th>
                        <th style="display: none;">&nbsp;</th>
                        <th>&nbsp;</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody class="admidio-sortable">
                    {foreach $data.values as $option}
                        <tr id="{$data.id}_option_{$option.id}" data-uuid="{$option.id}">
                            <td>
                                <input class="form-control focus-ring" type="text" name="{$data.id}[{$option.id}][value]" value="{$option.value|escape}" {if $option.obsolete}disabled="disabled"{/if} {foreach $data.attributes as $itemvar}{$itemvar@key}="{$itemvar}"{/foreach}>
                            </td>
                            <td class="align-middle" style="display: none;">
                                <div class="admidio-form-group d-flex justify-content-center">
                                    <input class="form-control focus-ring" type="text" name="{$data.id}[{$option.id}][obsolete]" value="{$option.obsolete}">
                                </div>
                            </td>
                            <td id="{$data.id}_option_{$option.id}_move_actions" class="text-center align-middle">
                                <a class="admidio-icon-link admidio-field-move" href="javascript:void(0);" data-direction="UP" data-target="{$data.id}_option_{$option.id}">
                                    <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$translationStrings.move_up}"></i>
                                </a>
                                <a class="admidio-icon-link admidio-field-move" href="javascript:void(0);" data-direction="DOWN" data-target="{$data.id}_option_{$option.id}">
                                    <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$translationStrings.move_down}"></i>
                                </a>
                                <a class="admidio-icon-link">
                                    <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="{$translationStrings.move_var}"></i>
                                </a>
                            </td>
                            <td id="{$data.id}_option_{$option.id}_delete_actions" class="text-center align-middle">
                                <a id="{$data.id}_option_{$option.id}_restore" class="admidio-icon-link" href="javascript:void(0);" onclick="restoreEntry('{$data.id}', '{$option.id}');" {if !$option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-arrow-counterclockwise text-success" data-bs-toggle="tooltip" title="{$translationStrings.restore}"></i>
                                </a>
                                <a id="{$data.id}_option_{$option.id}_delete" class="admidio-icon-link" href="javascript:void(0);" onclick="deleteEntry('{$data.id}', '{$option.id}');"{if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="{$translationStrings.delete}"></i>
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                        <tr id="table_row_button">
                            <td colspan="4">
                                <a class="icon-text-link" href="javascript:void(0);" onclick="javascript:addOptionRow('{$data.id}', {$translationStrings|json_encode|escape:'htmlall':'UTF-8'});">
                                    <i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ENTRY')}
                                </a>
                            </td>
                        </tr>
                </tbody>
            </table>

            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}

        </div>
    </div>

    {literal}
    <script>
        function addOptionRow(dataId, translationStrings) {
            const table = document.getElementById(dataId + '_table').getElementsByTagName('tbody')[0];
            const newRow = document.createElement('tr');
            const rows = table.querySelectorAll('tr[id^="' + dataId + '_option_"]');
            let maxId = 0;
            rows.forEach(row => {
                const currentId = row.id.replace(dataId + '_option_', '');
                const num = parseInt(currentId, 10);
                if (!isNaN(num) && num > maxId) {
                    maxId = num;
                }
            });
            const optionId = maxId + 1;
            newRow.innerHTML = `
                <td><input class="form-control focus-ring" type="text" name="${dataId}[${optionId}][value]" required="required"></td>
                <td class="align-middle" style="display: none;">
                    <div class="admidio-form-group form-check form-switch d-flex justify-content-center">
                        <input class="form-control focus-ring" type="text" name="${dataId}[${optionId}][obsolete]" value="">
                    </div>
                </td>
                <td id="${dataId}_option_${optionId}_move_actions" class="text-center align-middle">
                    <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)"
                        data-direction="UP" data-target="${dataId}_option_${optionId}">
                        <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_up}"></i>
                    </a>
                    <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)"
                        data-direction="DOWN" data-target="${dataId}_option_${optionId}">
                        <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_down}"></i>
                    </a>
                    <a class="admidio-icon-link">
                        <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="${translationStrings.move_var}"></i>
                    </a>
                </td>
                <td id="${dataId}_option_${optionId}_delete_actions" class="text-center align-middle">
                    <a id="${dataId}_option_${optionId}_restore" class="admidio-icon-link" href="javascript:void(0)" onclick="restoreEntry('${dataId}', '${optionId}');" style="display: none;">
                        <i class="bi bi-arrow-counterclockwise text-success" data-bs-toggle="tooltip" title="${translationStrings.restore}"></i>
                    </a>
                    <a id="${dataId}_option_${optionId}_delete" class="admidio-icon-link" href="javascript:void(0)" onclick="deleteEntry('${dataId}', '${optionId}');">
                        <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="${translationStrings.delete}"></i>
                    </a>
                </td>
            `;
            newRow.id = dataId + '_option_' + optionId;
            newRow.setAttribute('data-uuid', optionId);
            newRow.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
            table.insertBefore(newRow, table.querySelector('tr#table_row_button'));
        }
        function deleteEntry(dataId, entryId) {
            const row = document.getElementById(dataId + '_option_' + entryId);
            if (row) {
                const table = row.parentNode;
                const countOptions = table.querySelectorAll('tr[id^="' + dataId + '_option_"]').length;
                if (row.querySelector('input[name$="[value]"]').value.trim() === '' && row.querySelector('input[name$="[obsolete]"]').value.trim() === '') {
                    // check if the row is the last one
                    if (countOptions > 1) {
                        row.remove(); // Remove the row if the value is empty
                    }
                    return;
                } else if (row.querySelector('input[name$="[value]"]').value.trim() === '') {
                    // If the value is empty, just remove the row
                    if (countOptions <= 1) {
                        return;
                    }
                }
                // Mark the entry as obsolete
                row.querySelector('input[name$="[obsolete]"]').value = 1;
                // disable input fields
                row.querySelector('input[name$="[value]"]').disabled = true;
                // change displayed delete/restore option
                row.querySelector('#' + dataId + '_option_' + entryId + '_delete').style.display = 'none';
                row.querySelector('#' + dataId + '_option_' + entryId + '_restore').style.display = 'inline';
            }
        }
        function restoreEntry(dataId, entryId) {
            const row = document.getElementById(dataId + '_option_' + entryId);
            if (row) {
                row.querySelector('input[name$="[obsolete]"]').value = 0; // Unmark as obsolete
                // enable input fields
                row.querySelector('input[name$="[value]"]').disabled = false;
                // change displayed delete option
                row.querySelector('#' + dataId + '_option_' + entryId + '_delete').style.display = 'inline'; // Show delete icon
                row.querySelector('#' + dataId + '_option_' + entryId + '_restore').style.display = 'none'; // Hide restore icon
            }
        }
    </script>
    {/literal}
{/if}