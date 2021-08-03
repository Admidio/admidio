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

$report = new CategoryReport();
$config = $report->getConfigArray();

// Initialize and check the parameters
$getForm = admFuncVariableIsValid($_GET, 'form', 'string');

$gMessage->showHtmlTextOnly(true);

try
{
	switch ($getForm)
   	{
   		case 'configurations':
   		    unset($config);

			for ($conf = 0; isset($_POST['name'. $conf]); $conf++)
   			{
   				$values['name']           = $_POST['name'. $conf];
   				$values['selection_role'] = isset($_POST['selection_role'. $conf]) ? trim(implode(',', $_POST['selection_role'. $conf]),',') : '';
   				$values['selection_cat']  = isset($_POST['selection_cat'. $conf]) ? trim(implode(',', $_POST['selection_cat'. $conf]),',') : '';
   				$values['number_col']     = isset($_POST['number_col'. $conf]) ? 1 : 0 ;
   				$values['default_conf']   = (bool)$_POST['default_conf'. $conf];

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
   					$gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gL10n->get('SYS_COLUMN'))));
   				}

   				$values['col_fields'] = substr($fields,0,-1);
   				$config[] = $values;
   			}
   			saveConfigArray($config);
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

