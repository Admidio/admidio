{* reusable function to render tables *}
{function name="render_table" headers="" rows="" align="" tableId=""}
    <div class="table-responsive">
        <table id="{$tableId}" class="table table-condensed table-hover">
            <thead>
                <tr>
                    {foreach from=$headers key=colIndex item=header}
                        <th class="text-{$align[$colIndex]}">{$header}</th>
                    {/foreach}
                </tr>
            </thead>
            <tbody>
                {foreach from=$rows item=row}
                    <tr id="adm_inventory_item_{$row.item_uuid}">
                        {foreach from=$row.data item=cell name=table}
                            <td class="text-{$align[$smarty.foreach.table.index]}">{$cell|raw}</td>
                        {/foreach}
                        {if isset($row.actions)}
                            <td class="text-end">
                                {foreach $row.actions as $actionItem}
                                        <a
                                            {if isset($actionItem.popup)}
                                                class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                                            {elseif isset($actionItem.dataHref)}
                                                class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                            {else}
                                                class="admidio-icon-link" href="{$actionItem.url}"
                                            {/if}
                                            ><i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i>
                                        </a>
                                {/foreach}
                            </td>
                        {/if}
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/function}


<!-- Responsive Tabs and Accordions -->
<div class="d-none d-md-block">
    <!-- Tab Navigation -->
    <div class="tabs-x tabs-above tab-bordered">
        <ul class="nav nav-tabs admidio-tabs" id="adm_profile_tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="adm_profile_basic_informations_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_basic_informations_pane" type="button" role="tab" aria-controls="adm_profile_basic_data" aria-selected="true">
                    {$l10n->get('SYS_BASIC_DATA')}
                </button>
            </li>
            {if $showCurrentRoles}
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adm_profile_role_permissions_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_permissions_pane" type="button" role="tab" aria-controls="adm_profile_permissions" aria-selected="false">
                        {$l10n->get('SYS_PERMISSIONS')}
                    </button>
                </li>
        {/if}
        {if $showCurrentRoles || $showExternalRoles}
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adm_profile_role_memberships_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_pane" type="button" role="tab" aria-controls="adm_profile_role_memberships" aria-selected="false">
                        {$l10n->get('SYS_ROLE_MEMBERSHIPS')}
                    </button>
                </li>
            {/if}
        {if $showRelations}
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adm_profile_user_relations_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_user_relations_pane" type="button" role="tab" aria-controls="adm_profile_user_relations" aria-selected="false">
                        {$l10n->get('SYS_USER_RELATIONS')}
                    </button>
                </li>
        {/if}
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="adm_profile_tabs_content">
            <!-- Basic Data Tab -->
            <div class="tab-pane fade show active" id="adm_profile_basic_informations_pane" role="tabpanel" aria-labelledby="adm_profile_basic_informations_tab">
                <!-- Profile Data Card -->
                <div class="card admidio-tabbed-field-group">
                    <div class="card-header"> {$l10n->get('SYS_PROFILE_DATA')}
                        {if isset($urlEditProfile)}
                            <a class="btn btn-secondary float-end" id="adm_profile_relations_new_entry" href="{$urlEditProfile}">
                                <i class="bi bi-pencil-square me-1"></i>{$l10n->get('SYS_EDIT_PROFILE')}</a>
                        {/if}
                    </div>
                    <div class="card-body">
                        {include file="modules/profile.view.basic-informations.tpl"}
                    </div>
                </div>
                <!-- Dynamic Cards for additional Profile Data categories -->
                {foreach $profileData as $categoryName => $category}
                    <div class="card admidio-tabbed-field-group">
                        <div class="card-header">{$categoryName}</div>
                        <div class="card-body">
                            {include file="modules/profile.view.categories.tpl"}
                        </div>
                    </div>
                {/foreach}
            </div>

        <!-- Permissions Tab -->
        {if $showCurrentRoles}
            <div class="tab-pane fade" id="adm_profile_permissions_pane" role="tabpanel" aria-labelledby="adm_profile_permissions_tab">
                {include file="modules/profile.view.permissions.tpl"}
            </div>
        {/if}

        <!-- Role Memberships Tab -->
        {if $showCurrentRoles || $showExternalRoles}
            <div class="tab-pane fade" id="adm_profile_role_memberships_pane" role="tabpanel" aria-labelledby="adm_profile_role_memberships_tab">
                {if $showCurrentRoles}
                    <!-- Current Role Memberships Card -->
                    <div class="card admidio-tabbed-field-group" id="adm_profile_role_memberships_current_pane_content">
                        <div class="card-header">{$l10n->get('SYS_CURRENT_ROLE_MEMBERSHIP')}
                            {if $isAdministratorRoles}
                                <a class="btn btn-secondary float-end openPopup" id="adm_profile_role_memberships_change"
                                data-class="modal-lg" href="javascript:void(0);" data-href="{$urlEditRoles}">
                                    <i class="bi bi-person-gear me-1"></i>{$l10n->get('SYS_ROLE_MEMBERSHIPS_CHANGE')}</a>
                            {/if}
                        </div>
                        <div class="card-body">
                        </div>
                    </div>
                    <!-- Future Role Memberships Card -->
                    <div class="card admidio-tabbed-field-group" id="adm_profile_role_memberships_future_pane_content">
                        <div class="card-header">{$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}</div>
                        <div class="card-body">
                        </div>
                    </div>
                    <!-- Former Role Memberships Card -->
                    <div class="card admidio-tabbed-field-group" id="adm_profile_role_memberships_former_pane_content">
                        <div class="card-header">{$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}</div>
                        <div class="card-body">
                        </div>
                    </div>
                {/if}
                {if $showExternalRoles}
                    <!-- Other Org Role Memberships Card -->
                    <div class="card admidio-tabbed-field-group" id="adm_profile_role_memberships_other_org_pane_content">
                        <div class="card-header">
                            {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                            data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                            data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                        </div>
                        <div class="card-body">
                            {include file="modules/profile.view.other-org-memberships.tpl"}
                        </div>
                    </div>
                {/if}
            </div>
        {/if}
        <!-- User Relations Tab -->
        {if $showRelations}
            <div class="tab-pane fade" id="adm_profile_user_relations_pane" role="tabpanel" aria-labelledby="adm_profile_user_relations_tab">
                <div class="card admidio-tabbed-field-group">
                    <div class="card-header">
                        {if $isAdministratorUsers}
                            <a class="btn btn-secondary float-end" id="adm_profile_relations_new_entry" href="{$urlAssignUserRelations}">
                                <i class="bi bi-person-heart me-1"></i>{$l10n->get('SYS_CREATE_RELATIONSHIP')}</a>
                        {/if}
                    </div>
                    <div class="card-body">
                        {include file="modules/profile.view.relations.tpl"}
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

<div class="d-block d-md-none">
    <!-- Accordion Navigation -->
    <div class="accordion" id="adm_profile_accordion">
        <!-- Basic Data Accordion -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="adm_profile_basic_informations_accordion_heading">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#adm_profile_basic_informations_accordion" aria-expanded="true" aria-controls="adm_profile_basic_informations_accordion">
                    {$l10n->get('SYS_BASIC_DATA')}
                </button>
            </h2>
            <div id="adm_profile_basic_informations_accordion" class="accordion-collapse collapse show" aria-labelledby="adm_profile_basic_informations_accordion_heading" data-bs-parent="#adm_profile_accordion">
                <div class="accordion-body">
                    <div class="card admidio-accordion-field-group">
                        <div class="card-header"> {$l10n->get('SYS_PROFILE_DATA')}
                            {if isset($urlEditProfile)}
                                <a class="btn btn-secondary float-end" id="adm_profile_relations_new_entry" href="{$urlEditProfile}">
                                    <i class="bi bi-pencil-square me-1"></i>{$l10n->get('SYS_EDIT_PROFILE')}</a>
                            {/if}
                        </div>
                        <div class="card-body">
                            {include file="modules/profile.view.basic-informations.tpl"}
                        </div>
                    </div>
                    <!-- Dynamic Cards for additional Profile Data categories -->
                    {foreach $profileData as $categoryName => $category}
                        <div class="card admidio-accordion-field-group">
                            <div class="card-header">{$categoryName}</div>
                            <div class="card-body">
                                {include file="modules/profile.view.categories.tpl"}
                            </div>
                        </div>
                    {/foreach}

                    <!-- Inventory Keeper Cards -->
                    {if isset($keeperList)}
                        <div class="card admidio-accordion-field-group">
                            <div class="card-header">
                                {$keeperListHeader}
                                {if isset($urlInventoryKeeper)}
                                    <a class="admidio-icon-link float-end" href="{$urlInventoryKeeper}">
                                        <i class="bi bi-box-seam-fill" title="{$keeperListHeader}"></i>
                                    </a>
                                {/if}
                            </div>
                            <div class="card-body">
                                <p>{$l10n->get('SYS_INVENTORY_PROFILE_VIEW_KEEPER_DESC')}</p>
                                {render_table headers=$keeperList.headers rows=$keeperList.rows align=$keeperList.column_align tableId="adm_inventory_table_keeper_accordion"}
                            </div>
                        </div>
                    {/if}

                    <!-- Inventory Receiver Cards -->
                    {if isset($receiverList)}
                        <div class="card admidio-accordion-field-group">
                            <div class="card-header">
                                {$receiverListHeader}
                                {if isset($urlInventoryReceiver)}
                                    <a class="admidio-icon-link float-end" href="{$urlInventoryReceiver}">
                                        <i class="bi bi-box-seam-fill" title="{$receiverListHeader}"></i>
                                    </a>
                                {/if}
                            </div>
                            <div class="card-body">
                                <p>{$l10n->get('SYS_INVENTORY_PROFILE_VIEW_LAST_RECEIVER_DESC')}</p>
                                {render_table headers=$receiverList.headers rows=$receiverList.rows align=$receiverList.column_align tableId="adm_inventory_table_receiver_accordion"}
                            </div>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
        <!-- Permissions Accordion -->
        {if $showCurrentRoles}
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_profile_role_permissions_accordion_heading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_profile_role_permissions_accordion" aria-expanded="false" aria-controls="adm_profile_role_permissions_accordion">
                        {$l10n->get('SYS_PERMISSIONS')}
                    </button>
                </h2>
                <div id="adm_profile_role_permissions_accordion" class="accordion-collapse collapse" aria-labelledby="adm_profile_role_permissions_accordion_heading" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body">
                        {include file="modules/profile.view.permissions.tpl"}
                    </div>
                </div>
            </div>
        {/if}
        <!-- Role Memberships Accordion -->
        {if $showCurrentRoles || $showExternalRoles}
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_profile_role_memberships_accordion_heading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_profile_role_memberships_accordion" aria-expanded="false" aria-controls="adm_profile_role_memberships_accordion">
                        {$l10n->get('SYS_ROLE_MEMBERSHIPS')}
                    </button>
                </h2>
                <div id="adm_profile_role_memberships_accordion" class="accordion-collapse collapse" aria-labelledby="adm_profile_role_memberships_accordion_heading" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body">
                    {if $showCurrentRoles}
                        <!-- Current Role Memberships Card -->
                        <div class="card admidio-accordion-field-group" id="adm_profile_role_memberships_current_accordion_content">
                            <div class="card-header">{$l10n->get('SYS_CURRENT_ROLE_MEMBERSHIP')}
                                {if $isAdministratorRoles}
                                    <a class="btn btn-secondary float-end openPopup" id="adm_profile_role_memberships_change"
                                    data-class="modal-lg" href="javascript:void(0);" data-href="{$urlEditRoles}">
                                        <i class="bi bi-person-gear me-1"></i>{$l10n->get('SYS_ROLE_MEMBERSHIPS_CHANGE')}</a>
                                {/if}
                            </div>
                            <div class="card-body">
                            </div>
                        </div>
                        <!-- Future Role Memberships Card -->
                        <div class="card admidio-accordion-field-group" id="adm_profile_role_memberships_future_accordion_content">
                            <div class="card-header">{$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}</div>
                            <div class="card-body">
                            </div>
                        </div>
                        <!-- Former Role Memberships Card -->
                        <div class="card admidio-accordion-field-group" id="adm_profile_role_memberships_former_accordion_content">
                            <div class="card-header">{$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}</div>
                            <div class="card-body">
                            </div>
                        </div>
                    {/if}
                    
                    {if $showExternalRoles}
                        <!-- Other Org Role Memberships Card -->
                        <div class="card admidio-accordion-field-group" id="adm_profile_role_memberships_other_org_accordion_content">
                            <div class="card-header">
                                {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                                <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                            </div>
                            <div class="card-body">
                                {include file="modules/profile.view.other-org-memberships.tpl"}
                            </div>
                        </div>
                    {/if}
                    </div>
                </div>
            </div>
        {/if}
        <!-- User Relations Accordion -->
        {if $showRelations}
            <div class="accordion-item">
                <h2 class="accordion-header" id="adm_profile_user_relations_accordion_heading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#adm_profile_user_relations_accordion" aria-expanded="false" aria-controls="adm_profile_user_relations_accordion">
                        {$l10n->get('SYS_USER_RELATIONS')}
                    </button>
                </h2>
                <div id="adm_profile_user_relations_accordion" class="accordion-collapse collapse" aria-labelledby="adm_profile_user_relations_accordion_heading" data-bs-parent="#adm_profile_accordion">
                    <div class="accordion-body">
                        <div class="card admidio-accordion-field-group">
                            <div class="card-header">
                                {if $isAdministratorUsers}
                                    <a class="btn btn-secondary float-end" id="adm_profile_relations_new_entry" href="{$urlAssignUserRelations}">
                                        <i class="bi bi-person-heart me-1"></i>{$l10n->get('SYS_CREATE_RELATIONSHIP')}</a>
                                {/if}
                            </div>
                            <div class="card-body">
                                {include file="modules/profile.view.relations.tpl"}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</div>

{include file="sys-template-parts/system.info-create-edit.tpl"}