<?php
/**
 ***********************************************************************************************
 * User-Funktionen
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
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

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

// Initialize and check the parameters
$getUserId = admFuncVariableIsValid($_GET, 'usr_id', 'int', array('requireValue' => true));
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

// nur berechtigte User duerfen Funktionen aufrufen
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// nun erst einmal allgemein pruefen, ob der User zur aktuellen Orga gehoert
if(isMember($getUserId))
{
    $thisOrga = true;
}
else
{
    $thisOrga = false;
}

if($getMode !== 1)
{
    // pruefen, ob der User noch in anderen Organisationen aktiv ist
    $sql = 'SELECT rol_id
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid   = 1
               AND cat_org_id <> ? -- $gCurrentOrganization->getValue(\'org_id\')
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end     > ? -- DATE_NOW
               AND mem_usr_id  = ? -- $getUserId';
    $statement = $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id'), DATE_NOW, DATE_NOW, $getUserId));
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
            <img src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />
            '.$gL10n->get('MEM_MAKE_FORMER').'<br /><br />
            <img src="'.THEME_URL.'/icons/delete.png" alt="'.$gL10n->get('MEM_REMOVE_USER').'" />
            '.$gL10n->get('MEM_REMOVE_USER_DESC', array($gL10n->get('SYS_DELETE'))).'
        </p>

        <button id="btnFormer" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 2)).'\'"><img
            src="'.THEME_URL.'/icons/profile.png" alt="'.$gL10n->get('SYS_FORMER').'" />&nbsp;'.$gL10n->get('SYS_FORMER').'</button>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <button id="btnDelete" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 3)).'\'"><img
            src="'.THEME_URL.'/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" />&nbsp;'.$gL10n->get('SYS_DELETE').'</button>
    </div>');

    $page->show();
    exit();
}
elseif($getMode === 2)
{
    // User NUR aus der aktuellen Organisation entfernen

    // Administrators could not be deleted
    if(!$gCurrentUser->isAdministrator() && $user->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // User muss zur aktuellen Orga dazugehoeren
    // kein Suizid ermoeglichen
    if(!$thisOrga || (int) $gCurrentUser->getValue('usr_id') === $getUserId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $member = new TableMembers($gDb);

    $sql = 'SELECT mem_id, mem_rol_id, mem_usr_id, mem_begin, mem_end, mem_leader
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid  = 1
               AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND mem_usr_id = ? -- $getUserId';
    $mglStatement = $gDb->queryPrepared($sql, array($gCurrentOrganization->getValue('org_id'), DATE_NOW, DATE_NOW, $getUserId));

    while($row = $mglStatement->fetch())
    {
        // alle Rollen der aktuellen Gliedgemeinschaft auf ungueltig setzen
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP_OK', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))));
    // => EXIT
}
elseif($getMode === 3)
{
    // User aus der Datenbank loeschen

    // only administrators are allowed to do this
    if(!$gCurrentUser->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // User darf in keiner anderen Orga aktiv sein
    // kein Suizid ermoeglichen
    if($otherOrgaCount > 0 || (int) $gCurrentUser->getValue('usr_id') === $getUserId)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $phrase = $gL10n->get('SYS_DELETE_DATA');

    // User aus der Admidio Datenbank loeschen
    $user->delete();
}
elseif($getMode === 4)
{
    // only administrators are allowed to send new login data
    // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
    // nur an Mitglieder der eigenen Organisation schicken
    if(!$gCurrentUser->isAdministrator() || !$gSettingsManager->getBool('enable_system_mails') || !$thisOrga)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    if($gSettingsManager->getBool('enable_system_mails'))
    {
        try
        {
            // neues Passwort generieren und abspeichern
            $password = PasswordUtils::genRandomPassword(PASSWORD_GEN_LENGTH, PASSWORD_GEN_CHARS);
            $user->setPassword($password);
            $user->save();

            // Mail an den User mit den Loginaten schicken
            $sysmail = new SystemMail($gDb);
            $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            $sysmail->setVariable(1, $password);
            $sysmail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $user);

            $gMessage->setForwardUrl($gNavigation->getUrl());
            $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
            // => EXIT
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }
    }
}
elseif($getMode === 5)
{
    // Fragen, ob Zugangsdaten verschickt werden sollen
    $gMessage->setForwardYesNo(safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 4)));
    $gMessage->show($gL10n->get('MEM_SEND_NEW_LOGIN', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'))));
    // => EXIT
}
elseif($getMode === 6)
{
    if($thisOrga && $otherOrgaCount === 0 && $gCurrentUser->isAdministrator())
    {
        // only administrators are allowed to do this
        // User ist NUR Mitglied der aktuellen Orga -> dann fragen, ob Ehemaliger oder ganz loeschen
        admRedirect(safeUrl(ADMIDIO_URL . FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 1)));
        // => EXIT
    }
    elseif(!$thisOrga && $otherOrgaCount === 0 && $gCurrentUser->isAdministrator())
    {
        // only administrators are allowed to do this
        // User ist in keiner Orga mehr Mitglied -> kann komplett geloescht werden
        $gMessage->setForwardYesNo(safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 3)));
        $gMessage->show($gL10n->get('MEM_USER_DELETE', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'))), $gL10n->get('SYS_DELETE'));
        // => EXIT
    }
    else
    {
        // User kann nur aus dieser Orga entfernt werden
        $gMessage->setForwardYesNo(safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 2)));
        $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))), $gL10n->get('SYS_REMOVE'));
        // => EXIT
    }
}

$gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
$gMessage->show($phrase);
// => EXIT
