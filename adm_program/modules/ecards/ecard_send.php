<?php
/******************************************************************************
 * Send ecard to users and show status message
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('ecard_function.php');

// Initialize and check the parameters
$postTemplateName = admFuncVariableIsValid($_POST, 'ecard_template', 'file', array('requireValue' => true));
$postPhotoId      = admFuncVariableIsValid($_POST, 'photo_id', 'numeric', array('requireValue' => true));
$postPhotoNr      = admFuncVariableIsValid($_POST, 'photo_nr', 'numeric', array('requireValue' => true));

$funcClass       = new FunctionClass($gL10n);
$photoAlbum      = new TablePhotos($gDb, $postPhotoId);
$imageUrl        = $g_root_path.'/adm_program/modules/photos/photo_show.php?pho_id='.$postPhotoId.'&photo_nr='.$postPhotoNr.'&max_width='.$gPreferences['ecard_card_picture_width'].'&max_height='.$gPreferences['ecard_card_picture_height'];
$imageServerPath = SERVER_PATH. '/adm_my_files/photos/'.$photoAlbum->getValue('pho_begin', 'Y-m-d').'_'.$postPhotoId.'/'.$postPhotoNr.'.jpg';
$template        = THEME_SERVER_PATH. '/ecard_templates/';

$_SESSION['ecard_request'] = $_POST;

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_ecard_module'] != 1)
{
    // das Modul ist deaktiviert
    $gMessage($gL10n->get('SYS_MODULE_DISABLED'));
}
// pruefen ob User eingeloggt ist
if(!$gValidLogin)
{
    $gMessage($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$senderName  = $gCurrentUser->getValue('FIRST_NAME').' '.$gCurrentUser->getValue('LAST_NAME');
$senderEmail = $gCurrentUser->getValue('EMAIL');

if(isset($_POST['ecard_recipients']) == false || is_array($_POST['ecard_recipients'] == false))
{
    $_SESSION['ecard_request']['ecard_recipients'] = '';
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TO')));
}

if(strlen($_POST['ecard_message']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_MESSAGE')));
}

// Template wird geholt
$ecardDataToParse = $funcClass->getEcardTemplate($postTemplateName, $template);

// if template was not found then show error
if($ecardDataToParse === '')
{
    $gMessage($gL10n->get('SYS_MODULE_DISABLED'));
}

// check if user has right to send mail to selected roles and users
$arrayRoles = array();
$arrayUsers = array();

foreach($_POST['ecard_recipients'] as $key => $value)
{
    if(strpos($value, 'groupID') !== false)
    {
        $roleId = substr($value, 9);
        if($gCurrentUser->hasRightSendMailToRole($roleId))
        {
            $arrayRoles[] = $roleId;
        }
    }
    else
    {
        if($gCurrentUser->hasRightViewProfile($value))
        {
            $arrayUsers[] = $value;
        }
    }
}

$ecardSendResult = true;

if(count($arrayRoles) > 0)
// Wenn schon dann alle Namen und die dazugeh�rigen Emails auslesen und in die versand Liste hinzuf�gen
{
    $sql = 'SELECT DISTINCT first_name.usd_value as first_name, last_name.usd_value as last_name,
                   email.usd_value as email, rol_name
              FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
             RIGHT JOIN '. TBL_USER_DATA. ' as email
                ON email.usd_usr_id = usr_id
               AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
               AND LENGTH(email.usd_value) > 0
              LEFT JOIN '. TBL_USER_DATA. ' as last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
              LEFT JOIN '. TBL_USER_DATA. ' as first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
             WHERE rol_id           IN ('.implode(',', $arrayRoles).')
               AND rol_cat_id       = cat_id
               AND cat_org_id       = '.$gCurrentOrganization->getValue('org_id').'
               AND mem_rol_id       = rol_id
               AND mem_begin       <= \''.DATE_NOW.'\'
               AND mem_end          > \''.DATE_NOW.'\'
               AND mem_usr_id       = usr_id
               AND usr_valid        = 1
               AND email.usd_usr_id = email.usd_usr_id
             ORDER BY last_name, first_name';
    $resultUsers = $gDb->query($sql);

    while($row = $gDb->fetch_array($resultUsers))
    {
        if($ecardSendResult == true)
        {
            // create and send ecard
            $ecardHtmlData   = $funcClass->parseEcardTemplate($imageUrl, $_POST['ecard_message'], $ecardDataToParse, $row['first_name'].' '.$row['last_name'], $row['email']);
            $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $row['first_name'].' '.$row['last_name'], $row['email'], $imageServerPath);
        }
    }
}

if(count($arrayUsers) > 0)
{
    foreach($arrayUsers as $userId)
    {
        if($ecardSendResult == true)
        {
            $user = new User($gDb, $gProfileFields, $userId);

            // create and send ecard
            $ecardHtmlData   = $funcClass->parseEcardTemplate($imageUrl, $_POST['ecard_message'], $ecardDataToParse, $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $user->getValue('EMAIL'));
            $ecardSendResult = $funcClass->sendEcard($senderName, $senderEmail, $ecardHtmlData, $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME'), $user->getValue('EMAIL'), $imageServerPath);
        }
    }
}

// show result
if($ecardSendResult == true)
{
    $gMessage->setForwardUrl($gNavigation->getPreviousUrl());
    $gMessage->show($gL10n->get('ECA_SUCCESSFULLY_SEND'));
}
else
{
    $gMessage->show($gL10n->get('ECA_NOT_SUCCESSFULLY_SEND'));
}
