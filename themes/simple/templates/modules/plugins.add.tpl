{*
    The plugin directory: the plugins published for Admidio. A plugin file the administrator already
    has is uploaded on a page of its own (plugins.upload.tpl).
*}
<div class="card admidio-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-box-seam me-1"></i>{$l10n->get('SYS_PLUGIN_STORE')}</span>
        <a class="btn btn-sm btn-secondary" href="javascript:void(0)" onclick="{$storeRefreshHref}">
            <i class="bi bi-arrow-clockwise"></i> {$l10n->get('SYS_PLUGIN_STORE_REFRESH')}
        </a>
    </div>
    <div class="card-body">
        {if $storeError}
            {* An English developer diagnostic, like the ones a broken plugin produces. *}
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {$l10n->get('SYS_PLUGIN_STORE_UNAVAILABLE')}
                <div class="mt-2"><small><code>{$storeError}</code></small></div>
            </div>
        {elseif $store|@count eq 0}
            <p class="mb-0">{$l10n->get('SYS_PLUGIN_STORE_EMPTY')}</p>
        {else}
            <div class="table-responsive">
                <table id="adm_plugin_store_table" class="table table-condensed table-hover">
                    <thead>
                        <tr>
                            <th style="width: 20%; white-space: nowrap;">{$l10n->get('SYS_NAME')}</th>
                            <th>{$l10n->get('SYS_DESCRIPTION')}</th>
                            <th>{$l10n->get('SYS_VERSION')}</th>
                            <th>&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach $store as $storeEntry}
                            <tr id="adm_plugin_store_{$storeEntry.id}">
                                <td style="white-space: nowrap;">
                                    {if $storeEntry.icon neq ''}<i class="bi {$storeEntry.icon}"></i>{/if}
                                    {if $storeEntry.url neq ''}
                                        <a href="{$storeEntry.url}" target="_blank"><strong>{$storeEntry.name}</strong></a>
                                    {else}
                                        <strong>{$storeEntry.name}</strong>
                                    {/if}
                                </td>
                                <td>
                                    {$storeEntry.description}
                                    {if $storeEntry.author neq ''}
                                        <div><small class="text-muted">{$storeEntry.author}</small></div>
                                    {/if}
                                </td>
                                <td>
                                    {$storeEntry.version}
                                    {if $storeEntry.installed and $storeEntry.installedVersion neq $storeEntry.version}
                                        <div><small class="text-muted">{$l10n->get('SYS_INSTALLED_VERSION')}: {$storeEntry.installedVersion}</small></div>
                                    {/if}
                                </td>
                                <td class="text-end">
                                    {if $storeEntry.installed}
                                        <span class="badge bg-success">{$l10n->get('SYS_INSTALLED')}</span>
                                    {else}
                                        {include 'sys-template-parts/list.functions.tpl' data=$storeEntry}
                                    {/if}
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</div>
