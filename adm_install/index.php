<?php
/******************************************************************************
 * Index-Datei welche auf Installation bzw. Update verweist
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// pruefen, ob es eine Erstinstallation ist
if(file_exists('../config.php'))
{
   $page = 'update.php';
}
else
{
   $page = 'installation.php';
}

// weiterleiten auf die entsprechende Seite
header('Location: '.$page);
exit();
?>