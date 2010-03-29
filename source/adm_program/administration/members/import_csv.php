<?php
/******************************************************************************
 * User werden aus einer CSV-Datei importiert
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/role_dependency.php');

// setzt die Ausfuehrungszeit des Scripts auf 8 Min., falls viele Daten importiert werden
// allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
@set_time_limit(500);

// Importmodus in sprechenden Konstanten definieren
define('USER_IMPORT_NOT_EDIT', '1');
define('USER_IMPORT_DUPLICATE', '2');
define('USER_IMPORT_DISPLACE', '3');
define('USER_IMPORT_COMPLETE', '4');

// nur berechtigte User duerfen User importieren
if(!$g_current_user->editUsers())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Pflichtfelder prüfen

foreach($g_current_user->userFieldData as $field)
{
    if($field->getValue('usf_mandatory') == 1
    && strlen($_POST['usf-'. $field->getValue('usf_id')]) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $field->getValue('usf_name')));
    }
}

if(array_key_exists('first_row', $_POST))
{
    $first_row_title = true;
}
else
{
    $first_row_title = false;
}

// jede Zeile aus der Datei einzeln durchgehen und den Benutzer in der DB anlegen
$line = reset($_SESSION['file_lines']);
$user = new User($g_db);
$member = new TableMembers($g_db);
$start_row    = 0;
$count_import = 0;
$imported_fields = array();
$depRoles = array();

// Abhängige Rollen ermitteln
$depRoles = RoleDependency::getParentRoles($g_db,$_SESSION['rol_id']);

if($first_row_title == true)
{
    // erste Zeile ueberspringen, da hier die Spaltenbezeichnungen stehen
    $line = next($_SESSION['file_lines']);
    $start_row = 1;
}


for($i = $start_row; $i < count($_SESSION['file_lines']); $i++)
{
    $user->clear();
    $arr_columns = explode($_SESSION['value_separator'], $line);

    foreach($arr_columns as $col_key => $col_value)
    {
        // Hochkomma und Spaces entfernen
        $col_value = trim(strip_tags(str_replace('"', '', $col_value)));
        $col_value_to_lower = admStrToLower($col_value);

        // nun alle Userfelder durchgehen und schauen, bei welchem
        // die entsprechende Dateispalte ausgewaehlt wurde
        // dieser dann den Wert zuordnen
        foreach($user->userFieldData as $field)
        {
            if(strlen($_POST['usf-'. $field->getValue('usf_id')]) > 0 && $col_key == $_POST['usf-'. $field->getValue('usf_id')])
            {
                // importiertes Feld merken
                if(!isset($imported_fields[$field->getValue('usf_id')]))
                {
                    $imported_fields[$field->getValue('usf_id')] = $field->getValue('usf_name_intern');
                }

                if($field->getValue('usf_name_intern') == 'GENDER')
                {
                    if($col_value_to_lower == 'm'
                    || $col_value_to_lower == admStrToLower($g_l10n->get('SYS_MALE'))
                    || $col_value_to_lower == '1')
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '1');
                    }
                    if($col_value_to_lower == 'w'
                    || $col_value_to_lower == admStrToLower($g_l10n->get('SYS_FEMALE'))
                    || $col_value_to_lower == '2')
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '2');
                    }
                }
                elseif($field->getValue('usf_type') == 'CHECKBOX')
                {
                    if($col_value_to_lower == 'j'
                    || $col_value_to_lower == admStrToLower($g_l10n->get('SYS_YES'))
                    || $col_value_to_lower == 'y'
                    || $col_value_to_lower == 'yes'
                    || $col_value_to_lower == '1')
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '1');
                    }
                    if($col_value_to_lower == 'n'
                    || $col_value_to_lower == admStrToLower($g_l10n->get('SYS_NO'))
                    || $col_value_to_lower == 'no'
                    || $col_value_to_lower  == '0'
                    || strlen($col_value) == 0)
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '0');
                    }
                }
                elseif($field->getValue('usf_type') == 'DATE')
                {
                    if(strlen($col_value) > 0)
                    {
                        $date = new DateTimeExtended($col_value, $g_preferences['system_date'], 'date');
                        if($date->valid())
                        {
                            $user->setValue($field->getValue('usf_name_intern'), $date->format('Y-m-d'));
                        }
                    }
                }
                elseif($field->getValue('usf_type') == 'EMAIL')
                {
                    if(isValidEmailAddress($col_value))
                    {
                        $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 50));
                    }
                }
                elseif($field->getValue('usf_type') == 'INTEGER')
                {
                    // Zahl darf Punkt und Komma enthalten
                    if(is_numeric(strtr($col_value, ',.', '00')) == true)
                    {
                        $user->setValue($field->getValue('usf_name_intern'), $col_value);
                    }
                }
                elseif($field->getValue('usf_type') == 'TEXT_BIG')
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 255));
                }
                else
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 50));
                }
            }
        }
    }

    // nur Benutzer anlegen, wenn Vor- und Nachname vorhanden sind
    if(strlen($user->getValue('LAST_NAME')) > 0 && strlen($user->getValue('FIRST_NAME')) > 0)
    {
        // schauen, ob schon User mit dem Namen existieren und Daten einlesen
        $sql = 'SELECT MAX(usr_id) AS usr_id
                  FROM '. TBL_USERS. '
                  JOIN '. TBL_USER_DATA. ' last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = '.  $user->getProperty('LAST_NAME', 'usf_id'). '
                   AND last_name.usd_value  = "'. $user->getValue('LAST_NAME'). '"
                  JOIN '. TBL_USER_DATA. ' first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = '.  $user->getProperty('FIRST_NAME', 'usf_id'). '
                   AND first_name.usd_value  = "'. $user->getValue('FIRST_NAME'). '"
                 WHERE usr_valid = 1 ';
        $result = $g_db->query($sql);
        $row_duplicate_user = $g_db->fetch_array($result);
        if($row_duplicate_user['usr_id'] > 0)
        {
            $duplicate_user = new User($g_db, $row_duplicate_user['usr_id']);
        }
    
        if($row_duplicate_user['usr_id'] > 0)
        {
            if($_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // alle vorhandene Profilfelddaten des Users loeschen
                $duplicate_user->clearUserFieldArray(true);
            }
    
            if($_SESSION['user_import_mode'] == USER_IMPORT_COMPLETE
            || $_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // Daten des Nutzers werden angepasst
                foreach($imported_fields as $key => $field_name_intern)
                {
                    if($duplicate_user->getValue($field_name_intern) != $user->getValue($field_name_intern))
                    {
                        $duplicate_user->setValue($field_name_intern, $user->getValue($field_name_intern));
                    }
                }
                $user = $duplicate_user;
            }
        }
    
        if( $row_duplicate_user['usr_id'] == 0
        || ($row_duplicate_user['usr_id']  > 0 && $_SESSION['user_import_mode'] > USER_IMPORT_NOT_EDIT) )
        {
            // Usersatz anlegen
            $user->save();
            $count_import++;
            // Rollenmitgliedschaft zuordnen
            $member->startMembership($_SESSION['rol_id'], $user->getValue('usr_id'));
            
            //abhängige Rollen zuordnen
            foreach($depRoles as $depRole)
            {
                $member->startMembership($depRole, $user->getValue('usr_id'));
            }
            
            
        }
    }

    $line = next($_SESSION['file_lines']);
}

// Session-Variablen wieder initialisieren
$_SESSION['role']             = '';
$_SESSION['user_import_mode'] = '';
$_SESSION['file_lines']       = '';
$_SESSION['value_separator']  = '';

$g_message->setForwardUrl($g_root_path.'/adm_program/administration/members/members.php');
$g_message->show($g_l10n->get('MEM_PHR_IMPORT_SUCCESSFUL', $count_import));
?>