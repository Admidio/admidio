<?php
/**
 ***********************************************************************************************
 * User-Funktionen
 *
 * @copyright 2004-2021 The Admidio Team
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

// Only users with user-edit rights are allowed
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

if ($getMode === 1)
{
    // create html page object
    $page = new HtmlPage('admidio-members-message', $gL10n->get('MEM_REMOVE_USER'));

    $page->addHtml('
    <div class="message">
        <p class="lead">
            <i class="fas fa-user-clock"></i>&nbsp;'.$gL10n->get('MEM_MAKE_FORMER').'<br /><br />
            <i class="fas fa-trash-alt"></i>&nbsp;'.$gL10n->get('MEM_REMOVE_USER_DESC', array($gL10n->get('SYS_DELETE'))).'
        </p>

        <button id="btnFormer" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 2)).'\'">
            <i class="fas fa-user-clock"></i>'.$gL10n->get('SYS_FORMER').'</button>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <button id="btnDelete" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 3)).'\'">
            <i class="fas fa-trash-alt"></i>'.$gL10n->get('SYS_DELETE').'</button>
    </div>');

    $page->show();
    exit();
}

$orgId = (int) $gCurrentOrganization->getValue('org_id');

if ($getMode === 3 || $getMode === 6)
{
    // Check if user is also in other organizations
    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid   = 1
               AND cat_org_id <> ? -- $orgId
               AND mem_begin  <= ? -- DATE_NOW
               AND mem_end     > ? -- DATE_NOW
               AND mem_usr_id  = ? -- $getUserId';
    $pdoStatement = $gDb->queryPrepared($sql, array($orgId, DATE_NOW, DATE_NOW, $getUserId));
    $isAlsoInOtherOrgas = $pdoStatement->fetchColumn() > 0;
}

// Create user-object
$user = new User($gDb, $gProfileFields, $getUserId);

if ($getMode === 2)
{
    // User has to be a member of this organization
    // User could not delete himself
    // Administrators could not be deleted
    if (!isMember($getUserId) || (int) $gCurrentUser->getValue('usr_id') === $getUserId
    || (!$gCurrentUser->isAdministrator() && $user->isAdministrator()))
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
               AND (  cat_org_id = ? -- $orgId
                   OR cat_org_id IS NULL )
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND mem_usr_id = ? -- $getUserId';
    $pdoStatement = $gDb->queryPrepared($sql, array($orgId, DATE_NOW, DATE_NOW, $getUserId));

    while ($row = $pdoStatement->fetch())
    {
        // invalidate all roles of this organization
        $member->setArray($row);
        $member->stopMembership($row['mem_rol_id'], $row['mem_usr_id']);
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP_OK', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))));
    // => EXIT
}
elseif ($getMode === 3)
{
    // User must not be in any other organization
    // User could not delete himself
    // Only administrators are allowed to do this
    if ($isAlsoInOtherOrgas || (int) $gCurrentUser->getValue('usr_id') === $getUserId || !$gCurrentUser->isAdministrator())
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // Delete user from database
    $user->delete();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_DELETE_DATA'));
    // => EXIT
}
elseif ($getMode === 4)
{
    // User must be member of this organization
    // Only administrators are allowed to send new login data
    // E-Mail support must be enabled
    if (!isMember($getUserId) || !$gCurrentUser->isAdministrator() || !$gSettingsManager->getBool('enable_system_mails'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    try
    {
        // Generate new secure-random password and save it
        $password = SecurityUtils::getRandomString(PASSWORD_GEN_LENGTH, PASSWORD_GEN_CHARS);
        $user->setPassword($password);
        $user->save();

        // Send mail with login data to user
        $sysMail = new SystemMail($gDb);
        $sysMail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
        $sysMail->setVariable(1, $password);
        $sysMail->sendSystemMail('SYSMAIL_NEW_PASSWORD', $user);
    }
    catch (AdmException $e)
    {
        $e->showText();
        // => EXIT
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('SYS_EMAIL_SEND'));
    // => EXIT
}
elseif ($getMode === 5)
{
    // Ask to send new login-data
    $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 4)));
    $gMessage->show($gL10n->get('MEM_SEND_NEW_LOGIN', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))));
    // => EXIT
}
elseif ($getMode === 6)
{
    if (!$isAlsoInOtherOrgas && $gCurrentUser->isAdministrator())
    {
        if (isMember($getUserId))
        {
            // User is ONLY member of this organization -> ask if make to former member or delete completely
            admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 1)));
            // => EXIT
        }
        else
        {
            // User is not member of any organization -> ask if delete completely
            $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 3)));
            $gMessage->show($gL10n->get('MEM_USER_DELETE', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'))), $gL10n->get('SYS_DELETE'));
            // => EXIT
        }
    }
    else
    {
        // User could only be removed from this organization -> ask so
        $gMessage->setForwardYesNo(SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members_function.php', array('usr_id' => $getUserId, 'mode' => 2)));
        $gMessage->show($gL10n->get('MEM_REMOVE_MEMBERSHIP', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $gCurrentOrganization->getValue('org_longname'))), $gL10n->get('SYS_REMOVE'));
        // => EXIT
    }
}

$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
// => EXIT
