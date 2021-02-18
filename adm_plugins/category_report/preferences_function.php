<?php
/**
 ***********************************************************************************************
 * Verarbeiten der Einstellungen des Admidio-Plugins Kategoriereport
 * 
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Parameters:
 *
 * mode:  1 - Save preferences
 *        2 - show dialog for deinstallation
 *        3 - deinstall
 * form     - The name of the form preferences that were submitted.
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
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

// in ajax mode only return simple text on error
if ($getMode === 1)
{
    $gMessage->showHtmlTextOnly(true);
}

switch ($getMode)
{
case 1:
	try
	{
		switch ($getForm)
    	{
    		case 'configurations':
				unset($pPreferences->config['Konfigurationen']);
    			
				for ($conf = 0; isset($_POST['col_desc'. $conf]); $conf++)
    			{  				
    				$pPreferences->config['Konfigurationen']['col_desc'][]       = $_POST['col_desc'. $conf];
    				$pPreferences->config['Konfigurationen']['col_yes'][]        = $_POST['col_yes'. $conf];
    				$pPreferences->config['Konfigurationen']['col_no'][]         = $_POST['col_no'. $conf];
    				$pPreferences->config['Konfigurationen']['selection_role'][] = isset($_POST['selection_role'. $conf]) ? trim(implode(',', $_POST['selection_role'. $conf]),',') : ' ';
    				$pPreferences->config['Konfigurationen']['selection_cat'][]  = isset($_POST['selection_cat'. $conf]) ? trim(implode(',', $_POST['selection_cat'. $conf]),',') : ' ';
    				$pPreferences->config['Konfigurationen']['number_col'][]     = isset($_POST['number_col'. $conf]) ? 1 : 0 ;

    				$allColumnsEmpty = true;

    				$fields = '';
    				for ($number = 1; isset($_POST['column'.$conf.'_'.$number]); $number++)
    				{
        				if (strlen($_POST['column'.$conf.'_'.$number]) > 0)
        				{
        					$allColumnsEmpty = false;
            				$fields .= $_POST['column'.$conf.'_'.$number].',';
        				}
    				}	
    				
    				if ($allColumnsEmpty)
    				{
    					$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('PLG_KATEGORIEREPORT_COLUMN')));
    				}
    				
					$pPreferences->config['Konfigurationen']['col_fields'][] = substr($fields,0,-1);
    			}	
            	break; 
            	
       		case 'options':
 	        	$pPreferences->config['Optionen']['config_default'] = $_POST['config_default'];	
            	break;  
            
        	default:
           		$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    	}
	}
	catch(AdmException $e)
	{
		$e->showText();
	}    
    
	$pPreferences->save();

	echo 'success';
	break;

case 2:
	
	$headline = $gL10n->get('PLG_KATEGORIEREPORT_DEINSTALLATION');
	 
	// create html page object
    $page = new HtmlPage('plg-kategoriereport-deinstallation', $headline);
    
    // add current url to navigation stack
    $gNavigation->addUrl(CURRENT_URL, $headline);
    
    $page->addHtml('<p class="lead">'.$gL10n->get('PLG_KATEGORIEREPORT_DEINSTALLATION_FORM_DESC').'</p>');

    // show form
    $form = new HtmlForm('deinstallation_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER .'/preferences_function.php', array('mode' => 3)), $page);
    $radioButtonEntries = array('0' => $gL10n->get('PLG_KATEGORIEREPORT_DEINST_ACTORGONLY'), '1' => $gL10n->get('PLG_KATEGORIEREPORT_DEINST_ALLORG') );
    $form->addRadioButton('deinst_org_select',$gL10n->get('PLG_KATEGORIEREPORT_ORG_CHOICE'), $radioButtonEntries);    
    $form->addSubmitButton('btn_deinstall', $gL10n->get('PLG_KATEGORIEREPORT_DEINSTALLATION'), array('icon' => 'fa-trash-alt', 'class' => 'col-sm-offset-3'));
    
    // add form to html page and show page
    $page->addHtml($form->show(false));
    $page->show();
    break;
    
case 3:
    
	$gNavigation->clear();
	$gMessage->setForwardUrl($gHomepage);		

	$gMessage->show($gL10n->get('PLG_KATEGORIEREPORT_DEINST_STARTMESSAGE').$pPreferences->delete($_POST['deinst_org_select']) );
   	break;
}
