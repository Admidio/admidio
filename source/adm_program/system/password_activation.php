<?php
/******************************************************************************
 * Passwort Aktivierung
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Eischer 
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * aid      ..  Activation Id für die Bestaetigung das der User wirklich ein neues Passwort wuenscht
 * usr_id   ..  Die Id des Useres der ein neues Passwort wuenscht
 *****************************************************************************/
 
require_once('common.php');

// Systemmails und Passwort zusenden muessen aktiviert sein
if($g_preferences['enable_system_mails'] != 1 || $g_preferences['enable_password_recovery'] != 1)
{
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}

if(isset($_GET['aid']) && isset($_GET['usr_id']) && is_numeric($_GET['usr_id']))
{
    $user = new TableUsers($g_db, $_GET['usr_id']);
    
    if($user->getValue('usr_activation_code') == $_GET['aid'])
    {
        // das neue Passwort aktivieren
        $user->setValue('usr_password', $user->getValue('usr_new_password'));
        $user->setValue('usr_new_password', '');
        $user->setValue('usr_activation_code', '');
        $user->save();
        
        $g_message->setForwardUrl($g_root_path.'/adm_program/system/login.php', 2000);
        $g_message->show($g_l10n->get('SYS_PHR_PWACT_PW_SAVED'));
    }
    else
    {
        $g_message->show($g_l10n->get('SYS_PHR_PWACT_CODE_INVALID'));
    }
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}