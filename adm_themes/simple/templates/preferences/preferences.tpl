<ul id="admidioPreferencesTabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="tabsNavCommon" class="nav-link active" href="#tabsCommon" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_COMMON')}</a>
    </li>
    <li class="nav-item">
        <a id="tabsNavModules" class="nav-link" href="#tabsModules" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_MODULES')}</a>
    </li>
</ul>

<div id="admidioPreferencesTabContent" class="tab-content">
    <div class="tab-pane fade show active" id="tabsCommon" role="tabpanel">
        <div class="accordion" id="accordionPreferencesCommon">
            {foreach $accordionCommonPanels as $accordionPanel}
                <div id="admidioPanelPreferences{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-preferences-panel="{$accordionPanel['id']}" data-bs-toggle="collapse" data-bs-target="#collapsePreferences{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePreferences{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="collapsePreferences{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#accordionPreferencesCommon">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
    <div class="tab-pane fade" id="tabsModules" role="tabpanel">
        <div class="accordion" id="accordionPreferencesModules">
            {foreach $accordionModulePanels as $accordionPanel}
                <div id="admidioPanelPreferences{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-preferences-panel="{$accordionPanel['id']}" data-bs-toggle="collapse" data-bs-target="#collapsePreferences{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePreferences{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="collapsePreferences{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#accordionPreferencesModules">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>
