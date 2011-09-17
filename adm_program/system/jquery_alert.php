<?php
/******************************************************************************
 * jQuery Alert als Colorbox mit Close-Button
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

// Uebergabevariablen pruefen
if(isset($_GET['alert_message']) && strlen($_GET['alert_message']) > 0)
{
    $alert_message = strStripTags($_GET['alert_message']);
	$display_message = $gL10n->get($alert_message);
}

if(isset($_GET['message_var1']) && strlen($_GET['message_var1']) > 0)
{
    $message_var1 = $_GET['message_var1'];
	$display_message = $gL10n->get($alert_message, $message_var1);
}

if(isset($_GET['message_var2']) && strlen($_GET['message_var2']) > 0)
{
    $message_var2 = $_GET['message_var2'];
	$display_message = $gL10n->get($alert_message, $message_var1, $message_var1);
}

// Html des Modules ausgeben
echo '<link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/system.css" />
';

echo '<div style="margin-top: 30px;" id="message_window">
        <div class="formHead">'.$gL10n->get('SYS_NOTE').'</div>
		<div class="formBody">'.$display_message.'</div>
        <div style="text-align: left">
           <button id="btnDelete" type="button"
              onclick="parent.$.colorbox.close()"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$gL10n->get('SYS_OK').'" />&nbsp;'.$gL10n->get('SYS_OK').'</button>
        </div>
    <div>';

?>