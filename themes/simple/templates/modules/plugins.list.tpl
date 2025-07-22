<div class="table-responsive">
    <table id="adm_table_plugins" class="table table-condensed table-hover" style="max-width: 100%;">
        <thead>
            <tr>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th>{$l10n->get('SYS_DESCRIPTION')}</th>
                <th>{$l10n->get('SYS_AUTHOR')}</th>
                <th>{$l10n->get('SYS_PLUGIN_VERSION')}</th>
                <th>{$l10n->get('SYS_INSTALLED_VERSION')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        {foreach $list as $pluginNode}
            <tbody>
                <tr class="admidio-group-heading">
                    <td id="adm_plugin_group_{$pluginNode.id}" colspan="6">
                        <a id="adm_plugin_caret_{$pluginNode.id}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_plugin_entries_{$pluginNode.id}">
                            <i class="bi bi-caret-down-fill"></i>
                        </a> {$pluginNode.name}
                    </td>
                </tr>
            </tbody>
            {if isset($pluginNode.entries)}
            <tbody id="adm_plugin_entries_{$pluginNode.id}" class="admidio-sortable">
                {foreach $pluginNode.entries as $pluginEntry}
                    <tr id="adm_plugin_entry_{$pluginEntry.id}">
                        <td>{if isset($pluginEntry.icon)}<i class="bi {$pluginEntry.icon}"></i>{/if} {$pluginEntry.name}</td>
                        <td>{$pluginEntry.description}</td>
                        <td>{$pluginEntry.author}</td>
                        <td>{$pluginEntry.version}</td>
                        <td>{$pluginEntry.installedVersion}</td>
                        <td class="text-end">
                            {include 'sys-template-parts/list.functions.tpl' data=$pluginEntry}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
            {/if}
        {/foreach}
    </table>
</div>