<?php
/**
 ***********************************************************************************************
 * Preferences functions for the admidio module CategoryReport
 * 
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * 
 * Parameters:
 *
 * form     - The name of the form preferences that were submitted.
 * 
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/common_function.php');

// only authorized user are allowed to start this module
if (!$gCurrentUser->isAdministrator())
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$config = getConfigArray();

// Initialize and check the parameters
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

$gMessage->showHtmlTextOnly(true);

try
{
	switch ($getForm)
   	{
   		case 'configurations':
   		    unset($config);
   			
			for ($conf = 0; isset($_POST['col_desc'. $conf]); $conf++)
   			{  				
   				$config['col_desc'][]       = $_POST['col_desc'. $conf];
   				$config['col_yes'][]        = $_POST['col_yes'. $conf];
   				$config['col_no'][]         = $_POST['col_no'. $conf];
   				$config['selection_role'][] = isset($_POST['selection_role'. $conf]) ? trim(implode(',', $_POST['selection_role'. $conf]),',') : ' ';
   				$config['selection_cat'][]  = isset($_POST['selection_cat'. $conf]) ? trim(implode(',', $_POST['selection_cat'. $conf]),',') : ' ';
   				$config['number_col'][]     = isset($_POST['number_col'. $conf]) ? 1 : 0 ;

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
   					$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_COLUMN')));
   				}
   				
   				$config['col_fields'][] = substr($fields,0,-1);
   			}
   			saveConfigArray();
           	break; 
           	
      	case 'options':	
	        	$gSettingsManager->set('category_report_default_configuration', $_POST['default_config']);
           	break;  
           
       	default:
          		$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
   	}
}
catch(AdmException $e)
{
	$e->showText();
}    

echo 'success';

