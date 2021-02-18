<?php
/**
 ***********************************************************************************************
 * Kategoriereport
 *
 * Version 3.1.1
 *
 * Dieses Plugin erzeugt eine Auflistung aller Rollenzugehoerigkeiten eines Mitglieds.
 * 
 * Author: rmb
 *
 * Compatible with Admidio version 4
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *   
 * Parameters:
 *
 * mode   		    : Output (html, print, csv-ms, csv-oo, pdf, pdfl)
 * export_features  : 0 - (Default) No export menu
 *                    1 - Export menu is enabled
 * config		    : the selected configuration
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');
require_once(__DIR__ . '/classes/genreport.php');

//$scriptName ist der Name wie er im Menue eingetragen werden muss, also ohne evtl. vorgelagerte Ordner wie z.B. /playground/adm_plugins/kategoriereport...
$scriptName = substr($_SERVER['SCRIPT_NAME'], strpos($_SERVER['SCRIPT_NAME'], FOLDER_PLUGINS));

// only authorized user are allowed to start this module
if (!isUserAuthorized($scriptName))
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Konfiguration einlesen          
$pPreferences = new ConfigTablePKR();
if ($pPreferences->checkforupdate())
{
	$pPreferences->init();
}
else
{
	$pPreferences->read();
}

// Initialize and check the parameters
$validValues = array();
foreach ($pPreferences->config['Konfigurationen']['col_desc'] as $key => $dummy)
{
	$validValues[] = 'X'.$key.'X';
}
$getConfig          = admFuncVariableIsValid($_GET, 'config', 'string', array('defaultValue' => 'X'.$pPreferences->config['Optionen']['config_default'].'X', 'validValues' => $validValues) );
$getMode            = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('csv-ms', 'csv-oo', 'html', 'print', 'pdf', 'pdfl' )));
$getFilter          = admFuncVariableIsValid($_GET, 'filter', 'string');
$getExportAndFilter = admFuncVariableIsValid($_GET, 'export_and_filter', 'bool', array('defaultValue' => false));

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
        break;
}

// CSV file as string
$csvStr = ''; 

//die Anzeigeliste erzeugen 
$report = new GenReport();
$report->conf = trim($getConfig,'X');
$report->generate_listData();

$numMembers = count($report->listData);

if ($numMembers == 0)
{
    // Es sind keine Daten vorhanden !
    $gMessage->show($gL10n->get('SYS_NO_USER_FOUND'));
}

//die Spaltenanzahl bestimmen
$columnCount = count($report->headerData);
    
// define title (html) and headline
$title       = $gL10n->get('PLG_KATEGORIEREPORT_CATEGORY_REPORT');
$headline    = $gL10n->get('PLG_KATEGORIEREPORT_CATEGORY_REPORT');
$subheadline = $pPreferences->config['Konfigurationen']['col_desc'][trim($getConfig,'X')];   

$filename    = $g_organization.'-'.$headline.'-'.$subheadline;

if ($getMode === 'html' )
{
    $gNavigation->addStartUrl(CURRENT_URL, $headline);
}

if ($getMode !== 'csv')
{
    $datatable = false;
    $hoverRows = false;

    if ($getMode === 'print')
    {
        $page = new HtmlPage('plg-kategoriereport-main-print');
        $page->setPrintMode();
        $page->setTitle($title);
        $page->setHeadline($headline);
        $page->addHtml('<h5>'.$subheadline.'</h5>');
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }
    elseif ($getMode === 'pdf')
    {
        if (ini_get('max_execution_time')<600)
    	{
    		ini_set('max_execution_time', 600); //600 seconds = 10 minutes
    	}
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
        $pdf->setHeaderData('', 0, $headline);

        // set font
        $pdf->SetFont('times', '', 10);

        // add a page
        $pdf->AddPage();

        // Create table object for display
        $table = new HtmlTable('adm_lists_table', null, $hoverRows, $datatable, $classTable);
        $table->addAttribute('border', '1');
        
        $table->addTableHeader();
        $table->addRow();
        $table->addAttribute('align', 'center');
        $table->addColumn($subheadline, array('colspan' => $columnCount + 1));
        $table->addRow();
    }
    elseif ($getMode === 'html')
    {
        if ($getExportAndFilter)
        {
            $datatable = false;
        }
        else
        {
            $datatable = true;
        }
        
        $hoverRows = true;

        // create html page object
        $page = new HtmlPage('plg-kategoriereport-main-html');
        $page->setTitle($title);
        $page->setHeadline($headline);
        $page->addHtml('<h5>'.$subheadline.'</h5>');
        
        $page->addJavascript('
            $("#menu_item_lists_print_view").click(function() {
                window.open("'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array(
                    'mode'              => 'print',
                    'filter'            => $getFilter, 
                    'export_and_filter' => $getExportAndFilter,
                    'config'            => $getConfig
                )).'", "_blank");
            });',
            true
        );

        if ($getExportAndFilter)
        {
            // link to print overlay and exports
            $page->addPageFunctionsMenuItem('menu_item_lists_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void(0);', 'fa-print');

            // dropdown menu item with all export possibilities
            $page->addPageFunctionsMenuItem('menu_item_lists_export', $gL10n->get('SYS_EXPORT_TO'), '#', 'fa-file-download');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv_ms', $gL10n->get('SYS_MICROSOFT_EXCEL'),
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array(
                    'config'            => $getConfig,
                    'filter'            => $getFilter, 
                    'export_and_filter' => $getExportAndFilter,
                    'mode'              => 'csv-ms')),
                'fa-file-excel', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdf', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_PORTRAIT').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array(
                    'config'            => $getConfig,
                    'filter'            => $getFilter, 
                    'export_and_filter' => $getExportAndFilter,
                    'mode'              => 'pdf')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_pdfl', $gL10n->get('SYS_PDF').' ('.$gL10n->get('SYS_LANDSCAPE').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array(
                    'config'            => $getConfig,
                    'filter'            => $getFilter, 
                    'export_and_filter' => $getExportAndFilter,
                    'mode'              => 'pdfl')),
                'fa-file-pdf', 'menu_item_lists_export');
            $page->addPageFunctionsMenuItem('menu_item_lists_csv', $gL10n->get('SYS_CSV').' ('.$gL10n->get('SYS_UTF8').')',
                SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array(
                    'config'            => $getConfig,
                    'filter'            => $getFilter, 
                    'export_and_filter' => $getExportAndFilter,
                    'mode'              => 'csv-oo')),
                'fa-file-csv', 'menu_item_lists_export');
        }
        else
        {
            // if filter is not enabled, reset filterstring
            $getFilter = '';
        }
        
        if ($gCurrentUser->isAdministrator())
		{
    		// show link to pluginpreferences 
    		$page->addPageFunctionsMenuItem('admMenuItemPreferencesLists', $gL10n->get('PLG_KATEGORIEREPORT_SETTINGS'),
    		    ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php',  'fa-cog');
		}

		// process changes in the navbar form with javascript submit
		$page->addJavascript('
            $("#export_and_filter").change(function() {
                $("#navbar_catreport_form").submit();
            });
            $("#config").change(function() {
                $("#navbar_catreport_form").submit();
            });',
		    true
		);
		
		foreach ($pPreferences->config['Konfigurationen']['col_desc'] as $key => $item)
		{
		    $selectBoxEntries['X'.$key.'X'] = $item;
		}
	
		$form = new HtmlForm('navbar_catreport_form', SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_PLUGINS . PLUGIN_FOLDER .'/kategoriereport.php', array('headline' => $headline)), $page, array('type' => 'navbar', 'setFocus' => false));
		$form->addSelectBox('config', $gL10n->get('PLG_KATEGORIEREPORT_SELECT_CONFIGURATION'), $selectBoxEntries, array('showContextDependentFirstEntry' => false,'defaultValue' => $getConfig));
		
        if ($getExportAndFilter)
        {
            $form->addInput('filter', $gL10n->get('SYS_FILTER'), $getFilter);
        }
        $form->addCheckbox('export_and_filter', $gL10n->get('PLG_KATEGORIEREPORT_EXPORT_AND_FILTER'), $getExportAndFilter);

		$page->addHtml($form->show());
		
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
        $table->setDatatablesRowsPerPage($gSettingsManager->getInt('groups_roles_members_per_page'));
    }
    else
    {
        $table = new HtmlTable('adm_lists_table', $page, $hoverRows, $datatable, $classTable);
    }
}

$columnAlign  = array('center');
$columnValues = array($gL10n->get('SYS_ABR_NO'));
$columnNumber = 1;  
 
foreach ($report->headerData as $columnHeader) 
{
	// bei Profilfeldern ist in 'id' die usf_id, ansonsten 0
	$usf_id = $columnHeader['id'];
	
    if ($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'NUMBER'
        || $gProfileFields->getPropertyById($usf_id, 'usf_type') == 'DECIMAL_NUMBER')
    {
        $columnAlign[] = 'right';
    }
    else
    {
    	$columnAlign[] = 'center';    
    }
	 
    if ($getMode == 'csv')
    {
    	if ($columnNumber === 1)
        {
        	// in der ersten Spalte die laufende Nummer noch davorsetzen
            $csvStr .= $valueQuotes. $gL10n->get('SYS_ABR_NO'). $valueQuotes;
        }
        $csvStr .= $separator. $valueQuotes. $columnHeader['data']. $valueQuotes;
    }
    elseif ($getMode == 'pdf')
    {
    	if ($columnNumber === 1)
        {
        	$table->addColumn($gL10n->get('SYS_ABR_NO'), array('style' => 'text-align: center;font-size:14;background-color:#C7C7C7;'), 'th');
        }
        $table->addColumn($columnHeader['data'], array('style' => 'text-align: center;font-size:14;background-color:#C7C7C7;'), 'th');
    }
    elseif ($getMode == 'html' || $getMode == 'print')
    {
    	$columnValues[] = $columnHeader['data'];
    }
    $columnNumber++;
} 

if ($getMode === 'csv')
{
    $csvStr .= "\n";
}
elseif ($getMode === 'html' || $getMode === 'print')
{
    $table->setColumnAlignByArray($columnAlign);
    $table->addRowHeadingByArray($columnValues);
}
else
{
    $table->addTableBody();
      $table->setColumnAlignByArray($columnAlign);
}

$listRowNumber = 1;

// die Daten einlesen
foreach ($report->listData as $member => $memberdata) 
{
	$columnValues = array();
    $tmp_csv = '';

    // Felder zu Datensatz
    $columnNumber = 1;
    foreach ($memberdata as $key => $content) 
    {         
    	if ($getMode == 'html' || $getMode == 'print' || $getMode == 'pdf')
        {    
        	if ($columnNumber === 1)
            {
            	// die Laufende Nummer noch davorsetzen
                $columnValues[] = $listRowNumber;  
            }
        }
        else
        {
            if ($columnNumber === 1)
            {
                // erste Spalte zeigt lfd. Nummer an
                $tmp_csv .= $valueQuotes. $listRowNumber. $valueQuotes;
            }
        }
         
        /*****************************************************************/
        // create output format
       	/*****************************************************************/
        
        // format value for csv export
        $usf_id = 0;
        $usf_id = $report->headerData[$key]['id'];
      
        if ($usf_id !== 0 
         && $getMode == 'csv'
         && $content > 0
         && ($gProfileFields->getPropertyById($usf_id, 'usf_type') == 'DROPDOWN'
              || $gProfileFields->getPropertyById($usf_id, 'usf_type') == 'RADIO_BUTTON') )
        {
            // show selected text of optionfield or combobox
            $arrListValues = $gProfileFields->getPropertyById($usf_id, 'usf_value_list', 'text');
            $content       = $arrListValues[$content];
        }

        if ($getMode == 'csv')
        {
        	$tmp_csv .= $separator. $valueQuotes. $content. $valueQuotes;
        }
        else                   // create output in html layout
        {            
        	if ($usf_id !== 0)     //only profileFields
        	{
        		$content = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usf_id, 'usf_name_intern'), $content, $member);
        		if ($getMode === 'html'
        			&&    ($usf_id === (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id')
        				|| $usf_id === (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id')))
        		{
        			$content = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_id' => $member)).'">'.$content.'</a>';
        		}
        	}
       	
            // if empty string pass a whitespace
			if (strlen($content) > 0)
            {
            	$columnValues[] = $content;
			}
            else
            {
            	$columnValues[] = '&nbsp;';
            }
		}
		$columnNumber++;
    }

	if ($getFilter == '' || ($getFilter <> '' && (stristr(implode('',$columnValues), $getFilter  ) || stristr($tmp_csv, $getFilter))))
    {
		if ($getMode == 'csv')
   	 	{
    		$csvStr .= $tmp_csv. "\n";
    	}
   	 	else
    	{
        	$table->addRowByArray($columnValues, null, array('nobr' => 'true'));
    	}
        $listRowNumber++;
    }
}  // End-For (jeder gefundene User)

// Settings for export file
if ($getMode === 'csv' || $getMode === 'pdf')
{
    $filename = FileSystemUtils::getSanitizedPathEntry($filename) . '.' . $getMode;

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
    $pdf->writeHTML($table->getHtmlTable(), true, false, true);

    $file = ADMIDIO_PATH . FOLDER_DATA . '/' . $filename;

    // Save PDF to file
    $pdf->Output($file, 'F');

    // Redirect
    header('Content-Type: application/pdf');

    readfile($file);
    ignore_user_abort(true);

    try
    {
        FileSystemUtils::deleteFileIfExists($file);
    }
    catch (\RuntimeException $exception)
    {
        $gLogger->error('Could not delete file!', array('filePath' => $file));
        // TODO
    }
}
elseif ($getMode == 'html' && $getExportAndFilter)
{
    $page->addHtml('<div style="width:100%; height: 500px; overflow:auto; border:20px;">');
    $page->addHtml($table->show(false));
    $page->addHtml('</div><br/>');
    
    $page->show();
}
elseif (($getMode == 'html' && !$getExportAndFilter) || $getMode == 'print')
{
    $page->addHtml($table->show(false));
    
    $page->show();
}
