<?php
/**
 ***********************************************************************************************
 * Show role members list
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode:      Output(html, print, csv, xlsx, ods, pdf, pdfl)
 * date_from: Value for the start date of the date range filter (default: current date)
 * date_to:   Value for the end date of the date range filter (default: current date)
 * list_uuid: UUID of the list configuration that should be shown.
 *            If id is null then the default list of the role will be shown.
 * role_list: Comma separated UUID list of all roles whose members should be shown
 * relation_type_list:  Comma separated UUID list of the relation type whose members should be shown
 * mem_show_filter - 0  : (Default) show members of role that are active within the selected date range
 *                   1  : show only former members of the role
 *                   2  : show active and former members of the role
 ***********************************************************************************************
 */

use Admidio\Events\Entity\Event;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Service\RolesService;
use Admidio\Roles\ValueObject\ListData;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;
use Admidio\Changelog\Service\ChangelogService;

try {
    require_once(__DIR__ . '/../../system/common.php');

    unset($list);

    // Initialize and check the parameters
    $isAdministratorUserstatus = false;
    $getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date', array('defaultValue' => DATE_NOW));
    $getDateTo = admFuncVariableIsValid($_GET, 'date_to', 'date', array('defaultValue' => DATE_NOW));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('xlsx', 'ods', 'csv', 'html', 'print', 'pdf', 'pdfl')));
    $getListUuid = admFuncVariableIsValid($_GET, 'list_uuid', 'uuid');
    $getRoleList = admFuncVariableIsValid($_GET, 'role_list', 'string');
    $getMembersShowFiler = admFuncVariableIsValid($_GET, 'mem_show_filter', 'int', array('defaultValue' => 0));
    $getRelationTypeList = admFuncVariableIsValid($_GET, 'relation_type_list', 'string'); // could be int or int[], so string is necessary

    // check if the module is enabled and disallow access if it's disabled
    if (!$gSettingsManager->getBool('groups_roles_enable_module')) {
        throw new Exception('SYS_MODULE_DISABLED');
    }

    $roleUuidList = explode(',', $getRoleList);
    foreach ($roleUuidList as $key => $roleUuid) {
        if (!UUID::isValid($roleUuid)) {
            unset($roleUuidList[$key]);
        }
    }
    $numberRoles = count($roleUuidList);

    if ($numberRoles === 0) {
        throw new Exception('SYS_NO_ROLE_GIVEN');
    }

    // determine all roles relevant data
    $roleName = $gL10n->get('SYS_VARIOUS_ROLES');
    $htmlSubHeadline = '';
    $showLinkMailToList = true;
    $hasRightViewFormerMembers = true;
    $hasRightViewMembersProfile = true;
    $showComment = true;
    $showCountGuests = true;

    // read information about the roles
    $sql = 'SELECT rol_id, rol_name, rol_valid
          FROM ' . TBL_ROLES . '
         WHERE rol_uuid IN (' . Database::getQmForValues($roleUuidList) . ')';
    $rolesStatement = $gDb->queryPrepared($sql, $roleUuidList);
    $rolesData = $rolesStatement->fetchAll();

    foreach ($rolesData as $role) {
        $roleId = (int)$role['rol_id'];

        // check if user has right to view all roles
        // only users with the right to assign roles can view inactive roles
        if (!$gCurrentUser->hasRightViewRole($roleId)
            || ((int)$role['rol_valid'] === 0 && !$gCurrentUser->checkRolesRight('rol_assign_roles'))) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        // check if the user is allowed to view all profiles
        // if not, then only first name and last name will be shown
        if (!$gCurrentUser->hasRightViewProfiles($roleId)) {
            $hasRightViewMembersProfile = false;
        }

        // check if user has right to send mail to role
        if (!$gCurrentUser->hasRightSendMailToRole($roleId)) {
            $showLinkMailToList = false;
            // => do not show the link
        }

        if (!$gCurrentUser->hasRightViewFormerRolesMembers($roleId)) {
            $hasRightViewFormerMembers = false;
        }

        $htmlSubHeadline .= ', ' . $role['rol_name'];
    }

    $htmlSubHeadline = substr($htmlSubHeadline, 2);

    if ($numberRoles === 1) {
        $role = new Role($gDb);
        $role->readDataByUuid($roleUuidList[0]);
        $roleID = $role->getValue('rol_id');
        $roleName = $role->getValue('rol_name');
        $htmlSubHeadline = $role->getValue('cat_name');
        $hasRightViewFormerMembers = $gCurrentUser->hasRightViewFormerRolesMembers($roleID);

        // If it's an event list and user has right to edit user states then an additional column with edit link is shown
        if ($role->getValue('cat_name_intern') === 'EVENTS') {
            $event = new Event($gDb);
            $event->readDataByRoleId($roleID);

            $showComment = $event->getValue('dat_allow_comments');
            $showCountGuests = $event->getValue('dat_additional_guests');

            if ($getMode === 'html' && ($gCurrentUser->isAdministrator() || $gCurrentUser->isLeaderOfRole($roleID))) {
                $isAdministratorUserstatus = true;
            }
        }
    }

    // if user should not view former roles members then disallow it
    if (!$hasRightViewFormerMembers) {
        $getMembersShowFiler = 0;
        $getDateFrom = DATE_NOW;
        $getDateTo = DATE_NOW;
    }

    // Create date objects and format events in system format
    $objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
    if ($objDateFrom === false) {
        // check if date_from  has system format
        $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
    }
    $dateFrom = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $startDateEnglishFormat = $objDateFrom->format('Y-m-d');

    $objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
    if ($objDateTo === false) {
        // check if date_from  has system format
        $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
    }
    $dateTo = $objDateTo->format($gSettingsManager->getString('system_date'));
    $endDateEnglishFormat = $objDateTo->format('Y-m-d');

    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    // read names of all used relationships for later output
    $relationTypeName = '';
    $relationTypeUuidList = array();

    if ($getRelationTypeList !== '') {
        $relationTypeUuidList = explode(',', $getRelationTypeList);
    }

    if (count($relationTypeUuidList) > 0) {
        $sql = 'SELECT urt_uuid, urt_name
              FROM ' . TBL_USER_RELATION_TYPES . '
             WHERE urt_uuid IN (' . Database::getQmForValues($relationTypeUuidList) . ')
          ORDER BY urt_name';
        $relationTypesStatement = $gDb->queryPrepared($sql, $relationTypeUuidList);

        while ($relationType = $relationTypesStatement->fetch()) {
            $relationTypeName .= (empty($relationTypeName) ? '' : ', ') . $relationType['urt_name'];
        }
    }

    // check if user has the right to export lists
    if (in_array($getMode, array('csv', 'xlsx', 'ods', 'pdf'), true)
        && ($gSettingsManager->getInt('groups_roles_export') === 0 // no one should export lists
            || ($gSettingsManager->getInt('groups_roles_export') === 2 && !$gCurrentUser->checkRolesRight('rol_edit_user')))) { // users who don't have the right to edit all profiles
        throw new Exception('SYS_NO_RIGHTS');
    }

    // if no list parameter is set then load role default list configuration or system default list configuration
    if ($numberRoles === 1 && $getListUuid === '') {
        // set role default list configuration
        $listId = $role->getDefaultList();

        if ($listId === 0) {
            throw new Exception('SYS_DEFAULT_LIST_NOT_SET_UP');
        }

        $list = new ListConfiguration($gDb, $listId);
        $getListUuid = $list->getValue('lst_uuid');
    } else {
        // create list configuration object and create a sql statement out of it
        $list = new ListConfiguration($gDb);
        $list->readDataByUuid($getListUuid);
    }

    // only first name and last name should be shown
    if (!$hasRightViewMembersProfile) {
        $list->setModeShowOnlyNames();
    }

    // remove columns that are not necessary for the selected role
    if (!$showComment) {
        $list->removeColumn('mem_comment');
    }
    if (!$showCountGuests) {
        $list->removeColumn('mem_count_guests');
    }

    if (in_array($getMode, array('xlsx', 'ods', 'csv'))) {
        // set SQL options for export
        $sqlOptions = array(
            'showRolesMembers' => $roleUuidList,
            'showAllMembersThisOrga' => ($getMembersShowFiler === 2 ? true : false),
            'showFormerMembers' => ($getMembersShowFiler > 0 ? true : false),
            'showRelationTypes' => $relationTypeUuidList,
            'startDate' => $startDateEnglishFormat,
            'endDate' => $endDateEnglishFormat
        );
    } else {
        // set SQL options for displaying
        $sqlOptions = array(
            'showRolesMembers' => $roleUuidList,
            'showAllMembersThisOrga' => ($getMembersShowFiler === 2 ? true : false),
            'showFormerMembers' => ($getMembersShowFiler > 0 ? true : false),
            'showUserUUID' => true,
            'showLeaderFlag' => true,
            'showRelationTypes' => $relationTypeUuidList,
            'startDate' => $startDateEnglishFormat,
            'endDate' => $endDateEnglishFormat
        );
    }

    $listData = new ListData();
    $listData->setDataByConfiguration($list, $sqlOptions);

    if (in_array($getMode, array('xlsx', 'ods', 'csv'))) {
        // generate the export to xlsx, ods or csv file

        if ($getMembersShowFiler === 2) {
            $arrColumnNames = $list->getColumnNames();
            $arrColumnNames[] = $gL10n->get('INS_MEMBERSHIP');
             $listData->setColumnHeadlines($arrColumnNames);
        } else {
            $listData->setColumnHeadlines($list->getColumnNames());
        }
        $filename = $gCurrentOrganization->getValue('org_shortname') . '-' . str_replace('.', '', $roleName);
        if ((string)$list->getValue('lst_name') !== '') {
            $filename .= '-' . str_replace('.', '', $list->getValue('lst_name'));
        }
        $filename = FileSystemUtils::getSanitizedPathEntry($filename);

        switch ($getMode) {
            case 'xlsx':
                $listData->export($filename, 'xlsx');
                break;
            case 'ods':
                $listData->export($filename, 'ods');
                break;
            default:
                // the default will be a csv file
                $listData->export($filename);
        }
        exit();
    }

    // initialize some special mode parameters
    $classTable = '';
    $orientation = '';

    switch ($getMode) {
        case 'pdf':
            $classTable = 'table';
            $orientation = 'P';
            $getMode = 'pdf';
            break;
        case 'pdfl':
            $classTable = 'table';
            $orientation = 'L';
            $getMode = 'pdf';
            break;
        case 'html':
            $classTable = 'table table-condensed';
            break;
        case 'print':
            $classTable = 'table table-condensed table-striped';
            break;
        default:
    }

    // determine the number of users in this list
    $numMembers = $listData->rowCount();
    $membersList = $listData->getData($getMode);
    $userUuidList = array();

    // create an array with all user UUIDs that have a valid email address
    foreach ($membersList as $member) {
        $user = new User($gDb, $gProfileFields);
        $user->readDataByUuid($member['usr_uuid']);

        // only users with a valid email address should be added to the email list
        if (StringUtils::strValidCharacters($user->getValue('EMAIL'), 'email') && $gCurrentUserUUID !== $member['usr_uuid']) {
            $userUuidList[] = $member['usr_uuid'];
        }
    }

    // define title (html) and headline
    $title = $gL10n->get('SYS_LIST') . ' - ' . $roleName;
    if ((string)$list->getValue('lst_name') !== '') {
        $headline = $roleName . ' - ' . $list->getValue('lst_name');
    } else {
        $headline = $roleName;
    }

    if (count($relationTypeUuidList) === 1) {
        $headline .= ' - ' . $relationTypeName;
    } elseif (count($relationTypeUuidList) > 1) {
        $headline .= ' - ' . $gL10n->get('SYS_VARIOUS_USER_RELATION_TYPES');
    }

    // if html mode and last url was not a list view then save this url to navigation stack
    if ($getMode === 'html' && !str_contains($gNavigation->getUrl(), 'lists_show.php')) {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    $datatable = false;
    $hoverRows = false;

    if ($getMode !== 'html') {
        if ($getMembersShowFiler === 1) {
            $htmlSubHeadline .= ' - ' . $gL10n->get('SYS_FORMER_MEMBERS');
        } elseif ($getMembersShowFiler === 2) {
            $htmlSubHeadline .= ' - ' . $gL10n->get('SYS_ALL_MEMBERS');
        } else {
            if ($getDateFrom === DATE_NOW && $getDateTo === DATE_NOW) {
                $htmlSubHeadline .= ' - ' . $gL10n->get('SYS_ACTIVE_MEMBERS');
            } else {
                $htmlSubHeadline .= ' - ' . $gL10n->get('SYS_MEMBERS_BETWEEN_PERIOD', array($dateFrom, $dateTo));
            }
        }
    }

    if (count($relationTypeUuidList) > 1) {
        $htmlSubHeadline .= ' - ' . $relationTypeName;
    }

    if ($getMode === 'print') {
        // create html page object without the custom theme files
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-lists-show', $headline);
        $page->setContentFullWidth();
        $page->setPrintMode();
        $page->setTitle($title);
        $page->addHtml('<h5 class="admidio-content-subheader">' . $htmlSubHeadline . '</h5>');
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    } elseif ($getMode === 'pdf') {
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($headline);

        // remove default header/footer
        $pdf->setPrintHeader();
        $pdf->setPrintFooter(false);
        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->setHeaderMargin();
        $pdf->setFooterMargin(0);

        // headline for PDF
        $pdf->setHeaderData('', 0, $headline);

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_lists_table', null, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
    } elseif ($getMode === 'html') {
        $datatable = true;
        $hoverRows = true;

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-lists-show', $headline);
        $page->setContentFullWidth();
        $page->setTitle($title);

        // create select box with all list configurations
        $sql = 'SELECT lst_uuid, lst_name, lst_global
              FROM ' . TBL_LISTS . '
             WHERE lst_org_id = ? -- $gCurrentOrgId
               AND (  lst_usr_id = ? -- $gCurrentUserId
                   OR lst_global = true)
               AND lst_name IS NOT NULL
          ORDER BY lst_global, lst_name';
        $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $gCurrentUserId));

        $listConfigurations = array();
        while ($row = $pdoStatement->fetch()) {
            $listConfigurations[] = array($row['lst_uuid'], $row['lst_name'], (bool)$row['lst_global']);
        }

        foreach ($listConfigurations as &$rowConfigurations) {
            if ($rowConfigurations[2] == 0) {
                $rowConfigurations[2] = $gL10n->get('SYS_YOUR_LISTS');
            } else {
                $rowConfigurations[2] = $gL10n->get('SYS_GENERAL_LISTS');
            }
        }
        unset($rowConfigurations);

        // add list item for own list
        $listConfigurations[] = array('mylist', $gL10n->get('SYS_CONFIGURE_LISTS'), $gL10n->get('SYS_CONFIGURATION'));

        // add navbar with filter elements and the select box with all lists configurations
        $form = new FormPresenter(
            'adm_navbar_filter_form',
            'sys-template-parts/form.filter.tpl',
            ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php',
            $page,
            array('type' => 'navbar', 'setFocus' => false)
        );
        $form->addSelectBox(
            'list_configurations',
            $gL10n->get('SYS_CONFIGURATION_LIST'),
            $listConfigurations,
            array('defaultValue' => $getListUuid)
        );


        // Only for active members of a role and if user has right to view former members
        if ($hasRightViewFormerMembers) {
            // create filter menu with elements for role, relation type and date
            $selectBoxValues = array(
                '0' => $gL10n->get('SYS_ACTIVE_MEMBERS'),
                '1' => $gL10n->get('SYS_FORMER_MEMBERS'),
                '2' => $gL10n->get('SYS_ALL_MEMBERS')
            );

            // filter all items
            $form->addSelectBox(
                'mem_show_filter',
                $gL10n->get('SYS_MEMBERS'),
                $selectBoxValues,
                array(
                    'defaultValue' => $getMembersShowFiler,
                    'showContextDependentFirstEntry' => false
                )
            );

            // create filter menu with elements for start-/end date
            $form->addInput('date_from', $gL10n->get('SYS_ROLE_MEMBERSHIP_IN_PERIOD'), $dateFrom, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('date_to', $gL10n->get('SYS_ROLE_MEMBERSHIP_TO'), $dateTo, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('list_uuid', '', $getListUuid, array('property' => FormPresenter::FIELD_HIDDEN));
            $form->addInput('role_list', '', $getRoleList, array('property' => FormPresenter::FIELD_HIDDEN));
            $form->addInput('relation_type_list', '', $getRelationTypeList, array('property' => FormPresenter::FIELD_HIDDEN));
            $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
        }

        $form->addToHtmlPage();

        $page->addHtml('<h5 class="admidio-content-subheader">' . $htmlSubHeadline . '</h5>');
        $page->addJavascript(
            '
        $("#list_configurations").change(function() {
            elementId = $(this).attr("id");
            roleId    = elementId.substr(elementId.search(/_/) + 1);

            if ($(this).val() === "mylist") {
                self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php', array('role_list' => $getRoleList)) . '";
            } else {
                self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('mode' => 'html', 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)) . '&list_uuid=" + $(this).val();
            }
        });

        // change mode of members that should be shown
        $("#mem_show_filter").on("change", function() {
            var form = $("#adm_navbar_filter_form");
            var membersSelect = $("#mem_show_filter");
            membersSelect.attr("name", "mem_show_filter");
            form.submit();
        });

        $("#menu_item_mail_to_list").click(function() {
            redirectPost("' . ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php", {list_uuid: "' . $getListUuid . '", userUuidList: "' . implode(',', $userUuidList) . '"});
            return false;
        });

        $("#menu_item_lists_print_view").click(function() {
            window.open("' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mode' => 'print', 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)) . '", "_blank");
        });',
            true
        );

        // link to print overlay and exports
        $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'bi-printer-fill');

        // dropdown menu item with all export possibilities
        if ($gSettingsManager->getInt('groups_roles_export') === 1 // all users
            || ($gSettingsManager->getInt('groups_roles_export') === 2 && $gCurrentUser->checkRolesRight('rol_edit_user'))) { // users with the right to edit all profiles
            $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_DOWNLOAD_FILE'), '#', 'bi-download');
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_excel',
                $gL10n->get('SYS_MICROSOFT_EXCEL') . ' (*.xlsx)',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'xlsx')),
                'bi-file-earmark-excel',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_odf',
                $gL10n->get('SYS_ODF_SPREADSHEET'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'ods')),
                'bi-file-earmark-spreadsheet',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_csv',
                $gL10n->get('SYS_COMMA_SEPARATED_FILE'),
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'csv')),
                'bi-filetype-csv',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_pdf',
                $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_PORTRAIT') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'pdf')),
                'bi-file-earmark-pdf',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_pdfl',
                $gL10n->get('SYS_PDF') . ' (' . $gL10n->get('SYS_LANDSCAPE') . ')',
                SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'role_list' => $getRoleList, 'relation_type_list' => $getRelationTypeList, 'mem_show_filter' => $getMembersShowFiler, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'pdfl')),
                'bi-file-earmark-pdf',
                'menu_item_lists_export'
            );
        }

        if ($numberRoles === 1) {
            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser)) {
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_assign_members',
                    $gL10n->get('SYS_ASSIGN_MEMBERS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/members_assignment.php', array('role_uuid' => $role->getValue('rol_uuid'))),
                    'bi bi-person-plus-fill'
                );
            }
        }

        // link to email-module
        if ($showLinkMailToList) {
            $page->addPageFunctionsMenuItem(
                'menu_item_mail_to_list',
                $gL10n->get('SYS_EMAIL_TO_LIST'),
                'javascript:void(0);',
                'bi-envelope-fill'
            );
        }

        ChangelogService::displayHistoryButton($page, 'roles', 'members', true, array('related_id' => $getRoleList));

        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
        $table->setDatatablesRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
    } else {
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }

    if ($numMembers === 0) {
        // no members found
        $page->addHtml('<div class="alert alert-warning" role="alert">' . $gL10n->get('SYS_NO_USER_FOUND') . '</div>');
        $page->show();
        // => EXIT
    }

    // read column information from the list configuration
    $arrColumnNames = $list->getColumnNames();
    $arrColumnAlign = $list->getColumnAlignments();

    // set the first column for the counter
    if ($getMode === 'html') {
         // add column for former status
        if ($getMembersShowFiler === 2) {
            array_unshift($arrColumnNames, '<i class="bi bi-person-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('INS_MEMBERSHIP') . '"></i>');
            array_unshift($arrColumnAlign, 'center');
        }

        // in html mode we group leaders. Therefore, we need a special hidden column.
        array_unshift($arrColumnNames, $gL10n->get('INS_GROUPS'));
        array_unshift($arrColumnAlign, 'left');

        if ($isAdministratorUserstatus) {
            // add column for edit link
            $arrColumnNames[] = '&nbsp;';
        }
    }
    elseif ($getMembersShowFiler === 2) {
        array_unshift($arrColumnNames, $gL10n->get('INS_MEMBERSHIP'));
        array_unshift($arrColumnAlign, 'left');
    }

    // add column with sequential number
    array_unshift($arrColumnNames, $gL10n->get('SYS_ABR_NO'));
    array_unshift($arrColumnAlign, 'left');

    if ($getMode === 'html' || $getMode === 'print') {
        $table->setColumnAlignByArray($arrColumnAlign);
        $table->addRowHeadingByArray($arrColumnNames);
    } elseif ($getMode === 'pdf') {
        $table->setColumnAlignByArray($arrColumnAlign);
        $table->addTableHeader();
        $table->addRow();
        $table->addAttribute('align', 'center');
        $table->addColumn($headline, array('colspan' => count($arrColumnNames)));
        $table->addRow();

        // Write valid column headings
        for ($column = 0, $max = count($arrColumnNames); $column < $max; ++$column) {
            $table->addColumn($arrColumnNames[$column], array('style' => 'text-align: ' . $arrColumnAlign[$column] . '; font-size: 14px; background-color: #c7c7c7;'), 'th');
        }
    }

    $listHasLeaders = false; // Mark for change between leader and member
    $lastMemberIsLeader = false;
    $listRowNumber = 1;

    foreach ($membersList as $member) {
        $memberIsLeader = false;
        if (isset($member['mem_leader'])) {
            $memberIsLeader = (bool)$member['mem_leader'];
        }

        // in print preview and pdf we group the role leaders and the members and
        // add a specific header for them
        if ($memberIsLeader !== $lastMemberIsLeader) {
            if ($memberIsLeader) {
                $listHasLeaders = true;
                $title = $gL10n->get('SYS_LEADERS');
            } else {
                // if list has leaders then initialize row number for members
                $listRowNumber = 1;
                $title = $gL10n->get('SYS_PARTICIPANTS');
            }

            if ($getMode === 'print' || $getMode === 'pdf') {
                $colspan = ($getMembersShowFiler === 2) ? $list->countColumns() + 2 : $list->countColumns() + 1;
                $table->addRowByArray(array($title), '', array('class' => 'admidio-group-heading'), $colspan);
            }
            $lastMemberIsLeader = $memberIsLeader;
        }

        $columnValues = $member;
        unset($columnValues['usr_uuid']);

        if ($getMode === 'html') {
            if (isset($member['mem_former'])) {
                // Add icon for member or no member of the organization
                if ($member['mem_former']) {
                    $icon = 'bi-person-fill-x text-danger';
                    $iconText = $gL10n->get('SYS_FORMER_MEMBER_OF_GROUP', array($roleName));
                }
                else {
                    $icon = 'bi-person-fill-check';
                    $iconText = $gL10n->get('SYS_MEMBER_OF_GROUP', array($roleName));
                }
                unset($columnValues['mem_former']);
                $columnValues = array('mem_former' => '<i class="bi ' . $icon . '" data-bs-toggle="tooltip" title="' . $iconText . '"></i>') + $columnValues;
            }

            // in html mode we add a column with leader/member information to
            // enable the grouping function of jquery datatables
            if ($memberIsLeader) {
                $columnValues = array('mem_leader' => $gL10n->get('SYS_LEADERS')) + $columnValues;
            } else {
                $columnValues = array('mem_leader' => $gL10n->get('SYS_PARTICIPANTS')) + $columnValues;
            }

            // add a column with the row number at the first column
            array_unshift($columnValues, '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $member['usr_uuid'])) . '">' . $listRowNumber . '</a>');
        } elseif (in_array($getMode, array('print', 'pdf'), true)) {
            unset($columnValues['mem_leader']);

            // add a column with former status
            if (isset($member['mem_former'])) {
                if ($member['mem_former']) {
                    $columnValues = array('mem_former' => $gL10n->get('SYS_FORMER_MEMBER')) + $columnValues;
                } else {
                    $columnValues = array('mem_former' => $gL10n->get('SYS_MEMBER')) + $columnValues;
                }
            }

            // add a column with the row number at the first column
            array_unshift($columnValues, $listRowNumber);
        }

        if ($isAdministratorUserstatus) {
            // Get the matching event
            $sql = 'SELECT dat_uuid
                  FROM ' . TBL_EVENTS . '
                 WHERE dat_rol_id = ? -- $roleID';
            $datesStatement = $gDb->queryPrepared($sql, array($roleID));
            $dateUuid = $datesStatement->fetchColumn();
            // prepare edit icon
            $columnValues[] = '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                data-href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/events/events_participation.php', array('dat_uuid' => $dateUuid, 'user_uuid' => $member['usr_uuid'])) . '">
                                <i class="bi bi-pencil-square" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_EDIT') . '"></i></a>';
        }

        $table->addRowByArray($columnValues, '', array('nobr' => 'true'));

        ++$listRowNumber;
    }  // End-While (end found User)


    // if html mode and the role has leaders then group all data between leaders and members
    if ($getMode === 'html') {
        if ($list->isShowingLeaders() && $listHasLeaders) {
            $table->setDatatablesGroupColumn(2);
        } else {
            $table->setDatatablesColumnsHide(array(2));
        }
    }

    if ($getMode === 'pdf') {
        // send the new PDF to the User
        $filename = $gCurrentOrganization->getValue('org_shortname') . '-' . str_replace('.', '', $roleName);

        // file name in the current directory...
        if ((string)$list->getValue('lst_name') !== '') {
            $filename .= '-' . str_replace('.', '', $list->getValue('lst_name'));
        }

        $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;
        $file = ADMIDIO_PATH . FOLDER_TEMP_DATA . '/' . $filename;

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // necessary for IE6 to 8, because without it the download with SSL has problems
        header('Cache-Control: private');
        header('Pragma: public');

        // output the HTML content
        $pdf->writeHTML($table->getHtmlTable(), true, false, true);

        // Save PDF to file
        $pdf->Output($file, 'F');

        readfile($file);
        ignore_user_abort(true);

        try {
            FileSystemUtils::deleteFileIfExists($file);
        } catch (RuntimeException $exception) {
            $gLogger->error('Could not delete file!', array('filePath' => $file));
            // TODO
        }
    } else {
        // add table list to the page
        $page->addHtml($table->show());

        // create an infobox for the role
        if ($getMode === 'html' && $numberRoles === 1) {
            // only show infobox if additional role information fields are filled
            if ($role->getValue('rol_weekday') > 0
                || (string)$role->getValue('rol_start_date') !== ''
                || (string)$role->getValue('rol_start_time') !== ''
                || (string)$role->getValue('rol_location') !== ''
                || !empty($role->getValue('rol_cost'))
                || !empty($role->getValue('rol_max_members'))) {
                $smarty = HtmlPage::createSmartyObject();
                $smarty->assign('l10n', $gL10n);
                $smarty->assign('role', $role->getValue('rol_name'));

                $roleProperties = array(array('label' => $gL10n->get('SYS_CATEGORY'), 'value' => $role->getValue('cat_name')));

                // Description
                if ((string)$role->getValue('rol_description') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_DESCRIPTION'), 'value' => $role->getValue('rol_description'));
                }

                // Period
                if ((string)$role->getValue('rol_start_date') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_PERIOD'), 'value' => $gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))));
                }

                // Appointment
                $value = '';
                if ($role->getValue('rol_weekday') > 0) {
                    $value = RolesService::getWeekdays($role->getValue('rol_weekday')) . ' ';
                }
                if ((string)$role->getValue('rol_start_time') !== '') {
                    $value = $gL10n->get('SYS_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
                }
                if ($role->getValue('rol_weekday') > 0 || (string)$role->getValue('rol_start_time') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_APPOINTMENT'), 'value' => $value);
                }

                // Meeting Point
                if ((string)$role->getValue('rol_location') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_MEETING_POINT'), 'value' => $role->getValue('rol_location'));
                }

                // Member Fee
                if ((string)$role->getValue('rol_cost') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_CONTRIBUTIONv'), 'value' => $role->getValue('rol_cost') . ' ' . $gSettingsManager->getString('system_currency'));
                }

                // Fee period
                if ((string)$role->getValue('rol_cost_period') !== '' && $role->getValue('rol_cost_period') != 0) {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_CONTRIBUTION_PERIOD'), 'value' => Role::getCostPeriods($role->getValue('rol_cost_period')));
                }

                // max participants
                if ((string)$role->getValue('rol_max_members') !== '') {
                    $roleProperties[] = array('label' => $gL10n->get('SYS_MAX_PARTICIPANTS'), 'value' => $role->getValue('rol_max_members'));
                }

                $smarty->assign('roleProperties', $roleProperties);
                $page->addHtml($smarty->fetch('modules/groups-roles.infobox.tpl'));
            } // end of infobox
        }

        // show complete html page
        $page->show();
    }
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    echo $e->getMessage();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
