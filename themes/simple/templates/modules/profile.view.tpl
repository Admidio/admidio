<div id="adm_user_data_panel" class="card admidio-field-group">
    <div class="card-header">{$l10n->get('SYS_BASIC_DATA')}</div>
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

{foreach $profileData as $categoryName => $category}
    <div class="card admidio-field-group">
        <div class="card-header">{$categoryName}</div>
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
{/foreach}

{if $showCurrentRoles}
    <div class="card admidio-field-group" id="adm_profile_authorizations_box">
        <div class="card-header">{$l10n->get('SYS_PERMISSIONS')}</div>
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
    <div class="card admidio-field-group" id="adm_profile_roles_box">
        <div class="card-header">{$l10n->get('SYS_ROLE_MEMBERSHIPS')}
            {if $userRightAssignRoles}
                <a class="btn btn-secondary float-end openPopup" id="profile_role_memberships_change"
                   data-class="modal-lg" href="javascript:void(0);" data-href="{$urlEditRoles}">
                    <i class="bi bi-pencil-square me-1"></i>{$l10n->get('SYS_EDIT')}</a>
            {/if}
        </div>
        <div class="card-body">
        </div>
    </div>
    <div class="card admidio-field-group" id="adm_profile_future_roles_box" style="display: none;">
        <div class="card-header">{$l10n->get('SYS_FUTURE_ROLE_MEMBERSHIP')}</div>
        <div class="card-body">
        </div>
    </div>
{/if}

{if $showCurrentRoles}
    <div class="card admidio-field-group" id="adm_profile_former_roles_box" style="display: none;">
        <div class="card-header">{$l10n->get('SYS_FORMER_ROLE_MEMBERSHIP')}</div>
        <div class="card-body">
        </div>
    </div>
{/if}

{if $showExternalRoles}
    <div class="card admidio-field-group" id="profile_other_orga_roles_box">
        <div class="card-header">
            {$l10n->get('SYS_ROLE_MEMBERSHIP_OTHER_ORG')}
            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
               data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
               data-bs-content="{$l10n->get('SYS_VIEW_ROLES_OTHER_ORGAS')}"></i>
        </div>
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
{/if}

{if $showUserRelations}
    <div class="card admidio-field-group" id="profile_user_relations_box">
        <div class="card-header">{$l10n->get('SYS_USER_RELATIONS')}
            {if $userRightEditUser}
                <a class="admidio-icon-link float-end" id="profile_relations_new_entry" href="{$urlAssignUserRelations}">
                    <i class="bi bi-plus-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_CREATE_RELATIONSHIP')}"></i></a>
            {/if}
        </div>
        <div class="card-body" id="profile_user_relations_box_body">
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
                            {if $userRightEditUser}
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
                        <div id="relation_info_{$userRelation.uuid}_Content" style="display: none;">
                            {include file="sys-template-parts/system.info-create-edit.tpl" userCreatedName=$userRelation.userCreatedName userCreatedTimestamp=$userRelation.userCreatedTimestamp lastUserEditedName=$userRelation.lastUserEditedName lastUserEditedTimestamp=$userRelation.lastUserEditedTimestamp}
                        </div>
                    {/if}
                </li>
            {/foreach}
            </ul>
        </div>
    </div>
{/if}

{include file="sys-template-parts/system.info-create-edit.tpl"}
