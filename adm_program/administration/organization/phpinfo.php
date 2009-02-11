<?php
/******************************************************************************
 * Ausgabe der PHpInfo
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show('norights');
}

phpinfo();
?>