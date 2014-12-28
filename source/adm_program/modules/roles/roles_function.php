<?php
/******************************************************************************
 * Various functions for roles handling
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
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
$getRoleId = admFuncVariableIsValid($_GET, 'rol_id', 'numeric');
$getMode   = admFuncVariableIsValid($_GET, 'mode', 'numeric', array('requireValue' => true));

// only members who are allowed to create and edit roles should have access to 
// most of these functions
if(!$gCurrentUser->manageRoles() && $getMode != 9)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Rollenobjekt anlegen
$role = new TableRoles($gDb);

if($getRoleId > 0)
{
    $role->readDataById($getRoleId);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id')
    && $role->getValue('cat_org_id') > 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

$_SESSION['roles_request'] = $_POST;

if($getMode == 1)
{
    // create html page object
    $page = new HtmlPage();

    // show headline of the script
    $headline = $gL10n->get('ROL_ROLE_DELETE');
    $page->addHeadline($headline);

    // create module menu with back link
    $messageMenu = new HtmlNavbar('menu_message', $headline, $page);
    $messageMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
    $page->addHtml($messageMenu->show(false));

	$page->addHtml('
	<div class="message">
		<p class="lead">
                <img src="'. THEME_PATH. '/icons/roles_gray.png" alt="'.$gL10n->get('ROL_INACTIV_ROLE').'" />
                '.$gL10n->get('ROL_INACTIV_ROLE_DESC').'<br /><br />
                <img src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('ROL_ROLE_DELETE').'" />
                '.$gL10n->get('ROL_HINT_DELETE_ROLE', $gL10n->get('SYS_DELETE')).'
        </p>
        
        <button id="btn_inactive" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/roles/roles_function.php?rol_id='.$getRoleId.'&mode=3\'"><img
            src="'. THEME_PATH. '/icons/roles_gray.png" alt="'.$gL10n->get('ROL_INACTIV_ROLE').'" />&nbsp;'.$gL10n->get('ROL_INACTIV_ROLE').'</button>
        &nbsp;&nbsp;&nbsp;&nbsp;
        <button id="btn_delete" type="button" class="btn btn-primary"
            onclick="self.location.href=\''.$g_root_path.'/adm_program/modules/roles/roles_function.php?rol_id='.$getRoleId.'&mode=4\'"><img
            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" />&nbsp;'.$gL10n->get('SYS_DELETE').'</button>
    </div>');

    $page->show();
    exit();
}
elseif($getMode == 2)
{
    // Rolle anlegen oder updaten

    if(strlen($_POST['rol_name']) == 0)
    {
        // es sind nicht alle Felder gefuellt
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_NAME')));
    }
    if($_POST['rol_cat_id'] == 0)
    {
        // es sind nicht alle Felder gefuellt
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_CATEGORY')));
    }

    if($role->getValue('rol_name') != $_POST['rol_name'])
    {
        // Schauen, ob die Rolle bereits existiert
        $sql    = 'SELECT COUNT(*) as count
                     FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                    WHERE rol_name   LIKE \''. $_POST['rol_name']. '\'
                      AND rol_cat_id = '. $_POST['rol_cat_id']. '
                      AND rol_id    <> '. $getRoleId. '
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').' 
                          OR cat_org_id IS NULL ) ';
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);

        if($row['count'] > 0)
        {
            $gMessage->show($gL10n->get('ROL_ROLE_NAME_EXISTS'));
        }
    }

    // bei der Rolle "Webmaster" muessen bestimmte Flags gesetzt sein
    if($role->getValue('rol_webmaster') == 1)
    {
        $_POST['rol_assign_roles']   = 1;
        $_POST['rol_all_lists_view'] = 1;
        $_POST['rol_mail_to_all']    = 1;
    }

    // bei allen Checkboxen muss geprueft werden, ob hier ein Wert uebertragen wurde
    // falls nicht, dann den Wert hier auf 0 setzen, da 0 nicht uebertragen wird

    $checkboxes = array('rol_assign_roles'
                       ,'rol_approve_users'
                       ,'rol_announcements'
                       ,'rol_dates'
					   ,'rol_default_registration'
                       ,'rol_photo'
                       ,'rol_download'
                       ,'rol_guestbook'
                       ,'rol_guestbook_comments'
                       ,'rol_edit_user'
                       ,'rol_weblinks'
                       ,'rol_all_lists_view'
					   ,'rol_mail_to_all'
                       ,'rol_profile');

    foreach($checkboxes as $key => $value)
    {
        if(isset($_POST[$value]) == false || $_POST[$value] != 1)
        {
            $_POST[$value] = 0;
        }
    }


    // Zeitraum von/bis auf Gueltigkeit pruefen

    if(strlen($_POST['rol_start_date']) > 0)
    {
        $startDate = new DateTimeExtended($_POST['rol_start_date'], $gPreferences['system_date'], 'date');

        if($startDate->valid())
        {
            $_POST['rol_start_date'] = $startDate->format('Y-m-d');

            if(strlen($_POST['rol_end_date']) > 0)
            {
                $endDate = new DateTimeExtended($_POST['rol_end_date'], $gPreferences['system_date'], 'date');

                if($endDate->valid())
                {
                    $_POST['rol_end_date'] = $endDate->format('Y-m-d');
                }
                else
                {
                    $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('ROL_VALID_TO'), $gPreferences['system_date']));
                }

                // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
    			if ($startDate->getTimestamp() > $endDate->getTimestamp()) 
    			{
    				$gMessage->show($gL10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    			}
            }
            else
            {
                $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ROL_VALID_TO')));
            }
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_DATE_INVALID', $gL10n->get('ROL_VALID_FROM'), $gPreferences['system_date']));
        }
    }

    // Uhrzeit von/bis auf Gueltigkeit pruefen

    if(strlen($_POST['rol_start_time']) > 0)
    {
        $startTime = new DateTimeExtended($_POST['rol_start_time'], $gPreferences['system_time'], 'time');

        if($startTime->valid())
        {
            $_POST['rol_start_time'] = $startTime->format('H:i:s');
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('ROL_TIME_FROM'), $gPreferences['system_time']));
        }

        if(strlen($_POST['rol_end_time']) > 0)
        {
            $endTime = new DateTimeExtended($_POST['rol_end_time'], $gPreferences['system_time'], 'time');

            if($endTime->valid())
            {
                $_POST['rol_end_time'] = $endTime->format('H:i:s');
            }
            else
            {
                $gMessage->show($gL10n->get('SYS_TIME_INVALID', $gL10n->get('ROL_TIME_TO'), $gPreferences['system_time']));
            }
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('ROL_TIME_TO')));
        }
    }

    // Kontrollieren ob bei nachtraeglicher Senkung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde
    if($getRoleId > 0 && $_POST['rol_max_members'] != $role->getValue('rol_max_members'))
    {
        // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
		$role->setValue('rol_max_members', $_POST['rol_max_members']);
        $num_free_places = $role->countVacancies();

        if($num_free_places < 0)
        {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', $role->getValue('rol_name')));
        }
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'rol_') === 0)
        {
            $returnCode = $role->setValue($key, $value);
			
			// at least one role must have this flag otherwise show error
			if($returnCode == false && $key == 'rol_default_registration')
			{
				$gMessage->show($gL10n->get('ROL_NO_DEFAULT_ROLE', $gL10n->get('ROL_DEFAULT_REGISTRATION')));
			}
        }
    }

    // Daten in Datenbank schreiben
    $returnCode = $role->save();

    if($returnCode < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    // holt die Role ID des letzten Insert Statements
    if($getRoleId == 0)
    {
        $getRoleId = $role->getValue('rol_id');
    }

    // save role dependencies in database
    if(array_key_exists('dependent_roles', $_POST))
    {
        $sentChildRoles = $_POST['dependent_roles'];
        
        $roleDep = new RoleDependency($gDb);

        // holt eine Liste der ausgewählten Rolen
        $dbChildRoles = RoleDependency::getChildRoles($gDb,$getRoleId);

        //entferne alle Rollen die nicht mehr ausgewählt sind
        if($dbChildRoles != -1)
        {
            foreach ($dbChildRoles as $dbChildRole)
            {
                if(in_array($dbChildRole,$sentChildRoles) == false)
                {
                    $roleDep->get($dbChildRole,$getRoleId);
                    $roleDep->delete();
                }
            }
        }

        // add all new role dependencies to database
        if(count($sentChildRoles) > 0)
        {
            foreach ($sentChildRoles as $sentChildRole)
            {
                if($dbChildRoles != -1 && in_array($sentChildRole,$dbChildRoles) == false && $sentChildRole > 0)
                {
                    $roleDep->clear();
                    $roleDep->setChild($sentChildRole);
                    $roleDep->setParent($getRoleId);
                    $roleDep->insert($gCurrentUser->getValue('usr_id'));

                    //füge alle Mitglieder der ChildRole der ParentRole zu
                    $roleDep->updateMembership();
                }
            }
        }
    }
    else
    {
        RoleDependency::removeChildRoles($gDb,$getRoleId);
    }

    $gNavigation->deleteLastUrl();
    unset($_SESSION['roles_request']);

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('SYS_SAVE_DATA'));
}
elseif($getMode == 3)
{
    // Rolle zur inaktiven Rolle machen
    $return_code = $role->setInactive();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $gL10n->get('SYS_INACTIVE')));
}
elseif($getMode == 4)
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
}
elseif($getMode == 5)
{
    // Rolle wieder aktiv setzen
    $return_code = $role->setActive();

    if($return_code < 0)
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }

    $gMessage->setForwardUrl($gNavigation->getUrl());
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $gL10n->get('SYS_ACTIVE')));
}
elseif($getMode == 6)
{
    // Fragen, ob die inaktive Rolle geloescht werden soll
    $gMessage->setForwardYesNo($g_root_path.'/adm_program/modules/roles/roles_function.php?rol_id='.$getRoleId.'&amp;mode=4');
    $gMessage->show($gL10n->get('ROL_ROLE_DELETE_DESC', $role->getValue('rol_name')));
}
elseif($getMode == 7)
{
    $role->setValue('rol_visible',0);
    $role->save();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $gL10n->get('SYS_INVISIBLE')));
}
elseif($getMode == 8)
{
    $role->setValue('rol_visible',1);
    $role->save();

    $gMessage->setForwardUrl($gNavigation->getUrl(), 2000);
    $gMessage->show($gL10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $gL10n->get('SYS_VISIBLE')));
}
elseif($getMode == 9)
{
    if($role->hasFormerMembers() == true)
    {
        echo '1';
    }
    else
    {
        echo '0';
    }
    exit();
}
?>