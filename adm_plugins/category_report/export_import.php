<?php
/**
 ***********************************************************************************************
 * Exportieren und Importieren von Konfigurationen des Admidio-Plugins Kategoriereport
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode     : 1 - show dialog for export/import
 *            2 - export procedure
 *            3 - import procedure
 *
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/configtable.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$pPreferences = new ConfigTablePKR();
$pPreferences->read();

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('defaultValue' => 1));

switch ($getMode)
{
	case 1:
	
		$headline = $gL10n->get('PLG_KATEGORIEREPORT_EXPORT_IMPORT');
	 
	    // create html page object
    	$page = new HtmlPage('plg-kategoriereport-export-import', $headline);
    
    	$gNavigation->addUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', array('show_option' => 'options')));
    	$gNavigation->addUrl(CURRENT_URL);

    	// show form
    	$form = new HtmlForm('export_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/export_import.php', array('mode' => 2)), $page);
		$form->openGroupBox('export', $headline = $gL10n->get('PLG_KATEGORIEREPORT_EXPORT'));
    	$form->addDescription($gL10n->get('PLG_KATEGORIEREPORT_EXPORT_DESC'));
    	$form->addSelectBox('conf_id', $gL10n->get('PLG_FORMFILLER_CONFIGURATION').':', $pPreferences->config['Konfigurationen']['col_desc'], array( 'showContextDependentFirstEntry' => false));
		$form->addSubmitButton('btn_export', $gL10n->get('PLG_KATEGORIEREPORT_EXPORT'), array('icon' => 'fa-file-export', 'class' => ' col-sm-offset-3'));
    	$form->closeGroupBox();
    	 
      	// add form to html page and show page
    	$page->addHtml($form->show(false));
    
    	// show form
    	$form = new HtmlForm('import_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/export_import.php', array('mode' => 3)), $page, array('enableFileUpload' => true));
    	$form->openGroupBox('import', $headline = $gL10n->get('PLG_KATEGORIEREPORT_IMPORT'));
    	$form->addDescription($gL10n->get('PLG_KATEGORIEREPORT_IMPORT_DESC'));
    	$form->addFileUpload('importfile', $gL10n->get('SYS_FILE').':', array( 'allowedMimeTypes' => array('application/octet-stream,text/plain'), 'helpTextIdLabel' => 'PLG_KATEGORIEREPORT_IMPORT_INFO'));
		$form->addSubmitButton('btn_import', $gL10n->get('PLG_KATEGORIEREPORT_IMPORT'), array('icon' => 'fa-file-import', 'class' => ' col-sm-offset-3'));
    	$form->closeGroupBox(); 
    
    	// add form to html page and show page
    	$page->addHtml($form->show(false));
    	
    	$page->show();
    	break;
    
	case 2:
		$exportArray = array();

		foreach ($pPreferences->config['Konfigurationen'] as $key => $data)
		{
			$exportArray[$key] = $data[$_POST['conf_id']];
		} 
    
    	// Dateityp, der immer abgespeichert wird
		header('Content-Type: text/plain; Charset=utf-8');    

		// noetig fuer IE, da ansonsten der Download mit SSL nicht funktioniert
		header('Cache-Control: private');

		// Im Grunde ueberfluessig, hat sich anscheinend bewährt
		header("Content-Transfer-Encoding: binary");

		// Zwischenspeichern auf Proxies verhindern
		header("Cache-Control: post-check=0, pre-check=0");
		header('Content-Disposition: attachment; filename="'.$exportArray['col_desc'].'.cfg"');
	
		echo ';### ' . $exportArray['col_desc'].'.cfg' . ' ### ' . date('Y-m-d H:i:s') . ' ### utf-8 ###'."\r\n";
		echo ';### This is a configuration file of a configuration of the plugin Kategoriereport ###'."\r\n";
    	echo ';### ATTENTION: ADD NO LINES - DELETE NO LINES ###'."\r\n\r\n";
        
    	// der Abschnitt 'columns', der hier zusammengesetzt wird, dient in der Export-Datei nur der Information
    	// er wird beim Import nicht ausgewertet
    	$exportArray['columns'] = explode(',', $exportArray['col_fields']);
	
    	$cat = new TableAccess($gDb, TBL_CATEGORIES, 'cat');
    	$role = new TableRoles($gDb);
    	
    	foreach ($exportArray['columns'] as $key => $data)
		{
		    $type = substr($data, 0, 1);
		    switch ($type)
		    {
		        case 'p':                    //p=profileField
		            $exportArray['name'][] = $gProfileFields->getPropertyById((int) substr($data, 1), 'usf_name_intern');
		            break;
		            
		        case 'c':                    //c=categorie
		            $cat->readDataById((int) substr($data, 1));
		            $exportArray['name'][] = $cat->getValue('cat_name_intern');
		            break;
		            
		        case 'r':                    //r=role
		        case 'w':                    //w=without (Leader)
		        case 'l':                    //l=leader
		            $role->readDataById((int) substr($data, 1));
		            $exportArray['name'][]  = $role->getValue('rol_name');
		            break;
		            
		        case 'n':                    //n=number
		        case 'a':                    //a=additional
		            $exportArray['name'][]  = '';
		            break;
		    }        
		}
		
		foreach ($exportArray as $key => $data)
		{
			if (!is_array($data))
			{
				echo $key." = '".$data."'\r\n";
			}
		} 
		foreach ($exportArray as $key => $data)
		{
			if (is_array($data))
			{
				echo "\r\n";
				echo "[".$key."]\r\n";
				foreach ($data as $subkey => $subdata)
				{
					echo $subkey." = '".$subdata."'\r\n";
				}
			}
		} 		
		break;
   	
	case 3:

		if (!isset($_FILES['userfile']['name']))
		{
		    $gNavigation->clear();
    		$gMessage->show($gL10n->get('PLG_KATEGORIEREPORT_IMPORT_ERROR_OTHER'), $gL10n->get('SYS_ERROR'));	
		}
		elseif (strlen($_FILES['userfile']['name'][0]) === 0)
		{
		    $gNavigation->clear();
    		$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_FILE'))));
		}
		elseif (strtolower(substr($_FILES['userfile']['name'][0],-4)) <> '.cfg')
		{
		    $gNavigation->clear();
			$gMessage->show($gL10n->get('PLG_KATEGORIEREPORT_IMPORT_ERROR_FILE'), $gL10n->get('SYS_ERROR'));	
		}
		
		$parsedArray = parse_ini_file ( $_FILES['userfile']['tmp_name'][0], TRUE );
	
		//pruefen, ob die eingelesene Datei eine Formularkonfiguration enthaelt
		if (	!(isset($parsedArray['col_desc']) && $parsedArray['col_desc'] <> '')
			||  !(isset($parsedArray['columns']) && is_array($parsedArray['columns']))			
			||  !(isset($parsedArray['name']) && is_array($parsedArray['name']))		
			||  !(count($parsedArray['columns']) == count($parsedArray['name']))  )
		{
		    $gNavigation->clear();
			$gMessage->show($gL10n->get('PLG_KATEGORIEREPORT_IMPORT_ERROR_FILE'), $gL10n->get('SYS_ERROR'));
		}
	
		$importArray = array();
	
		//alle Werte der eingelesenen Datei, die kein Array sind, in $importArray überfuehren
		//dabei werden nur Werte eingelesen, die in der aktuellen $pPreferences->config vorhanden sind
		foreach ($pPreferences->config['Konfigurationen'] as $key => $data)
		{
			if (isset($parsedArray[$key]))
			{
				if (!is_array($parsedArray[$key]))
				{
				    if($key == 'col_desc')
				    {
				        $importArray[$key] = createColDescConfig($parsedArray[$key]);
				    }
				    else
				    {
				        $importArray[$key] = $parsedArray[$key];
				    }					
				}
			}
		}
			
		$pointer = count($pPreferences->config['Konfigurationen']['col_desc']);
    	foreach ($importArray as $key => $data)	
    	{
        	$pPreferences->config['Konfigurationen'][$key][$pointer] = $data;
    	}		

		$pPreferences->save();

		$gMessage->setForwardUrl(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences.php', array('show_option' => 'options')));
		$gMessage->show($gL10n->get('PLG_KATEGORIEREPORT_IMPORT_SUCCESS'));
		
   		break;
}
