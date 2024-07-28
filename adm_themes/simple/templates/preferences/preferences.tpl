<ul id="admidio-preferences-tabs" class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a id="tabs_nav_common" class="nav-link active" href="#tabs-common" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_COMMON')}</a>
    </li>
    <li class="nav-item">
        <a id="tabs_nav_modules" class="nav-link" href="#tabs-modules" data-bs-toggle="tab" role="tab">{$l10n->get('SYS_MODULES')}</a>
    </li>
</ul>

<div id="admidio-preferences-tab-content" class="tab-content">
    <div class="tab-pane fade show active" id="tabs-common" role="tabpanel">
        <div class="accordion" id="accordion_preferences">
            {foreach $accordionCommonPanels as $accordionPanel}
                <div id="admidio-panel-common-preferences-{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-bs-toggle="collapse" data-bs-target="#collapse-common-preferences-{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-common-preferences-{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="collapse-common-preferences-{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#accordion_preferences">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
    <div class="tab-pane fade" id="tabs-modules" role="tabpanel">
        <div class="accordion" id="accordion_modules">
            {foreach $accordionModulePanels as $accordionPanel}
                <div id="admidio-panel-module-preferences-{$accordionPanel['id']}" class="accordion-item">
                    <h2 class="accordion-header" data-bs-toggle="collapse" data-bs-target="#collapse-module-preferences-{$accordionPanel['id']}">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-module-preferences-{$accordionPanel['id']}" aria-expanded="true" aria-controls="collapseOne">
                            <i class="bi {$accordionPanel['icon']}"></i>{$accordionPanel['title']}
                        </button>
                    </h2>
                    <div id="collapse-module-preferences-{$accordionPanel['id']}" class="accordion-collapse collapse" data-bs-parent="#accordion_preferences">
                        <div class="accordion-body">
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>
