<?php
/**
 ***********************************************************************************************
 * User-Funktionen
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
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

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'numeric', array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'numeric', array('requireValue' => true));

// nur berechtigte User duerfen Funktionen aufrufen
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// nun erst einmal allgemein pruefen, ob der User zur aktuellen Orga gehoert
if(isMember($getUserId))
{
    $this_orga = true;
}
else
{
    $this_orga = false;
}

if($getMode !== 1)
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
    $statement = $gDb->query($sql);
    $otherOrgaCount = $statement->rowCount();

    // User-Objekt anlegen
    $user = new User($gDb, $gProfileFields, $getUserId);
}

if($getMode === 1)
{
    // create html page object
    $page = new HtmlPage($gL10n->get('MEM_REMOVE_USER'));

    // add back link to module menu
    $messageMenu = $page->getMenu();
    $messageMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    $page->addHtml('
    <div class="message">
        <p class="lead">
            <img src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />
            '.$gL10n->get('MEM_MAKE_FORMER').'<br /><br />
            <img src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" />
            '.$gL10n->get('MEM_REMOVE_USER_DESC', $gL10n->get('SYS_DELETE')).'
        </p>

        <button id="btnFormer" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='.$getUserId.'&mode=2\'"><img
            src="'.THEME_PATH.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />&nbsp;'.$gL10n->get('SYS_FORMER').'</button>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <button id="btnDelete" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $getUserId. '&mode=3\'"><img
            src="'.THEME_PATH.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" />&nbsp;'.$gL10n->get('SYS_DELETE').'</button>
    </div>');

    $page->show();
    exit();
}
elseif($getMode === 2)
{
    // User NUR aus der aktuellen Organisation entfernen

    // Es duerfen keine Webmaster entfernt werden
    if(!$gCurrentUser->isWebmaster() && $user->isWebmaster())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // User muss zur aktuellen Orga dazugehoeren
    // kein Suizid ermoeglichen
    if(!$this_orga || $gCurrentUser->getValue('usr_id') == $getUserId)
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
    $mglStatement = $gDb->query($sql);

    while($row = $mglStatement->fetch())
    {
        // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP_OK', $gCurrentOrganization->getValue('org_longname')));
}
elseif($getMode === 3)
{
    // User aus der Datenbank loeschen

    // nur Webmaster duerfen dies
    if(!$gCurrentUser->isWebmaster())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // User darf in keiner anderen Orga aktiv sein
    // kein Suizid ermoeglichen
    if($otherOrgaCount > 0 || $gCurrentUser->getValue('usr_id') == $getUserId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $phrase = $gL10n->get('SYS_DELETE_DATA');

    // User aus der Admidio Datenbank loeschen
    $user->delete();
}
elseif($getMode === 4)
{
    // nur Webmaster duerfen User neue Zugangsdaten zuschicken
    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
    // nur an Mitglieder der eigenen Organisation schicken
    if(!$gCurrentUser->isWebmaster() || $gPreferences['enable_system_mails'] != 1 || !$this_orga)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    if($gPreferences['enable_system_mails'] == 1)
    {
        try
        {
            // neues Passwort generieren und abspeichern
            $password = PasswordHashing::genRandomPassword(8);
            $user->setPassword($password);
            $user->save();

            // Mail an den User mit den Loginaten schicken
            $sysmail = new SystemMail($gDb);
            $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            $sysmail->setVariable(1, $password);
            $sysmail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $user);

            $gMessage->setForwardUrl($gNavigation->getUrl());
            $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
        }
        catch(AdmException $e)
        {
            $e->showText();
        }
    }
}
elseif($getMode === 5)
{
    // Fragen, ob Zugangsdaten verschickt werden sollen
    $gMessage->setForwardYesNo($g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $getUserId. '&mode=4');
    $gMessage->show($gL10n->get('MEM_SEND_NEW_LOGIN', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME')));
}
elseif($getMode === 6)
{
    if($this_orga && $otherOrgaCount === 0 && $gCurrentUser->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist NUR Mitglied der aktuellen Orga -> dann fragen, ob Ehemaliger oder ganz loeschen
        header('Location: '.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $getUserId. '&mode=1');
        exit();
    }
    elseif(!$this_orga && $otherOrgaCount === 0 && $gCurrentUser->isWebmaster())
    {
        // nur Webmaster duerfen dies
        // User ist in keiner Orga mehr Mitglied -> kann komplett geloescht werden
        $gMessage->setForwardYesNo($g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $getUserId. '&mode=3');
        $gMessage->show($gL10n->get('MEM_USER_DELETE', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname')), $gL10n->get('SYS_DELETE'));
    }
    else
    {
        // User kann nur aus dieser Orga entfernt werden
        $gMessage->setForwardYesNo($g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $getUserId. '&mode=2');
        $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP', $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname')), $gL10n->get('SYS_REMOVE'));
    }
}

$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
$gMessage->show($phrase);
