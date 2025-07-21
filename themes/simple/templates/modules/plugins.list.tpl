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
        <tbody id="adm_plugin_entries" class="admidio-sortable">
            {foreach $list as $pluginEntry}
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
    </table>
</div>