<?php
/**
 ***********************************************************************************************
 * Show role members list
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode:            Output(html, print, csv-ms, csv-oo, pdf, pdfl)
 * date_from:       Value for the start date of the date range filter (default: current date)
 * date_to:         Value for the end date of the date range filter (default: current date)
 * list_uuid:       UUID of the list configuration that should be shown.
 *                  If id is null then the default list of the role will be shown.
 * rol_ids:         ID of the role or an integer array of all role ids whose members should be shown
 * urt_ids:         ID of the relation type or an integer array of all relation types ids whose members should be shown
 * show_former_members: 0 - (Default) show members of role that are active within the selected date range
 *                      1 - show only former members of the role
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');

unset($list);

// Initialize and check the parameters
$editUserStatus       = false;
$getDateFrom          = admFuncVariableIsValid($_GET, 'date_from', 'date', array('defaultValue' => DATE_NOW));
$getDateTo            = admFuncVariableIsValid($_GET, 'date_to', 'date', array('defaultValue' => DATE_NOW));
$getMode              = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl')));
$getListUuid          = admFuncVariableIsValid($_GET, 'list_uuid', 'string');
$getRoleIds           = admFuncVariableIsValid($_GET, 'rol_ids', 'string'); // could be int or int[], so string is necessary
$getShowFormerMembers = admFuncVariableIsValid($_GET, 'show_former_members', 'bool', array('defaultValue' => false));
$getRelationTypeIds   = admFuncVariableIsValid($_GET, 'urt_ids', 'string'); // could be int or int[], so string is necessary

// check if the module is enabled and disallow access if it's disabled
if (!$gSettingsManager->getBool('groups_roles_enable_module')) {
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
    // => EXIT
}

$roleIds = array_map('intval', array_filter(explode(',', $getRoleIds), 'is_numeric'));
$numberRoles = count($roleIds);

if ($numberRoles === 0) {
    $gMessage->show($gL10n->get('SYS_NO_ROLE_GIVEN'));
    // => EXIT
}

// determine all roles relevant data
$roleName        = $gL10n->get('SYS_VARIOUS_ROLES');
$htmlSubHeadline = '';
$showLinkMailToList = true;
$hasRightViewFormerMembers = true;
$hasRightViewMembersProfile = true;
$showComment = true;
$showCountGuests = true;

// read information about the roles
$sql = 'SELECT rol_id, rol_name, rol_valid
          FROM '.TBL_ROLES.'
         WHERE rol_id IN ('.Database::getQmForValues($roleIds).')';
$rolesStatement = $gDb->queryPrepared($sql, $roleIds);
$rolesData      = $rolesStatement->fetchAll();

foreach ($rolesData as $role) {
    $roleId = (int) $role['rol_id'];

    // check if user has right to view all roles
    // only users with the right to assign roles can view inactive roles
    if (!$gCurrentUser->hasRightViewRole($roleId)
    || ((int) $role['rol_valid'] === 0 && !$gCurrentUser->checkRolesRight('rol_assign_roles'))) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
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

    $htmlSubHeadline .= ', '.$role['rol_name'];
}

$htmlSubHeadline = substr($htmlSubHeadline, 2);

if ($numberRoles === 1) {
    $role = new TableRoles($gDb, $roleIds[0]);
    $roleName        = $role->getValue('rol_name');
    $htmlSubHeadline = $role->getValue('cat_name');
    $hasRightViewFormerMembers = $gCurrentUser->hasRightViewFormerRolesMembers($roleIds[0]);

    // If it's an event list and user has right to edit user states then an additional column with edit link is shown
    if ($role->getValue('cat_name_intern') === 'EVENTS') {
        $event = new TableDate($gDb);
        $event->readDataByRoleId($roleIds[0]);

        $showComment = $event->getValue('dat_allow_comments');
        $showCountGuests = $event->getValue('dat_additional_guests');

        if ($getMode === 'html' && ($gCurrentUser->isAdministrator() || $gCurrentUser->isLeaderOfRole($roleIds[0]))) {
            $editUserStatus = true;
        }
    }
}

// if user should not view former roles members then disallow it
if (!$hasRightViewFormerMembers) {
    $getShowFormerMembers = false;
    $getDateFrom = DATE_NOW;
    $getDateTo   = DATE_NOW;
}

// Create date objects and format dates in system format
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
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

// read names of all used relationships for later output
$relationTypeName = '';
$relationTypeIds = array_map('intval', array_filter(explode(',', $getRelationTypeIds), 'is_numeric'));
if (count($relationTypeIds) > 0) {
    $sql = 'SELECT urt_id, urt_name
              FROM '.TBL_USER_RELATION_TYPES.'
             WHERE urt_id IN ('.Database::getQmForValues($relationTypeIds).')
          ORDER BY urt_name';
    $relationTypesStatement = $gDb->queryPrepared($sql, $relationTypeIds);

    while ($relationType = $relationTypesStatement->fetch()) {
        $relationTypeName .= (empty($relationTypeName) ? '' : ', ').$relationType['urt_name'];
    }
}

// initialize some special mode parameters
$separator   = '';
$valueQuotes = '';
$charset     = '';
$classTable  = '';
$orientation = '';

switch ($getMode) {
    case 'csv-ms':
        $separator   = ';';  // Microsoft Excel 2007 or new needs a semicolon
        $valueQuotes = '"';  // all values should be set with quotes
        $getMode     = 'csv';
        $charset     = 'iso-8859-1';
        break;
    case 'csv-oo':
        $separator   = ',';  // a CSV file should have a comma
        $valueQuotes = '"';  // all values should be set with quotes
        $getMode     = 'csv';
        $charset     = 'utf-8';
        break;
    case 'pdf':
        $classTable  = 'table';
        $orientation = 'P';
        $getMode     = 'pdf';
        break;
    case 'pdfl':
        $classTable  = 'table';
        $orientation = 'L';
        $getMode     = 'pdf';
        break;
    case 'html':
        $classTable  = 'table table-condensed';
        break;
    case 'print':
        $classTable  = 'table table-condensed table-striped';
        break;
    default:
}

// check if user has the right to export lists
if (in_array($getMode, array('csv', 'pdf'), true)
&& ($gSettingsManager->getInt('groups_roles_export') === 0 // no one should export lists
   || ($gSettingsManager->getInt('groups_roles_export') === 2 && !$gCurrentUser->checkRolesRight('rol_edit_user')))) { // users who don't have the right to edit all profiles
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

$mainSql = ''; // Main SQL statement for lists
$csvStr = ''; // CSV file as string

// if no list parameter is set then load role default list configuration or system default list configuration
if ($numberRoles === 1 && $getListUuid === '') {
    // set role default list configuration
    $listId = $role->getDefaultList();

    if ($listId === 0) {
        $gMessage->show($gL10n->get('SYS_DEFAULT_LIST_NOT_SET_UP'));
        // => EXIT
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

// create the main sql
$mainSql = $list->getSQL(
    array('showRolesMembers'  => $roleIds,
          'showFormerMembers' => $getShowFormerMembers,
          'showRelationTypes' => $relationTypeIds,
          'startDate' => $startDateEnglishFormat,
          'endDate'   => $endDateEnglishFormat
    )
);

// determine the number of users in this list
$listStatement = $gDb->query($mainSql); // TODO add more params
$numMembers = $listStatement->rowCount();

// get all members and their data of this list in an array
$membersList = $listStatement->fetchAll(PDO::FETCH_BOTH);

$userUuidList = array();
foreach ($membersList as $member) {
    $user = new User($gDb, $gProfileFields, $member['usr_id']);

    // only users with a valid email address should be added to the email list
    if (StringUtils::strValidCharacters($user->getValue('EMAIL'), 'email') && $gCurrentUserId !== (int) $member['usr_id']) {
        $userUuidList[] = $member['usr_uuid'];
    }
}

// define title (html) and headline
$title = $gL10n->get('SYS_LIST').' - '.$roleName;
if ((string) $list->getValue('lst_name') !== '') {
    $headline = $roleName.' - '.$list->getValue('lst_name');
} else {
    $headline = $roleName;
}

if (count($relationTypeIds) === 1) {
    $headline .= ' - '.$relationTypeName;
} elseif (count($relationTypeIds) > 1) {
    $headline .= ' - '.$gL10n->get('SYS_VARIOUS_USER_RELATION_TYPES');
}

// if html mode and last url was not a list view then save this url to navigation stack
if ($getMode === 'html' && !str_contains($gNavigation->getUrl(), 'lists_show.php')) {
    $gNavigation->addUrl(CURRENT_URL, $headline);
}

if ($getMode !== 'csv') {
    $datatable = false;
    $hoverRows = false;

    if ($getMode !== 'html') {
        if ($getShowFormerMembers === 1) {
            $htmlSubHeadline .= ' - '.$gL10n->get('SYS_FORMER_MEMBERS');
        } else {
            if ($getDateFrom === DATE_NOW && $getDateTo === DATE_NOW) {
                $htmlSubHeadline .= ' - '.$gL10n->get('SYS_ACTIVE_MEMBERS');
            } else {
                $htmlSubHeadline .= ' - '.$gL10n->get('SYS_MEMBERS_BETWEEN_PERIOD', array($dateFrom, $dateTo));
            }
        }
    }

    if (count($relationTypeIds) > 1) {
        $htmlSubHeadline .= ' - '.$relationTypeName;
    }

    if ($getMode === 'print') {
        // create html page object without the custom theme files
        $page = new HtmlPage('admidio-lists-show', $headline);
        $page->setPrintMode();
        $page->setTitle($title);
        $page->addHtml('<h5 class="admidio-content-subheader">'.$htmlSubHeadline.'</h5>');
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
        $page = new HtmlPage('admidio-lists-show', $headline);
        $page->setTitle($title);

        // create select box with all list configurations
        $sql = 'SELECT lst_uuid, lst_name, lst_global
                  FROM '.TBL_LISTS.'
                 WHERE lst_org_id = ? -- $gCurrentOrgId
                   AND (  lst_usr_id = ? -- $gCurrentUserId
                       OR lst_global = true)
                   AND lst_name IS NOT NULL
              ORDER BY lst_global ASC, lst_name ASC';
        $pdoStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId, $gCurrentUserId));

        $listConfigurations = array();
        while ($row = $pdoStatement->fetch()) {
            $listConfigurations[] = array($row['lst_uuid'], $row['lst_name'], (bool) $row['lst_global']);
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
        $filterNavbar = new HtmlNavbar('menu_list_filter', null, null, 'filter');
        $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', $page, array('type' => 'navbar', 'setFocus' => false));
        $form->addSelectBox(
            'list_configurations',
            $gL10n->get('SYS_CONFIGURATION_LIST'),
            $listConfigurations,
            array('defaultValue' => $getListUuid)
        );


        // Only for active members of a role and if user has right to view former members
        if ($hasRightViewFormerMembers) {
            // create filter menu with elements for start-/end date
            $form->addInput('date_from', $gL10n->get('SYS_ROLE_MEMBERSHIP_IN_PERIOD'), $dateFrom, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('date_to', $gL10n->get('SYS_ROLE_MEMBERSHIP_TO'), $dateTo, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('list_uuid', '', $getListUuid, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('rol_ids', '', $getRoleIds, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addInput('urt_ids', '', $getRelationTypeIds, array('property' => HtmlForm::FIELD_HIDDEN));
            $form->addCheckbox('show_former_members', $gL10n->get('SYS_SHOW_FORMER_MEMBERS_ONLY'), $getShowFormerMembers);
            $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
        }

        $filterNavbar->addForm($form->show());
        $page->addHtml($filterNavbar->show());

        $page->addHtml('<h5 class="admidio-content-subheader">'.$htmlSubHeadline.'</h5>');
        $page->addJavascript(
            '
            $("#list_configurations").change(function() {
                elementId = $(this).attr("id");
                roleId    = elementId.substr(elementId.search(/_/) + 1);

                if ($(this).val() === "mylist") {
                    self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/mylist.php', array('rol_ids' => $getRoleIds)) . '";
                } else {
                    self.location.href = "' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/groups-roles/lists_show.php', array('mode' => 'html', 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)) . '&list_uuid=" + $(this).val();
                }
            });

            $("#menu_item_mail_to_list").click(function() {
                redirectPost("'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php", {list_uuid: "'.$getListUuid.'", userUuidList: "'.implode(',', $userUuidList).'"});
                return false;
            });

            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'mode' => 'print', 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo)).'", "_blank");
            });',
            true
        );

        // link to print overlay and exports
        $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');

        // dropdown menu item with all export possibilities
        if ($gSettingsManager->getInt('groups_roles_export') === 1 // all users
        || ($gSettingsManager->getInt('groups_roles_export') === 2 && $gCurrentUser->checkRolesRight('rol_edit_user'))) { // users with the right to edit all profiles
            $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT_TO'), '#', 'fa-file-download');
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_csv_ms',
                $gL10n->get('SYS_MICROSOFT_EXCEL'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'csv-ms')),
                'fa-file-excel',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_pdf',
                $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'pdf')),
                'fa-file-pdf',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_pdfl',
                $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'pdfl')),
                'fa-file-pdf',
                'menu_item_lists_export'
            );
            $page->addPageFunctionsMenuItem(
                'menu_item_lists_csv',
                $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('list_uuid' => $getListUuid, 'rol_ids' => $getRoleIds, 'urt_ids' => $getRelationTypeIds, 'show_former_members' => $getShowFormerMembers, 'date_from' => $getDateFrom, 'date_to' => $getDateTo, 'mode' => 'csv-oo')),
                'fa-file-csv',
                'menu_item_lists_export'
            );
        }

        if ($numberRoles === 1) {
            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser)) {
                $page->addPageFunctionsMenuItem(
                    'menu_item_lists_assign_members',
                    $gL10n->get('SYS_ASSIGN_MEMBERS'),
                    SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/members_assignment.php', array('role_uuid' => $role->getValue('rol_uuid'))),
                    'fa-user-plus'
                );
            }
        }

        // link to email-module
        if ($showLinkMailToList) {
            $page->addPageFunctionsMenuItem(
                'menu_item_mail_to_list',
                $gL10n->get('SYS_EMAIL_TO_LIST'),
                'javascript:void(0);',
                'fa-envelope'
            );
        }

        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
        $table->setDatatablesRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
    } else {
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }
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
    // in html mode we group leaders. Therefore, we need a special hidden column.
    array_unshift($arrColumnNames, $gL10n->get('INS_GROUPS'));
    array_unshift($arrColumnAlign, 'left');

    if ($editUserStatus) {
        // add column for edit link
        $arrColumnNames[] = '&nbsp;';
    }
}

// add column with sequential number
array_unshift($arrColumnNames, $gL10n->get('SYS_ABR_NO'));
array_unshift($arrColumnAlign, 'left');

if ($getMode === 'csv') {
    $csvStr = $valueQuotes . implode($valueQuotes.$separator.$valueQuotes, $arrColumnNames) . $valueQuotes . "\n";
} elseif ($getMode === 'html' || $getMode === 'print') {
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
        $table->addColumn($arrColumnNames[$column], array('style' => 'text-align: '.$arrColumnAlign[$column].'; font-size: 14px; background-color: #c7c7c7;'), 'th');
    }
}

$lastGroupHead = null; // Mark for change between leader and member
$listRowNumber = 1;

foreach ($membersList as $member) {
    $memberIsLeader = (bool) $member['mem_leader'];

    if ($getMode !== 'csv') {
        // in print preview and pdf we group the role leaders and the members and
        // add a specific header for them
        if ($memberIsLeader !== $lastGroupHead && ($memberIsLeader || $lastGroupHead !== null)) {
            if ($memberIsLeader) {
                $title = $gL10n->get('SYS_LEADERS');
            } else {
                // if list has leaders then initialize row number for members
                $listRowNumber = 1;
                $title = $gL10n->get('SYS_PARTICIPANTS');
            }

            if ($getMode === 'print' || $getMode === 'pdf') {
                $table->addRowByArray(array($title), null, array('class' => 'admidio-group-heading'), $list->countColumns() + 1);
            }
            $lastGroupHead = $memberIsLeader;
        }
    }

    // if html mode and the role has leaders then group all data between leaders and members
    if ($getMode === 'html') {
        // TODO set only once (yet it is set x times as members gets displayed)
        if ($memberIsLeader) {
            $table->setDatatablesGroupColumn(2);
        } else {
            $table->setDatatablesColumnsHide(array(2));
        }
    }

    $columnValues = array();

    // Fields of recordset
    for ($columnNumber = 1, $max = $list->countColumns(); $columnNumber <= $max; ++$columnNumber) {
        $column = $list->getColumnObject($columnNumber);

        // in the SQL mem_leader, usr_id and usr_uuid starts before the column
        // the Index to the row must be set to 3 directly
        $sqlColumnNumber = $columnNumber + 2;

        $usfId = 0;
        if ($column->getValue('lsc_usf_id') > 0) {
            // check if customs field and remember
            $usfId = (int) $column->getValue('lsc_usf_id');
        }

        // before adding the first column, add a column with the row number
        if ($columnNumber === 1) {
            if ($getMode === 'html') {
                // add serial
                $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $member['usr_uuid'])).'">'.$listRowNumber.'</a>';
            }
            if (in_array($getMode, array('print', 'pdf'), true)) {
                // add serial
                $columnValues[] = $listRowNumber;
            } else {
                // 1st column may show the serial
                $csvStr .= $valueQuotes.$listRowNumber.$valueQuotes;
            }

            // in html mode we add a column with leader/member information to
            // enable the grouping function of jquery datatables
            if ($getMode === 'html') {
                if ($memberIsLeader) {
                    $columnValues[] = $gL10n->get('SYS_LEADERS');
                } else {
                    $columnValues[] = $gL10n->get('SYS_PARTICIPANTS');
                }
            }
        }

        // fill content with data of database
        if ($getMode === 'csv') {
            $csvStr .= $separator.$valueQuotes . $list->convertColumnContentForOutput($columnNumber, $getMode, (string) $member[$sqlColumnNumber], $member['usr_uuid']) . $valueQuotes;
        } else {
            $columnValues[] = $list->convertColumnContentForOutput($columnNumber, $getMode, (string) $member[$sqlColumnNumber], $member['usr_uuid']);
        }
    }

    if ($editUserStatus) {
        // Get the matching event
        $sql = 'SELECT dat_uuid
                  FROM '.TBL_DATES.'
                 WHERE dat_rol_id = ? -- $roleIds[0]';
        $datesStatement = $gDb->queryPrepared($sql, $roleIds);
        $dateUuid       = $datesStatement->fetchColumn();
        // prepare edit icon
        $columnValues[] = '<a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                data-href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/dates/popup_participation.php', array('dat_uuid' => $dateUuid, 'user_uuid' => $member['usr_uuid'])) . '">
                                <i class="fas fa-edit" data-toggle="tooltip" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
    }

    if ($getMode === 'csv') {
        $csvStr .= "\n";
    } else {
        $table->addRowByArray($columnValues, null, array('nobr' => 'true'));
    }

    ++$listRowNumber;
}  // End-While (end found User)

$filename = '';

// Settings for export file
if ($getMode === 'csv' || $getMode === 'pdf') {
    $filename = $gCurrentOrganization->getValue('org_shortname') . '-' . str_replace('.', '', $roleName);

    // file name in the current directory...
    if ((string) $list->getValue('lst_name') !== '') {
        $filename .= '-' . str_replace('.', '', $list->getValue('lst_name'));
    }

    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;

    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // necessary for IE6 to 8, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}

if ($getMode === 'csv') {
    // download CSV file
    header('Content-Type: text/comma-separated-values; charset='.$charset);

    if ($charset === 'iso-8859-1') {
        echo iconv("UTF-8","ISO-8859-1", $csvStr);
    } else {
        echo $csvStr;
    }
}
// send the new PDF to the User
elseif ($getMode === 'pdf') {
    // output the HTML content
    $pdf->writeHTML($table->getHtmlTable(), true, false, true);

    $file = ADMIDIO_PATH . FOLDER_DATA . '/' . $filename;

    // Save PDF to file
    $pdf->Output($file, 'F');

    // Redirect
    header('Content-Type: application/pdf');

    readfile($file);
    ignore_user_abort(true);

    try {
        FileSystemUtils::deleteFileIfExists($file);
    } catch (RuntimeException $exception) {
        $gLogger->error('Could not delete file!', array('filePath' => $file));
        // TODO
    }
} elseif ($getMode === 'html' || $getMode === 'print') {
    // add table list to the page
    $page->addHtml($table->show());

    // create an infobox for the role
    if ($getMode === 'html' && $numberRoles === 1) {
        $htmlBox = '';

        // only show infobox if additional role information fields are filled
        if ($role->getValue('rol_weekday') > 0
        || (string) $role->getValue('rol_start_date') !== ''
        || (string) $role->getValue('rol_start_time') !== ''
        || (string) $role->getValue('rol_location') !== ''
        || !empty($role->getValue('rol_cost'))
        || !empty($role->getValue('rol_max_members'))) {
            $htmlBox = '
            <div class="card admidio-blog" id="adm_lists_infobox">
                <div class="card-header">'.$gL10n->get('SYS_INFOBOX').': '.$role->getValue('rol_name').'</div>
                <div class="card-body">';
            $form = new HtmlForm('list_infobox_items');
            $form->addStaticControl('infobox_category', $gL10n->get('SYS_CATEGORY'), $role->getValue('cat_name'));

            // Description
            if ((string) $role->getValue('rol_description') !== '') {
                $form->addStaticControl('infobox_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'));
            }

            // Period
            if ((string) $role->getValue('rol_start_date') !== '') {
                $form->addStaticControl('infobox_period', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_DATE_FROM_TO', array($role->getValue('rol_start_date', $gSettingsManager->getString('system_date')), $role->getValue('rol_end_date', $gSettingsManager->getString('system_date')))));
            }

            // Event
            $value = '';
            if ($role->getValue('rol_weekday') > 0) {
                $value = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
            }
            if ((string) $role->getValue('rol_start_time') !== '') {
                $value = $gL10n->get('SYS_FROM_TO', array($role->getValue('rol_start_time', $gSettingsManager->getString('system_time')), $role->getValue('rol_end_time', $gSettingsManager->getString('system_time'))));
            }
            if ($role->getValue('rol_weekday') > 0 || (string) $role->getValue('rol_start_time') !== '') {
                $form->addStaticControl('infobox_date', $gL10n->get('DAT_DATE'), $value);
            }

            // Meeting Point
            if ((string) $role->getValue('rol_location') !== '') {
                $form->addStaticControl('infobox_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
            }

            // Member Fee
            if ((string) $role->getValue('rol_cost') !== '') {
                $form->addStaticControl('infobox_contribution', $gL10n->get('SYS_CONTRIBUTION'), (float) $role->getValue('rol_cost').' '.$gSettingsManager->getString('system_currency'));
            }

            // Fee period
            if ((string) $role->getValue('rol_cost_period') !== '' && $role->getValue('rol_cost_period') != 0) {
                $form->addStaticControl('infobox_contribution_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), TableRoles::getCostPeriods($role->getValue('rol_cost_period')));
            }

            // max participants
            if ((string) $role->getValue('rol_max_members') !== '') {
                $form->addStaticControl('infobox_max_participants', $gL10n->get('SYS_MAX_PARTICIPANTS'), (int) $role->getValue('rol_max_members'));
            }
            $htmlBox .= $form->show();
            $htmlBox .= '</div>
            </div>';
        } // end of infobox

        $page->addHtml($htmlBox);
    }

    // show complete html page
    $page->show();
}
