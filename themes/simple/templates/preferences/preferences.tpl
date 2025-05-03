{* === Für große Bildschirme: Tabs === *}
<div class="d-none d-md-block">
    <ul id="adm_preferences_tabs" class="nav nav-tabs admidio-tabs" role="tablist">
        {foreach $preferenceTabs as $tab name=tabLoop}
            <li class="nav-item" role="presentation">
                <button class="nav-link{if $smarty.foreach.tabLoop.first} active{/if}" id="adm_tabs_{$tab.key|escape:'url'}" data-bs-toggle="tab" data-bs-target="#adm_tab_{$tab.key|escape:'url'}" type="button" role="tab" aria-controls="adm_tab_{$tab.key|escape:'url'}" aria-selected="{if $smarty.foreach.tabLoop.first}true{else}false{/if}">
                    {$tab.label}
                </button>
            </li>
        {/foreach}
    </ul>

    <div class="tab-content" id="adm_preferences_tab_content">
        {foreach $preferenceTabs as $tab name=contentLoop}
            <div class="tab-pane fade{if $smarty.foreach.contentLoop.first} show active{/if}" id="adm_tab_{$tab.key|escape:'url'}" role="tabpanel">
                {* === Unter-Tabs === *}
                <ul id="adm_subtabs_{$tab.key|escape:'url'}" class="nav nav-tabs admidio-tabs" role="tablist">
                    {foreach $tab.panels as $panel name=panelLoop}
                        <li class="nav-item" role="presentation">
                            <button class="nav-link{if $smarty.foreach.panelLoop.first} active{/if}" id="adm_tab_{$panel.id}_nav" data-bs-toggle="tab" data-bs-target="#adm_tab_{$panel.id}_content" type="button" role="tab" aria-controls="adm_tab_{$panel.id}_content" aria-selected="{if $smarty.foreach.panelLoop.first}true{else}false{/if}">
                                <i class="bi {$panel.icon}"></i>&nbsp;{$panel.title}
                            </button>
                        </li>
                    {/foreach}
                </ul>

                <div class="tab-content" id="adm_preferences_subtab_content_{$tab.key|escape:'url'}">
                    {foreach $tab.panels as $panel name=panelContentLoop}
                        <div class="tab-pane fade{if $smarty.foreach.panelContentLoop.first} show active{/if}" id="adm_tab_{$panel.id}_content" role="tabpanel">
                            <div id="adm_panel_preferences_{$panel.id}" data-preferences-panel="{$panel.id}">
                                {* AJAX-Container für Formular *}
                            </div>
                        </div>
                    {/foreach}
                </div>
            </div>
        {/foreach}
    </div>
</div>

{* === Für kleine Bildschirme: ein globales Accordion === *}
<div class="d-block d-md-none">
    <div class="accordion" id="adm_preferences_accordion">
        {foreach $preferenceTabs as $tab name=outer}
            {** Überschrift für jede Tab-Gruppe **}
            <div class="card admidio-accordion-field-group">
                <div class="card-header">{$tab.label}</div>
                <div class="card-body">
                    {foreach $tab.panels as $panel name=inner}
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading_{$panel.id}">
                                <button class="accordion-button{if not ($smarty.foreach.outer.first && $smarty.foreach.inner.first)} collapsed{/if}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_{$panel.id}" aria-expanded="{if $smarty.foreach.outer.first && $smarty.foreach.inner.first}true{else}false{/if}" aria-controls="collapse_{$panel.id}">
                                    <i class="bi {$panel.icon} me-1"></i>{$panel.title}
                                </button>
                            </h2>
                            <div class="accordion-collapse collapse{if $smarty.foreach.outer.first && $smarty.foreach.inner.first} show{/if}" id="collapse_{$panel.id}" aria-labelledby="heading_{$panel.id}" data-bs-parent="#adm_preferences_accordion">
                                <div class="accordion-body">
                                    <div id="adm_panel_preferences_{$panel.id}" data-preferences-panel="{$panel.id}" >
                                        {* AJAX-Container *}
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/foreach}
                </div>
            </div>
        {/foreach}
    </div>
</div>
