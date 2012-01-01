<?php 
/******************************************************************************
 * Registrieren
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require('common.php');

// pruefen, ob Modul aufgerufen werden darf
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
else
{
    // bei Registrierung wird ueber das Profil weiter bearbeitet
    header('Location: '.$g_root_path.'/adm_program/modules/profile/profile_new.php?new_user=2');
    exit();
}

?>