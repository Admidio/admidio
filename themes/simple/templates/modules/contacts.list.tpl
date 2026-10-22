<div class="table-responsive">
    {if $showSelectActions}
        <div id="adm_contacts_table_select_actions" class="mb-3">
            <ul class="nav admidio-menu-function-node">
                <li class="nav-item">
                    <button id="edit-selected" class="btn nav-link btn-primary" disabled="disabled">
                        <i class="bi bi-pencil-square me-1"></i>{$l10n->get('SYS_EDIT_SELECTION')}
                    </button>
                </li>
                <li class="nav-item">
                    <button id="delete-selected" class="btn nav-link btn-primary" disabled="disabled">
                        <i class="bi bi-trash me-1"></i>{$l10n->get('SYS_DELETE_SELECTION')}
                    </button>
                </li>
            </ul>
        </div>
    {/if}
    <table id="adm_contacts_table" class="table table-condensed table-hover" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach $headers as $header}
                    <th>{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {* serverside processing data *}
        </tbody>
    </table>
</div>
