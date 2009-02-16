<?php
/******************************************************************************
 * User werden aus einer CSV-Datei importiert
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once(SERVER_PATH. '/adm_program/system/classes/table_members.php');
require_once(SERVER_PATH. '/adm_program/system/classes/role_dependency.php');

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
    $g_message->show('norights');
}

// Pflichtfelder prüfen

foreach($g_current_user->userFieldData as $field)
{
    if($field->getValue('usf_mandatory') == 1
    && strlen($_POST['usf-'. $field->getValue('usf_id')]) == 0)
    {
        $g_message->show('feld', $field->getValue('usf_name'));
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
                    $imported_fields[$field->getValue('usf_id')] = $field->getValue('usf_name');
                }

                if($field->getValue('usf_name') == 'Geschlecht')
                {
                    if(strtolower($col_value) == 'm'
                    || strtolower($col_value) == 'männlich'
                    || $col_value             == '1')
                    {
                        $user->setValue($field->getValue('usf_name'), '1');
                    }
                    if(strtolower($col_value) == 'w'
                    || strtolower($col_value) == 'weiblich'
                    || $col_value             == '2')
                    {
                        $user->setValue($field->getValue('usf_name'), '2');
                    }
                }
                elseif($field->getValue('usf_type') == 'CHECKBOX')
                {
                    if(strtolower($col_value) == 'j'
                    || strtolower($col_value) == 'ja'
                    || strtolower($col_value) == 'y'
                    || strtolower($col_value) == 'yes'
                    || $col_value             == '1')
                    {
                        $user->setValue($field->getValue('usf_name'), '1');
                    }
                    if(strtolower($col_value) == 'n'
                    || strtolower($col_value) == 'nein'
                    || strtolower($col_value) == 'no'
                    || $col_value             == '0'
                    || strlen($col_value)     == 0)
                    {
                        $user->setValue($field->getValue('usf_name'), '0');
                    }
                }
                elseif($field->getValue('usf_type') == 'DATE')
                {
                    if(strlen($col_value) > 0
                    && dtCheckDate($col_value))
                    {
                        $user->setValue($field->getValue('usf_name'), dtFormatDate($col_value, 'Y-m-d'));
                    }
                }
                elseif($field->getValue('usf_type') == 'EMAIL')
                {
                    if(isValidEmailAddress($col_value))
                    {
                        $user->setValue($field->getValue('usf_name'), substr($col_value, 0, 50));
                    }
                }
                elseif($field->getValue('usf_type') == 'INTEGER')
                {
                    // Zahl darf Punkt und Komma enthalten
                    if(is_numeric(strtr($col_value, ',.', '00')) == true)
                    {
                        $user->setValue($field->getValue('usf_name'), $col_value);
                    }
                }
                elseif($field->getValue('usf_type') == 'TEXT_BIG')
                {
                    $user->setValue($field->getValue('usf_name'), substr($col_value, 0, 255));
                }
                else
                {
                    $user->setValue($field->getValue('usf_name'), substr($col_value, 0, 50));
                }
            }
        }
    }

    // schauen, ob schon User mit dem Namen existieren
    $sql = 'SELECT usr_id 
              FROM '. TBL_USERS. '
              JOIN '. TBL_USER_DATA. ' last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = '.  $user->getProperty('Nachname', 'usf_id'). '
               AND last_name.usd_value  = "'. $user->getValue('Nachname'). '"
              JOIN '. TBL_USER_DATA. ' first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = '.  $user->getProperty('Vorname', 'usf_id'). '
               AND first_name.usd_value  = "'. $user->getValue('Vorname'). '"
             WHERE usr_valid = 1 ';
    $result = $g_db->query($sql);
    $dup_users = $g_db->num_rows($result);

    if($dup_users > 0)
    {
        $duplicate_user = new User($g_db);
        
        if($_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
        {
            // alle vorhandene User mit dem Namen loeschen            
            while($row = $g_db->fetch_array($result))
            {
                $duplicate_user->readData($row['usr_id']);
                $duplicate_user->delete();
            }
        }
        elseif($_SESSION['user_import_mode'] == USER_IMPORT_COMPLETE && $dup_users == 1)
        {
            // Daten des Nutzers werden angepasst
            $row = $g_db->fetch_array($result);
            $duplicate_user->readData($row['usr_id']);
            
            foreach($imported_fields as $key => $field_name)
            {
                if($field_name != 'Nachname' && $field_name != 'Vorname'
                && $duplicate_user->getValue($field_name) != $user->getValue($field_name))
                {
                    $duplicate_user->setValue($field_name, $user->getValue($field_name));
                }
            }
            $user = $duplicate_user;
        }
    }

    if( $dup_users == 0
    || ($dup_users  > 0 && $_SESSION['user_import_mode'] > USER_IMPORT_NOT_EDIT) )
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

    $line = next($_SESSION['file_lines']);
}

// Session-Variablen wieder initialisieren
$_SESSION['role']             = '';
$_SESSION['user_import_mode'] = '';
$_SESSION['file_lines']       = '';
$_SESSION['value_separator']  = '';

$g_message->setForwardUrl($g_root_path.'/adm_program/administration/members/members.php');
$g_message->show('import', $count_import);
?>