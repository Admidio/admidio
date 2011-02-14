<?php
/******************************************************************************
 * jQuery Alert als Colorbox mit Close-Button
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('common.php');

// Uebergabevariablen pruefen
if(isset($_GET['alert_message']) && strlen($_GET['alert_message']) > 0)
{
    $alert_message = strStripTags($_GET['alert_message']);
	$display_message = $g_l10n->get($alert_message);
}

if(isset($_GET['message_var1']) && strlen($_GET['message_var1']) > 0)
{
    $message_var1 = $_GET['message_var1'];
	$display_message = $g_l10n->get($alert_message, $message_var1);
}

if(isset($_GET['message_var2']) && strlen($_GET['message_var2']) > 0)
{
    $message_var2 = $_GET['message_var2'];
	$display_message = $g_l10n->get($alert_message, $message_var1, $message_var1);
}


// Ausgabe der Meldung
echo '<div class="formBody">'.$display_message.'</div>';

// Html des Modules ausgeben
echo '
<div style="position: relative; top: 5px">
	<button id="btnDelete" type="button"
		onclick="parent.$.colorbox.close()"><img src="'. THEME_PATH. '/icons/ok.png" alt="'.$g_l10n->get('SYS_OK').'" />&nbsp;'.$g_l10n->get('SYS_OK').'</button>
</div>';

?>