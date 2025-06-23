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
                                <a class="admidio-icon-link admidio-entry-move" href="javascript:void(0);" data-direction="UP" data-target="{$data.id}_option_{$option.id}">
                                    <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$translationStrings.move_up}"></i>
                                </a>
                                <a class="admidio-icon-link admidio-entry-move" href="javascript:void(0);" data-direction="DOWN" data-target="{$data.id}_option_{$option.id}">
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
                                <a id="{$data.id}_option_{$option.id}_delete" class="admidio-icon-link" href="javascript:void(0);" onclick="deleteEntry('{$data.id}', '{$option.id}', '{$urlAdmidio}/modules/profile-fields.php?mode=delete_option_entry&uuid={$fieldUUID}&option_id={$option.id}', '{$csrfToken}');"{if $option.obsolete} style="display: none;"{/if}>
                                    <i class="bi bi-trash-fill text-danger" data-bs-toggle="tooltip" title="{$translationStrings.delete}"></i>
                                </a>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
                <tfoot>
                    <tr id="table_row_button">
                        <td colspan="4">
                            <a class="icon-text-link" href="javascript:void(0);" onclick="javascript:addOptionRow('{$data.id}', '{$urlAdmidio}/modules/profile-fields.php?mode=delete_option_entry&uuid={$fieldUUID}&option_id={$option.id}', '{$csrfToken}', {$translationStrings|json_encode|escape:'htmlall':'UTF-8'});">
                                <i class="bi bi-plus-circle-fill"></i> {$l10n->get('SYS_ADD_ENTRY')}
                            </a>
                        </td>
                    </tr>
                </tfoot>
            </table>

            {include file="sys-template-parts/parts/form.part.helptext.tpl"}
            {include file="sys-template-parts/parts/form.part.warning.tpl"}

        </div>
    </div>
{/if}