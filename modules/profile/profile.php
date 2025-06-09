<?php
/**
 ***********************************************************************************************
 * Show user profile
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * user_uuid : Show profile of the user with this uuid. If this parameter is not set then
 *             the profile of the current user will be shown.
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Roles\Entity\Role;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRelation;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Changelog\Service\ChangelogService;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\UI\Component\DataTables;
use Admidio\Inventory\ValueObjects\ItemsData;

try {
    require_once(__DIR__ . '/../../system/common.php');
    require_once(__DIR__ . '/roles_functions.php');
    require(__DIR__ . '/../../system/login_valid.php');

    // Initialize and check the parameters
    $getUserUuid = admFuncVariableIsValid($_GET, 'user_uuid', 'uuid', array('defaultValue' => $gCurrentUser->getValue('usr_uuid')));

    // create user object
    $user = new User($gDb, $gProfileFields);
    $user->readDataByUuid($getUserUuid);

    // check if right to view profile exists
    if (!$gCurrentUser->hasRightViewProfile($user)) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    /**
     * this function returns the html code for a field with description, formatting the content correctly
     * @param string $fieldNameIntern
     * @param User $user
     * @return false|array<string,string>
     */
    function getFieldCode(string $fieldNameIntern, User $user)
    {
        global $gCurrentUser, $gProfileFields, $gL10n, $gSettingsManager;

        if (!$gCurrentUser->allowedViewProfileField($user, $fieldNameIntern)) {
            return false;
        }

        $html = array('label' => '', 'value' => '');

        // get value of field in html format
        $value = $user->getValue($fieldNameIntern, 'html');

        // if birthday then show age
        if ($gProfileFields->getProperty($fieldNameIntern, 'usf_name_intern') === 'BIRTHDAY' && $value !== '') {
            $birthday = DateTime::createFromFormat('Y-m-d', $user->getValue($fieldNameIntern, 'Y-m-d'));
            $now = new DateTime('now');
            $value = $value . '&nbsp;&nbsp;&nbsp;(' . $birthday->diff($now)->y . ' ' . $gL10n->get('SYS_YEARS') . ')';
        } elseif (strlen($gProfileFields->getProperty($fieldNameIntern, 'usf_icon')) > 0) {
            $value = $gProfileFields->getProperty($fieldNameIntern, 'usf_icon') . $value;
        }

        // show html of field, if user has a value for that field, or it's a checkbox field
        if (strlen($user->getValue($fieldNameIntern)) > 0 || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'CHECKBOX') {
            $html['label'] = $gProfileFields->getProperty($fieldNameIntern, 'usf_name');
            $html['value'] = $value;
        }

        return $html;
    }

    $userId = $user->getValue('usr_id');

    // set headline
    if ($userId === $gCurrentUserId) {
        $headline = $gL10n->get('SYS_MY_PROFILE');
    } else {
        $headline = $gL10n->get('SYS_PROFILE_FROM', array($user->getValue('FIRST_NAME'), $user->getValue('LAST_NAME')));
    }

    // if user UUID was not set and own profile should be shown then initialize navigation
    if (!isset($_GET['user_uuid'])) {
        $gNavigation->addStartUrl(CURRENT_URL, $headline, 'bi-person-fill');
    } else {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    // create html page object
    $page = PagePresenter::withHtmlIDAndHeadline('admidio-profile', $headline);
    $page->addTemplateFile('modules/profile.view.tpl');
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/zxcvbn/dist/zxcvbn.js');
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.js');

    $page->addJavascript('
        var profileJS = new ProfileJS(gRootPath);
        profileJS.userUuid                = "' . $getUserUuid . '";

        function callbackProfilePhoto() {
            var imgSrc = $("#adm_profile_photo").attr("src");
            var timestamp = new Date().getTime();
            $("#adm_button_delete_photo").hide();
            $("#adm_profile_photo").attr("src", imgSrc + "&" + timestamp);
        }

        function callbackRoles() {
            if (profileJS) {
                profileJS.formerRoleCount++;
                profileJS.reloadFormerRoleMemberships();
            }
        }

        function callbackFormerRoles() {
            if (profileJS) {
                profileJS.formerRoleCount--;
                if (profileJS.formerRoleCount === 0) {
                    /* Tabs */
                    $("#adm_profile_role_memberships_former_pane_content").fadeOut("slow");
                    /* Accordions */
                    $("#adm_profile_role_memberships_former_accordion_content").fadeOut("slow");
                }
            }
        }

        function callbackFutureRoles() {
            if (profileJS) {
                profileJS.futureRoleCount--;
                if (profileJS.futureRoleCount === 0) {
                    /* Tabs */
                    $("#adm_profile_role_memberships_future_pane_content").fadeOut("slow");
                    /* Accordions */
                    $("#adm_profile_role_memberships_future_accordion_content").fadeOut("slow");
                }
            }
        }

        function formSubmitEvent(rolesAreaId = "") {
            $(rolesAreaId + " .admidio-form-membership-period").submit(function(event) {
                var memberUuid = $(this).attr("data-admidio");
                var formAlert  = $("#adm_membership_period_form_" + memberUuid + " .form-alert");

                event.preventDefault(); // avoid to execute the actual submit of the form.
                formAlert.hide();

                $.post({
                    url: $(this).attr("action"),
                    data: $(this).serialize(),
                    success: function(data)
                    {
                        if (data === "success") {
                            formAlert.attr("class", "alert alert-success form-alert");
                            formAlert.html("<i class=\"bi bi-check-lg\"></i><strong>' . $gL10n->get('SYS_SAVE_DATA') . '</strong>");
                            formAlert.fadeIn("slow");
                            formAlert.animate({opacity: 1.0}, 5000);
                            formAlert.fadeOut("slow");

                            var membershipPeriod = $("#adm_membership_period_" + memberUuid);
                            membershipPeriod.animate({opacity: 1.0}, 5000);
                            membershipPeriod.fadeOut("slow");

                            profileJS.reloadRoleMemberships();
                            profileJS.reloadFormerRoleMemberships();
                            profileJS.reloadFutureRoleMemberships();
                            formSubmitEvent();
                        } else {
                            formAlert.attr("class", "alert alert-danger form-alert");
                            formAlert.fadeIn();
                            formAlert.html("<i class=\"bi bi-exclamation-circle-fill\"></i>" + data);
                        }
                    }
                 });
                return false;
            });
        }
    ');
    $page->addJavascript('
        $(document).on("click", ".admidio-create-edit-info", function() {
            $("#" + $(this).attr("id") + "_Content").toggle("fast");
        });

        profileJS.reloadRoleMemberships();
        profileJS.reloadFormerRoleMemberships();
        profileJS.reloadFutureRoleMemberships();

        $("#menu_item_profile_tfa").attr("href", "javascript:void(0);");
        $("#menu_item_profile_tfa").attr("data-href", "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/two_factor_authentication.php', array('user_uuid' => $getUserUuid)) . '");
        $("#menu_item_profile_tfa").attr("class", "nav-link btn btn-primary openPopup");

        $("body").on("hidden.bs.modal", ".modal", function() {
            $(this).removeData("bs.modal");
            profileJS.reloadRoleMemberships();
            profileJS.reloadFormerRoleMemberships();
            profileJS.reloadFutureRoleMemberships();
        });

        formSubmitEvent();',
        true
    );

    // show link to TFA settings if Two Factor authentication activated in global settings AND
    // - user is current user OR
    // - user is administrator and user is member of current organization and user has a login name
    if (
        $gSettingsManager->getBool('two_factor_authentication_enabled') &&
        ($userId === $gCurrentUserId
            || ($gCurrentUser->isAdministrator() && isMember($userId) && strlen($user->getValue('usr_login_name')) > 0))
    ) {
        $page->addPageFunctionsMenuItem(
            'menu_item_profile_tfa',
            $gL10n->get('SYS_TFA'),
            SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/two_factor_authentication.php', array('user_uuid' => $getUserUuid)),
            'bi-shield-lock-fill'
        );
    }

    // show link to view profile field change history
    ChangelogService::displayHistoryButton($page, 'profile', 'users,user_data,user_relations,members', $gCurrentUser->hasRightEditProfile($user), array('uuid' => $getUserUuid));

    // show link to export the profile as vCard
    $page->addPageFunctionsMenuItem(
        'menu_item_profile_vcard',
        $gL10n->get('SYS_EXPORT_VCARD'),
        SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_function.php', array('mode' => 'export', 'user_uuid' => $getUserUuid)),
        'bi-download'
    );


    // *******************************************************************************
    // User data block
    // *******************************************************************************

    $bNameOutput = false;    // Flag whether the address has already been displayed
    $bAddressOutput = false;    // Flag whether the address has already been displayed
    $masterData = array();
    $profileData = array();
    $categoryData = array();
    $category = '';

    // Loop over all fields of the master data

    foreach ($gProfileFields->getProfileFields() as $field) {
        // Display only fields of the basic data
        if ($gCurrentUser->allowedViewProfileField($user, $field->getValue('usf_name_intern'))) {
            // if profile_show_empty_fields is set to 0 then skip empty profile fields
            if (!$gSettingsManager->getBool('profile_show_empty_fields') && $user->getValue($field->getValue('usf_name_intern'), 'html') === '') {
                continue;
            }

            if ($field->getValue('cat_name_intern') === 'BASIC_DATA') {
                $masterData[$field->getValue('usf_name_intern')] = array(
                    'id' => $field->getValue('usf_name_intern'),
                    'label' => $field->getValue('usf_name'),
                    'icon' => $field->getValue('usf_icon'),
                    'value' => $user->getValue($field->getValue('usf_name_intern'), 'html')
                );

                if ($field->getValue('usf_name_intern') === 'LAST_NAME') {
                    if ($user->getValue('usr_login_name') === '') {
                        $value = $gL10n->get('SYS_NOT_REGISTERED');
                    } elseif ($userId !== $gCurrentUserId && $gSettingsManager->getBool('enable_pm_module')) {
                        $value = '<a class="icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('msg_type' => 'PM', 'user_uuid' => $getUserUuid)) . '" title="' . $gL10n->get('SYS_WRITE_PM') . '">' .
                            '<i class="bi bi-chat-left-fill"></i>' . $user->getValue('usr_login_name') . '</a>';
                    } else {
                        $value = $user->getValue('usr_login_name');
                    }
                    $masterData['usr_login_name'] = array('id' => 'usr_login_name', 'label' => $gL10n->get('SYS_USERNAME'), 'value' => $value);

                    // pseudo password field
                    $value = '';
                    if ($userId === $gCurrentUserId) {
                            $value = '<a class="btn btn-secondary openPopup" href="javascript:void(0)" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/password.php', array('user_uuid' => $getUserUuid)) . '">' .
                            '<i class="bi bi-key-fill"></i>' . $gL10n->get('SYS_CHANGE_PASSWORD') . '</a>';
                    } elseif ($gCurrentUser->isAdministrator() && isMember($userId) &&  strlen($user->getValue('usr_login_name')) > 0) {
                        // Administrators can change or send password if login is configured and user is member of current organization
                        if (strlen($user->getValue('EMAIL')) > 0 && $gSettingsManager->getBool('system_notifications_enabled')) {
                            $value = '<a class="btn btn-secondary admidio-messagebox" href="javascript:void(0)" data-buttons="yes-no"
                                data-message="' . $gL10n->get('SYS_SEND_NEW_LOGIN', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))) . '"
                                data-href="callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/contacts/contacts_function.php', array('mode' => 'send_login', 'user_uuid' => $getUserUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')">' .
                            '<i class="bi bi-key-fill"></i>' . $gL10n->get('ORG_SEND_NEW_PASSWORD') . '</a>';
                        } else {
                            // if user has no email or send email is disabled then administrator could set a new password       
                            $value = '<a class="btn btn-secondary openPopup" href="javascript:void(0)" data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/password.php', array('user_uuid' => $getUserUuid)) . '">' .
                            '<i class="bi bi-key-fill"></i>' . $gL10n->get('SYS_CHANGE_PASSWORD') . '</a>';
                        }
                    }
                    if ($value !== '') {
                        $masterData['usr_password'] = array('id' => 'usr_password', 'label' => $gL10n->get('SYS_PASSWORD'), 'icon' => '', 'value' => $value);
                    }

                    if (
                        !empty($user->getValue('usr_actual_login'))
                        && ($userId === $gCurrentUserId || $gCurrentUser->isAdministrator())
                    ) {
                        $masterData['usr_actual_login'] = array('id' => 'usr_actual_login', 'label' => '', 'value' => $user->getValue('usr_actual_login'));
                    }
                }
            } else {
                if ($category !== $field->getValue('cat_name')) {
                    if ($category !== '') {
                        $profileData[$category] = $categoryData;
                    }
                    $categoryData = array();
                    $category = $field->getValue('cat_name');
                }
                $categoryData[$field->getValue('usf_name_intern')] = array(
                    'id' => $field->getValue('usf_name_intern'),
                    'label' => $field->getValue('usf_name'),
                    'icon' => $field->getValue('usf_icon'),
                    'value' => $user->getValue($field->getValue('usf_name_intern'), 'html')
                );
            }
        }
    }

    if (count($categoryData) > 0) {
        $profileData[$category] = $categoryData;
    }

    // add missing address fields to masterData so that there is less logic in template necessary
    if (isset($masterData['STREET']) || isset($masterData['POSTCODE']) || isset($masterData['CITY']) || isset($masterData['COUNTRY'])) {
        if (!isset($masterData['STREET'])) {
            $masterData['STREET'] = array('id' => 'ADDRESS', 'label' => '', 'value' => '');
        }
        if (!isset($masterData['POSTCODE'])) {
            $masterData['POSTCODE'] = array('id' => 'POSTCODE', 'label' => '', 'value' => '');
        }
        if (!isset($masterData['CITY'])) {
            $masterData['CITY'] = array('id' => 'CITY', 'label' => '', 'value' => '');
        }
        if (!isset($masterData['COUNTRY'])) {
            $masterData['COUNTRY'] = array('id' => 'COUNTRY', 'label' => '', 'value' => '');
        }

        // set urls for map and route
        $destination = array_filter(array(
            $masterData['STREET']['value'],
            $masterData['POSTCODE']['value'],
            $masterData['CITY']['value'],
            $masterData['COUNTRY']['value']
        ));
        $origin = array_filter(array(
            $gCurrentUser->getValue('STREET'),
            $gCurrentUser->getValue('POSTCODE'),
            $gCurrentUser->getValue('CITY'),
            $gCurrentUser->getValue('COUNTRY')
        ));

        $page->assignSmartyVariable('urlMapAddress', SecurityUtils::encodeUrl('https://www.google.com/maps/search/', array('api' => 1, 'query' => implode(',', $destination))));
        if ($userId !== $gCurrentUserId) {
            $page->assignSmartyVariable('urlMapRoute', SecurityUtils::encodeUrl('https://www.google.com/maps/dir/', array('api' => 1, 'origin' => implode(',', $origin), 'destination' => implode(',', $destination))));
        }
    }

    $page->assignSmartyVariable('showCurrentRoles', $gSettingsManager->getBool('profile_show_roles'));
    $page->assignSmartyVariable('showFormerRoles', $gSettingsManager->getBool('profile_show_former_roles'));
    $page->assignSmartyVariable('isAdministratorRoles', $gCurrentUser->isAdministratorRoles());
    $page->assignSmartyVariable('isAdministratorUsers', $gCurrentUser->isAdministratorUsers());
    $page->assignSmartyVariable('masterData', $masterData);
    $page->assignSmartyVariable('profileData', $profileData);
    $page->assignSmartyVariable('lastLoginInfo', $gL10n->get('SYS_LAST_LOGIN_ON', array($user->getValue('usr_actual_login', $gSettingsManager->getString('system_date')), $user->getValue('usr_actual_login', $gSettingsManager->getString('system_time')))));
    $page->assignSmartyVariable('urlProfilePhoto', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $getUserUuid, 'timestamp' => $user->getValue('usr_timestamp_change', 'Y-m-d-H-i-s'))));
    // Only authorized users are allowed to edit the profile and the profile photo
    if ($gCurrentUser->hasRightEditProfile($user)) {
        $page->assignSmartyVariable('urlEditProfile', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('user_uuid' => $user->getValue('usr_uuid'))));
        $page->assignSmartyVariable('urlProfilePhotoUpload', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('user_uuid' => $getUserUuid)));
        // the image can only be deleted if corresponding rights exist
        if (
            ((string) $user->getValue('usr_photo') !== '' && (int) $gSettingsManager->get('profile_photo_storage') === 0)
            || is_file(ADMIDIO_PATH . FOLDER_DATA . '/user_profile_photos/' . $userId . '.jpg') && (int) $gSettingsManager->get('profile_photo_storage') === 1
        ) {
            $page->assignSmartyVariable('urlProfilePhotoDelete', 'callUrlHideElement(\'no_element\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_edit.php', array('mode' => 'delete', 'user_uuid' => $getUserUuid)) . '\', \'' . $gCurrentSession->getCsrfToken() . '\', \'callbackProfilePhoto\')');
        }
    }

    if ($gSettingsManager->getInt('inventory_module_enabled') > 0 && $gSettingsManager->getBool('inventory_profile_view_enabled')) {
        // ******************************************************************************
        // Block with inventory items (optimized)
        // ******************************************************************************
        $itemsKeeper = new ItemsData($gDb, $gCurrentOrgId);
        $itemsReceiver = new ItemsData($gDb, $gCurrentOrgId);
        
        // Read items by user
        $itemsKeeper->readItemsByUser($user->getValue('usr_id'), array('KEEPER'));
        $itemsReceiver->readItemsByUser($user->getValue('usr_id'), array('LAST_RECEIVER'));
        
        // Determine creation mode based on available items
        $creationMode = 'none';
        if (!empty($itemsKeeper->getItems()) && (empty($itemsReceiver->getItems()) || $gSettingsManager->GetBool('inventory_items_disable_lending'))) {
            $creationMode = 'keeper';
        } elseif (empty($itemsKeeper->getItems()) && (!empty($itemsReceiver->getItems()) && !$gSettingsManager->GetBool('inventory_items_disable_lending'))) {
            $creationMode = 'receiver';
        } elseif (!empty($itemsKeeper->getItems()) && (!empty($itemsReceiver->getItems()) && !$gSettingsManager->GetBool('inventory_items_disable_lending'))) {
            $creationMode = 'both';
        }
        
        // Helper function to set up DataTables
        function setupDataTable($page, $tableId, $templateData) 
        {
            // create DataTable objects for tabs and accordions
            foreach (['_tab','_accordion'] as $suffix) {
                $dt = new DataTables($page, $tableId . $suffix);
                $headerCount = count($templateData['headers']);
                $dt->disableColumnsSort(array($headerCount));
                $dt->setColumnsNotHideResponsive(array($headerCount));
                $dt->createJavascript(count($templateData['rows']), $headerCount);
                $dt->setColumnAlignByArray($templateData['column_align']);
                $dt->setRowsPerPage(10);
            }
        }
        
        $inventoryPage = new InventoryPresenter();
        switch($creationMode) {
            case 'keeper':
                $templateData = $inventoryPage->prepareDataProfile($itemsKeeper, 'KEEPER');
                setupDataTable($page, 'adm_inventory_table_keeper', $templateData);
            
                $page->assignSmartyVariable('keeperList', $templateData);
                $page->assignSmartyVariable('keeperListHeader', $gL10n->get('SYS_INVENTORY') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $itemsKeeper->getProperty('KEEPER', 'inf_name') . ')');
                if ($gSettingsManager->getInt('inventory_module_enabled') !== 3  || ($gSettingsManager->getInt('inventory_module_enabled') === 3 && $gCurrentUser->isAdministratorInventory())) {
                    $page->assignSmartyVariable('urlInventoryKeeper', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('items_filter' => 2, 'items_filter_keeper' => $user->getValue('usr_id'))));     
                }
                break;
        
            case 'receiver':
                $templateData = $inventoryPage->prepareDataProfile($itemsReceiver, 'LAST_RECEIVER');
                setupDataTable($page, 'adm_inventory_table_receiver', $templateData);
            
                $page->assignSmartyVariable('receiverList', $templateData);
                $page->assignSmartyVariable('receiverListHeader', $gL10n->get('SYS_INVENTORY') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $itemsReceiver->getProperty('LAST_RECEIVER', 'inf_name') . ')');
                if ($gSettingsManager->getInt('inventory_module_enabled') !== 3  || ($gSettingsManager->getInt('inventory_module_enabled') === 3 && $gCurrentUser->isAdministratorInventory())) {
                    $page->assignSmartyVariable('urlInventoryReceiver', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('items_filter' => 2, 'items_filter_keeper' => $user->getValue('usr_id'))));
                }
                break;
        
            case 'both':
                $templateDataKeeper = $inventoryPage->prepareDataProfile($itemsKeeper, 'KEEPER');
                $templateDataReceiver = $inventoryPage->prepareDataProfile($itemsReceiver, 'LAST_RECEIVER');
            
                setupDataTable($page, 'adm_inventory_table_keeper', $templateDataKeeper);
                setupDataTable($page, 'adm_inventory_table_receiver', $templateDataReceiver);
            
                $page->assignSmartyVariable('keeperList', $templateDataKeeper);
                $page->assignSmartyVariable('keeperListHeader', $gL10n->get('SYS_INVENTORY') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $itemsKeeper->getProperty('KEEPER', 'inf_name') . ')');
                if ($gSettingsManager->getInt('inventory_module_enabled') !== 3  || ($gSettingsManager->getInt('inventory_module_enabled') === 3 && $gCurrentUser->isAdministratorInventory())) {
                    $page->assignSmartyVariable('urlInventoryKeeper', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('items_filter' => 2, 'items_filter_keeper' => $user->getValue('usr_id'))));
                }  
            
                $page->assignSmartyVariable('receiverList', $templateDataReceiver);
                $page->assignSmartyVariable('receiverListHeader', $gL10n->get('SYS_INVENTORY') . ' (' . $gL10n->get('SYS_VIEW') . ': ' . $itemsReceiver->getProperty('LAST_RECEIVER', 'inf_name') . ')');
                if ($gSettingsManager->getInt('inventory_module_enabled') !== 3  || ($gSettingsManager->getInt('inventory_module_enabled') === 3 && $gCurrentUser->isAdministratorInventory())) {
                    $page->assignSmartyVariable('urlInventoryReceiver', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/inventory.php', array('items_filter' => 2, 'items_filter_keeper' => $user->getValue('usr_id'))));
                }
                break;
        
            default:
                break;
        }
        $page->assignSmartyVariable('showInventoryOnProfile', $gSettingsManager->getBool('inventory_profile_view_enabled'));
    }

    if ($gSettingsManager->getBool('profile_show_roles')) {
        // *******************************************************************************
        // Authorizations block
        // *******************************************************************************

        // Array with all permissions
        $rolesRights = array(
            'rol_all_lists_view',
            'rol_announcements',
            'rol_approve_users',
            'rol_assign_roles',
            'rol_events',
            'rol_documents_files',
            'rol_inventory_admin',
            'rol_edit_user',
            'rol_forum_admin',
            'rol_mail_to_all',
            'rol_photo',
            'rol_profile',
            'rol_weblinks'
        );

        $rightsOrigin = array();
        $userRightsArray = array();

        // Abfragen der aktiven Rollen mit Berechtigung und Schreiben in ein Array
        foreach ($rolesRights as $rolesRightsDbName) {
            $sql = 'SELECT rol_name
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_ROLES . '
                    ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE rol_valid  = true
                   AND mem_begin <= ? -- DATE_NOW
                   AND mem_end    > ? -- DATE_NOW
                   AND mem_usr_id = ? -- $userId
                   AND (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )
                   AND ' . $rolesRightsDbName . ' = true
              ORDER BY cat_org_id, cat_sequence, rol_name';
            $queryParams = array(DATE_NOW, DATE_NOW, $userId, $gCurrentOrgId);
            $roleStatement = $gDb->queryPrepared($sql, $queryParams);

            $roles = array();
            while ($roleName = $roleStatement->fetchColumn()) {
                $roles[] = $roleName;
            }

            if (count($roles) > 0) {
                $rightsOrigin[$rolesRightsDbName] = implode(', ', $roles);
            }
        }

        if (count($rightsOrigin) > 0) {

            if ($user->checkRolesRight('rol_assign_roles')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_assign_roles'],
                    'right' => $gL10n->get('SYS_RIGHT_ASSIGN_ROLES'),
                    'icon' => 'bi-people-fill'
                );
            }
            if ($user->checkRolesRight('rol_approve_users')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_approve_users'],
                    'right' => $gL10n->get('SYS_RIGHT_APPROVE_USERS'),
                    'icon' => 'bi-card-checklist'
                );
            }
            if ($user->checkRolesRight('rol_edit_user')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_edit_user'],
                    'right' => $gL10n->get('SYS_RIGHT_EDIT_USER'),
                    'icon' => 'bi-person-fill-gear'
                );
            }

            if ($user->checkRolesRight('rol_mail_to_all')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_mail_to_all'],
                    'right' => $gL10n->get('SYS_RIGHT_MAIL_TO_ALL'),
                    'icon' => 'bi-envelope-fill'
                );
            }
            if ($user->checkRolesRight('rol_profile')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_profile'],
                    'right' => $gL10n->get('SYS_RIGHT_PROFILE'),
                    'icon' => 'bi-person-fill'
                );
            }
            if ($user->checkRolesRight('rol_announcements') && (int) $gSettingsManager->get('announcements_module_enabled') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_announcements'],
                    'right' => $gL10n->get('SYS_RIGHT_ANNOUNCEMENTS'),
                    'icon' => 'bi-newspaper'
                );
            }
            if ($user->checkRolesRight('rol_events') && (int) $gSettingsManager->get('events_module_enabled') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_events'],
                    'right' => $gL10n->get('SYS_RIGHT_DATES'),
                    'icon' => 'bi-calendar-week-fill'
                );
            }
            if ($user->checkRolesRight('rol_photo') && (int) $gSettingsManager->get('photo_module_enabled') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_photo'],
                    'right' => $gL10n->get('SYS_RIGHT_PHOTOS'),
                    'icon' => 'bi-image-fill'
                );
            }
            if ($user->checkRolesRight('rol_documents_files') && (int) $gSettingsManager->getBool('documents_files_module_enabled')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_documents_files'],
                    'right' => $gL10n->get('SYS_RIGHT_DOCUMENTS_FILES'),
                    'icon' => 'bi-file-earmark-arrow-down-fill'
                );
            }
            if ($user->checkRolesRight('rol_inventory_admin') && (int) $gSettingsManager->getInt('inventory_module_enabled') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_inventory_admin'],
                    'right' => $gL10n->get('SYS_RIGHT_INVENTORY'),
                    'icon' => 'bi-box-seam-fill'
                );
            }
            if ($user->checkRolesRight('rol_forum_admin') && $gSettingsManager->getInt('forum_module_enabled') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_forum_admin'],
                    'right' => $gL10n->get('SYS_RIGHT_FORUM'),
                    'icon' => 'bi-chat-dots-fill'
                );
            }
            if ($user->checkRolesRight('rol_weblinks') && (int) $gSettingsManager->get('enable_weblinks_module') > 0) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_weblinks'],
                    'right' => $gL10n->get('SYS_RIGHT_WEBLINKS'),
                    'icon' => 'bi-link-45deg'
                );
            }
            if ($user->checkRolesRight('rol_all_lists_view')) {
                $userRightsArray[] = array(
                    'roles' => $rightsOrigin['rol_all_lists_view'],
                    'right' => $gL10n->get('SYS_RIGHT_ALL_LISTS_VIEW'),
                    'icon' => 'bi-list-task'
                );
            }
        }
        $page->assignSmartyVariable('userRights', $userRightsArray);
        $page->assignSmartyVariable('urlEditRoles', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/roles.php', array('user_uuid' => $getUserUuid, 'inline' => true)));
    }


    if (
        $gSettingsManager->getBool('profile_show_extern_roles')
        && ($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->isParentOrganization())
    ) {
        // *******************************************************************************
        // Block with roles from other organizations
        // *******************************************************************************

        // list all roles where the viewed user has an active membership
        $sql = 'SELECT *
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
        INNER JOIN ' . TBL_ORGANIZATIONS . '
                ON org_id = cat_org_id
             WHERE mem_usr_id  = ? -- $userId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end    >= ? -- DATE_NOW
               AND rol_valid   = true
               AND cat_name_intern <> \'EVENTS\'
               AND org_id     <> ? -- $gCurrentOrgId
          ORDER BY org_shortname, cat_sequence, rol_name';
        $roleStatement = $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $gCurrentOrgId));

        if ($roleStatement->rowCount() > 0) {

            $showRolesOtherOrganizations = false;
            $actualOrganization = 0;
            $externalRoles = array();
            $role = new Role($gDb);

            while ($row = $roleStatement->fetch()) {
                $orgId = (int) $row['org_id'];

                // if roles of new organization than read the rights of this organization
                if ($actualOrganization !== $orgId) {
                    $gCurrentUser->setOrganization($orgId);
                    $actualOrganization = $orgId;
                }

                // check if current user has right to view the role of that organization
                if ($gCurrentUser->hasRightViewRole($orgId)) {
                    $role->clear();
                    $role->setArray($row);

                    if (!$showRolesOtherOrganizations) {
                        $showRolesOtherOrganizations = true;
                        $page->assignSmartyVariable('showExternalRoles', true);
                    }

                    $startDate = DateTime::createFromFormat('Y-m-d', $row['mem_begin']);
                    $externalRoles[] = array(
                        'organization' => $row['org_shortname'],
                        'category' => $role->getValue('cat_name'),
                        'role' => $role->getValue('rol_name'),
                        'leader' => $row['mem_leader'],
                        'timestamp' => $gL10n->get('SYS_SINCE', array($startDate->format($gSettingsManager->getString('system_date'))))
                    );
                }
            }

            $gCurrentUser->setOrganization($gCurrentOrgId);
            $page->assignSmartyVariable('externalRoles', $externalRoles);
        }
    } else {
        $page->assignSmartyVariable('showExternalRoles', false);
    }

    if ($gSettingsManager->getBool('contacts_user_relations_enabled')) {
        // *******************************************************************************
        // user relations block
        // *******************************************************************************
        $sql = 'SELECT COUNT(*) AS count
              FROM ' . TBL_USER_RELATIONS . '
        INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                ON ure_urt_id  = urt_id
             WHERE ure_usr_id1 = ? -- $userId
               AND urt_name        <> \'\'
               AND urt_name_male   <> \'\'
               AND urt_name_female <> \'\'';
        $statement = $gDb->queryPrepared($sql, array($userId));
        $count = (int) $statement->fetchColumn();

        if ($count > 0) {
            $sql = 'SELECT *
                  FROM ' . TBL_USER_RELATIONS . '
            INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                    ON ure_urt_id  = urt_id
                 WHERE ure_usr_id1 = ? -- $userId
                   AND urt_name        <> \'\'
                   AND urt_name_male   <> \'\'
                   AND urt_name_female <> \'\'
              ORDER BY urt_name';
            $relationStatement = $gDb->queryPrepared($sql, array($userId));

            $relationType = new UserRelationType($gDb);
            $relation = new UserRelation($gDb);
            $otherUser = new User($gDb, $gProfileFields);
            $userRelations = array();

            while ($row = $relationStatement->fetch()) {
                $editUserIcon = '';
                $relationType->clear();
                $relationType->setArray($row);
                $relation->clear();
                $relation->setArray($row);
                $otherUser->clear();
                $otherUser->readDataById($relation->getValue('ure_usr_id2'));

                $relationName = $relationType->getValue('urt_name');
                if ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_MALE')) {
                    $relationName = $relationType->getValue('urt_name_male');
                } elseif ($otherUser->getValue('GENDER', 'text') === $gL10n->get('SYS_FEMALE')) {
                    $relationName = $relationType->getValue('urt_name_female');
                }

                $userRelation = array(
                    'uuid' => $relation->getValue('ure_uuid'),
                    'relationName' => $relationName,
                    'userFirstName' => $otherUser->getValue('FIRST_NAME'),
                    'userLastName' => $otherUser->getValue('LAST_NAME'),
                    'urlUserProfile' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $otherUser->getValue('usr_uuid')))
                );

                if ($gCurrentUser->hasRightEditProfile($otherUser)) {
                    $userRelation['urlUserEdit'] = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_new.php', array('user_uuid' => $otherUser->getValue('usr_uuid')));
                }

                if ($gCurrentUser->isAdministratorUsers()) {
                    $userRelation['urlRelationDelete'] = 'callUrlHideElement(\'row_ure_' . $relation->getValue('ure_uuid') . '\', \'' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/userrelations_function.php', array('mode' => 'delete', 'ure_uuid' => $relation->getValue('ure_uuid'))) . '\', \'' . $gCurrentSession->getCsrfToken() . '\')';
                }

                // only show info if system setting is activated
                if ((int) $gSettingsManager->get('system_show_create_edit') > 0) {
                    $userRelation['userCreatedName'] = $relation->getNameOfCreatingUser();
                    $userRelation['userCreatedTimestamp'] = $relation->getValue('ure_timestamp_create');
                    $userRelation['lastUserEditedName'] = $relation->getNameOfLastEditingUser();
                    $userRelation['lastUserEditedTimestamp'] = $relation->getValue('ure_timestamp_change');
                }
                $userRelations[] = $userRelation;
            }
            $page->assignSmartyVariable('showRelations', true);
            $page->assignSmartyVariable('showRelationsCreateEdit', $gSettingsManager->get('system_show_create_edit') > 0);
            $page->assignSmartyVariable('userRelations', $userRelations);
        }
        $page->assignSmartyVariable('urlAssignRelations', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/roles.php', array('user_uuid' => $getUserUuid, 'inline' => true)));
        $page->assignSmartyVariable('urlAssignUserRelations', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/userrelations/userrelations_new.php', array('user_uuid' => $getUserUuid)));
    }
    else {
        $page->assignSmartyVariable('showRelations', false);
    }

    // show information about user who creates the recordset and changed it
    $page->assignSmartyVariable('userCreatedName', $user->getNameOfCreatingUser());
    $page->assignSmartyVariable('userCreatedTimestamp', $user->getValue('usr_timestamp_create'));
    $page->assignSmartyVariable('lastUserEditedName', $user->getNameOfLastEditingUser());
    $page->assignSmartyVariable('lastUserEditedTimestamp', $user->getValue('usr_timestamp_change'));

    $page->addCssFile(ADMIDIO_URL . FOLDER_LIBS . '/bootstrap-tabs-x/css/bootstrap-tabs-x-admidio.css');
    $page->addJavascriptFile(ADMIDIO_URL . FOLDER_LIBS . '/bootstrap-tabs-x/js/bootstrap-tabs-x-admidio.js');

    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
