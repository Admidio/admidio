<?php
/******************************************************************************
 * User werden aus einer CSV-Datei importiert
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Pflichtfelder prüfen

foreach($gProfileFields->mProfileFields as $field)
{
    if($field->getValue('usf_mandatory') == 1
    && strlen($_POST['usf-'. $field->getValue('usf_id')]) == 0)
    {
        $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $field->getValue('usf_name')));
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
$user = new User($gDb, $gProfileFields);
$member = new TableMembers($gDb);
$start_row    = 0;
$count_import = 0;
$imported_fields = array();
$depRoles = array();

// Abhängige Rollen ermitteln
$depRoles = RoleDependency::getParentRoles($gDb,$_SESSION['rol_id']);

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
        foreach($gProfileFields->mProfileFields as $field)
        {
            if(strlen($_POST['usf-'. $field->getValue('usf_id')]) > 0 && $col_key == $_POST['usf-'. $field->getValue('usf_id')])
            {
                // importiertes Feld merken
                if(!isset($imported_fields[$field->getValue('usf_id')]))
                {
                    $imported_fields[$field->getValue('usf_id')] = $field->getValue('usf_name_intern');
                }

				if($field->getValue('usf_name_intern') == 'COUNTRY')
				{
					$user->setValue($field->getValue('usf_name_intern'), $gL10n->getCountryByName($col_value));
				}
                elseif($field->getValue('usf_type') == 'CHECKBOX')
                {
                    if($col_value_to_lower == 'j'
                    || $col_value_to_lower == admStrToLower($gL10n->get('SYS_YES'))
                    || $col_value_to_lower == 'y'
                    || $col_value_to_lower == 'yes'
                    || $col_value_to_lower == '1')
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '1');
                    }
                    if($col_value_to_lower == 'n'
                    || $col_value_to_lower == admStrToLower($gL10n->get('SYS_NO'))
                    || $col_value_to_lower == 'no'
                    || $col_value_to_lower  == '0'
                    || strlen($col_value) == 0)
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '0');
                    }
                }
                elseif($field->getValue('usf_type') == 'DROPDOWN'
                    || $field->getValue('usf_type') == 'RADIO_BUTTON')
                {
					// Position aus der Auswahlbox speichern
					$arrListValues = $field->getValue('usf_value_list', 'text');
					$position = 0;

					foreach($arrListValues as $key => $value)
					{
						if(strcmp($col_value, trim($arrListValues[$position])) == 0)
						{
							// if col_value is text than save position if text is equal to text of position
							$user->setValue($field->getValue('usf_name_intern'), $position+1);
						}
						elseif(is_numeric($col_value) && !is_numeric($arrListValues[$position]) && $col_value > 0 && $col_value < 1000)
						{
							// if col_value is numeric than save position if col_value is equal to position
							$user->setValue($field->getValue('usf_name_intern'), $col_value);
						}
						$position++;
					}
                }
                elseif($field->getValue('usf_type') == 'EMAIL')
                {
                    $col_value = admStrToLower($col_value);
                    if(strValidCharacters($col_value, 'email'))
                    {
                        $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 255));
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
                elseif($field->getValue('usf_type') == 'TEXT')
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 50));
                }
                else
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($col_value, 0, 255));
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
                   AND last_name.usd_usf_id = '.  $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
                   AND last_name.usd_value  = \''. $user->getValue('LAST_NAME'). '\'
                  JOIN '. TBL_USER_DATA. ' first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = '.  $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
                   AND first_name.usd_value  = \''. $user->getValue('FIRST_NAME'). '\'
                 WHERE usr_valid = 1 ';
        $result = $gDb->query($sql);
        $row_duplicate_user = $gDb->fetch_array($result);
        if($row_duplicate_user['usr_id'] > 0)
        {
            $duplicate_user = new User($gDb, $gProfileFields, $row_duplicate_user['usr_id']);
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
						if($gProfileFields->getProperty($field_name_intern, 'usf_type') == 'DATE')
						{
							// the date must be formated
							$duplicate_user->setValue($field_name_intern, $user->getValue($field_name_intern, $gPreferences['system_date']));
						}
						elseif($field_name_intern == 'COUNTRY')
						{
							// we need the iso-code and not the name of the country
							$duplicate_user->setValue($field_name_intern, $gL10n->getCountryByName($user->getValue($field_name_intern)));
						}
						else
						{
							$duplicate_user->setValue($field_name_intern, $user->getValue($field_name_intern));
						}
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

$gMessage->setForwardUrl($g_root_path.'/adm_program/administration/members/members.php');
$gMessage->show($gL10n->get('MEM_IMPORT_SUCCESSFUL', $count_import));
?>