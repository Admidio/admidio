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

//$gMessage->showTextOnly(true);
$gMessage->showThemeBody(false);
$gMessage->setCloseButton();

if (!isset($_POST) || !array_key_exists('ecard_template',$_POST))
	die($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));

// Initialize and check the parameters
$postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', null, true );
$imageName		  = admFuncVariableIsValid($_POST, 'ecard_image_name', 'string', null, true);
$nameRecipient    = array_key_exists('name_recipient',$_POST) ? $_POST['name_recipient'] : '';
$emailRecipient	  = array_key_exists('email_recipient',$_POST) ? $_POST['email_recipient'] : '';
$ecardMessage     = array_key_exists('ecard_message',$_POST) ? $_POST['ecard_message'] : '';

$funcClass = new FunctionClass($gL10n);

// read content of template file
list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($postTemplateName, THEME_SERVER_PATH. '/ecard_templates/');

if ($error) 
	die($gL10n->get('SYS_ERROR_PAGE_NOT_FOUND'));

// show output of parsed template
echo $funcClass->parseEcardTemplate($imageName, $ecardMessage, $ecard_data_to_parse, $g_root_path, $gCurrentUser, $nameRecipient, $emailRecipient);

?>