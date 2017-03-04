<?php
/**
 ***********************************************************************************************
 * Various functions for roles handling
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * rol_id: ID of role, that should be edited
 * mode :  1 - show different consequences of role will be deleted
 *         2 - create or edit role
 *         3 - set role inaktive
 *         4 - delete role
 *         5 - set role active
 *         6 - ask if inactive role should be deleted
 *         7 - set role invisible
 *         8 - set role visible
 *         9 - return if role has former members ? Return: 1 und 0
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'int');
$getMode   = admFuncVariableIsValid($_GET, 'mode',   'int', array('requireValue' => true));

// only members who are allowed to create and edit roles should have access to
// most of these functions
if($getMode !== 9 && !$gCurrentUser->manageRoles())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

if($getRoleId > 0)
{
    $role->readDataById($getRoleId);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if((int) $role->getValue('cat_org_id') !== (int) $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }
}

$_SESSION['roles_request'] = $_POST;
$rolName = $role->getValue('rol_name');

if($getMode === 1)
{
    // create html page object
    $page = new HtmlPage($gL10n->get('ROL_ROLE_DELETE'));

    // add back link to module menu
    $messageMenu = $page->getMenu();
    $messageMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

    $page->addHtml('
        <div class="message">
            <p class="lead">
                <img src="'. THEME_URL. '/icons/roles_gray.png" alt="'.$gL10n->get('ROL_INACTIV_ROLE').'" />
                '.$gL10n->get('ROL_INACTIV_ROLE_DESC').'<br /><br />
                <img src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" />
                '.$gL10n->get('ROL_HINT_DELETE_ROLE', $gL10n->get('SYS_DELETE')).'
            </p>

            <button id="btn_inactive" type="button" class="btn btn-primary"
                onclick="self.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$getRoleId.'&mode=3\'"><img
                src="'. THEME_URL. '/icons/roles_gray.png" alt="'.$gL10n->get('ROL_INACTIV_ROLE').'" />&nbsp;'.$gL10n->get('ROL_INACTIV_ROLE').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btn_delete" type="button" class="btn btn-primary"
                onclick="self.location.href=\''.ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$getRoleId.'&mode=4\'"><img
                src="'. THEME_URL. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" />&nbsp;'.$gL10n->get('SYS_DELETE').'</button>
        </div>'
    );

    $page->show();
    exit();
}
elseif($getMode === 2)
{
    // Rolle anlegen oder updaten

    if(!array_key_exists('rol_name', $_POST) || $_POST['rol_name'] === '')
    {
        // es sind nicht alle Felder gefuellt
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
        // => EXIT
    }
    if((int) $_POST['rol_cat_id'] === 0)
    {
        // es sind nicht alle Felder gefuellt
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
        // => EXIT
    }

    if($rolName !== $_POST['rol_name'])
    {
        // Schauen, ob die Rolle bereits existiert
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_name LIKE \''. $_POST['rol_name']. '\'
                   AND rol_cat_id = '. (int) $_POST['rol_cat_id']. '
                   AND rol_id    <> '. $getRoleId. '
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )';
        $pdoStatement = $gDb->query($sql);

        if($pdoStatement->fetchColumn() > 0)
        {
            $gMessage->show($gL10n->get('ROL_ROLE_NAME_EXISTS'));
            // => EXIT
        }
    }

    // Administrator role need some more flags
    if($role->getValue('rol_administrator') == 1)
    {
        $_POST['rol_assign_roles']   = 1;
        $_POST['rol_all_lists_view'] = 1;
        $_POST['rol_mail_to_all']    = 1;
    }

    // bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
    // falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

    $checkboxes = array(
        'rol_assign_roles',
        'rol_approve_users',
        'rol_announcements',
        'rol_dates',
        'rol_default_registration',
        'rol_photo', 'rol_download',
        'rol_guestbook',
        'rol_guestbook_comments',
        'rol_edit_user',
        'rol_weblinks',
        'rol_all_lists_view',
        'rol_mail_to_all',
        'rol_profile',
        'rol_inventory'
    );

    foreach($checkboxes as $key => $value)
    {
        if(!isset($_POST[$value]) || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }

    // ------------------------------------------------
    // Check valid format of date input
    // ------------------------------------------------

    $validFromDate = '';
    $validToDate   = '';

    if(strlen($_POST['rol_start_date']) > 0)
    {
        $validFromDate = DateTime::createFromFormat($gPreferences['system_date'], $_POST['rol_start_date']);
        if(!$validFromDate)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('ROL_VALID_FROM'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            // now write date and time with database format to date object
            $_POST['rol_start_date'] = $validFromDate->format('Y-m-d');
        }
    }

    if(strlen($_POST['rol_end_date']) > 0)
    {
        $validToDate = DateTime::createFromFormat($gPreferences['system_date'], $_POST['rol_end_date']);
        if(!$validToDate)
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('ROL_VALID_TO'), $gPreferences['system_date']));
            // => EXIT
        }
        else
        {
            // now write date and time with database format to date object
            $_POST['rol_end_date'] = $validToDate->format('Y-m-d');
        }
    }

    // DateTo should be greater than DateFrom (Timestamp must be less)
    if(strlen($_POST['rol_start_date']) > 0 && strlen($_POST['rol_end_date']) > 0)
    {
        if ($validFromDate > $validToDate)
        {
            $gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
            // => EXIT
        }

    }

    // ------------------------------------------------
    // Check valid format of time input
    // ------------------------------------------------

    if(strlen($_POST['rol_start_time']) > 0)
    {
        $validFromTime = DateTime::createFromFormat('Y-m-d '.$gPreferences['system_time'], DATE_NOW.' '.$_POST['rol_start_time']);
        if(!$validFromTime)
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('ROL_TIME_FROM'), $gPreferences['system_time']));
            // => EXIT
        }
        else
        {
            // now write date and time with database format to date object
            $_POST['rol_start_time'] = $validFromTime->format('H:i:s');
        }
    }

    if(strlen($_POST['rol_end_time']) > 0)
    {
        $validToTime = DateTime::createFromFormat('Y-m-d '.$gPreferences['system_time'], DATE_NOW.' '.$_POST['rol_end_time']);
        if(!$validToTime)
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('ROL_TIME_TO'), $gPreferences['system_time']));
            // => EXIT
        }
        else
        {
            // now write date and time with database format to date object
            $_POST['rol_end_time'] = $validToTime->format('H:i:s');
        }
    }

    // Kontrollieren ob bei nachtraeglicher Senkung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde
    if($getRoleId > 0 && (int) $_POST['rol_max_members'] !== (int) $role->getValue('rol_max_members'))
    {
        // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
        $role->setValue('rol_max_members', $_POST['rol_max_members']);
        $num_free_places = $role->countVacancies();

        if($num_free_places < 0)
        {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', $rolName));
            // => EXIT
        }
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'rol_') === 0)
        {
            $returnCode = $role->setValue($key, $value);

            // at least one role must have this flag otherwise show error
            if(!$returnCode && $key === 'rol_default_registration')
            {
                $gMessage->show($gL10n->get('ROL_NO_DEFAULT_ROLE', $gL10n->get('ROL_DEFAULT_REGISTRATION')));
                // => EXIT
            }
        }
    }

    // Daten in Datenbank schreiben
    $returnCode = $role->save();

    if($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    // holt die Role ID des letzten Insert Statements
    if($getRoleId === 0)
    {
        $getRoleId = $role->getValue('rol_id');
    }

    // save role dependencies in database
    if(array_key_exists('dependent_roles', $_POST))
    {
        $sentChildRoles = array_map('intval', $_POST['dependent_roles']);

        $roleDep = new RoleDependency($gDb);

        // holt eine Liste der ausgewählten Rolen
        $dbChildRoles = RoleDependency::getChildRoles($gDb, $getRoleId);

        // entferne alle Rollen die nicht mehr ausgewählt sind
        if(count($dbChildRoles) > 0)
        {
            foreach ($dbChildRoles as $dbChildRole)
            {
                if(!in_array($dbChildRole, $sentChildRoles, true))
                {
                    $roleDep->get($dbChildRole, $getRoleId);
                    $roleDep->delete();
                }
            }
        }

        // add all new role dependencies to database
        if(count($sentChildRoles) > 0)
        {
            foreach ($sentChildRoles as $sentChildRole)
            {
                if($sentChildRole > 0 && !in_array($sentChildRole, $dbChildRoles, true))
                {
                    $roleDep->clear();
                    $roleDep->setChild($sentChildRole);
                    $roleDep->setParent($getRoleId);
                    $roleDep->insert($gCurrentUser->getValue('usr_id'));

                    // füge alle Mitglieder der ChildRole der ParentRole zu
                    $roleDep->updateMembership();
                }
            }
        }
    }
    else
    {
        RoleDependency::removeChildRoles($gDb, $getRoleId);
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['roles_request']);

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
    // => EXIT
}
elseif($getMode === 3)
{
    // Rolle zur inaktiven Rolle machen
    $returnValue = $role->setInactive();

    if($returnValue === false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $rolName, $gL10n->get('SYS_INACTIVE')));
    // => EXIT
}
elseif($getMode === 4)
{
    // delete role from database
    try
    {
        $role->delete();
    }
    catch(AdmException $e)
    {
        $e->showHtml();
    }

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_DELETE_DATA'));
    // => EXIT
}
elseif($getMode === 5)
{
    // Rolle wieder aktiv setzen
    $returnValue = $role->setActive();

    if($returnValue === false)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $rolName, $gL10n->get('SYS_ACTIVE')));
    // => EXIT
}
elseif($getMode === 6)
{
    // Fragen, ob die inaktive Rolle geloescht werden soll
    $gMessage->setForwardYesNo(ADMIDIO_URL.FOLDER_MODULES.'/roles/roles_function.php?rol_id='.$getRoleId.'&amp;mode=4');
    $gMessage->show($gL10n->get('ROL_ROLE_DELETE_DESC', $rolName));
    // => EXIT
}
elseif($getMode === 7)
{
    $role->setValue('rol_visible', 0);
    $role->save();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $rolName, $gL10n->get('SYS_INVISIBLE')));
    // => EXIT
}
elseif($getMode === 8)
{
    $role->setValue('rol_visible', 1);
    $role->save();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $rolName, $gL10n->get('SYS_VISIBLE')));
    // => EXIT
}
elseif($getMode === 9)
{
    if($role->hasFormerMembers())
    {
        echo '1';
    }
    else
    {
        echo '0';
    }
    exit();
}
