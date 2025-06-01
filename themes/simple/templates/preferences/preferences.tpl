<!-- for big screens: Tabs -->
<div class="d-none d-md-block">
    <div class="tabs-x tabs-left tab-bordered">
        <!-- variable set the first tab active -->
        {assign var="globalFirst" value=true}
        <ul id="adm_preferences_tabs" class="nav nav-tabs flex-column list-group admidio-preferences-group" role="tablist">
            {foreach $preferenceTabs as $tab}
                <li class="list-group-item group-heading" role="presentation">
                    <h5 class="mb-0">{$tab.label}</h5>
                </li>

                {foreach $tab.panels as $panel name=pList}
                    <li class="list-group-item nav-item{if $smarty.foreach.pList.last} group-last{/if}" role="presentation">
                        <button class="nav-link text-start{if $globalFirst} active{/if}" id="adm_tab_{$panel.id}" data-bs-toggle="tab" data-bs-target="#adm_tab_{$panel.id}_content" type="button" role="tab" aria-controls="adm_tab_{$panel.id}_content" aria-selected="{if $globalFirst}true{else}false{/if}" {if not $globalFirst}tabindex="-1"{/if}>
                            <i class="bi {$panel.icon} me-1"></i>{$panel.title}
                        </button>
                    </li>
                    {if $globalFirst}{assign var="globalFirst" value=false}{/if}
                {/foreach}
            {/foreach}
        </ul>

        <!-- Tab Content -->
        <div id="adm_preferences_tab_content" class="tab-content">
            {assign var="globalFirst" value=true}
            {foreach $preferenceTabs as $tab}
                {foreach $tab.panels as $panel}
                    <div class="tab-pane fade{if $globalFirst} active show{/if}" id="adm_tab_{$panel.id}_content" role="tabpanel" aria-labelledby="tab-{$panel.id}">
                        <!-- heading for each tab group -->
                        {if !$panel.subcards}                                              
                            <div class="card admidio-tabbed-field-group">
                                <div class="card-header">{$panel.title}</div>
                                <div class="card-body">
                        {/if} 
                                    <div id="adm_panel_preferences_{$panel.id}" data-preferences-panel="{$panel.id}">
                                        <!-- AJAX-Container for panel content -->
                                    </div>
                        {if !$panel.subcards}
                                </div>
                            </div>
                        {/if}
                    </div>
                    {if $globalFirst}{assign var="globalFirst" value=false}{/if}
                {/foreach}
            {/foreach}
        </div>
    </div>
</div>

<!-- for small screens: Accordions -->
<div class="d-block d-md-none">
    <div class="accordion" id="adm_preferences_accordion">
        {foreach $preferenceTabs as $tab name=outer}
            <!-- heading for each accordion group -->
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
                                        <!-- AJAX-Container for panel content -->
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