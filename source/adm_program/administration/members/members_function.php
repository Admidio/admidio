<?php
/******************************************************************************
 * User-Funktionen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode: 1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *       2 - User NUR aus der Gliedgemeinschaft entfernen
 *       3 - User aus der Datenbank loeschen
 *       4 - User E-Mail mit neuen Zugangsdaten schicken
 *       5 - Frage, ob Zugangsdaten geschickt werden soll
 *       6 - Frage, ob Mitglied geloescht werden soll
 * usr_id :  Id des Benutzers, der bearbeitet werden soll
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/system_mail.php');
require_once('../../system/classes/table_members.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', null, true);
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', null, true);

// nur berechtigte User duerfen Funktionen aufrufen
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// nun erst einmal allgemein pruefen, ob der User zur aktuellen Orga gehoert
if(isMember($getUserId) == true)
{
    $this_orga = true;
}
else
{
    $this_orga = false;
}

if($getMode != 1)
{
    // pruefen, ob der User noch in anderen Organisationen aktiv ist
    $sql    = 'SELECT rol_id
                 FROM '. TBL_ROLES. ', '. TBL_MEMBERS. ', '. TBL_CATEGORIES. '
                WHERE rol_valid   = 1
                  AND rol_cat_id  = cat_id
                  AND cat_org_id <> '. $gCurrentOrganization->getValue('org_id'). '
                  AND mem_rol_id  = rol_id
                  AND mem_begin  <= \''.DATE_NOW.'\'
                  AND mem_end     > \''.DATE_NOW.'\'
                  AND mem_usr_id  = '. $getUserId;
    $result = $gDb->query($sql);
    $other_orga = $gDb->num_rows($result);

    // User-Objekt anlegen
    $user = new User($gDb, $gProfileFields, $getUserId);
}

if($getMode == 1)
{
    // Html-Kopf ausgeben
    $gLayout['title'] = $gL10n->get('SYS_NOTE');
    require(SERVER_PATH. '/adm_program/system/overall_header.php');

    // Html des Modules ausgeben
    echo '<br /><br /><br />
    <div class="formLayout" id="user_delete_message_form" style="width: 400px">
        <div class="formHead">'.$gL10n->get('MEM_REMOVE_USER').'</div>
        <div class="formBody">
            <p align="left">
                <img src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />
                '.$gL10n->get('MEM_MAKE_FORMER').'
            </p>
            <p align="left">
                <img src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" />
                '.$gL10n->get('MEM_REMOVE_USER', $gL10n->get('SYS_DELETE')).'
            </p>
            <button id="btnBack" type="button" onclick="history.back()"><img src="'.THEME_PATH.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" />&nbsp;'.$gL10n->get('SYS_BACK').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnDelete" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $getUserId. '&mode=3\'"><img 
                src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" />&nbsp;'.$gL10n->get('SYS_DELETE').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnFormer" type="button" onclick="self.location.href=\''.$g_root_path.'/adm_program/administration/members/members_function.php?usr_id='.$getUserId.'&mode=2\'"><img 
                src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />&nbsp;'.$gL10n->get('SYS_FORMER').'</button>
        </div>
    </div>';

    require(SERVER_PATH. '/adm_program/system/overall_footer.php');
    exit();
}
elseif($getMode == 2)
{
    // User NUR aus der aktuellen Organisation entfernen

    // Es duerfen keine Webmaster entfernt werden
    if($gCurrentUser->isWebmaster() == false
    && $user->isWebmaster()           == true)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // User muss zur aktuellen Orga dazugehoeren
    // kein Suizid ermoeglichen
    if($this_orga == false
    || $gCurrentUser->getValue('usr_id') == $getUserId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    
    $member = new TableMembers($gDb);

    $sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. '
             WHERE rol_valid  = 1
               AND rol_cat_id = cat_id
               AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
               AND mem_rol_id = rol_id
               AND mem_begin <= \''.DATE_NOW.'\'
               AND mem_end    > \''.DATE_NOW.'\'
               AND mem_usr_id = '. $getUserId;
    $result_mgl = $gDb->query($sql);

    while($row = $gDb->fetch_array($result_mgl))
    {
        // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $gMessage->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
    $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP_OK', $gCurrentOrganization->getValue('org_longname')));
}
elseif($getMode == 3)
{
    // User aus der Datenbank loeschen
    
    // nur Webmaster duerfen dies
    if($gCurrentUser->isWebmaster() == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    
    // User darf in keiner anderen Orga aktiv sein
    // kein Suizid ermoeglichen
    if($other_orga > 0
    || $gCurrentUser->getValue('usr_id') == $getUserId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // Paralell im Forum loeschen, wenn g_forum gesetzt ist
    if($gPreferences['enable_forum_interface'])
    {
        $gForum->userDelete($user->getValue('usr_login_name'));
        
        $phrase = $gL10n->get('SYS_FORUM_USER_DELETE');
    }
    else
    {
        $phrase = $gL10n->get('SYS_DELETE_DATA');
    }
    
    // User aus der Admidio Datenbank loeschen
    $user->delete();
}
elseif($getMode == 4)
{
    // nur Webmaster duerfen User neue Zugangsdaten zuschicken
    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
    // nur an Mitglieder der eigenen Organisation schicken
    if($gCurrentUser->isWebmaster() == false
    || $gPreferences['enable_system_mails'] != 1
    || $this_orga == false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    if($gPreferences['enable_system_mails'] == 1)
    {
        // neues Passwort generieren und abspeichern
        $password = substr(md5(time()), 0, 8);
        $user->setValue('usr_password', $password);
        $user->save();

        // Mail an den User mit den Loginaten schicken
        $sysmail = new SystemMail($gDb);
        $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
        $sysmail->setVariable(1, $password);
        if($sysmail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $user) == true)
        {
            $phrase = $gL10n->get('SYS_EMAIL_SEND', $user->getValue('EMAIL'));
        }
        else
        {
            $phrase = $gL10n->get('SYS_EMAIL_NOT_SEND', $user->getValue('EMAIL'));
        }
        $gMessage->setForwardUrl($_SESSION['navigation']->getUrl());
        $gMessage->show($phrase);
    }
}
elseif($getMode == 5)
{
    // Fragen, ob Zugangsdaten verschickt werden sollen
    $gMessage->setForwardYesNo($g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $getUserId. '&mode=4');
    $gMessage->show($gL10n->get('MEM_SEND_NEW_LOGIN', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME')));
}
elseif($getMode == 6)
{
    if($this_orga == true && $other_orga == 0 && $gCurrentUser->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist NUR Mitglied der aktuellen Orga -> dann fragen, ob Ehemaliger oder ganz loeschen
        header('Location: '.$g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $getUserId. '&mode=1');
        exit();
    }
    elseif($this_orga == false && $other_orga == 0 && $gCurrentUser->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist in keiner Orga mehr Mitglied -> kann komplett geloescht werden
        $gMessage->setForwardYesNo($g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $getUserId. '&mode=3');
        $gMessage->show($gL10n->get('MEM_USER_DELETE', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname')),$gL10n->get('SYS_DELETE'));
    }
    else
    {
        // User kann nur aus dieser Orga entfernt werden
        $gMessage->setForwardYesNo($g_root_path.'/adm_program/administration/members/members_function.php?usr_id='. $getUserId. '&mode=2');
        $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname')),$gL10n->get('SYS_REMOVE'));
    }
}

$gMessage->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$gMessage->show($phrase);
?>