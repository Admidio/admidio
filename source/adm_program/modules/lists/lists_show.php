<?php
/******************************************************************************
 * Show role members list
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode   : Output (html, print, csv-ms, csv-oo, pdf, pdfl)
 * lst_id : Id of the list configuration that should be shown.
 *          If id is null then the default list of the role will be shown.
 * rol_id : Id of the role whose members should be shown
 * start  : Position of query recordset where the visual output should start
 * show_members : 0 - (Default) show active members of role
 *                1 - show former members of role
 *                2 - show active and former members of role
 * full_screen  : 0 - (Default) show sidebar, head and page bottom of html page
 *                1 - Only show the list without any other html unnecessary elements
 *
 *****************************************************************************/
 
require_once('../../system/common.php');

// Initialize and check the parameters
$getMode        = admFuncVariableIsValid($_GET, 'mode', 'string', null, true, array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl' ));
$getListId      = admFuncVariableIsValid($_GET, 'lst_id', 'numeric', 0);
$getRoleId      = admFuncVariableIsValid($_GET, 'rol_id', 'numeric', 0);
$getStart       = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getShowMembers = admFuncVariableIsValid($_GET, 'show_members', 'numeric', 0);
$getFullScreen  = admFuncVariableIsValid($_GET, 'full_screen', 'numeric', 0);


// Initialize the content of this parameter (otherwise some servers will keep the content)
unset($role_ids);

if($getRoleId > 0)
{
    $role_ids[] = $getRoleId;
}
else
{
    $role_ids = $_SESSION['role_ids'];
    $getRoleId = $role_ids[0];
}

// Rollenobjekt erzeugen
$role = new TableRoles($gDb, $getRoleId);

//Testen ob Recht zur Listeneinsicht besteht
if($role->viewRole() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// if no list parameter is set then load role default list configuration or system default list configuration
if($getListId == 0)
{
    // set role default list configuration
    $getListId = $role->getDefaultList();
    
    if($getListId == 0)
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

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('usr_login_name' => $gL10n->get('SYS_USERNAME'),
                      'usr_photo'      => $gL10n->get('PHO_PHOTO'),
                      'mem_begin'      => $gL10n->get('SYS_START'),
                      'mem_end'        => $gL10n->get('SYS_END'),
                      'mem_leader'     => $gL10n->get('SYS_LEADER')
                      );

$mainSql      = '';   // enthaelt das Haupt-Sql-Statement fuer die Liste
$str_csv      = '';   // enthaelt die komplette CSV-Datei als String
$leiter       = 0;    // Gruppe besitzt Leiter
$memberStatus = '';

try
{
    // create list configuration object and create a sql statement out of it
    $list = new ListConfiguration($gDb, $getListId);
    $mainSql = $list->getSQL($role_ids, $getShowMembers);
    //echo $mainSql; exit();
}
catch(AdmException $e)
{
    $e->showHtml();
}


// determine the number of users in this list
$resultList = $gDb->query($mainSql);
$numMembers = $gDb->num_rows($resultList);

if($numMembers == 0)
{
    // Es sind keine Daten vorhanden !
    $gMessage->show($gL10n->get('LST_NO_USER_FOUND'));
}

if($numMembers < $getStart)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// define title (html) and headline
$title = $gL10n->get('LST_LIST').' - '. $role->getValue('rol_name');
if(strlen($list->getValue('lst_name')) > 0)
{
    $headline = $role->getValue('rol_name').' - '.$list->getValue('lst_name');
}
else
{
    $headline = $role->getValue('rol_name');
}

// if html mode and last url was not a list view then save this url to navigation stack
if($getMode == 'html' && $getStart == 0 && strpos($gNavigation->getUrl(), 'lists_show.php') === false)
{
    $gNavigation->addUrl(CURRENT_URL);
}

if($getMode != 'csv')
{
    $datatable = false;
    $hoverRows = false;
    
    if($getShowMembers == 0)
    {
        $memberStatus = $gL10n->get('LST_ACTIVE_MEMBERS');
    }
    elseif($getShowMembers == 1)
    {
        $memberStatus = $gL10n->get('LST_FORMER_MEMBERS');
    }
    elseif($getShowMembers == 2)
    {
        $memberStatus = $gL10n->get('LST_ACTIVE_FORMER_MEMBERS');
    }

    if($getMode == 'print')
    {
        // create html page object without the custom theme files
        $page = new HtmlPage();
        $page->excludeThemeHtml();
        $page->setPrintMode();
                
        $page->setTitle($title);
        $page->addHeadline($headline);
    }
    elseif($getMode == 'pdf')
    {
        require_once(SERVER_PATH. '/adm_program/libs/tcpdf/tcpdf.php');
        $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Admidio');
        $pdf->SetTitle($role->getValue('rol_name') . ' - ' . $role->getValue('cat_name'));

        // remove default header/footer
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(false);
 		// set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
		
        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->SetMargins(10, 20, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(0);

        //headline for PDF
        $pdf->SetHeaderData('', '', $headline, '');
		
        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

    }
    elseif($getMode == 'html')
    {
        $datatable = true;
        $hoverRows = true;

        // create html page object
        $page = new HtmlPage();

        if($getFullScreen == true)
        {
            $page->excludeThemeHtml();
        }

        $page->setTitle($title);
        $page->addHeadline($headline);
        
        $page->addHtml('<h5>'.$role->getValue('cat_name').' - '.$memberStatus.'</h5>');
        $page->addJavascript('
            $("#export_list_to").change(function () {
                if($(this).val().length > 1) {
                    self.location.href = "'. $g_root_path. '/adm_program/modules/lists/lists_show.php?" +
                        "lst_id='. $getListId. '&rol_id='. $getRoleId. '&mode=" + $(this).val() + "&show_members='.$getShowMembers.'";
                }
            });
            
            $("#menu_item_print_view").click(function () {
                window.open("'.$g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&mode=print&rol_id='.$getRoleId.'&show_members='.$getShowMembers.'", "_blank");
            });', true);
        
        // create module menu
        $listsMenu = new HtmlNavbar('menu_lists_list');

        $listsMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

        if($getFullScreen == true)
        {
            $listsMenu->addItem('menu_item_normal_picture', $g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&amp;mode=html&amp;rol_id='.$getRoleId.'&amp;show_members='.$getShowMembers.'&amp;full_screen=0', 
                $gL10n->get('SYS_NORMAL_PICTURE'), 'arrow_in.png');
        }
        else
        {
            $listsMenu->addItem('menu_item_full_screen', $g_root_path.'/adm_program/modules/lists/lists_show.php?lst_id='.$getListId.'&amp;mode=html&amp;rol_id='.$getRoleId.'&amp;show_members='.$getShowMembers.'&amp;full_screen=1', 
                $gL10n->get('SYS_FULL_SCREEN'), 'arrow_out.png');
        }
        
        // link to assign or remove members if you are allowed to do it
        if($role->allowedToAssignMembers($gCurrentUser))
        {
            $listsMenu->addItem('menu_item_assign_members', $g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='. $role->getValue('rol_id'), 
                $gL10n->get('SYS_ASSIGN_MEMBERS'), 'add.png');
        }
        
        // link to print overlay and exports
        $listsMenu->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');
        
        $form = new HtmlForm('navbar_export_to_form', '', $page, 'navbar');
        $selectBoxEntries = array('' => $gL10n->get('LST_EXPORT_TO').' ...', 'csv-ms' => $gL10n->get('LST_MICROSOFT_EXCEL').' ('.$gL10n->get('SYS_ISO_8859_1').')', 'pdf' => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')', 
                                  'pdfl' => $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')', 'csv-oo' => $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')');
        $form->addSelectBox('export_list_to', null, $selectBoxEntries);
        $listsMenu->addForm($form->show(false));

        // show module menu
        $page->addHtml($listsMenu->show(false));
    }

    // Create table object for display
    $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);

    if($getMode == 'pdf')
    {
        $table->addAttribute('border', '1');
        $table->addTableHeader();
        $table->addRow();
        $table->addColumn('', array('colspan' => $list->countColumns() + 1));
        $table->addAttribute('align', 'center');
        $table->addData($pdfHtmlHeadline);
        $table->addRow();
    }
    elseif($getMode == 'html')
    {
        $table->setDatatablesRowsPerPage($gPreferences['lists_members_per_page']);
    }
}

// initialize array parameters for table and set the first column for the counter
if($getMode == 'html')
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
for($columnNumber = 1; $columnNumber <= $list->countColumns(); $columnNumber++)
{
    $column = $list->getColumnObject($columnNumber);

    // den Namen des Feldes ermitteln
    if($column->getValue('lsc_usf_id') > 0)
    {
        // benutzerdefiniertes Feld
        $usf_id = $column->getValue('lsc_usf_id');
        $columnHeader = $gProfileFields->getPropertyById($usf_id, 'usf_name');

        if($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'CHECKBOX'
        || $gProfileFields->getPropertyById($usf_id, 'usf_name_intern') == 'GENDER')
        {
            $columnAlign[] = 'center';
        }
        elseif($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'NUMERIC')
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

    // versteckte Felder duerfen nur von Leuten mit entsprechenden Rechten gesehen werden
    if($usf_id == 0
    || $gCurrentUser->editUsers()
    || $gProfileFields->getPropertyById($usf_id, 'usf_hidden') == 0)
    {
        if($getMode == 'csv')
        {
            if($columnNumber == 1)
            {
                // die Laufende Nummer noch davorsetzen
                $str_csv = $str_csv. $valueQuotes. $gL10n->get('SYS_ABR_NO'). $valueQuotes;
            }
            $str_csv = $str_csv. $separator. $valueQuotes. $columnHeader. $valueQuotes;
        }
        elseif($getMode == 'pdf')
        {
            if($columnNumber == 1)
            {
                $table->addColumn($gL10n->get('SYS_ABR_NO'), array('style' => 'text-align: '.$columnAlign[$columnNumber-1].';font-size:14;background-color:#C7C7C7;'), 'th');
            }
            
            $table->addColumn($columnHeader, array('style' => 'text-align: '.$columnAlign[$columnNumber-1].';font-size:14;background-color:#C7C7C7;'), 'th');
        }
        elseif($getMode == 'html' || $getMode == 'print')
        {
            $columnValues[] = $columnHeader;
        }
    }
}  // End-For

if($getMode == 'csv')
{
    $str_csv = $str_csv. "\n";
}
elseif($getMode == 'html' || $getMode == 'print')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
}
else
{
    $table->addTableBody();
}

// set number of first member of this page (leaders are counted separately)
if($getStart > $role->countLeaders())
{
    $listRowNumber = $getStart - $role->countLeaders() + 1;
}
else
{
    $listRowNumber = $getStart + 1;    
}

$lastGroupHead = -1;             // Merker um Wechsel zwischen Leiter und Mitglieder zu merken

// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!$gDb->data_seek($resultList, $getStart))
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

for($j = 0; $j + $getStart < $numMembers; $j++)
{
    if($row = $gDb->fetch_array($resultList))
    {
        if($getMode == 'print' || $getMode == 'pdf')
        {
            // in print preview and pdf we group the role leaders and the members and 
            // add a specific header for them
            if($lastGroupHead != $row['mem_leader']
            && ($row['mem_leader'] != 0 || $lastGroupHead != -1))
            {
                if($row['mem_leader'] == 1)
                {
                    $title = $gL10n->get('SYS_LEADER');
                }
                else
                {
                    // if list has leaders then initialize row number for members
                    $listRowNumber = 1;
                    $title = $gL10n->get('SYS_PARTICIPANTS');
                }
                
                $table->addRowByArray(array($title), null, array('class' => 'group-heading'), 1, ($list->countColumns() + 1));
                $lastGroupHead = $row['mem_leader'];
            }
        }

        // if html mode and the role has leaders then group all data between leaders and members
        if($getMode == 'html')
        {
            if($row['mem_leader'] != 0)
            {
                $table->setDatatablesGroupColumn(2);
            }
            else
            {
                $table->setDatatablesColumnsHide(2);                
            }
        }

        $columnValues = array();

        // Felder zu Datensatz
        for($columnNumber = 1; $columnNumber <= $list->countColumns(); $columnNumber++)
        {
            $column = $list->getColumnObject($columnNumber);

            // da im SQL noch mem_leader und usr_id vor die eigentlichen Spalten kommen,
            // muss der Index auf row direkt mit 2 anfangen
            $sqlColumnNumber = $columnNumber + 1;

            if($column->getValue('lsc_usf_id') > 0)
            {
                // pruefen, ob ein benutzerdefiniertes Feld und Kennzeichen merken
                $b_user_field = true;
                $usf_id = $column->getValue('lsc_usf_id');
            }
            else
            {
                $b_user_field = false;
                $usf_id = 0;
            }

            // versteckte Felder duerfen nur von Leuten mit entsprechenden Rechten gesehen werden
            if($usf_id == 0
            || $gCurrentUser->editUsers()
            || $gProfileFields->getPropertyById($usf_id, 'usf_hidden') == 0)
            {
                if($getMode == 'html' || $getMode == 'print' || $getMode == 'pdf')
                {    
                    if($columnNumber == 1)
                    {
                        // die Laufende Nummer noch davorsetzen
                        $columnValues[] = $listRowNumber;
                        
                        // in html mode we add an additional column with leader/member information to
                        // enable the grouping function of jquery datatables
                        if($getMode == 'html')
                        {
                            if($row['mem_leader'] == 1)
                            {
                                $columnValues[] = $gL10n->get('SYS_LEADER');
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
                    if($columnNumber == 1)
                    {
                        // erste Spalte zeigt lfd. Nummer an
                        $str_csv = $str_csv. $valueQuotes. $listRowNumber. $valueQuotes;
                    }
                }
    
                $content  = '';

                /*****************************************************************/
                // create field content for each field type and output format
                /*****************************************************************/
                if($usf_id == $gProfileFields->getProperty('COUNTRY', 'usf_id') && $usf_id!=0)
                {
                    $content = $gL10n->getCountryByCode($row[$sqlColumnNumber]);
                }
                elseif($column->getValue('lsc_special_field') == 'usr_photo')
                {
                    // show user photo
                    if($getMode == 'html' || $getMode == 'print')
                    {
                        $content = '<img src="'.$g_root_path.'/adm_program/modules/profile/profile_photo_show.php?usr_id='.$row['usr_id'].'" style="vertical-align: middle;" alt="'.$gL10n->get('LST_USER_PHOTO').'" />';
                    }
                    if ($getMode == 'csv' && $row[$sqlColumnNumber] != NULL)
                    {
                        $content = $gL10n->get('LST_USER_PHOTO');
                    }
                }
                elseif($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'DATE'
                ||     $column->getValue('lsc_special_field') == 'mem_begin'
                ||     $column->getValue('lsc_special_field') == 'mem_end') 
                {
                    if(strlen($row[$sqlColumnNumber]) > 0)
                    {
                        // date must be formated
                        $date = new DateTimeExtended($row[$sqlColumnNumber], 'Y-m-d', 'date');
                        $content = $date->format($gPreferences['system_date']);
                    }
                }
                elseif( ($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'DROPDOWN'
                      || $gProfileFields->getPropertyById($usf_id, 'usf_type') == 'RADIO_BUTTON') 
                && $getMode == 'csv')
                {
                    if(strlen($row[$sqlColumnNumber]) > 0)
                    {
                        // show selected text of optionfield or combobox
                        $arrListValues = $gProfileFields->getPropertyById($usf_id, 'usf_value_list', 'text');
                        $content       = $arrListValues[$row[$sqlColumnNumber]];
                    }
                }
                else 
                {
                    $content = $row[$sqlColumnNumber];
                }

                // format value for csv export
                if($getMode == 'csv')
                {
                    $str_csv = $str_csv. $separator. $valueQuotes. $content. $valueQuotes;
                }
                // create output in html layout
                else
                {
                    $content = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usf_id, 'usf_name_intern'), $content, $row['usr_id']);
                    // if empty string pass a whitespace
                    if(strlen($content) > 0)
                    {
                        $columnValues[] = $content;
                    }
                    else
                    {
                        $columnValues[] = '&nbsp;';
                    }
                }
            }
        }

        if($getMode == 'csv')
        {
            $str_csv = $str_csv. "\n";
        }
        elseif($getMode == 'html')
        {
            $table->addRowByArray($columnValues, null, array('style' => 'cursor: pointer', 'onclick' => 'window.location.href=\''. $g_root_path. '/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '\''));
        }
        elseif($getMode == 'print' || $getMode == 'pdf')
        {
            $table->addRowByArray($columnValues, null, array('nobr' => 'true'));
        }

        $listRowNumber++;
    }
}  // End-While (jeder gefundene User)

// Settings for export file
if($getMode == 'csv' || $getMode == 'pdf')
{
    //file name in the current directory...
    if(strlen($list->getValue('lst_name')) > 0)
    {
        $filename = $gCurrentOrganization->getValue('org_shortname'). '-'. str_replace('.', '', $role->getValue('rol_name')). '-'. str_replace('.', '', $list->getValue('lst_name')).'.'.$getMode;
    }
    else
    {
        $filename = $gCurrentOrganization->getValue('org_shortname'). '-'. str_replace('.', '', $role->getValue('rol_name')).'.'.$getMode;
    }
    
     // for IE the filename must have special chars in hexadecimal 
    if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
    {
        $filename = urlencode($filename);
    }

    header('Content-Disposition: attachment; filename="'.$filename.'"');
    
    // neccessary for IE6 to 8, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
    
}

if($getMode == 'csv')
{
    // nun die erstellte CSV-Datei an den User schicken
    header('Content-Type: text/comma-separated-values; charset='.$charset);

    if($charset == 'iso-8859-1')
    {
        echo utf8_decode($str_csv);
    }
    else
    {
        echo $str_csv;
    }
}
// send the new PDF to the User
elseif($getMode == 'pdf')
{
    // output the HTML content
    $pdf->writeHTML($table->getHtmlTable(), true, false, true, false, '');
    
    //Save PDF to file
    $pdf->Output($filename, 'F');
    
    //Redirect
    header('Content-Type: application/pdf');

    readfile($filename);
    ignore_user_abort(true);
    unlink($filename);  
}
elseif($getMode == 'html' || $getMode == 'print')
{    
    // add table list to the page
    $page->addHtml($table->show(false));

    
    // create a infobox for the role
    if($getMode == 'html')
    {
        $htmlBox = '';
         
        // only show infobox if additional role information fields are filled
        if(strlen($role->getValue('rol_start_date')) > 0
        || $role->getValue('rol_weekday') > 0
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
    
                    //Beschreibung
                    if(strlen($role->getValue('rol_description')) > 0)
                    {
                        $form->addStaticControl('infobox_description', $gL10n->get('SYS_DESCRIPTION'), $role->getValue('rol_description'));
                    }
    
                    //Zeitraum
                    if(strlen($role->getValue('rol_start_date')) > 0)
                    {
                        $form->addStaticControl('infobox_period', $gL10n->get('SYS_PERIOD'), $gL10n->get('SYS_DATE_FROM_TO', $role->getValue('rol_start_date', $gPreferences['system_date']), $role->getValue('rol_end_date', $gPreferences['system_date'])));
                    }
    
                    //Termin
                    if($role->getValue('rol_weekday') > 0 || strlen($role->getValue('rol_start_time')) > 0)
                    {
                        if($role->getValue('rol_weekday') > 0)
                        {
                            $value = DateTimeExtended::getWeekdays($role->getValue('rol_weekday')).' ';
                        }
                        if(strlen($role->getValue('rol_start_time')) > 0)
                        {
                            $value = $gL10n->get('LST_FROM_TO', $role->getValue('rol_start_time', $gPreferences['system_time']), $role->getValue('rol_end_time', $gPreferences['system_time']));
                        }
    
                        $form->addStaticControl('infobox_date', $gL10n->get('DAT_DATE'), $value);
                    }
    
                    //Treffpunkt
                    if(strlen($role->getValue('rol_location')) > 0)
                    {
                        $form->addStaticControl('infobox_location', $gL10n->get('SYS_LOCATION'), $role->getValue('rol_location'));
                    }
    
                    //Beitrag
                    if(strlen($role->getValue('rol_cost')) > 0)
                    {
                        $form->addStaticControl('infobox_contribution', $gL10n->get('SYS_CONTRIBUTION'), $role->getValue('rol_cost'). ' '.$gPreferences['system_currency']);
                    }
    
                    //Beitragszeitraum
                    if(strlen($role->getValue('rol_cost_period')) > 0 && $role->getValue('rol_cost_period') != 0)
                    {
                        $form->addStaticControl('infobox_contribution_period', $gL10n->get('SYS_CONTRIBUTION_PERIOD'), $role->getCostPeriods($role->getValue('rol_cost_period')));
                    }
    
                    //maximale Teilnehmerzahl
                    if(strlen($role->getValue('rol_max_members')) > 0)
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

?>