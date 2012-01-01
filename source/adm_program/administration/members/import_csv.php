<?php
/******************************************************************************
 * Import users from a csv file
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_members.php');
require_once('../../system/classes/role_dependency.php');

$_SESSION['import_csv_request'] = $_REQUEST;

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

// Lastname und firstname are mandatory fields
if(strlen($_POST['usf-'.$gProfileFields->getProperty('LAST_NAME', 'usf_id')]) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gProfileFields->getProperty('LAST_NAME', 'usf_name')));
}
if(strlen($_POST['usf-'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id')]) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gProfileFields->getProperty('FIRST_NAME', 'usf_name')));
}

if(array_key_exists('first_row', $_POST))
{
    $firstRowTitle = true;
}
else
{
    $firstRowTitle = false;
}

// jede Zeile aus der Datei einzeln durchgehen und den Benutzer in der DB anlegen
$line = reset($_SESSION['file_lines']);
$user = new User($gDb, $gProfileFields);
$member   = new TableMembers($gDb);
$startRow = 0;
$countImportNewUser  = 0;
$countImportEditUser = 0;
$countImportEditRole = 0;
$importedFields = array();
$depRoles = array();

// Abhängige Rollen ermitteln
$depRoles = RoleDependency::getParentRoles($gDb,$_SESSION['rol_id']);

if($firstRowTitle == true)
{
    // erste Zeile ueberspringen, da hier die Spaltenbezeichnungen stehen
    $line = next($_SESSION['file_lines']);
    $startRow = 1;
}


for($i = $startRow; $i < count($_SESSION['file_lines']); $i++)
{
    $user->clear();
    $columnArray = explode($_SESSION['value_separator'], $line);

    foreach($columnArray as $columnKey => $columnValue)
    {
        // Hochkomma und Spaces entfernen
        $columnValue = trim(strip_tags(str_replace('"', '', $columnValue)));
        $columnValueToLower = admStrToLower($columnValue);

        // nun alle Userfelder durchgehen und schauen, bei welchem
        // die entsprechende Dateispalte ausgewaehlt wurde
        // dieser dann den Wert zuordnen
        foreach($gProfileFields->mProfileFields as $field)
        {
            if(strlen($_POST['usf-'. $field->getValue('usf_id')]) > 0 && $columnKey == $_POST['usf-'. $field->getValue('usf_id')])
            {
                // importiertes Feld merken
                if(!isset($importedFields[$field->getValue('usf_id')]))
                {
                    $importedFields[$field->getValue('usf_id')] = $field->getValue('usf_name_intern');
                }

				if($field->getValue('usf_name_intern') == 'COUNTRY')
				{
					$user->setValue($field->getValue('usf_name_intern'), $gL10n->getCountryByName($columnValue));
				}
                elseif($field->getValue('usf_type') == 'CHECKBOX')
                {
                    if($columnValueToLower == 'j'
                    || $columnValueToLower == admStrToLower($gL10n->get('SYS_YES'))
                    || $columnValueToLower == 'y'
                    || $columnValueToLower == 'yes'
                    || $columnValueToLower == '1')
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '1');
                    }
                    if($columnValueToLower == 'n'
                    || $columnValueToLower == admStrToLower($gL10n->get('SYS_NO'))
                    || $columnValueToLower == 'no'
                    || $columnValueToLower  == '0'
                    || strlen($columnValue) == 0)
                    {
                        $user->setValue($field->getValue('usf_name_intern'), '0');
                    }
                }
                elseif($field->getValue('usf_type') == 'DROPDOWN'
                    || $field->getValue('usf_type') == 'RADIO_BUTTON')
                {
					// Position aus der Auswahlbox speichern
					$arrListValues = $field->getValue('usf_value_list', 'text');
					$position = 1;

					foreach($arrListValues as $key => $value)
					{
						if(strcmp($columnValue, trim($arrListValues[$position])) == 0)
						{
							// if col_value is text than save position if text is equal to text of position
							$user->setValue($field->getValue('usf_name_intern'), $position);
						}
						elseif(is_numeric($columnValue) && !is_numeric($arrListValues[$position]) && $columnValue > 0 && $columnValue < 1000)
						{
							// if col_value is numeric than save position if col_value is equal to position
							$user->setValue($field->getValue('usf_name_intern'), $columnValue);
						}
						$position++;
					}
                }
                elseif($field->getValue('usf_type') == 'EMAIL')
                {
                    $columnValue = admStrToLower($columnValue);
                    if(strValidCharacters($columnValue, 'email'))
                    {
                        $user->setValue($field->getValue('usf_name_intern'), substr($columnValue, 0, 255));
                    }
                }
                elseif($field->getValue('usf_type') == 'INTEGER')
                {
                    // Zahl darf Punkt und Komma enthalten
                    if(is_numeric(strtr($columnValue, ',.', '00')) == true)
                    {
                        $user->setValue($field->getValue('usf_name_intern'), $columnValue);
                    }
                }
                elseif($field->getValue('usf_type') == 'TEXT')
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($columnValue, 0, 50));
                }
                else
                {
                    $user->setValue($field->getValue('usf_name_intern'), substr($columnValue, 0, 255));
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
        $rowDuplicateUser = $gDb->fetch_array($result);
        if($rowDuplicateUser['usr_id'] > 0)
        {
            $duplicate_user = new User($gDb, $gProfileFields, $rowDuplicateUser['usr_id']);
        }
    
        if($rowDuplicateUser['usr_id'] > 0)
        {
            if($_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // delete all user data of profile fields
                $duplicate_user->deleteUserFieldData();
            }
    
            if($_SESSION['user_import_mode'] == USER_IMPORT_COMPLETE
            || $_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // Daten des Nutzers werden angepasst
                foreach($importedFields as $key => $field_name_intern)
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
    
        if( $rowDuplicateUser['usr_id'] == 0
        || ($rowDuplicateUser['usr_id']  > 0 && $_SESSION['user_import_mode'] > USER_IMPORT_NOT_EDIT) )
        {
            if($rowDuplicateUser['usr_id'] == 0)
            {
                $countImportNewUser++;
            }
            elseif($rowDuplicateUser['usr_id']  > 0 && $user->columnsValueChanged)
            {
                $countImportEditUser++;
            }
        
            // save user data
            $user->save();            

            // assign role membership to user
            if($member->startMembership($_SESSION['rol_id'], $user->getValue('usr_id')))
            {
                $countImportEditRole++;
            }
            
            // assign dependent role memberships to user
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
$gMessage->show($gL10n->get('MEM_IMPORT_SUCCESSFUL', $countImportNewUser, $countImportEditUser, $countImportEditRole));
?>