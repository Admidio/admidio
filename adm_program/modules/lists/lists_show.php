<?php
/**
 ***********************************************************************************************
 * Show role members list
 *
 * @copyright 2004-2016 The Admidio Team
 * @see https://www.admidio.org/
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
$getDateFrom        = admFuncVariableIsValid($_GET, 'date_from',    'date',   array('defaultValue' => DATE_NOW));
$getDateTo          = admFuncVariableIsValid($_GET, 'date_to',      'date',   array('defaultValue' => DATE_NOW));
$getMode            = admFuncVariableIsValid($_GET, 'mode',         'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl')));
$getListId          = admFuncVariableIsValid($_GET, 'lst_id',       'int');
$getRoleIds         = admFuncVariableIsValid($_GET, 'rol_ids',      'string'); // could be int or int[], so string is necessary
$getShowMembers     = admFuncVariableIsValid($_GET, 'show_members', 'int');
$getRelationtypeIds = admFuncVariableIsValid($_GET, 'urt_ids',      'string'); // could be int or int[], so string is necessary
$getFullScreen      = admFuncVariableIsValid($_GET, 'full_screen',  'bool');

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
    // => EXIT
}

if($objDateFrom > $objDateTo)
{
    $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    // => EXIT
}

// determine all roles relevant data
$roleName        = $gL10n->get('LST_VARIOUS_ROLES');
$htmlSubHeadline = '';
$showLinkMailToList = true;

if ($numberRoles > 1)
{
    $sql = 'SELECT rol_id, rol_name
              FROM '.TBL_ROLES.'
             WHERE rol_id IN ('.implode(',', $roleIds).')';
    $rolesStatement = $gDb->query($sql);
    $rolesData      = $rolesStatement->fetchAll();

    foreach ($rolesData as $role)
    {
    	// check if user has right to view all roles
        if (!$gCurrentUser->hasRightViewRole($role['rol_id']))
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        }
        
      	// check if user has right to send mail to role
    	if (!$gCurrentUser->hasRightSendMailToRole($role['rol_id']))
        {
            $showLinkMailToList = false;
            // => do not show the link
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
        // => EXIT
    }

	// check if user has right to send mail to role
    if (!$gCurrentUser->hasRightSendMailToRole($roleIds[0]))
    {
    	$showLinkMailToList = false;
        // => do not show the link
    }
    
    $roleName         = $role->getValue('rol_name');
    $htmlSubHeadline .= $role->getValue('cat_name');
}

$relationtypeName = '';
$relationtypeIds = array_map('intval', array_filter(explode(',', $getRelationtypeIds), 'is_numeric'));
if (count($relationtypeIds) > 0)
{
    $sql = 'SELECT urt_id, urt_name
              FROM '.TBL_USER_RELATION_TYPES.'
             WHERE urt_id IN ('.implode(',', $relationtypeIds).')
          ORDER BY urt_name';
    $relationtypesStatement = $gDb->query($sql);
    while($relationtype = $relationtypesStatement->fetch())
    {
        $relationtypeName .= (empty($relationtypeName) ? '' : ', ').$relationtype['urt_name'];
    }
}

// if no list parameter is set then load role default list configuration or system default list configuration
if ($numberRoles === 1 && $getListId === 0)
{
    // set role default list configuration
    $getListId = $role->getDefaultList();

    if ($getListId === 0)
    {
        $gMessage->show($gL10n->get('LST_DEFAULT_LIST_NOT_SET_UP'));
        // => EXIT
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
$arrColName = array(
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
$csvStr = ''; // CSV file as string

try
{
    // create list configuration object and create a sql statement out of it
    $list = new ListConfiguration($gDb, $getListId);
    $mainSql = $list->getSQL($roleIds, $getShowMembers, $startDateEnglishFormat, $endDateEnglishFormat, $relationtypeIds);
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

if ($numMembers === 0)
{
    // Es sind keine Daten vorhanden !
    $gMessage->show($gL10n->get('LST_NO_USER_FOUND'));
    // => EXIT
}

$userIdList = array();
foreach ($membersList as $member)
{
    $user = new User($gDb, $gProfileFields, $member['usr_id']);
    
    // besitzt der User eine gueltige E-Mail-Adresse? && aktuellen User ausschließen
    if (strValidCharacters($user->getValue('EMAIL'), 'email') && $gCurrentUser->getValue('usr_id')<>$member['usr_id'])
    {
        $userIdList[] = $member['usr_id'];
    }
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

if (count($relationtypeIds) === 1)
{
    $headline .= ' - '.$relationtypeName;
}
elseif (count($relationtypeIds) > 1)
{
    $headline .= ' - '.$gL10n->get('LST_VARIOUS_USER_RELATION_TYPES');
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

    if (count($relationtypeIds) > 1)
    {
        $htmlSubHeadline .= ' - '.$relationtypeName;
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
        require_once(ADMIDIO_PATH . FOLDER_LIBS_SERVER . '/tcpdf/tcpdf.php');
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
        $pdf->setHeaderMargin(10);
        $pdf->setFooterMargin(0);

        // headline for PDF
        $pdf->setHeaderData('', '', $headline, '');

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_lists_table', null, $hoverRows, $datatable, $classTable);
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
            $form = new HtmlForm('navbar_filter_form', ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php', $page, array('type' => 'navbar', 'setFocus' => false));
            $form->addInput('date_from', $gL10n->get('LST_ROLE_MEMBERSHIP_IN_PERIOD'), $dateFrom, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('date_to', $gL10n->get('LST_ROLE_MEMBERSHIP_TO'), $dateTo, array('type' => 'date', 'maxLength' => 10));
            $form->addInput('lst_id', '', $getListId, array('property' => FIELD_HIDDEN));
            $form->addInput('rol_ids', '', $getRoleIds, array('property' => FIELD_HIDDEN));
            $form->addInput('show_members', '', $getShowMembers, array('property' => FIELD_HIDDEN));
            $form->addSubmitButton('btn_send', $gL10n->get('SYS_OK'));
            $filterNavbar->addForm($form->show(false));
            $page->addHtml($filterNavbar->show());
        }

        $page->addHtml('<h5>'.$htmlSubHeadline.'</h5>');
        $page->addJavascript('
            $("#export_list_to").change(function () {
                if($(this).val().length > 1) {
                    var result = $(this).val();
                    $(this).prop("selectedIndex",0);
                    self.location.href = "'.ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?" +
                        "lst_id='.$getListId.'&rol_ids='.$getRoleIds.'&mode=" + result + "&show_members='.$getShowMembers.'&date_from='.$getDateFrom.'&date_to='.$getDateTo.'";
                }
            });

            $("#menu_item_mail_to_list").click(function () {
            	$("#page").load("'.ADMIDIO_URL.FOLDER_MODULES.'/messages/messages_write.php", {lst_id : "'.$getListId.'", userIdList : "'.implode(',', $userIdList).'" } );
            	return false;
            });

            $("#menu_item_print_view").click(function () {
                window.open("'.ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?lst_id='.$getListId.'&rol_ids='.$getRoleIds.'&mode=print&show_members='.$getShowMembers.'&date_from='.$getDateFrom.'&date_to='.$getDateTo.'", "_blank");
            });', true);

        // get module menu
        $listsMenu = $page->getMenu();

        $listsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

        if ($getFullScreen)
        {
            $listsMenu->addItem('menu_item_normal_picture', ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?lst_id='.$getListId.'&amp;rol_ids='.$getRoleIds.'&amp;mode=html&amp;show_members='.$getShowMembers.'&amp;full_screen=false&amp;date_from='.$getDateFrom.'&date_to='.$getDateTo.'',
                $gL10n->get('SYS_NORMAL_PICTURE'), 'arrow_in.png');
        }
        else
        {
            $listsMenu->addItem('menu_item_full_screen', ADMIDIO_URL.FOLDER_MODULES.'/lists/lists_show.php?lst_id='.$getListId.'&amp;rol_ids='.$getRoleIds.'&amp;mode=html&amp;show_members='.$getShowMembers.'&amp;full_screen=true&amp;date_from='.$getDateFrom.'&date_to='.$getDateTo.'',
                $gL10n->get('SYS_FULL_SCREEN'), 'arrow_out.png');
        }

        if ($numberRoles === 1)
        {
            // link to assign or remove members if you are allowed to do it
            if ($role->allowedToAssignMembers($gCurrentUser))
            {
                $listsMenu->addItem('menu_item_assign_members', ADMIDIO_URL.FOLDER_MODULES.'/lists/members_assignment.php?rol_id='.$role->getValue('rol_id'),
                    $gL10n->get('SYS_ASSIGN_MEMBERS'), 'add.png');
            }
        }

        // link to print overlay and exports
        $listsMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');

        //link to email-module
        if($showLinkMailToList)
        {
        	//$listsMenu->addHtml('div id="dd" ');
        	$listsMenu->addItem('menu_item_mail_to_list', '', $gL10n->get('LST_EMAIL_TO_LIST'), 'email.png');
        }
        
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
for ($columnNumber = 1, $iMax = $list->countColumns(); $columnNumber <= $iMax; ++$columnNumber)
{
    $column = $list->getColumnObject($columnNumber);

    // Find name of the field
    if ($column->getValue('lsc_usf_id') > 0)
    {
        // customs field
        $usfId = (int) $column->getValue('lsc_usf_id');
        $columnHeader = $gProfileFields->getPropertyById($usfId, 'usf_name');

        if ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX'
        ||  $gProfileFields->getPropertyById($usfId, 'usf_name_intern') === 'GENDER')
        {
            $columnAlign[] = 'center';
        }
        elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'NUMBER'
        ||      $gProfileFields->getPropertyById($usfId, 'usf_type') === 'DECIMAL')
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
        $usfId = 0;
        $columnHeader = $arrColName[$column->getValue('lsc_special_field')];
        $columnAlign[] = 'left';
    }

    if ($getMode === 'csv' && $columnNumber === 1)
    {
        // add serial
        $csvStr .= $valueQuotes.$gL10n->get('SYS_ABR_NO').$valueQuotes;
    }

    if ($getMode === 'pdf' && $columnNumber === 1)
    {
        // add serial
        $arrValidColumns[] = $gL10n->get('SYS_ABR_NO');
    }

    // show hidden fields only for user with rights
    if ($usfId === 0 || $gCurrentUser->editUsers() || $gProfileFields->getPropertyById($usfId, 'usf_hidden') == 0)
    {
        if ($getMode === 'csv')
        {
            $csvStr .= $separator.$valueQuotes.$columnHeader.$valueQuotes;
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
    $csvStr .= "\n";
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
    for ($column = 0, $max = count($arrValidColumns); $column < $max; ++$column)
    {
        $table->addColumn($arrValidColumns[$column], array('style' => 'text-align: '.$columnAlign[$column].';font-size:14;background-color:#C7C7C7;'), 'th');
    }
}
else
{
    $table->addTableBody();
}

$lastGroupHead = null; // Mark for change between leader and member
$listRowNumber = 1;

foreach ($membersList as $member)
{
    $memberIsLeader = (bool) $member['mem_leader'];

    if ($getMode !== 'csv')
    {
        // in print preview and pdf we group the role leaders and the members and
        // add a specific header for them
        if ($memberIsLeader !== $lastGroupHead && ($memberIsLeader || $lastGroupHead !== null))
        {
            if ($memberIsLeader)
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
                $table->addRowByArray(array($title), null, array('class' => 'admidio-group-heading'), $list->countColumns() + 1);
            }
            $lastGroupHead = $memberIsLeader;
        }
    }

    // if html mode and the role has leaders then group all data between leaders and members
    if ($getMode === 'html')
    {
        // TODO set only once (yet it is set x times as members gets displayed)
        if ($memberIsLeader)
        {
            $table->setDatatablesGroupColumn(2);
        }
        else
        {
            $table->setDatatablesColumnsHide(array(2));
        }
    }

    $columnValues = array();

    // Fields of recordset
    for ($columnNumber = 1, $max = $list->countColumns(); $columnNumber <= $max; ++$columnNumber)
    {
        $column = $list->getColumnObject($columnNumber);

        // in the SQL mem_leader and usr_id starts before the column
        // the Index to the row must be set to 2 directly
        $sqlColumnNumber = $columnNumber + 1;

        if ($column->getValue('lsc_usf_id') > 0)
        {
            // check if customs field and remember
            $b_user_field = true;
            $usfId = (int) $column->getValue('lsc_usf_id');
        }
        else
        {
            $b_user_field = false;
            $usfId = 0;
        }

        if ($columnNumber === 1)
        {
            if ($getMode === 'html' || $getMode === 'print' || $getMode === 'pdf')
            {
                // add serial
                $columnValues[] = $listRowNumber;
            }
            else
            {
                // 1st column may show the serial
                $csvStr .= $valueQuotes.$listRowNumber.$valueQuotes;
            }

            // in html mode we add an additional column with leader/member information to
            // enable the grouping function of jquery datatables
            if ($getMode === 'html')
            {
                if ($memberIsLeader)
                {
                    $columnValues[] = $gL10n->get('SYS_LEADERS');
                }
                else
                {
                    $columnValues[] = $gL10n->get('SYS_PARTICIPANTS');
                }
            }
        }

        // hidden fields are only for users with rights
        if ($usfId === 0 || $gCurrentUser->editUsers() || $gProfileFields->getPropertyById($usfId, 'usf_hidden') == 0)
        {

            // fill content with data of database
            $content = $member[$sqlColumnNumber];

            /*****************************************************************/
            // in some cases the content must have a special output format
            /*****************************************************************/
            if ($usfId > 0 && $usfId === (int) $gProfileFields->getProperty('COUNTRY', 'usf_id'))
            {
                $content = $gL10n->getCountryByCode($member[$sqlColumnNumber]);
            }
            elseif ($column->getValue('lsc_special_field') === 'usr_photo')
            {
                // show user photo
                if ($getMode === 'html' || $getMode === 'print')
                {
                    $content = '<img src="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile_photo_show.php?usr_id='.$member['usr_id'].'" style="vertical-align: middle;" alt="'.$gL10n->get('LST_USER_PHOTO').'" />';
                }
                if ($getMode === 'csv' && $member[$sqlColumnNumber] != null)
                {
                    $content = $gL10n->get('LST_USER_PHOTO');
                }
            }
            elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX')
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
                elseif($content != 1)
                {
                    $content = 0;
                }
            }
            elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'DATE'
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
            &&    ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'DROPDOWN'
                || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'RADIO_BUTTON'))
            {
                if (strlen($member[$sqlColumnNumber]) > 0)
                {
                    // show selected text of optionfield or combobox
                    $arrListValues = $gProfileFields->getPropertyById($usfId, 'usf_value_list', 'text');
                    $content = $arrListValues[$member[$sqlColumnNumber]];
                }
            }

            // format value for csv export
            if ($getMode === 'csv')
            {
                $csvStr .= $separator.$valueQuotes.$content.$valueQuotes;
            }
            // create output in html layout
            else
            {
                // firstname and lastname get a link to the profile
                if ($getMode === 'html'
                &&    ($usfId === (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id')
                    || $usfId === (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id')))
                {
                    $htmlValue = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member['usr_id']);
                    $columnValues[] = '<a href="'.ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php?user_id='.$member['usr_id'].'">'.$htmlValue.'</a>';
                }
                else
                {
                    // within print mode no links should be set
                    if ($getMode === 'print'
                    &&    ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'EMAIL'
                        || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'PHONE'
                        || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'URL'))
                    {
                        $columnValues[] = $content;
                    }
                    else
                    {
                        // checkbox must set a sorting value
                        if($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX')
                        {
                            $columnValues[] = array('value' => $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member['usr_id']), 'order' => $content);
                        }
                        else
                        {
                            $columnValues[] = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $member['usr_id']);
                        }
                    }
                }
            }
        }
    }

    if ($getMode === 'csv')
    {
        $csvStr .= "\n";
    }
    else
    {
        $table->addRowByArray($columnValues, null, array('nobr' => 'true'));
    }

    ++$listRowNumber;
}  // End-While (end found User)

$filename = '';

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
        echo utf8_decode($csvStr);
    }
    else
    {
        echo $csvStr;
    }
}
// send the new PDF to the User
elseif ($getMode === 'pdf')
{
    // output the HTML content
    $pdf->writeHTML($table->getHtmlTable(), true, false, true, false, '');

    $file = ADMIDIO_PATH . FOLDER_DATA . '/' . $filename;

    // Save PDF to file
    $pdf->Output($file, 'F');

    // Redirect
    header('Content-Type: application/pdf');

    readfile($file);
    ignore_user_abort(true);
    unlink($file);
}
elseif ($getMode === 'html' || $getMode === 'print')
{
    // add table list to the page
    $page->addHtml($table->show());

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
            $value = '';
            if ($role->getValue('rol_weekday') > 0)
            {
                $value = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
            }
            if (strlen($role->getValue('rol_start_time')) > 0)
            {
                $value = $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
            }
            if ($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
            {
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
                $form->addStaticControl('infobox_contribution_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), TableRoles::getCostPeriods($role->getValue('rol_cost_period')));
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
