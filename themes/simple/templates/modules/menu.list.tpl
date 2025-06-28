<table id="adm_table_menu" class="table table-hover" width="100%" style="width: 100%;">
    <thead>
        <tr>
            <th>{$l10n->get('SYS_TITLE')}</th>
            <th>&nbsp;</th>
            <th>{$l10n->get('SYS_URL')}</th>
            <th class="text-center"><i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$l10n->get('SYS_MENU_ITEM')])}"></i></th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    {foreach $list as $menuNode}
        <tbody>
            <tr class="admidio-group-heading">
                <td id="adm_menu_group_{$menuNode.uuid}" colspan="5">
                    <a id="adm_menu_caret_{$menuNode.uuid}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_menu_entries_{$menuNode.uuid}">
                        <i class="bi bi-caret-down-fill"></i>
                    </a> {$menuNode.name}
                </td>
            </tr>
        </tbody>
        <tbody id="adm_menu_entries_{$menuNode.uuid}" class="admidio-sortable">
            {foreach $menuNode.entries as $menuEntry}
                <tr id="adm_menu_entry_{$menuEntry.uuid}">
                    <td style="word-break: break-word;"><i class="bi bi-{$menuEntry.icon}"></i> {$menuEntry.name}</td>
                    <td>
                        <a class="admidio-icon-link admidio-menu-move" href="javascript:void(0)" data-uuid="{$menuEntry.uuid}"
                           data-direction="UP" data-target="adm_menu_entry_{$menuEntry.uuid}">
                            <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', [$headline])}"></i>
                        </a>
                        <a class="admidio-icon-link admidio-menu-move" href="javascript:void(0)" data-uuid="{$menuEntry.uuid}"
                           data-direction="DOWN" data-target="adm_menu_entry_{$menuEntry.uuid}">
                            <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', [$headline])}"></i>
                        </a>
                    </td>
                    <td><a href="{$menuEntry.urlLink}" title="{$menuEntry.description}">{$menuEntry.url}</a></td>
                    <td class="text-center">
                        {if $menuEntry.standard}
                            <i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$l10n->get('SYS_MENU_ITEM')])}"></i>
                        {/if}
                    </td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$menuEntry}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    {/foreach}
</table>
