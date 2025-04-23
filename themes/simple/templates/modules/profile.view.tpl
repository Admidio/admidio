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
    {/if}
    {if $showCurrentRoles || $showExternalRoles}
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="adm_profile_role_memberships_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships" type="button" role="tab" aria-controls="adm_profile_role_memberships" aria-selected="false">
                {$l10n->get('SYS_ROLE_MEMBERSHIPS')}
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
                <div class="row">
                    <div class="col-sm-8">
                        {$showName = true}
                        {$showUsername = true}
                        {$showAddress = true}
                        {foreach $masterData as $profileField}
                            {if {$profileField.id} == 'LAST_NAME' || {$profileField.id} == 'FIRST_NAME' || {$profileField.id} == 'GENDER'}
                                {if $showName}
                                    {$showName = false}
                                    <div class="admidio-form-group row mb-3">
                                        <div class="col-sm-3">
                                            {$l10n->get('SYS_NAME')}
                                        </div>
                                        <div class="col-sm-9">
                                            <strong>
                                            {$masterData.FIRST_NAME.value} {$masterData.LAST_NAME.value}
                                            {if isset($masterData.GENDER)}
                                                {$masterData.GENDER.value}
                                            {/if}
                                            </strong>
                                        </div>
                                    </div>
                                {/if}
                            {elseif {$profileField.id} == 'usr_login_name' || {$profileField.id} == 'usr_actual_login'}
                                {if $showUsername}
                                    {$showUsername = false}
                                    <div class="admidio-form-group row mb-3">
                                        <div class="col-sm-3">
                                            {$profileField.label}
                                        </div>
                                        <div class="col-sm-9">
                                            <strong>{$profileField.value}</strong>
                                            {if isset($masterData.usr_actual_login)}
                                                <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                                   data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                                   data-bs-content="{$lastLoginInfo}"></i>
                                            {/if}
                                        </div>
                                    </div>
                                {/if}
                            {elseif {$profileField.id} == 'STREET' || {$profileField.id} == 'POSTCODE' || {$profileField.id} == 'CITY' || {$profileField.id} == 'COUNTRY'}
                                    {if $showAddress}
                                        {$showAddress = false}
                                        <div class="admidio-form-group row mb-3">
                                            <div class="col-sm-3">
                                                {$l10n->get('SYS_ADDRESS')}
                                            </div>
                                            <div class="col-sm-9"><strong>
                                                {$masterData.STREET.value}<br />
                                                {$masterData.POSTCODE.value}  {$masterData.CITY.value}<br />
                                                {$masterData.COUNTRY.value}</strong>
                                                {if isset($urlMapAddress)}
                                                    <br />
                                                    <a class="icon-link" href="{$urlMapAddress}" target="_blank" title="{$l10n->get('SYS_MAP_LINK_HOME_DESC')}">
                                                        <i class="bi bi-pin-map-fill"></i>{$l10n->get('SYS_MAP')}</a>
                                                    {if isset($urlMapRoute)}
                                                        &nbsp;-&nbsp;
                                                        <a class="icon-link" href="{$urlMapRoute}" target="_blank" title="{$l10n->get('SYS_MAP_LINK_ROUTE_DESC')}">
                                                            <i class="bi bi-sign-turn-right-fill"></i>{$l10n->get('SYS_SHOW_ROUTE')}</a>
                                                    {/if}
                                                {/if}
                                            </div>
                                        </div>
                                    {/if}
                            {else}
                                <div class="admidio-form-group row mb-3">
                                    <div class="col-sm-3">
                                        {if strlen($profileField.icon) > 0}
                                            {$profileField.icon}
                                        {/if}
                                        {$profileField.label}
                                    </div>
                                    <div class="col-sm-9">
                                        <strong>{$profileField.value}</strong>
                                    </div>
                                </div>
                            {/if}
                        {/foreach}
                    </div>
                    <div class="col-sm-4 text-end">
                        <img id="adm_profile_photo" class="rounded" src="{$urlProfilePhoto}" alt="{$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}" />
                        {if isset($urlProfilePhotoUpload)}
                            <ul class="list-unstyled">
                                <li><a class="icon-link" href="{$urlProfilePhotoUpload}">
                                        <i class="bi bi-upload"></i>{$l10n->get('SYS_UPLOAD_PROFILE_PICTURE')}</a></li>
                                {if isset($urlProfilePhotoDelete)}
                                    <li><a id="adm_button_delete_photo" class="icon-link admidio-messagebox" href="javascript:void(0);"
                                           data-buttons="yes-no" data-message="{$l10n->get('SYS_WANT_DELETE_PHOTO')}"
                                           data-href="{$urlProfilePhotoDelete}"><i class="bi bi-trash"></i>{$l10n->get('SYS_DELETE_PROFILE_PICTURE')}</a></li>
                                {/if}
                            </ul>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Tabs for Profile Data -->
    {foreach $profileData as $categoryName => $category}
        <div class="tab-pane fade" id="adm_profile_{$categoryName|escape|replace:' ':'_'}" role="tabpanel" aria-labelledby="adm_profile_{$categoryName|escape|replace:' ':'_'}_tab">
            <div class="card admidio-tabbed-field-group">
                <div class="card-body">
                    {$fieldGroupOpened = false}
                    {foreach $category as $profileField}
                            {if $fieldGroupOpened eq false}
                                    <div class="admidio-form-group row mb-3">
                            {/if}
                            <div class="col-sm-2">
                                    {if strlen($profileField.icon) > 0}
                                            {$profileField.icon}
                                    {/if}
                                    {$profileField.label}
                            </div>
                            <div class="col-sm-4">
                                    <strong>{$profileField.value}</strong>
                            </div>
                            {if $fieldGroupOpened eq false}
                                    {$fieldGroupOpened = true}
                            {else}
                                    {$fieldGroupOpened = false}
                                    </div>
                            {/if}
                    {/foreach}
                    {if $fieldGroupOpened}
                            </div>
                    {/if}
                </div>
            </div>
        </div>
    {/foreach}


    <!-- Permissions Tab -->
    <div class="tab-pane fade" id="adm_profile_permissions" role="tabpanel" aria-labelledby="adm_profile_permissions_tab">
        <div class="card admidio-tabbed-field-group">
            <div class="card-body">
                <div class="row">
                    {if count($userRights) > 0}
                        {foreach $userRights as $userRight}
                            <div class="col-sm-6 col-md-4 admidio-profile-user-right" data-bs-toggle="popover" data-bs-html="true"
                                data-bs-trigger="hover click" data-bs-placement="auto" data-bs-content="{$l10n->get('SYS_ASSIGNED_BY_ROLES')}:
                                <strong>{$userRight.roles}</strong>"><i class="bi {$userRight.icon}"></i>{$userRight.right}</div>
                        {/foreach}
                    {else}
                        <div class="col-sm-12">{$l10n->get('SYS_NO_PERMISSIONS_ASSIGNED')}</div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- Role Memberships Tab -->
    <div class="tab-pane fade" id="adm_profile_role_memberships" role="tabpanel" aria-labelledby="adm_profile_role_memberships_tab">
        <div class="card admidio-tabbed-field-group">
            <div class="card-header">
                <ul class="nav nav-tabs profile-card-header-tabs" id="adm_profile_role_memberships_tabs">
                {if $showCurrentRoles}
                    <li class="nav-item">
                        <button class="nav-link active" id="adm_profile_role_memberships_current_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_current" type="button" role="tab" aria-controls="adm_profile_role_memberships_current" aria-current="false">
                            {$l10n->get('SYS_CURRENT_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="adm_profile_role_memberships_future_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_future" type="button" role="tab" aria-controls="adm_profile_role_memberships_future"aria-current="false">
                            {$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="adm_profile_role_memberships_former_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_former" type="button" role="tab" aria-controls="adm_profile_role_memberships_former" aria-current="false">
                            {$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}
                        </button>
                    </li>
                    {if $showExternalRoles}
                        <li class="nav-item">
                            <button class="nav-link" id="adm_profile_role_memberships_other_org_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_other_org" type="button" role="tab" aria-controls="adm_profile_role_memberships_other_org" aria-current="false">
                                {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}                               
                                <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                            </button>
                        </li>
                    {/if}
                {else if $showExternalRoles}
                    <li class="nav-item">
                        <button class="nav-link active" id="adm_profile_role_memberships_other_org_tab" data-bs-toggle="tab" data-bs-target="#adm_profile_role_memberships_other_org" type="button" role="tab" aria-controls="adm_profile_role_memberships_other_org" aria-current="true">
                            {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
                            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                            data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                            data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
                        </button>
                    </li>
                {/if}
            </div>
            <div class="tab-content" id="adm_profile_role_memberships_tabs_content">
                <div class="tab-pane fade  show active" id="adm_profile_role_memberships_current" role="tabpanel" aria-labelledby="adm_profile_role_memberships_current_tab">
                    <div class="card-body">
                    </div>
                </div>
                
                <div class="tab-pane fade" id="adm_profile_role_memberships_future" role="tabpanel" aria-labelledby="adm_profile_role_memberships_future_tab">
                    <div class="card-body">
                    </div>
                </div>

                <div class="tab-pane fade" id="adm_profile_role_memberships_former" role="tabpanel" aria-labelledby="adm_profile_role_memberships_former_tab">
                    <div class="card-body">
                    </div>
                </div>

                <div class="tab-pane fade" id="adm_profile_role_memberships_other_org" role="tabpanel" aria-labelledby="adm_profile_role_memberships_other_org_tab">
                    <div class="card-body" id="profile_other_orga_roles_box_body">
                        <ul class="list-group admidio-list-roles-assign">
                            {foreach $externalRoles as $externalRole}
                                <li class="list-group-item">
                                    <span>{$externalRole.organization} - {$externalRole.category} - {$externalRole.role}
                                        {if $externalRole.leader}
                                            &nbsp;-&nbsp;{$l10n->get('SYS_LEADER')}
                                        {/if}
                                    </span>
                                    <span class="float-end">{$externalRole.timestamp}</span>
                                </li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="adm_profile_user_relations" role="tabpanel" aria-labelledby="adm_profile_user_relations_tab">
        <div class="card admidio-tabbed-field-group">
            <div class="card-body">
                <ul class="list-group admidio-list-roles-assign">
                {foreach $userRelations as $userRelation}
                    <li id="row_ure_{$userRelation.uuid}" class="list-group-item">
                        <div>
                            <span>{$userRelation.relationName} - <a href="{$userRelation.urlUserProfile}">{$userRelation.userFirstName} {$userRelation.userLastName}</a>
                                {if isset($userRelation.urlUserEdit)}
                                    <a class="admidio-icon-link" href="{$userRelation.urlUserEdit}"><i
                                        class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT_USER_IN_RELATION')}"></i></a>
                                {/if}
                            </span>
                            <span class="float-end text-end">
                                {if $isAdministratorUsers}
                                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                                    data-message="{$l10n->get('SYS_DELETE_ENTRY', array({$userRelation.relationName}))}" data-href="{$userRelation.urlRelationDelete}"><i
                                        class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_CANCEL_RELATIONSHIP')}"></i></a>
                                {/if}
                                {if $showRelationsCreateEdit}
                                    <a class="admidio-icon-link admidio-create-edit-info" id="relation_info_{$userRelation.uuid}" href="javascript:void(0)"><i
                                        class="bi bi-info-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_INFORMATIONS')}"></i></a>
                                {/if}
                            </span>
                        </div>
                        {if $showRelationsCreateEdit}
                            <div id="relation_info_{$userRelation.uuid}_content" style="display: none;">
                                {include file="sys-template-parts/system.info-create-edit.tpl" userCreatedName=$userRelation.userCreatedName userCreatedTimestamp=$userRelation.userCreatedTimestamp lastUserEditedName=$userRelation.lastUserEditedName lastUserEditedTimestamp=$userRelation.lastUserEditedTimestamp}
                            </div>
                        {/if}
                    </li>
                {/foreach}
                </ul>
            </div>
        </div>
    </div>
</div>

{include file="sys-template-parts/system.info-create-edit.tpl"}
