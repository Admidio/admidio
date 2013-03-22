<?php
/******************************************************************************
 * Preview of ecard
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

// preview will be called before form is send, so there are now POST parameters available 
// then show nothing. Second call is with POST parameters then show preview
require_once('../../system/common.php');
require_once('ecard_function.php');

if (!isset($_POST) || !array_key_exists('ecard',$_POST))
	die($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));

// Initialize and check the parameters
$postTemplateName = admFuncVariableIsValid($_POST['ecard'], 'template_name', 'file', null, true );
if (!isset($postTemplateName))
	die($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));

$imageName			= admFuncVariableIsValid($_POST['ecard'], 'image_name', 'string');
$nameRecipient		= array_key_exists('name_recipient',$_POST['ecard']) ? $_POST['ecard']['name_recipient'] : '';
$emailRecipient	= array_key_exists('email_recipient',$_POST['ecard']) ? $_POST['ecard']['email_recipient'] : '';
$admEcardMessage  = array_key_exists('admEcardMessage',$_POST) ? $_POST['admEcardMessage'] : '';

$funcClass = new FunctionClass($gL10n);

// read content of template file
list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($postTemplateName, THEME_SERVER_PATH. '/ecard_templates/');

if ($error) 
	die($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));

// show output of parsed template
echo $funcClass->parseEcardTemplate($imageName, $admEcardMessage, $ecard_data_to_parse, $g_root_path, $gCurrentUser, $nameRecipient, $emailRecipient);

?>