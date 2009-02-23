<?php 
/******************************************************************************
 * Registrieren
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require("common.php");

// pruefen, ob Modul aufgerufen werden darf
if($g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}
else
{
    // bei Registrierung wird ueber das Profil weiter bearbeitet
    header("Location: $g_root_path/adm_program/modules/profile/profile_new.php?new_user=2");
    exit();
}

?>