{* The plugins that crashed Admidio and are kept out until they are tried again. Only administrators
   get any, see PluginsPresenter::getCrashNotices(). The texts are encoded for HTML already. *}
{if count($crashedPlugins) > 0}
    <div class="alert alert-danger" id="adm_plugins_crashed">
        {foreach $crashedPlugins as $crashedPlugin}
            <div class="d-flex align-items-start{if not $crashedPlugin@last} mb-3{/if}">
                <div class="flex-grow-1">
                    <i class="bi bi-exclamation-octagon-fill"></i> {$crashedPlugin.message}
                    {if $crashedPlugin.dependants neq ''}<br />{$crashedPlugin.dependants}{/if}
                    <br /><small><code>{$crashedPlugin.location}</code> &ndash; {$crashedPlugin.time}</small>
                </div>
                <button class="btn btn-sm btn-primary ms-3 admidio-send-csrf-token" data-url="{$crashedPlugin.retryUrl}"
                    data-csrf-token="{$crashedPlugin.csrfToken}"><i class="bi bi-arrow-clockwise"></i> {$l10n->get('SYS_PLUGIN_CRASHED_RETRY')}</button>
            </div>
        {/foreach}
        <hr />
        <small>{$l10n->get('SYS_PLUGIN_CRASHED_INFO')}</small>
    </div>
{/if}
