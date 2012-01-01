<?php
/******************************************************************************
 * Passwort Aktivierung
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * aid      ..  Activation Id für die Bestaetigung das der User wirklich ein neues Passwort wuenscht
 * usr_id   ..  Die Id des Useres der ein neues Passwort wuenscht
 *****************************************************************************/
 
require_once('common.php');

// Initialize and check the parameters
$getActivationId = admFuncVariableIsValid($_GET, 'aid', 'string', null, true);
$getUserId       = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);

// Systemmails und Passwort zusenden muessen aktiviert sein
if($gPreferences['enable_system_mails'] != 1 || $gPreferences['enable_password_recovery'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

$user = new TableUsers($gDb, $getUserId);

if($user->getValue('usr_activation_code') == $getActivationId)
{
	// das neue Passwort aktivieren
	$user->setValue('usr_password', $user->getValue('usr_new_password'));
	$user->setValue('usr_new_password', '');
	$user->setValue('usr_activation_code', '');
	$user->save();
	
	$gMessage->setForwardUrl($g_root_path.'/adm_program/system/login.php', 2000);
	$gMessage->show($gL10n->get('SYS_PWACT_PW_SAVED'));
}
else
{
	$gMessage->show($gL10n->get('SYS_PWACT_CODE_INVALID'));
}
