<?php
/**
 ***********************************************************************************************
 * Show role members list
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode:            Output(html, print, csv-ms, csv-oo, pdf, pdfl)
 * date_from:       Value for the start date of the date range filter (default: current date)
 * date_to:         Value for the end date of the date range filter (default: current date)
 * lst_id:          Id of the list configuration that should be shown.
 *                  If id is null then the default list of the role will be shown.
 * rol_id:          Id of the role whose members should be shown
 * show_members:    0 - (Default) show active members of role
 *                  1 - show former members of role
 *                  2 - show active and former members of role
 * full_screen:     false - (Default) show sidebar, head and page bottom of html page
 *                  true  - Only show the list without any other html unnecessary elements
 ***********************************************************************************************
 */
require_once('../../system/common.php');

unset($list);

// Initialize and check the parameters
$getDateFrom    = admFuncVariableIsValid($_GET, 'date_from',    'date',   array('defaultValue' => DATE_NOW));
$getDateTo      = admFuncVariableIsValid($_GET, 'date_to',      'date',   array('defaultValue' => DATE_NOW));
$getMode        = admFuncVariableIsValid($_GET, 'mode',         'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl')));
$getListId      = admFuncVariableIsValid($_GET, 'lst_id',       'int');
$getRoleIds     = admFuncVariableIsValid($_GET, 'rol_ids',      'string'); // could be int or int[], so string is necessary
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'int');
$getFullScreen  = admFuncVariableIsValid($_GET, 'full_screen',  'bool');

// Create date objects and format dates in system format
$objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
if ($objDateFrom === false)
{
    // check if date_from  has system format
    $objDateFrom = DateTime::createFromFormat($gPreferences['system_date'], $getDateFrom);
}
$dateFrom = $objDateFrom->format($gPreferences['system_date']);
$startDateEnglishFormat = $objDateFrom->format('Y-m-d');

$objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
if ($objDateTo === false)
{
    // check if date_from  has system format
    $objDateTo = DateTime::createFromFormat($gPreferences['system_date'], $getDateTo);
}
$dateTo = $objDateTo->format($gPreferences['system_date']);
$endDateEnglishFormat = $objDateTo->format('Y-m-d');

$roleIds = array_map('intval', array_filter(explode(',', $getRoleIds), 'is_numeric'));
$numberRoles = count($roleIds);

if ($numberRoles === 0)
{
    $gMessage->show($gL10n->get('LST_NO_ROLE_GIVEN'));
}

if($objDateFrom > $objDateTo)
{
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
}

// determine all roles relevant data
$roleName        = $gL10n->get('LST_VARIOUS_ROLES');
$htmlSubHeadline = '';

if ($numberRoles > 1)
{
    $sql = 'SELECT rol_id, rol_name
              FROM '.TBL_ROLES.'
             WHERE rol_id IN ('.implode(',', $roleIds).')';
    $rolesStatement = $gDb->query($sql);
    $rolesData      = $rolesStatement->fetchAll();

    // check if user has right to view all roles
    foreach ($rolesData as $role)
    {
        if (!$gCurrentUser->hasRightViewRole($role['rol_id']))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }

        $htmlSubHeadline .= ', '.$role['rol_name'];
    }

    $htmlSubHeadline = substr($htmlSubHeadline, 2);
}
else
{
    $role = new TableRoles($gDb, $roleIds[0]);

    // check if user has right to view role
    if (!$gCurrentUser->hasRightViewRole($roleIds[0]))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $roleName         = $role->getValue('rol_name');
    $htmlSubHeadline .= $role->getValue('cat_name');
}

// if no list parameter is set then load role default list configuration or system default list configuration
if ($numberRoles === 1 && $getListId === 0)
{
    // set role default list configuration
    $getListId = $role->getDefaultList();

    if ($getListId === 0)
    {
        $gMessage->show($gL10n->get('LST_DEFAULT_LIST_NOT_SET_UP'));
    }
}

// initialize some special mode parameters
$separator   = '';
$valueQuotes = '';
$charset     = '';
$classTable  = '';
$orientation = '';

switch ($getMode)
{
    case 'csv-ms':
        $separator   = ';';  // Microsoft Excel 2007 or new needs a semicolon
        $valueQuotes = '"';  // all values should be set with quotes
        $getMode     = 'csv';
        $charset     = 'iso-8859-1';
        break;
    case 'csv-oo':
        $separator   = ',';   // a CSV file should have a comma
        $valueQuotes = '"';   // all values should be set with quotes
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
        break;
}

// Array to assign names to tables
$arr_col_name = array(
    'usr_login_name' => $gL10n->get('SYS_USERNAME'),
    'usr_photo'      => $gL10n->get('PHO_PHOTO'),
    'mem_begin'      => $gL10n->get('SYS_START'),
    'mem_end'        => $gL10n->get('SYS_END'),
    'mem_leader'     => $gL10n->get('SYS_LEADERS')
);

// Array for valid colums visible for current user.
// Needed for PDF export to set the correct colspan for the layout
// Maybe there are hidden fields.
$arrValidColumns = array();

$mainSql = ''; // Main SQL statement for lists
$str_csv = ''; // CSV file as string
$leiter  = 0;  // Group has leaders

try
{
    // create list configuration object and create a sql statement out of it
    $list = new ListConfiguration($gDb, $getListId);
    $mainSql = $list->getSQL($roleIds, $getShowMembers, $startDateEnglishFormat, $endDateEnglishFormat);
    // echo $mainSql; exit();
}
catch (AdmException $e)
{
    $e->showHtml();
}
// determine the number of users in this list
$listStatement = $gDb->query($mainSql);
$numMembers = $listStatement->rowCount();

// get all members and their data of this list in an array
$membersList = $listStatement->fetchAll();

if ($numMembers == 0)
{
    // Es sind keine Daten vorhanden !
    $gMessage->show($gL10n->get('LST_NO_USER_FOUND'));
}

// define title (html) and headline
$title = $gL10n->get('LST_LIST').' - '.$roleName;
if (strlen($list->getValue('lst_name')) > 0)
{
    $headline = $roleName.' - '.$list->getValue('lst_name');
}
else
{
    $headline = $roleName;
}

// if html mode and last url was not a list view then save this url to navigation stack
if ($getMode === 'html' && strpos($gNavigation->getUrl(), 'lists_show.php') === false)
{
    $gNavigation->addUrl(CURRENT_URL);
}

if ($getMode !== 'csv')
{
    $datatable = false;
    $hoverRows = false;

    if ($getShowMembers === 0)
    {
        $htmlSubHeadline .= ' - '.$gL10n->get('LST_ACTIVE_MEMBERS');
    }
    elseif ($getShowMembers === 1)
    {
        $htmlSubHeadline .= ' - '.$gL10n->get('LST_FORMER_MEMBERS');
    }
    elseif ($getShowMembers === 2)
    {
        $htmlSubHeadline .= ' - '.$gL10n->get('LST_ACTIVE_FORMER_MEMBERS');
    }

    if ($getMode === 'print')
    {
        // create html page object without the custom theme files
        $page = new HtmlPage();
        $page->hideThemeHtml();
        $page->hideMenu();
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $page->addHtml('<h5>'.$htmlSubHeadline.'</h5>');
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }
    elseif ($getMode === 'pdf')
    {
        require_once(SERVER_PATH.'/adm_program/libs/tcpdf/tcpdf.php');
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($headline);

        // remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(0);

        // headline for PDF
        $pdf->SetHeaderData('', '', $headline, '');

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_lists_table', $pdf, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
    }
    elseif ($getMode === 'html')
    {
        $datatable = true;
        $hoverRows = true;

        // create html page object
        $page = new HtmlPage();

        if ($getFullScreen)
        {
            $page->hideThemeHtml();
        }

        $page->setTitle($title);
        $page->setHeadline($headline);

        // Only for active members of a role
        if ($getShowMembers === 0)
        {
            // create filter menu with elements for start-/enddate
            $filterNavbar = new HtmlNavbar('menu_list_filter', null, null, 'filter');
            $form = new HtmlForm('navbar_filter_form', $g_root_path.'/adm_program/modules/lists/lists_show.php', $page, array('type' => 'navbar', 'setFocus' => false));
            $form->addInput('date_from', $gL10n->get('LST_ROLE_MEMBERSHIP_IN_PERIOD'), $dateFrom, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('date_to', $gL10n->get('LST_ROLE_MEMBERSHIP_TO'), $dateTo, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('lst_id', '', $getListId, array('property' => FIELD_HIDDEN));
            $form->addInput('rol_ids', '', $getRoleIds, array('property' => FIELD_HIDDEN));
            $form->addInput('show_members', '', $getShowMembers, array('property' => FIELD_HIDDEN));
            $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
            $filterNavbar->addForm($form->show(false));
            $page->addHtml($filterNavbar->show(false));
        }

        $page->addHtml('<h5>'.$htmlSubHeadline.'</h5>');
        $page->addJavascript('
            $("#export_list_to").change(function () {
                if($(this).val().length > 1) {
                    var result = $(this).val();
                    $(this).prop("selectedIndex",0);
                    self.location.href = "'.$g_root_path.'/adm_program/modules/lists/lists_show.php?" +
                        "lst_id='.$getListId.'&rol_ids='.$getRoleIds.'&mode=" + result + "&show_members='.$getShowMembers.'&date_from='.$getDateFrom.'&date_to='.$getDateTo.'";
                }
            });

            $("#menu_item_print_view").click(function () {
                window.open("'.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&rol_ids='.$getRoleIds.'&mode=print&show_members='.$getShowMembers.'&date_from='.$getDateFrom.'&date_to='.$getDateTo.'", "_blank");
            });', true);

        // get module menu
        $listsMenu = $page->getMenu();

        $listsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

        if ($getFullScreen)
        {
            $listsMenu->addItem('menu_item_normal_picture', $g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&amp;rol_ids='.$getRoleIds.'&amp;mode=html&amp;show_members='.$getShowMembers.'&amp;full_screen=false&amp;date_from='.$getDateFrom.'&date_to='.$getDateTo.'',
                $gL10n->get('SYS_NORMAL_PICTURE'), 'arrow_in.png');
        }
        else
        {
            $listsMenu->addItem('menu_item_full_screen', $g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&amp;rol_ids='.$getRoleIds.'&amp;mode=html&amp;show_members='.$getShowMembers.'&amp;full_screen=true&amp;date_from='.$getDateFrom.'&date_to='.$getDateTo.'',
                $gL10n->get('SYS_FULL_SCREEN'), 'arrow_out.png');
        }

        if ($numberRoles === 1)
        {
            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser))
            {
                $listsMenu->addItem('menu_item_assign_members', $g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$role->getValue('rol_id'),
                    $gL10n->get('SYS_ASSIGN_MEMBERS'), 'add.png');
            }
        }

        // link to print overlay and exports
        $listsMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');

        $form = new HtmlForm('navbar_export_to_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        $selectBoxEntries = array(
            ''       => $gL10n->get('LST_EXPORT_TO').' ...',
            'csv-ms' => $gL10n->get('LST_MICROSOFT_EXCEL').' ('.$gL10n->get('SYS_ISO_8859_1').')',
            'pdf'    => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
            'pdfl'   => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
            'csv-oo' => $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')'
        );
        $form->addSelectBox('export_list_to', null, $selectBoxEntries, array('showContextDependentFirstEntry' => false));
        $listsMenu->addForm($form->show(false));

        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
        $table->setDatatablesRowsPerPage($gPreferences['lists_members_per_page']);
    }
    else
    {
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }
}

// initialize array parameters for table and set the first column for the counter
if ($getMode === 'html')
{
    // in html mode we group leaders. Therefore we need a special hidden column.
    $columnAlign  = array('left', 'left');
    $columnValues = array($gL10n->get('SYS_ABR_NO'), $gL10n->get('INS_GROUPS'));
}
else
{
    $columnAlign  = array('left');
    $columnValues = array($gL10n->get('SYS_ABR_NO'));
}

// headlines for columns
for ($columnNumber = 1; $columnNumber <= $list->countColumns(); ++$columnNumber)
{
    $column = $list->getColumnObject($columnNumber);

    // Find name of the field
    if ($column->getValue('lsc_usf_id') > 0)
    {
        // customs field
        $usf_id = $column->getValue('lsc_usf_id');
        $columnHeader = $gProfileFields->getPropertyById($usf_id, 'usf_name');

        if ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'CHECKBOX'
        ||  $gProfileFields->getPropertyById($usf_id, 'usf_name_intern') === 'GENDER')
        {
            $columnAlign[] = 'center';
        }
        elseif ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'NUMBER'
        ||      $gProfileFields->getPropertyById($usf_id, 'usf_type') === 'DECIMAL')
        {
            $columnAlign[] = 'right';
        }
        else
        {
            $columnAlign[] = 'left';
        }
    }
    else
    {
        $usf_id = 0;
        $columnHeader = $arr_col_name[$column->getValue('lsc_special_field')];
        $columnAlign[] = 'left';
    }

    if ($getMode === 'csv')
    {
        if ($columnNumber === 1)
        {
            // add serial
            $str_csv = $str_csv.$valueQuotes.$gL10n->get('SYS_ABR_NO').$valueQuotes;
        }
    }

    if ($getMode === 'pdf')
    {
        if ($columnNumber === 1)
        {
            // add serial
            $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
        }
    }

    // show hidden fields only for user with rights
    if ($usf_id == 0 || $gCurrentUser->editUsers() || $gProfileFields->getPropertyById($usf_id, 'usf_hidden') == 0)
    {
        if ($getMode === 'csv')
        {
            $str_csv = $str_csv.$separator.$valueQuotes.$columnHeader.$valueQuotes;
        }
        elseif ($getMode === 'pdf')
        {
            $arrValidColumns[] = $columnHeader;
        }
        elseif ($getMode === 'html' || $getMode === 'print')
        {
            $columnValues[] = $columnHeader;
        }
    }
}  // End-For

if ($getMode === 'csv')
{
    $str_csv = $str_csv."\n";
}
elseif ($getMode === 'html' || $getMode === 'print')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
}
elseif ($getMode === 'pdf')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addTableHeader();
    $table->addRow();
    $table->addAttribute('align', 'center');
    $table->addColumn($headline, array('colspan' => count($arrValidColumns)));
    $table->addRow();

    // Write valid column headings
    for ($column = 0; $column < count($arrValidColumns); ++$column)
    {
        $table->addColumn($arrValidColumns[$column], array('style' => 'text-align: '.$columnAlign[$column].';font-size:14;background-color:#C7C7C7;'), 'th');
    }
}
else
{
    $table->addTableBody();
}

$lastGroupHead = -1; // Mark for change between leader and member
$listRowNumber = 1;

foreach ($membersList as $member)
{
    if ($getMode !== 'csv')
    {
        // in print preview and pdf we group the role leaders and the members and
        // add a specific header for them
        if ($lastGroupHead != $member['mem_leader'] && ($member['mem_leader'] != 0 || $lastGroupHead != -1))
        {
            if ($member['mem_leader'] == 1)
            {
                $title = $gL10n->get('SYS_LEADERS');
            }
            else
            {
                // if list has leaders then initialize row number for members
                $listRowNumber = 1;
                $title = $gL10n->get('SYS_PARTICIPANTS');
            }

            if ($getMode === 'print' || $getMode === 'pdf')
            {
                $table->addRowByArray(array($title), null, array('class' => 'admidio-group-heading'), 1, $list->countColumns() + 1);
            }
            $lastGroupHead = $member['mem_leader'];
        }
    }

    // if html mode and the role has leaders then group all data between leaders and members
    if ($getMode === 'html')
    {
        if ($member['mem_leader'] != 0)
        {
            $table->setDatatablesGroupColumn(2);
        }
        else
        {
            $table->setDatatablesColumnsHide(2);
        }
    }

    $columnValues = array();

    // Fields of recordset
    for ($columnNumber = 1; $columnNumber <= $list->countColumns(); ++$columnNumber)
    {
        $column = $list->getColumnObject($columnNumber);

        // in the SQL mem_leader and usr_id starts before the column
        // the Index to the row must be set to 2 directly
        $sqlColumnNumber = $columnNumber + 1;

        if ($column->getValue('lsc_usf_id') > 0)
        {
            // check if customs field and remember
            $b_user_field = true;
            $usf_id = $column->getValue('lsc_usf_id');
        }
        else
        {
            $b_user_field = false;
            $usf_id = 0;
        }

        if ($getMode === 'html' || $getMode === 'print' || $getMode === 'pdf')
        {
            if ($columnNumber === 1)
            {
                // add serial
                $columnValues[] = $listRowNumber;

                // in html mode we add an additional column with leader/member information to
                // enable the grouping function of jquery datatables
                if ($getMode === 'html')
                {
                    if ($member['mem_leader'] == 1)
                    {
                        $columnValues[] = $gL10n->get('SYS_LEADERS');
                    }
                    else
                    {
                        $columnValues[] = $gL10n->get('SYS_PARTICIPANTS');
                    }
                }
            }
        }
        else
        {
            if ($columnNumber === 1)
            {
                // 1st column may show the serial
                $str_csv = $str_csv.$valueQuotes.$listRowNumber.$valueQuotes;
            }
        }

        // hidden fields are only for users with rights
        if ($usf_id == 0 || $gCurrentUser->editUsers() || $gProfileFields->getPropertyById($usf_id, 'usf_hidden') == 0)
        {

            // fill content with data of database
            $content = $member[$sqlColumnNumber];

            /*****************************************************************/
            // in some cases the content must have a special output format
            /*****************************************************************/
            if ($usf_id > 0 && $usf_id == $gProfileFields->getProperty('COUNTRY', 'usf_id'))
            {
                $content = $gL10n->getCountryByCode($member[$sqlColumnNumber]);
            }
            elseif ($column->getValue('lsc_special_field') === 'usr_photo')
            {
                // show user photo
                if ($getMode === 'html' || $getMode === 'print')
                {
                    $content = '<img src="'.$g_root_path.'/adm_program/modules/profile/profile_photo_show.php?usr_id='.$member['usr_id'].'" style="vertical-align: middle;" alt="'.$gL10n->get('LST_USER_PHOTO').'" />';
                }
                if ($getMode === 'csv' && $member[$sqlColumnNumber] != null)
                {
                    $content = $gL10n->get('LST_USER_PHOTO');
                }
            }
            elseif ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'CHECKBOX')
            {
                if ($getMode === 'csv')
                {
                    if ($content == 1)
                    {
                        $content = $gL10n->get('SYS_YES');
                    }
                    else
                    {
                        $content = $gL10n->get('SYS_NO');
                    }
                }
            }
            elseif ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'DATE'
            || $column->getValue('lsc_special_field') === 'mem_begin'
            || $column->getValue('lsc_special_field') === 'mem_end')
            {
                if (strlen($member[$sqlColumnNumber]) > 0)
                {
                    // date must be formated
                    $date = DateTime::createFromFormat('Y-m-d', $member[$sqlColumnNumber]);
                    $content = $date->format($gPreferences['system_date']);
                }
            }
            elseif ($getMode === 'csv'
            &&    ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'DROPDOWN'
                || $gProfileFields->getPropertyById($usf_id, 'usf_type') === 'RADIO_BUTTON'))
            {
                if (strlen($member[$sqlColumnNumber]) > 0)
                {
                    // show selected text of optionfield or combobox
                    $arrListValues = $gProfileFields->getPropertyById($usf_id, 'usf_value_list', 'text');
                    $content = $arrListValues[$member[$sqlColumnNumber]];
                }
            }

            // format value for csv export
            if ($getMode === 'csv')
            {
                $str_csv = $str_csv.$separator.$valueQuotes.$content.$valueQuotes;
            }
            // create output in html layout
            else
            {
                // firstname and lastname get a link to the profile
                if ($getMode === 'html'
                &&    ($usf_id == $gProfileFields->getProperty('LAST_NAME', 'usf_id')
                    || $usf_id == $gProfileFields->getProperty('FIRST_NAME', 'usf_id')))
                {
                    $htmlValue = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usf_id, 'usf_name_intern'), $content, $member['usr_id']);
                    $columnValues[] = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$member['usr_id'].'">'.$htmlValue.'</a>';
                }
                else
                {
                    if ($getMode === 'print'
                    &&    ($gProfileFields->getPropertyById($usf_id, 'usf_type') === 'EMAIL'
                        || $gProfileFields->getPropertyById($usf_id, 'usf_type') === 'PHONE'
                        || $gProfileFields->getPropertyById($usf_id, 'usf_type') === 'URL'))
                    {
                        $columnValues[] = $content;
                    }
                    else
                    {
                        $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usf_id, 'usf_name_intern'), $content, $member['usr_id']);
                    }
                }
            }
        }
    }

    if ($getMode === 'csv')
    {
        $str_csv = $str_csv."\n";
    }else
    {
        $table->addRowByArray($columnValues, null, array('nobr' => 'true'));
    }

    ++$listRowNumber;
}  // End-While (end found User)

// Settings for export file
if ($getMode === 'csv' || $getMode === 'pdf')
{
    // file name in the current directory...
    if (strlen($list->getValue('lst_name')) > 0)
    {
        $filename = $gCurrentOrganization->getValue('org_shortname').'-'.str_replace('.', '', $roleName).'-'.str_replace('.', '', $list->getValue('lst_name')).'.'.$getMode;
    }
    else
    {
        $filename = $gCurrentOrganization->getValue('org_shortname').'-'.str_replace('.', '', $roleName).'.'.$getMode;
    }

    // for IE the filename must have special chars in hexadecimal
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
    {
        $filename = urlencode($filename);
    }

    header('Content-Disposition: attachment; filename="'.$filename.'"');

    // necessary for IE6 to 8, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}

if ($getMode === 'csv')
{
    // download CSV file
    header('Content-Type: text/comma-separated-values; charset='.$charset);

    if ($charset === 'iso-8859-1')
    {
        echo utf8_decode($str_csv);
    }
    else
    {
        echo $str_csv;
    }
}
// send the new PDF to the User
elseif ($getMode === 'pdf')
{
    // output the HTML content
    $pdf->writeHTML($table->getHtmlTable(), true, false, true, false, '');

    // Save PDF to file
    $pdf->Output(SERVER_PATH.'/adm_my_files/'.$filename, 'F');

    // Redirect
    header('Content-Type: application/pdf');

    readfile(SERVER_PATH.'/adm_my_files/'.$filename);
    ignore_user_abort(true);
    unlink(SERVER_PATH.'/adm_my_files/'.$filename);
}
elseif ($getMode === 'html' || $getMode === 'print')
{
    // add table list to the page
    $page->addHtml($table->show(false));

    // create a infobox for the role
    if ($getMode === 'html' && $numberRoles === 1)
    {
        $htmlBox = '';

        // only show infobox if additional role information fields are filled
        if ($role->getValue('rol_weekday') > 0
        || strlen($role->getValue('rol_start_date')) > 0
        || strlen($role->getValue('rol_start_time')) > 0
        || strlen($role->getValue('rol_location')) > 0
        || strlen($role->getValue('rol_cost')) > 0
        || strlen($role->getValue('rol_max_members')) > 0)
        {
            $htmlBox = '
            <div class="panel panel-default" id="adm_lists_infobox">
                <div class="panel-heading">'.$gL10n->get('LST_INFOBOX').': '.$role->getValue('rol_name').'</div>
                <div class="panel-body">';
            $form = new HtmlForm('list_infobox_items', null);
            $form->addStaticControl('infobox_category', $gL10n->get('SYS_CATEGORY'), $role->getValue('cat_name'));

            // Description
            if (strlen($role->getValue('rol_description')) > 0)
            {
                $form->addStaticControl('infobox_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'));
            }

            // Period
            if (strlen($role->getValue('rol_start_date')) > 0)
            {
                $form->addStaticControl('infobox_period', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])));
            }

            // Event
            if ($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
            {
                if ($role->getValue('rol_weekday') > 0)
                {
                    $value = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                }
                if (strlen($role->getValue('rol_start_time')) > 0)
                {
                    $value = $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
                }

                $form->addStaticControl('infobox_date', $gL10n->get('DAT_DATE'), $value);
            }

            // Meeting Point
            if (strlen($role->getValue('rol_location')) > 0)
            {
                $form->addStaticControl('infobox_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
            }

            // Member Fee
            if (strlen($role->getValue('rol_cost')) > 0)
            {
                $form->addStaticControl('infobox_contribution', $gL10n->get('SYS_CONTRIBUTION'), $role->getValue('rol_cost').' '.$gPreferences['system_currency']);
            }

            // Fee period
            if (strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
            {
                $form->addStaticControl('infobox_contribution_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), $role->getCostPeriods($role->getValue('rol_cost_period')));
            }

            // max participants
            if (strlen($role->getValue('rol_max_members')) > 0)
            {
                $form->addStaticControl('infobox_max_participants', $gL10n->get('SYS_MAX_PARTICIPANTS'), $role->getValue('rol_max_members'));
            }
            $htmlBox .= $form->show(false);
            $htmlBox .= '</div>
            </div>';
        } // end of infobox

        $page->addHtml($htmlBox);
    }

    // show complete html page
    $page->show();
}
