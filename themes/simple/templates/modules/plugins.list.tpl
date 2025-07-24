<!-- for big screens: Table -->
<div class="d-none d-md-block">
    <div class="table-responsive" id="adm_plugins_table">
        <table id="adm_table_plugins" class="table table-condensed table-hover" style="max-width: 100%;">
            <thead>
                <tr>
                    <th>{$l10n->get('SYS_NAME')}</th>
                    <th>&nbsp;</th> {* sort *}
                    <th>{$l10n->get('SYS_DESCRIPTION')}</th>
                    <th>{$l10n->get('SYS_AUTHOR')}</th>
                    <th>{$l10n->get('SYS_WEBSITE')}</th>
                    <th>{$l10n->get('SYS_PLUGIN_VERSION')}</th>
                    <th>{$l10n->get('SYS_INSTALLED_VERSION')}</th>
                    <th>&nbsp;</th> {* actions *}
                </tr>
            </thead>
            {foreach $list as $pluginNode}
                <tbody>
                    <tr class="admidio-group-heading">
                        <td id="adm_plugin_group_{$pluginNode.id}" colspan="8">
                            <a id="adm_plugin_caret_{$pluginNode.id}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_plugin_entries_{$pluginNode.id}">
                                <i class="bi bi-caret-down-fill"></i>
                            </a> {$pluginNode.name}
                        </td>
                    </tr>
                </tbody>
                {if isset($pluginNode.entries)}
                <tbody id="adm_plugin_entries_{$pluginNode.id}" class="admidio-sortable">
                    {foreach $pluginNode.entries as $pluginEntry}
                        <tr id="adm_plugin_entry_{$pluginEntry.id}" data-uuid="{$pluginEntry.id}">
                            <td>{if isset($pluginEntry.icon)}<i class="bi {$pluginEntry.icon}"></i>{/if} {$pluginEntry.name}</td>
                            <td>
                            {if {string_contains haystack=$pluginNode.id needle='overview'}}
                                <div class="btn-group" role="group">
                                    <a class="admidio-icon-link admidio-plugin-move" href="javascript:void(0)" data-uuid="{$pluginEntry.id}"
                                    data-direction="UP" data-target="adm_plugin_entry_{$pluginEntry.id}">
                                        <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('SYS_PLUGIN'))}"></i></a>
                                    <a class="admidio-icon-link admidio-plugin-move" href="javascript:void(0)" data-uuid="{$pluginEntry.id}"
                                    data-direction="DOWN" data-target="adm_plugin_entry_{$pluginEntry.id}">
                                        <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('SYS_PLUGIN'))}"></i></a>
                                    <a class="admidio-icon-link">
                                        <i class="bi bi-arrows-move handle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_VAR', array('SYS_PLUGIN'))}"></i></a>
                                </div>
                            {/if}
                            </td>
                            <td>{$pluginEntry.description}</td>
                            <td>{$pluginEntry.author}</td>
                            <td>{$pluginEntry.url}</td>
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
</div>

<!-- for small screens: Cards -->
<div class="d-block d-md-none admidio-margin-bottom">
    <div class="accordion" id="adm_plugins_accordion">
    {foreach $list as $pluginNode name=outer}
        <div class="accordion-item">
            <h2 class="accordion-header" id="heading_{$pluginNode.id}">
                <button class="accordion-button{if not ($smarty.foreach.outer.first)} collapsed{/if}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{$pluginNode.id}" aria-expanded="{if $smarty.foreach.outer.first}true{else}false{/if}" aria-controls="collapse_{$pluginNode.id}">
                    {$pluginNode.name}
                </button>
            </h2>
            <div class="accordion-collapse collapse{if $smarty.foreach.outer.first} show{/if}" id="collapse_{$pluginNode.id}" aria-labelledby="heading_{$pluginNode.id}" data-bs-parent="#adm_plugins_accordion">
                <div class="accordion-body">
                {foreach $pluginNode.entries as $pluginEntry}
                    <div class="card admidio-accordion-field-group" id="adm_plugin_card_entry_{$pluginEntry.id}" data-uuid="{$pluginEntry.id}">
                        <div class="card-header">
                            {if isset($pluginEntry.icon)}<i class="bi {$pluginEntry.icon}"></i>{/if} {$pluginEntry.name}
                            <div class="dropdown float-end d-flex">
                                {if {string_contains haystack=$pluginNode.id needle='overview'}}
                                    <a class="admidio-icon-link admidio-plugin-move" href="javascript:void(0)" data-uuid="{$pluginEntry.id}"
                                    data-direction="UP" data-target="adm_plugin_card_entry_{$pluginEntry.id}">
                                        <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', array('SYS_PLUGIN'))}"></i></a>
                                    <a class="admidio-icon-link admidio-plugin-move" href="javascript:void(0)" data-uuid="{$pluginEntry.id}"
                                    data-direction="DOWN" data-target="adm_plugin_card_entry_{$pluginEntry.id}">
                                        <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', array('SYS_PLUGIN'))}"></i></a>
                                {/if}
                                <a class="admidio-icon-link" href="#" role="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                                {if {array_key_exists array=$pluginEntry key="actions"} && count($pluginEntry.actions) > 0 || isset({$pluginEntry.url})}
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                        {if {array_key_exists array=$pluginEntry key="actions"} && count($pluginEntry.actions) > 0}
                                            {foreach $pluginEntry.actions as $actionItem}
                                                <a {if isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                                        {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                                            {/foreach}
                                            {if isset($pluginEntry.url)}
                                                <li><hr class="dropdown-divider"></li>
                                            {/if}
                                        {/if}
                                        {if isset($pluginEntry.url)}
                                            <li style="padding: 0 var(--bs-dropdown-item-padding-x);">{$l10n->get('SYS_WEBSITE')}:</li>
                                            <li class="dropdown-item">{$pluginEntry.url}</li>
                                        {/if}
                                    </ul>
                                {/if}
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="adm_plugin_card_entry_{$pluginEntry.id}_description">
                                <strong>{$l10n->get('SYS_DESCRIPTION')}:</strong>
                                <p>{$pluginEntry.description}</p>
                            </div>
                            <div id="adm_plugin_card_entry_{$pluginEntry.id}_author">
                                <strong>{$l10n->get('SYS_AUTHOR')}:</strong>
                                <p>{$pluginEntry.author}</p>
                            </div>
                            <div id="adm_plugin_card_entry_{$pluginEntry.id}_version">
                                <strong>{$l10n->get('SYS_PLUGIN_VERSION')}:</strong>
                                <p>{$pluginEntry.version}</p>
                            </div>
                            <div id="adm_plugin_card_entry_{$pluginEntry.id}_installed_version">
                                <strong>{$l10n->get('SYS_INSTALLED_VERSION')}:</strong>
                                <p>{$pluginEntry.installedVersion}</p>
                            </div>
                        </div>
                    </div>
                {/foreach}
                </div>
            </div>
        </div>
    {/foreach}
    </div>
</div>