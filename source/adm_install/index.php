<?php
/******************************************************************************
 * Index-Datei welche auf Installation bzw. Update verweist
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// pruefen, ob es eine Erstinstallation ist
if(file_exists("../config.php"))
{
   $page = "update.php";
}
else
{
   $page = "installation.php";
}

// Html des Modules ausgeben
header('Content-type: text/html; charset=utf-8'); 
echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
<head>
    <!-- (c) 2004 - 2008 The Admidio Team - http://www.admidio.org -->
    
    <title>Admidio - Installation/Update</title>

    <meta name="author"   content="Admidio Team" />
    <meta name="robots"   content="noindex" />

    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="refresh" content="0; url='. $page. '" />
</head>
<body>
    Wenn Sie nicht automatisch weitergeleitet werden, dann klicken Sie <a href="'. $page. '">hier</a> !
</body>
</html>';
?>