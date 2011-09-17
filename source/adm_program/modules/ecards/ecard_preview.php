<?php
/******************************************************************************
 * Grußkarte Vorschau
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
/****************** includes *************************************************/
require_once('../../system/common.php');
require_once('ecard_function.php');

$funcClass = new FunctionClass($gL10n);
/****************** Ausgabe des geparsten Templates **************************/
$bbcode_enable = false;
if($gPreferences['enable_bbcode'])
{
    $bbcode_enable = true;
}

$funcClass->getVars();
list($error,$ecard_data_to_parse) = $funcClass->getEcardTemplate($ecard['template_name'], THEME_SERVER_PATH. '/ecard_templates/');
if ($error) 
{
    echo $gL10n->get('SYS_ERROR_PAGE_NOT_FOUND');
} 
else 
{
    if(isset($ecard['name_recipient']) && isset($ecard['email_recipient']))
    {
        echo $funcClass->parseEcardTemplate($ecard,$ecard_data_to_parse,$g_root_path,$gCurrentUser,$ecard['name_recipient'],$ecard['email_recipient'],$bbcode_enable);
    }
}
?>
