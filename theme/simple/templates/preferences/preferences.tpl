<ul id="adm_preferences_tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="adm_tabs_nav_common" class="nav-link active" href="#adm_tabs_common" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_COMMON')}</a>
    </li>
    <li class="nav-item">
        <a id="adm_tabs_nav_modules" class="nav-link" href="#adm_tabs_modules" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_MODULES')}</a>
    </li>
</ul>

<div id="adm_preferences_tab_content" class="tab-content">
    <div class="tab-pane fade show active" id="adm_tabs_common" role="tabpanel">
        <div class="accordion" id="adm_accordion_preferences_common">
            {foreach $accordionCommonPanels as $accordionPanel}
                <div id="adm_panel_preferences_{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-preferences-panel="{$accordionPanel['id']}" data-bs-toggle="collapse" data-bs-target="#adm_collapse_preferences_{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_preferences_{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="adm_collapse_preferences_{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#adm_accordion_preferences_common">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
    <div class="tab-pane fade" id="adm_tabs_modules" role="tabpanel">
        <div class="accordion" id="adm_accordion_preferences_modules">
            {foreach $accordionModulePanels as $accordionPanel}
                <div id="adm_panel_preferences_{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-preferences-panel="{$accordionPanel['id']}" data-bs-toggle="collapse" data-bs-target="#adm_collapse_preferences_{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_preferences_{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="adm_collapse_preferences_{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#adm_accordion_preferences_modules">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>
