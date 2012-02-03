<?php
/******************************************************************************
 * Preview of ecard
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/

// preview will be called before form is send, so there are now POST parameters available 
// then show nothing. Second call is with POST parameters then show preview
if(isset($_POST['ecard']['template_name']))
{
	require_once('../../system/common.php');
	require_once('ecard_function.php');

	// Initialize and check the parameters
	$postTemplateName = admFuncVariableIsValid($_POST['ecard'], 'template_name', 'file', null, true);

	$funcClass = new FunctionClass($gL10n);
	$funcClass->getVars();

	// read content of template file
	list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($postTemplateName, THEME_SERVER_PATH. '/ecard_templates/');

	if ($error) 
	{
		echo $gL10n->get('SYS_ERROR_PAGE_NOT_FOUND');
	} 
	else 
	{
		if(isset($ecard['name_recipient']) && isset($ecard['email_recipient']))
		{
			// show output of parsed template
			echo $funcClass->parseEcardTemplate($ecard,$_POST['admEcardMessage'], $ecard_data_to_parse,$g_root_path,$gCurrentUser,$ecard['name_recipient'],$ecard['email_recipient']);
		}
	}
}
?>
