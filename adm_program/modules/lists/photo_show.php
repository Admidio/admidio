<?php
 /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id : die ID des Users dessen Bild angezeigt werden soll
 *
 *****************************************************************************/
require("../../system/common.php");
require("../../system/login_valid.php");

if(is_numeric($_GET['usr_id']))
{
    // Ausgabe des Bildes aus dem Sessionarray
    header("Content-Type: image/jpeg");
    echo $_SESSION['profilphoto'][$_GET['usr_id']];

    unset($_SESSION['profilphoto'][$_GET['usr_id']]);
}
?>
