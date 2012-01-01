<?php
/******************************************************************************
 * Show jQuery alert in Colorbox with close button
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

// Initialize and check the parameters
$getAlertMessage = admFuncVariableIsValid($_GET, 'alert_message', 'string', null, true);
$getMessageVar1  = admFuncVariableIsValid($_GET, 'message_var1', 'string', '');
$getMessageVar2  = admFuncVariableIsValid($_GET, 'message_var2', 'string', '');

// show html of message
echo '<link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/system.css" />
';

echo '<div style="margin-top: 30px;" id="message_window">
        <div class="formHead">'.$gL10n->get('SYS_NOTE').'</div>
		<div class="formBody">'.$gL10n->get($getAlertMessage, $getMessageVar1, $getMessageVar2).'</div>
        <div style="text-align: left">
           <button id="btnOk" type="button"
              onclick="parent.$.colorbox.close()"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('SYS_OK').'" />&nbsp;'.$gL10n->get('SYS_OK').'</button>
        </div>
    <div>';

?>