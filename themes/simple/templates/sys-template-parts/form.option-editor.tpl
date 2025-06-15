{if $data.property eq 4}
    {* Nur als nicht editierbarer Text, z. B. im Hintergrund *}
    <textarea style="display: none;" name="{$data.id}" id="{$data.id}"
        {foreach $data.attributes as $itemvar}
            {$itemvar@key}="{$itemvar}"
        {/foreach}
    >{$data.value}</textarea>

{else}
    {assign var="translationStrings" value=[
        'move_up'  => $l10n->get('SYS_MOVE_UP', array('SYS_OPTION_ENTRY')),
        'move_down'=> $l10n->get('SYS_MOVE_DOWN', array('SYS_OPTION_ENTRY')),
        'move_var' => $l10n->get('SYS_MOVE_VAR', array('SYS_OPTION_ENTRY')),
        'restore'  => $l10n->get('SYS_RESTORE_ENTRY'),
        'delete'   => $l10n->get('SYS_DELETE')
    ]}
    <div id="{$data.id}_group" class="admidio-form-group
        {if $formType neq "vertical" and $formType neq "navbar"} row{/if}
        {if $formType neq "navbar"} mb-3{/if}
        {if $data.property eq 1} admidio-form-group-required{/if}">

        <label for="{$data.id}" class="{if $formType neq "vertical" and $formType neq "navbar"}col-sm-3 col-form-label{else}form-label{/if}">
            {include file="sys-template-parts/parts/form.part.icon.tpl"}
            {$data.label}
        </label>

        <div{if $formType neq "vertical" and $formType neq "navbar"} class="col-sm-9"{/if}>

            {* --- Die Tabelle mit den editierbaren Optionen --- *}
            <table id="adm_option_editor_{$data.id}" class="table table-hover" width="100%" style="width: 100%;">
                <thead>
                    <tr>
                        <th>{$l10n->get('SYS_VALUE')}</th>
                        <th style="display: none;">&nbsp;</th>
                        <th>&nbsp;</th>
                        <th>&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="adm_profile_fields_{$data.id}" class="admidio-sortable">
                    {foreach $data.values as $option}
                        <tr id="adm_option_{$option.id}" data-uuid="{$option.id}">
                            <td>
                                <input class="form-control focus-ring" type="text" name="{$data.id}[{$option.id}][value]" value="{$option.value|escape}" {if $option.obsolete}disabled="disabled"{/if}>
                            </td>
                            <td class="align-middle" style="display: none;">
                                <div class="admidio-form-group form-check form-switch d-flex justify-content-center">
                                    <input class="form-check-input focus-ring" type="checkbox" name="{$data.id}[{$option.id}][obsolete]" value="1" {if $option.obsolete}checked{/if}>
                                </div>
                            </td>
                            <td id="adm_option_{$option.id}_move_actions" class="text-center align-middle">
                                <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-direction="UP" data-target="adm_option_{$option.id}" {if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$translationStrings.move_up}"></i>
                                </a>
                                <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)" data-direction="DOWN" data-target="adm_option_{$option.id}" {if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$translationStrings.move_down}"></i>
                                </a>
                                <a class="admidio-icon-link" {if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="{$translationStrings.move_var}"></i>
                                </a>
                            </td>
                            <td id="adm_option_{$option.id}_delete_actions" class="text-center align-middle">
                                <a id="adm_option_{$option.id}_restore" class="admidio-icon-link" href="javascript:void(0)" onclick="restoreEntry('{$option.id}');" {if !$option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-arrow-counterclockwise text-success" data-bs-toggle="tooltip" title="{$translationStrings.restore}"></i>
                                </a>
                                <a id="adm_option_{$option.id}_delete" class="admidio-icon-link" href="javascript:void(0)" onclick="deleteEntry('{$option.id}');"{if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="{$translationStrings.delete}"></i>
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
            {* --- Button zum Hinzufügen einer neuen Option --- *}
            <button type="button" class="btn btn-sm btn-primary" onclick="addOptionRow('{$data.id}', {$translationStrings|json_encode|escape:'htmlall':'UTF-8'})">
                <i class="bi bi-plus-circle"></i> {$l10n->get('SYS_ADD_ENTRY')}
            </button>

            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}

        </div>
    </div>

    {* JS nur einmal ausgeben *}
    {if !$smarty.get._option_editor_js_loaded}
        {assign var="_option_editor_js_loaded" value=true scope="global"}
        {literal}
        <script>
        function addOptionRow(dataId, translationStrings) {
            const table = document.getElementById('adm_option_editor_' + dataId).getElementsByTagName('tbody')[0];
            const newRow = document.createElement('tr');
            const rows = table.querySelectorAll('tr[id^="adm_option_"]');
            let maxId = 0;
            rows.forEach(row => {
                const currentId = row.id.replace('adm_option_', '');
                const num = parseInt(currentId, 10);
                if (!isNaN(num) && num > maxId) {
                    maxId = num;
                }
            });
            const optionId = maxId + 1;
            newRow.innerHTML = `
                <td><input class="form-control focus-ring" type="text" name="${dataId}[${optionId}][value]"></td>
                <td class="align-middle" style="display: none;">
                    <div class="admidio-form-group form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input focus-ring" type="checkbox" name="${dataId}[${optionId}][obsolete]" value="1">
                    </div>
                </td>
                <td id="adm_option_${optionId}_move_actions" class="text-center align-middle">
                    <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)"
                        data-direction="UP" data-target="adm_option_${optionId}">
                        <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_up}"></i>
                    </a>
                    <a class="admidio-icon-link admidio-field-move" href="javascript:void(0)"
                        data-direction="DOWN" data-target="adm_option_${optionId}">
                        <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="${translationStrings.move_down}"></i>
                    </a>
                    <a class="admidio-icon-link">
                        <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="${translationStrings.move_var}"></i>
                    </a>
                </td>
                <td id="adm_option_${optionId}_delete_actions" class="text-center align-middle">
                    <a id="adm_option_${optionId}_restore" class="admidio-icon-link" href="javascript:void(0)" onclick="restoreEntry('${optionId}');" style="display: none;">
                        <i class="bi bi-arrow-counterclockwise text-success" data-bs-toggle="tooltip" title="${translationStrings.restore}"></i>
                    </a>
                    <a id="adm_option_${optionId}_delete" class="admidio-icon-link" href="javascript:void(0)" onclick="deleteEntry('${optionId}');">
                        <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="${translationStrings.delete}"></i>
                    </a>
                </td>
            `;
            newRow.id = 'adm_option_' + optionId;
            newRow.setAttribute('data-uuid', optionId);
            newRow.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
                new bootstrap.Tooltip(el);
            });
            table.appendChild(newRow);
        }
        function deleteEntry(entryId) {
            const row = document.getElementById('adm_option_' + entryId);
            if (row) {
                row.querySelector('input[name$="[obsolete]"]').checked = true; // Mark as obsolete
                // disable input fields
                row.querySelector('input[name$="[value]"]').disabled = true;
                const moveActions = row.querySelector('#adm_option_' + entryId + '_move_actions');
                if (moveActions) {
                    moveActions.querySelectorAll('*').forEach(function(elem) {
                        elem.style.display = 'none';
                    });
                }
                // change displayed delete option
                row.querySelector('#adm_option_' + entryId + '_delete').style.display = 'none'; // Hide delete icon
                row.querySelector('#adm_option_' + entryId + '_restore').style.display = 'inline'; // Show restore icon
            }
        }
        function restoreEntry(entryId) {
            const row = document.getElementById('adm_option_' + entryId);
            if (row) {
                row.querySelector('input[name$="[obsolete]"]').checked = false; // Unmark as obsolete
                // enable input fields
                row.querySelector('input[name$="[value]"]').disabled = false;
                const moveActions = row.querySelector('#adm_option_' + entryId + '_move_actions');
                if (moveActions) {
                    moveActions.querySelectorAll('*').forEach(function(elem) {
                        elem.style.display = 'inline';
                    });
                }                // change displayed delete option
                row.querySelector('#adm_option_' + entryId + '_delete').style.display = 'inline'; // Show delete icon
                row.querySelector('#adm_option_' + entryId + '_restore').style.display = 'none'; // Hide restore icon
            }
        }
        </script>
        {/literal}
    {/if}
{/if}