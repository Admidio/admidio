<?php
/******************************************************************************
 * Index-Datei welche auf Installation bzw. Update verweist
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// pruefen, ob es eine Erstinstallation ist
if(file_exists("../adm_config/config.php"))
{
   $page = "update.php";
}
else
{
   $page = "installation.php";
}

// Html des Modules ausgeben
echo '
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <title>Admidio - Installation/Update</title>

    <meta name="author"   content="Admidio Team">
    <meta name="robots"   content="noindex">

    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta http-equiv="refresh" content="0; url='. $page. '">
</head>
<body>
    Wenn Sie nicht automatisch weitergeleitet werden, dann klicken Sie <a href="'. $page. '">hier</a> !
</body>
</html>';
?>