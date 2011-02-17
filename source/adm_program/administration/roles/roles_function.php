<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Rollen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * rol_id: ID der Rolle, die angezeigt werden soll
 * mode :  1 - MsgBox, in der erklaert wird, welche Auswirkungen das Loeschen hat
 *         2 - Rolle anlegen oder updaten
 *         3 - Rolle zur inaktiven Rolle machen
 *         4 - Rolle loeschen
 *         5 - Rolle wieder aktiv setzen
 *         6 - Frage, ob inaktive Rolle geloescht werden soll
 *         7 - Rolle verstecken 
 *         8 - Rolle zeigen 
 *          
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_roles.php');
require_once('../../system/classes/role_dependency.php');

// nur Moderatoren duerfen Rollen erfassen & verwalten
if(!$g_current_user->assignRoles())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_rol_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['mode']) == false
|| is_numeric($_GET['mode']) == false
|| $_GET['mode'] < 1 || $_GET['mode'] > 8)
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['rol_id']))
{
    if(is_numeric($_GET['rol_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
    $req_rol_id = $_GET['rol_id'];
}

// Rollenobjekt anlegen
$role = new TableRoles($g_db);

if($req_rol_id > 0)
{
    $role->readData($req_rol_id);

    // Pruefung, ob die Rolle zur aktuellen Organisation gehoert
    if($role->getValue('cat_org_id') != $g_current_organization->getValue('org_id')
    && $role->getValue('cat_org_id') > 0)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}

$_SESSION['roles_request'] = $_REQUEST;
$msg_code = '';

if($_GET['mode'] == 1)
{
    // Html-Kopf ausgeben
    $g_layout['title'] = 'Messagebox';
    require(THEME_SERVER_PATH. '/overall_header.php');

    // Html des Modules ausgeben
    echo '
    <div class="formLayout" id="edit_announcements_form">
        <div class="formHead">'.$g_l10n->get('ROL_ROLE_DELETE').'</div>
        <div class="formBody">
            <p align="left">
                <img src="'. THEME_PATH. '/icons/roles_gray.png" alt="'.$g_l10n->get('ROL_INACTIV_ROLE').'" />
                '.$g_l10n->get('ROL_INACTIV_ROLE_DESC').'
            </p>
            <p align="left">
                <img src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('ROL_ROLE_DELETE').'" />
                '.$g_l10n->get('ROL_DELETE_ROLE', $g_l10n->get('SYS_DELETE')).'
            </p>
            <button id="btnDelete" type="button"
                onclick="self.location.href=\''.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='. $_GET['rol_id']. '&mode=4\'"><img
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" />&nbsp;'.$g_l10n->get('SYS_DELETE').'</button>
            &nbsp;&nbsp;&nbsp;&nbsp;
            <button id="btnInactive" type="button"
                onclick="self.location.href=\''.$g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='. $_GET['rol_id']. '&mode=3\'"><img
                src="'. THEME_PATH. '/icons/roles_gray.png" alt="'.$g_l10n->get('ROL_INACTIV_ROLE').'" />&nbsp;'.$g_l10n->get('ROL_INACTIV_ROLE').'</button>

            <ul class="iconTextLinkList">
                <li>
                    <span class="iconTextLink">
                        <a href="#" onclick="history.back()"><img
                        src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                        <a href="#" onclick="history.back()">'.$g_l10n->get('SYS_BACK').'</a>
                    </span>
                </li>
            </ul>
        </div>
    </div>';

    require(THEME_SERVER_PATH. '/overall_footer.php');
    exit();
}
elseif($_GET['mode'] == 2)
{
    // Rolle anlegen oder updaten

    if(strlen($_POST['rol_name']) == 0)
    {
        // es sind nicht alle Felder gefuellt
        $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_NAME')));
    }
    if($_POST['rol_cat_id'] == 0)
    {
        // es sind nicht alle Felder gefuellt
        $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('SYS_CATEGORY')));
    }

    if($role->getValue('rol_name') != $_POST['rol_name'])
    {
        // Schauen, ob die Rolle bereits existiert
        $sql    = "SELECT COUNT(*) as count
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                    WHERE rol_name   LIKE '". $_POST['rol_name']. "'
                      AND rol_cat_id = ". $_POST['rol_cat_id']. "
                      AND rol_id    <> ". $req_rol_id. "
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = ". $g_current_organization->getValue('org_id').' 
                          OR cat_org_id IS NULL ) ';
        $result = $g_db->query($sql);
        $row    = $g_db->fetch_array($result);

        if($row['count'] > 0)
        {
            $g_message->show($g_l10n->get('ROL_ROLE_NAME_EXISTS'));
        }
    }

    // bei der Rolle "Webmaster" muessen bestimmte Flags gesetzt sein
    if(strcmp($_POST['rol_name'], $g_l10n->get('SYS_WEBMASTER')) == 0)
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
                       ,'rol_photo'
                       ,'rol_download'
                       ,'rol_guestbook'
                       ,'rol_guestbook_comments'
                       ,'rol_inventory'
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
        $startDate = new DateTimeExtended($_POST['rol_start_date'], $g_preferences['system_date'], 'date');

        if($startDate->valid())
        {
            $_POST['rol_start_date'] = $startDate->format('Y-m-d');

            if(strlen($_POST['rol_end_date']) > 0)
            {
                $endDate = new DateTimeExtended($_POST['rol_end_date'], $g_preferences['system_date'], 'date');

                if($endDate->valid())
                {
                    $_POST['rol_end_date'] = $endDate->format('Y-m-d');
                }
                else
                {
                    $g_message->show($g_l10n->get('SYS_DATE_INVALID', $g_l10n->get('ROL_VALID_TO'), $g_preferences['system_date']));
                }

                // Enddatum muss groesser oder gleich dem Startdatum sein (timestamp dann umgekehrt kleiner)
    			if ($startDate->getTimestamp() > $endDate->getTimestamp()) 
    			{
    				$g_message->show($g_l10n->get('SYS_DATE_END_BEFORE_BEGIN'));
    			}
            }
            else
            {
                $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('ROL_VALID_TO')));
            }
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_DATE_INVALID', $g_l10n->get('ROL_VALID_FROM'), $g_preferences['system_date']));
        }
    }

    // Uhrzeit von/bis auf Gueltigkeit pruefen

    if(strlen($_POST['rol_start_time']) > 0)
    {
        $startTime = new DateTimeExtended($_POST['rol_start_time'], $g_preferences['system_time'], 'time');

        if($startTime->valid())
        {
            $_POST['rol_start_time'] = $startTime->format('H:i:s');
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_TIME_INVALID', $g_l10n->get('ROL_TIME_FROM'), $g_preferences['system_time']));
        }

        if(strlen($_POST['rol_end_time']) > 0)
        {
            $endTime = new DateTimeExtended($_POST['rol_end_time'], $g_preferences['system_time'], 'time');

            if($endTime->valid())
            {
                $_POST['rol_end_time'] = $endTime->format('H:i:s');
            }
            else
            {
                $g_message->show($g_l10n->get('SYS_TIME_INVALID', $g_l10n->get('ROL_TIME_TO'), $g_preferences['system_time']));
            }
        }
        else
        {
            $g_message->show($g_l10n->get('SYS_FIELD_EMPTY', $g_l10n->get('ROL_TIME_TO')));
        }
    }

    // Kontrollieren ob bei nachtraeglicher Senkung der maximalen Mitgliederzahl diese nicht bereits ueberschritten wurde

    if($req_rol_id > 0
    && $_POST['rol_max_members'] < $role->getValue('rol_max_members'))
    {
        // Zaehlen wieviele Leute die Rolle bereits haben, ohne Leiter
        $num_free_places = $role->countVacancies();

        if($num_free_places == 0)
        {
            $g_message->show($g_l10n->get('SYS_ROLE_MAX_MEMBERS', $role->getValue('rol_name')));
        }
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, 'rol_') === 0)
        {
            $role->setValue($key, $value);
        }
    }

    // Daten in Datenbank schreiben
    $return_code = $role->save();

    if($return_code < 0)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    // holt die Role ID des letzten Insert Statements
    if($req_rol_id == 0)
    {
        $req_rol_id = $role->getValue('rol_id');
    }

    //Rollenabhaengigkeiten setzten
    if(array_key_exists('ChildRoles', $_POST))
    {
        $sentChildRoles = $_POST['ChildRoles'];

        $roleDep = new RoleDependency($g_db);

        // holt eine Liste der ausgewählten Rolen
        $DBChildRoles = RoleDependency::getChildRoles($g_db,$req_rol_id);

        //entferne alle Rollen die nicht mehr ausgewählt sind
        if($DBChildRoles != -1)
        {
            foreach ($DBChildRoles as $DBChildRole)
            {
                if(in_array($DBChildRole,$sentChildRoles))
                    continue;
                else
                {

                    $roleDep->get($DBChildRole,$req_rol_id);
                    $roleDep->delete();
                }
            }
        }
        //fuege alle neuen Rollen hinzu
        foreach ($sentChildRoles as $sentChildRole)
        {
            if((-1 == $DBChildRoles) || in_array($sentChildRole,$DBChildRoles))
                continue;
            else
            {
                $roleDep->clear();
                $roleDep->setChild($sentChildRole);
                $roleDep->setParent($req_rol_id);
                $roleDep->insert($g_current_user->getValue('usr_id'));

                //füge alle Mitglieder der ChildRole der ParentRole zu
                $roleDep->updateMembership();

            }

        }

    }
    else
    {
        RoleDependency::removeChildRoles($g_db,$req_rol_id);
    }

    $_SESSION['navigation']->deleteLastUrl();
    unset($_SESSION['roles_request']);

    $msg_code = 'SYS_SAVE_DATA';
}
elseif($_GET['mode'] == 3)
{
    // Rolle zur inaktiven Rolle machen
    $return_code = $role->setInactive();

    if($return_code < 0)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    $g_message->show($g_l10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $g_l10n->get('SYS_INACTIVE')));
}
elseif($_GET['mode'] == 4)
{
    // Rolle aus der DB loeschens
    $return_code = $role->delete();

    if($return_code == false)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    $msg_code = 'SYS_DELETE_DATA';
}
elseif($_GET['mode'] == 5)
{
    // Rolle wieder aktiv setzen
    $return_code = $role->setActive();

    if($return_code < 0)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }

    $g_message->show($g_l10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $g_l10n->get('SYS_ACTIVE')));
}
elseif($_GET['mode'] == 6)
{
    // Fragen, ob die inaktive Rolle geloescht werden soll
    $g_message->setForwardYesNo($g_root_path.'/adm_program/administration/roles/roles_function.php?rol_id='.$req_rol_id.'&amp;mode=4');
    $g_message->show($g_l10n->get('ROL_ROLE_DELETE_DESC', $role->getValue('rol_name')));
}
elseif($_GET['mode'] == 7)
{
    $role->setValue('rol_visible',0);
    $role->save();

    $g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
    $g_message->show($g_l10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $g_l10n->get('SYS_INVISIBLE')));
}
elseif($_GET['mode'] == 8)
{
    $role->setValue('rol_visible',1);
    $role->save();

    $g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
    $g_message->show($g_l10n->get('ROL_ROLE_SET_MODE', $role->getValue('rol_name'), $g_l10n->get('SYS_VISIBLE')));
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($g_l10n->get($msg_code));
?>