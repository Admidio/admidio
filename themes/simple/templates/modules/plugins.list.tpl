{* The plugins that threw while they were loaded. Without this they would merely be absent. *}
{if count($failures) > 0}
    <div class="alert alert-danger" id="adm_plugins_failures">
        <i class="bi bi-exclamation-circle-fill"></i> {$l10n->get('SYS_PLUGIN_LOAD_FAILED')}
        <ul class="mb-0">
            {foreach $failures as $failedPlugin => $failureReason}
                <li><strong>{$failedPlugin}</strong>: {$failureReason}</li>
            {/foreach}
        </ul>
    </div>
{/if}

<!-- for big screens: Table -->
<div class="d-none d-md-block">
    <div class="table-responsive" id="adm_plugins_table">
        <table id="adm_table_plugins" class="table table-condensed table-hover" style="max-width: 100%;">
            <thead>
                <tr>
                    <th>{$l10n->get('SYS_NAME')}</th>
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
                        <td id="adm_plugin_group_{$pluginNode.id}" colspan="7">
                            <a id="adm_plugin_caret_{$pluginNode.id}" class="admidio-icon-link admidio-open-close-caret" data-target="adm_plugin_entries_{$pluginNode.id}">
                                <i class="bi bi-caret-down-fill"></i>
                            </a> {$pluginNode.name}
                        </td>
                    </tr>
                </tbody>
                <tbody id="adm_plugin_entries_{$pluginNode.id}">
                    {foreach $pluginNode.entries as $pluginEntry}
                        <tr id="adm_plugin_entry_{$pluginEntry.id}" data-uuid="{$pluginEntry.id}">
                            <td>{if $pluginEntry.icon neq ''}<i class="bi {$pluginEntry.icon}"></i>{/if} {$pluginEntry.name}</td>
                            <td>
                                {$pluginEntry.description}
                                {* Developer diagnostics in English, not a translated message. *}
                                {foreach $pluginEntry.diagnostics as $diagnostic}
                                    <div class="text-danger"><small><code>{$diagnostic}</code></small></div>
                                {/foreach}
                            </td>
                            <td>{$pluginEntry.author}</td>
                            <td>
                                {if $pluginEntry.url neq ''}
                                    <a href="{$pluginEntry.url}" target="_blank" rel="noopener noreferrer"
                                       data-bs-toggle="tooltip" title="{$pluginEntry.url}" style="display:inline-flex;">
                                        <i class="bi bi-link-45deg"></i>{$pluginEntry.urlHost}</a>
                                {/if}
                            </td>
                            <td>{$pluginEntry.version}</td>
                            <td data-bs-toggle="tooltip"
                                {if $pluginEntry.versionState === 'not_installed'}
                                    style="color: var(--bs-danger);"
                                {elseif $pluginEntry.versionState === 'update'}
                                    style="color: var(--bs-warning);" title="{$l10n->get('SYS_UPDATE_AVAILABLE')}"
                                {elseif $pluginEntry.versionState === 'current'}
                                    style="color: var(--bs-success);" title="{$l10n->get('SYS_UP_TO_DATE')}"
                                {/if}
                                >{if $pluginEntry.versionState === 'not_installed'}{$l10n->get('SYS_NOT_INSTALLED')}{else}{$pluginEntry.installedVersion}{/if}</td>
                            <td class="text-end">
                                {include 'sys-template-parts/list.functions.tpl' data=$pluginEntry}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
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
                            {if $pluginEntry.icon neq ''}<i class="bi {$pluginEntry.icon}"></i>{/if} {$pluginEntry.name}
                            <div class="dropdown float-end d-flex">
                                <a class="admidio-icon-link" href="#" role="button" id="adm_dropdown_menu_button_{$pluginEntry.id}" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="bi bi-three-dots" data-bs-toggle="tooltip"></i></a>
                                {if count($pluginEntry.actions) > 0 || $pluginEntry.url neq ''}
                                    <ul class="dropdown-menu" aria-labelledby="adm_dropdown_menu_button_{$pluginEntry.id}">
                                        {foreach $pluginEntry.actions as $actionItem}
                                            <li>
                                                <a {if isset($actionItem.dataHref)} class="dropdown-item admidio-messagebox" href="javascript:void(0);"
                                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                                        {else} class="dropdown-item" href="{$actionItem.url}"{/if}>
                                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i> {$actionItem.tooltip}</a>
                                            </li>
                                        {/foreach}
                                        {if $pluginEntry.url neq ''}
                                            {if count($pluginEntry.actions) > 0}
                                                <li><hr class="dropdown-divider"></li>
                                            {/if}
                                            <li style="padding: 0 var(--bs-dropdown-item-padding-x);">{$l10n->get('SYS_WEBSITE')}:</li>
                                            <li class="dropdown-item">
                                                <a href="{$pluginEntry.url}" target="_blank" rel="noopener noreferrer">
                                                    <i class="bi bi-link-45deg"></i>{$pluginEntry.urlHost}</a>
                                            </li>
                                        {/if}
                                    </ul>
                                {/if}
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="adm_plugin_card_entry_{$pluginEntry.id}_description">
                                <strong>{$l10n->get('SYS_DESCRIPTION')}:</strong>
                                <p>{$pluginEntry.description}</p>
                                {foreach $pluginEntry.diagnostics as $diagnostic}
                                    <p class="text-danger"><small><code>{$diagnostic}</code></small></p>
                                {/foreach}
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
                                {if $pluginEntry.versionState === 'not_installed'}
                                    <p><span style="color: var(--bs-danger);">{$l10n->get('SYS_NOT_INSTALLED')}</span></p>
                                {elseif $pluginEntry.versionState === 'update'}
                                    <p><span style="color: var(--bs-warning);">{$pluginEntry.installedVersion} ({$l10n->get('SYS_UPDATE_AVAILABLE')})</span></p>
                                {elseif $pluginEntry.versionState === 'current'}
                                    <p><span style="color: var(--bs-success);">{$pluginEntry.installedVersion} ({$l10n->get('SYS_UP_TO_DATE')})</span></p>
                                {else}
                                    <p>{$pluginEntry.installedVersion}</p>
                                {/if}
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
