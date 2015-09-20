<?php
/******************************************************************************
 * Activate new password
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * aid      ..  Activation id for confirmation of new password
 * usr_id   ..  Id of the user who wants a new password
 *****************************************************************************/

require_once('common.php');

// Initialize and check the parameters
$getActivationId = admFuncVariableIsValid($_GET, 'aid',    'string',  array('requireValue' => true));
$getUserId       = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('requireValue' => true));

// Systemmails und Passwort zusenden muessen aktiviert sein
if($gPreferences['enable_system_mails'] != 1 || $gPreferences['enable_password_recovery'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

$user = new TableUsers($gDb, $getUserId);

if($user->getValue('usr_activation_code') === $getActivationId)
{
    // activate the new password
    $user->setPassword($user->getValue('usr_new_password'), false, false);
    $user->setPassword('', true, false);
    $user->setValue('usr_activation_code', '');
    $user->save();

    $gMessage->setForwardUrl($g_root_path.'/adm_program/system/login.php', 2000);
    $gMessage->show($gL10n->get('SYS_PWACT_PW_SAVED'));
}
else
{
    $gMessage->show($gL10n->get('SYS_PWACT_CODE_INVALID'));
}
