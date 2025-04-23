<!-- Responsive Tabs and Accordions -->
<div class="d-none d-md-block">
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs profile-tabs" id="adm_profile_tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="adm_profile_basic_data_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_basic_data" type="button" role="tab" aria-controls="adm_profile_basic_data" aria-selected="true">
                {$l10n->get('SYS_BASIC_DATA')}
            </button>
        </li>
        {foreach $profileData as $categoryName => $category}
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adm_profile_{$categoryName|escape|replace:' ':'_'}_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_{$categoryName|escape|replace:' ':'_'}" type="button" role="tab" aria-controls="adm_profile_{$categoryName|escape|replace:' ':'_'}" aria-selected="false">
                    {$categoryName}
                </button>
            </li>
        {/foreach}
        {if $showCurrentRoles}
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adm_profile_role_permissions_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_permissions" type="button" role="tab" aria-controls="adm_profile_permissions" aria-selected="false">
                    {$l10n->get('SYS_PERMISSIONS')}
                </button>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">{$l10n->get('SYS_ROLE_MEMBERSHIPS')}</a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button class="dropdown-item" id="adm_profile_role_memberships_current_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_current" type="button" role="tab" aria-controls="adm_profile_role_memberships_current" aria-current="false">
                            {$l10n->get('SYS_CURRENT_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" id="adm_profile_role_memberships_future_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_future" type="button" role="tab" aria-controls="adm_profile_role_memberships_future"aria-current="false">
                            {$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    <li>
                        <button class="dropdown-item" id="adm_profile_role_memberships_former_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_former" type="button" role="tab" aria-controls="adm_profile_role_memberships_former" aria-current="false">
                            {$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    {if $showExternalRoles}
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item" id="adm_profile_role_memberships_other_org_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_other_org" type="button" role="tab" aria-controls="adm_profile_role_memberships_other_org" aria-current="false">
                                {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                                <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                            </button>
                        </li>
                    {/if}
                </ul>
            </li>
        {else if $showExternalRoles}
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adm_profile_role_memberships_other_org_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_other_org" type="button" role="tab" aria-controls="adm_profile_role_memberships_other_org" aria-current="false">
                    {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                    <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                    data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                    data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                </button>
            </li>
        {/if}
        {if $showUserRelations}
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="adm_profile_user_relations_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_user_relations" type="button" role="tab" aria-controls="adm_profile_user_relations" aria-selected="false">
                    {$l10n->get('SYS_USER_RELATIONS')}
                </button>
            </li>
        {/if}
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="adm_profile_tabs_content">
        <!-- Basic Data Tab -->
        <div class="tab-pane fade show active" id="adm_profile_basic_data" role="tabpanel" aria-labelledby="adm_profile_basic_data_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                    {include file="modules/profile.view.basic-data.tpl"}
                </div>
            </div>
        </div>

        <!-- Dynamic Tabs for additional Profile Data -->
        {foreach $profileData as $categoryName => $category}
            <div class="tab-pane fade" id="adm_profile_{$categoryName|escape|replace:' ':'_'}" role="tabpanel" aria-labelledby="adm_profile_{$categoryName|escape|replace:' ':'_'}_tab">
                <div class="card admidio-tabbed-field-group">
                    <div class="card-body">
                        {include file="modules/profile.view.dynamic-data.tpl"}
                    </div>
                </div>
            </div>
        {/foreach}

        <!-- Permissions Tab -->
        <div class="tab-pane fade" id="adm_profile_permissions" role="tabpanel" aria-labelledby="adm_profile_permissions_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                    {include file="modules/profile.view.permissions-data.tpl"}
                </div>
            </div>
        </div>

        <!-- Current Role Memberships Tab -->
        <div class="tab-pane fade" id="adm_profile_role_memberships_current" role="tabpanel" aria-labelledby="adm_profile_role_memberships_current_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                </div>
            </div>
        </div>

        <!-- Future Role Memberships Tab -->
        <div class="tab-pane fade" id="adm_profile_role_memberships_future" role="tabpanel" aria-labelledby="adm_profile_role_memberships_future_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                </div>
            </div>
        </div>

        <!-- Former Role Memberships Tab -->
        <div class="tab-pane fade" id="adm_profile_role_memberships_former" role="tabpanel" aria-labelledby="adm_profile_role_memberships_former_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                </div>
            </div>
        </div>

        <!-- Other Org Role Memberships Tab -->
        <div class="tab-pane fade" id="adm_profile_role_memberships_other_org" role="tabpanel" aria-labelledby="adm_profile_role_memberships_other_org_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                    {include file="modules/profile.view.other-org-memberships-data.tpl"}
                </div>
            </div>
        </div>


        <!-- User Relations Tab -->
        <div class="tab-pane fade" id="adm_profile_user_relations" role="tabpanel" aria-labelledby="adm_profile_user_relations_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                    {include file="modules/profile.view.relations-data.tpl"}
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-block d-md-none">
    <!-- Accordion Navigation -->
    <div class="accordion" id="adm_profile_accordion">
        <!-- Basic Data Accordion -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="adm_heading_profile_basic_data">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_basic_data" aria-expanded="true" aria-controls="adm_collapse_profile_basic_data">
                    {$l10n->get('SYS_BASIC_DATA')}
                </button>
            </h2>
            <div id="adm_collapse_profile_basic_data" class="accordion-collapse collapse show" aria-labelledby="adm_heading_profile_basic_data" data-bs-parent="#adm_profile_accordion">
                <div class="accordion-body admidio-tabbed-field-group">
                    {include file="modules/profile.view.basic-data.tpl"}
                </div>
            </div>
        </div>
        <!-- Dynamic Accordions for additional Profile Data -->
        {foreach $profileData as $categoryName => $category}
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_heading_profile_{$categoryName|escape|replace:' ':'_'}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_{$categoryName|escape|replace:' ':'_'}" aria-expanded="false" aria-controls="adm_collapse_profile_{$categoryName|escape|replace:' ':'_'}">
                        {$categoryName}
                    </button>
                </h2>
                <div id="adm_collapse_profile_{$categoryName|escape|replace:' ':'_'}" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_{$categoryName|escape|replace:' ':'_'}" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body admidio-tabbed-field-group">
                        {include file="modules/profile.view.dynamic-data.tpl"}
                    </div>
                </div>
            </div>
        {/foreach}
        <!-- Permissions Accordion -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="adm_heading_profile_role_permissions">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_role_permissions" aria-expanded="false" aria-controls="adm_collapse_profile_role_permissions">
                    {$l10n->get('SYS_PERMISSIONS')}
                </button>
            </h2>
            <div id="adm_collapse_profile_role_permissions" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_role_permissions" data-bs-parent="#adm_profile_accordion">
                <div class="accordion-body admidio-tabbed-field-group">
                    {include file="modules/profile.view.permissions-data.tpl"}
                </div>
            </div>
        </div>
        {if $showCurrentRoles}
            <!-- Current Role Memberships Accordion -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_heading_profile_role_memberships_current">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_role_memberships_current" aria-expanded="false" aria-controls="adm_collapse_profile_role_memberships_current">
                        {$l10n->get('SYS_CURRENT_ROLE_MEMBERSHIP')}
                    </button>
                </h2>
                <div id="adm_collapse_profile_role_memberships_current" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_role_memberships_current" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body admidio-tabbed-field-group">
                    </div>
                </div>
            </div>
            <!-- Future Role Memberships Accordion -->
            <div class="accordion-item" id="adm_profile_role_memberships_future_accordion">
                <h2 class="accordion-header" id="adm_heading_profile_role_memberships_future">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_role_memberships_future" aria-expanded="false" aria-controls="adm_collapse_profile_role_memberships_future">
                        {$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}
                    </button>
                </h2>
                <div id="adm_collapse_profile_role_memberships_future" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_role_memberships_future" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body admidio-tabbed-field-group">
                    </div>
                </div>
            </div>
            <!-- Former Role Memberships Accordion -->
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_heading_profile_role_memberships_former">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_role_memberships_former" aria-expanded="false" aria-controls="adm_collapse_profile_role_memberships_former">
                        {$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}
                    </button>
                </h2>
                <div id="adm_collapse_profile_role_memberships_former" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_role_memberships_former" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body admidio-tabbed-field-group">
                    </div>
                </div>
            </div>
        {/if}
        <!-- Other Org Role Memberships Accordion -->
        {if $showExternalRoles}
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_heading_profile_role_memberships_other_org">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_role_memberships_other_org" aria-expanded="false" aria-controls="adm_collapse_profile_role_memberships_other_org">
                        {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                        <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                        data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                        data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                    </button>
                </h2>
                <div id="adm_collapse_profile_role_memberships_other_org" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_role_memberships_other_org" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body admidio-tabbed-field-group">
                        {include file="modules/profile.view.other-org-memberships-data.tpl"}
                    </div>
                </div>
            </div>
        {/if}
        <!-- User Relations Accordion -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="adm_heading_profile_user_relations">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_collapse_profile_user_relations" aria-expanded="false" aria-controls="adm_collapse_profile_user_relations">
                    {$l10n->get('SYS_USER_RELATIONS')}
                </button>
            </h2>
            <div id="adm_collapse_profile_user_relations" class="accordion-collapse collapse" aria-labelledby="adm_heading_profile_user_relations" data-bs-parent="#adm_profile_accordion">
                <div class="accordion-body admidio-tabbed-field-group">
                    {include file="modules/profile.view.relations-data.tpl"}
                </div>
            </div>
        </div>
    </div>
</div>

{include file="sys-template-parts/system.info-create-edit.tpl"}