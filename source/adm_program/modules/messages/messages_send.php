<?php
/******************************************************************************
 * Check PM form and save it
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * usr_id  - Send PM to this user
 * subject - set PM subject
 *
 *****************************************************************************/

require_once('../../system/common.php');

//Stop if mail module is disabled
if($gPreferences['enable_mail_module'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

//check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Check form values
$getUserId       = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', 0);
$getPMId         = admFuncVariableIsValid($_GET, 'pm_id', 'numeric', 0);
$postSubject     = admFuncVariableIsValid($_POST, 'subject', 'string', '');
$postTo          = admFuncVariableIsValid($_POST, 'pm_to', 'string', '');
$postBody        = admFuncVariableIsValid($_POST, 'pm_body', 'html', '');

echo $postSubject;

//check for valid call
if ($getUserId == 0)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$postFrom = $gCurrentUser->getValue('usr_id');

//put values into SESSION
$_SESSION['pm_request'] = array(
    'PMfrom'        => $postFrom,
    'subject'       => $postSubject,
    'mail_body'     => $postBody);

// es muss geprueft werden ob der User ueberhaupt
// auf diese zugreifen darf oder ob die UsrId ueberhaupt eine gueltige Mailadresse hat...

//usr_id wurde uebergeben, dann Kontaktdaten des Users aus der DB fischen
$user = new User($gDb, $gProfileFields, $getUserId);

// darf auf die User-Id zugegriffen werden    
if(($gCurrentUser->editUsers() == false && isMember($user->getValue('usr_id')) == false)|| strlen($user->getValue('usr_id')) == 0 )
{
    $gMessage->show($gL10n->get('SYS_USER_ID_NOT_FOUND'));
}

// besitzt der User eine gueltige E-Mail-Adresse
if (!strValidCharacters($user->getValue('EMAIL'), 'email'))
{
    $gMessage->show($gL10n->get('SYS_USER_NO_EMAIL', $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
}

// aktuelle Seite im NaviObjekt speichern. Dann kann in der Vorgaengerseite geprueft werden, ob das
// Formular mit den in der Session gespeicherten Werten ausgefuellt werden soll...
$gNavigation->addUrl(CURRENT_URL);

if ($getPMId == 0)
{
    $sql = 'SELECT msg_id1, count(*) 
              FROM '. TBL_MESSAGES. '
             GROUP BY msg_id1';

    $result = $gDb->query($sql);
	$getPMId = $gDb->num_rows($result) + 1;
	$PMId2 = 1;
	
	$sql = 'INSERT INTO '. TBL_MESSAGES. " (msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_user1read, msg_user2read) 
        VALUES ('".$getPMId."', 0, '".$postSubject."', '".$gCurrentUser->getValue('usr_id')."', '".$getUserId."', '', CURRENT_TIMESTAMP, '0', '1')";
	
	$gNavigation->deleteLastUrl();
}
else
{
	$sql = 'SELECT * 
              FROM '. TBL_MESSAGES. '
			  WHERE msg_id1 = '.$getPMId;

    $result = $gDb->query($sql);
	$PMId2 = $gDb->num_rows($result);
	
	$sql = 'UPDATE '. TBL_MESSAGES. " SET  msg_user2read = '1', msg_timestamp = CURRENT_TIMESTAMP
            WHERE msg_id2 = 0 and msg_id1 = ".$getPMId." and msg_usrid1 = '".$gCurrentUser->getValue('usr_id')."'";
    $gDb->query($sql);
	
	$sql = 'UPDATE '. TBL_MESSAGES. " SET  msg_user1read = '1', msg_timestamp = CURRENT_TIMESTAMP
            WHERE msg_id2 = 0 and msg_id1 = ".$getPMId." and msg_usrid2 = '".$gCurrentUser->getValue('usr_id')."'";
}

$gDb->query($sql);
	
$sql = 'INSERT INTO '. TBL_MESSAGES. " (msg_id1, msg_id2, msg_subject, msg_usrid1, msg_usrid2, msg_message, msg_timestamp, msg_user1read, msg_user2read) 
        VALUES ('".$getPMId."', '".$PMId2."', '', '".$gCurrentUser->getValue('usr_id')."', '".$getUserId."', '".$postBody."', CURRENT_TIMESTAMP, '0', '0')";

if (!$gDb->query($sql)) {
  $gMessage->setForwardUrl($gNavigation->getUrl());
  $gMessage->show($gL10n->get('PMS_WAS_NOT_SENT_TO', $postTo, $postTo));
}

// Bei erfolgreichem Versenden wird aus dem NaviObjekt die am Anfang hinzugefuegte URL wieder geloescht...
$gNavigation->deleteLastUrl();

if($gNavigation->count() > 0)
{
    $gMessage->setForwardUrl($gNavigation->getUrl());
}
else
{
    $gMessage->setForwardUrl($gHomepage);
}

$gMessage->show($gL10n->get('PMS_WAS_SENT_TO', $postTo, $postTo));

?>