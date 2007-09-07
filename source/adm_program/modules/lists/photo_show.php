<?php
   /******************************************************************************
 * Photoresizer
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
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

header("Content-Type: image/jpeg");
$id = $_GET['usr_id'];
echo $_SESSION['profilphoto'][$id];
unset($_SESSION['profilphoto'][$id]);

?>
