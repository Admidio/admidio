<?php
/**
 ***********************************************************************************************
 * Import users from a csv file
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

$_SESSION['import_csv_request'] = $_POST;

// create readable constants for user import mode
define('USER_IMPORT_NOT_EDIT', '1');
define('USER_IMPORT_DUPLICATE', '2');
define('USER_IMPORT_DISPLACE', '3');
define('USER_IMPORT_COMPLETE', '4');

// nur berechtigte User duerfen User importieren
if(!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// Lastname und firstname are mandatory fields
if(strlen($_POST['usf-'.$gProfileFields->getProperty('LAST_NAME', 'usf_id')]) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('LAST_NAME', 'usf_name'))));
    // => EXIT
}
if(strlen($_POST['usf-'.$gProfileFields->getProperty('FIRST_NAME', 'usf_id')]) === 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', array($gProfileFields->getProperty('FIRST_NAME', 'usf_name'))));
    // => EXIT
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
$startRow = 0;
$countImportNewUser  = 0;
$countImportEditUser = 0;
$countImportEditRole = 0;
$importedFields = array();

// Abhängige Rollen ermitteln
$depRoles = RoleDependency::getParentRoles($gDb, (int) $_SESSION['rol_id']);

if($firstRowTitle)
{
    // erste Zeile ueberspringen, da hier die Spaltenbezeichnungen stehen
    $line = next($_SESSION['file_lines']);
    $startRow = 1;
}

// set execution time to 10 minutes because we have a lot to do
PhpIniUtils::startNewExecutionTimeLimit(600);

for($i = $startRow, $iMax = count($_SESSION['file_lines']); $i < $iMax; ++$i)
{
    $user->clear();
    $columnArray = explode($_SESSION['value_separator'], $line);

    foreach($columnArray as $columnKey => $columnValue)
    {
        // Hochkomma und Spaces entfernen
        $columnValue = trim(strip_tags(str_replace('"', '', $columnValue)));

        // nun alle Userfelder durchgehen und schauen, bei welchem
        // die entsprechende Dateispalte ausgewaehlt wurde
        // dieser dann den Wert zuordnen
        /**
         * @var TableUserField $field
         */
        foreach($gProfileFields->getProfileFields() as $field)
        {
            $usfId = (int) $field->getValue('usf_id');

            if(strlen($_POST['usf-'. $usfId]) > 0 && $columnKey == $_POST['usf-'. $usfId])
            {
                $usfNameIntern = $field->getValue('usf_name_intern');

                // importiertes Feld merken
                if(!isset($importedFields[$usfId]))
                {
                    $importedFields[$usfId] = $usfNameIntern;
                }

                if($usfNameIntern === 'COUNTRY')
                {
                    $user->setValue($usfNameIntern, $gL10n->getCountryIsoCode($columnValue));
                }
                else
                {
                    switch ($field->getValue('usf_type'))
                    {
                        case 'CHECKBOX':
                            $columnValueToLower = StringUtils::strToLower($columnValue);
                            if(in_array($columnValueToLower, array('y', 'yes', '1', 'j', StringUtils::strToLower($gL10n->get('SYS_YES'))), true))
                            {
                                $user->setValue($usfNameIntern, '1');
                            }
                            if(in_array($columnValueToLower, array('n', 'no', '0', '', StringUtils::strToLower($gL10n->get('SYS_NO'))), true))
                            {
                                $user->setValue($usfNameIntern, '0');
                            }
                            break;
                        case 'DROPDOWN':
                        case 'RADIO_BUTTON':
                            // save position of combobox
                            $arrListValues = $field->getValue('usf_value_list', 'text');
                            $position = 1;

                            foreach($arrListValues as $value)
                            {
                                if(StringUtils::strToLower($columnValue) === StringUtils::strToLower(trim($arrListValues[$position])))
                                {
                                    // if col_value is text than save position if text is equal to text of position
                                    $user->setValue($usfNameIntern, $position);
                                }
                                elseif(is_numeric($columnValue) && !is_numeric($arrListValues[$position]) && $columnValue > 0 && $columnValue < 1000)
                                {
                                    // if col_value is numeric than save position if col_value is equal to position
                                    $user->setValue($usfNameIntern, $columnValue);
                                }
                                ++$position;
                            }
                            break;
                        case 'EMAIL':
                            if(strValidCharacters($columnValue, 'email'))
                            {
                                $user->setValue($usfNameIntern, substr($columnValue, 0, 255));
                            }
                            break;
                        case 'INTEGER':
                            // number could contain dot and comma
                            if(is_numeric(strtr($columnValue, ',.', '00')))
                            {
                                $user->setValue($usfNameIntern, $columnValue);
                            }
                            break;
                        case 'TEXT':
                            $user->setValue($usfNameIntern, substr($columnValue, 0, 100));
                            break;
                        default:
                            $user->setValue($usfNameIntern, substr($columnValue, 0, 4000));
                    }
                }
            }
        }
    }

    // create new user only if firstname and lastname are filled
    if(strlen($user->getValue('LAST_NAME')) > 0 && strlen($user->getValue('FIRST_NAME')) > 0)
    {
        // search for existing user with same name and read user data
        $sql = 'SELECT MAX(usr_id) AS usr_id
                  FROM '.TBL_USERS.'
            INNER JOIN '.TBL_USER_DATA.' AS last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
                   AND last_name.usd_value  = ? -- $user->getValue(\'LAST_NAME\', \'database\')
            INNER JOIN '.TBL_USER_DATA.' AS first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
                   AND first_name.usd_value  = ? -- $user->getValue(\'FIRST_NAME\', \'database\')
                 WHERE usr_valid = 1';
        $queryParams = array(
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $user->getValue('LAST_NAME', 'database'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $user->getValue('FIRST_NAME', 'database')
        );
        $pdoStatement = $gDb->queryPrepared($sql, $queryParams);
        $maxUserId = (int) $pdoStatement->fetchColumn();
        if($maxUserId > 0)
        {
            $duplicateUser = new User($gDb, $gProfileFields, $maxUserId);
        }

        if($maxUserId > 0)
        {
            if($_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // delete all user data of profile fields
                $duplicateUser->deleteUserFieldData();
            }

            if($_SESSION['user_import_mode'] == USER_IMPORT_COMPLETE
            || $_SESSION['user_import_mode'] == USER_IMPORT_DISPLACE)
            {
                // edit data of user, if user already exists
                foreach($importedFields as $fieldNameIntern)
                {
                    if($duplicateUser->getValue($fieldNameIntern) != $user->getValue($fieldNameIntern))
                    {
                        if($gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'DATE')
                        {
                            // the date must be formated
                            $duplicateUser->setValue($fieldNameIntern, $user->getValue($fieldNameIntern, $gSettingsManager->getString('system_date')));
                        }
                        elseif($fieldNameIntern === 'COUNTRY')
                        {
                            // we need the iso-code and not the name of the country
                            $duplicateUser->setValue($fieldNameIntern, $gL10n->getCountryIsoCode($user->getValue($fieldNameIntern)));
                        }
                        elseif($gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'DROPDOWN'
                            || $gProfileFields->getProperty($fieldNameIntern, 'usf_type') === 'RADIO_BUTTON')
                        {
                            // get number and not value of entry
                            $duplicateUser->setValue($fieldNameIntern, $user->getValue($fieldNameIntern, 'database'));
                        }
                        else
                        {
                            $duplicateUser->setValue($fieldNameIntern, $user->getValue($fieldNameIntern));
                        }
                    }
                }
                $user = $duplicateUser;
            }
        }

        if($maxUserId === 0 || ($maxUserId > 0 && $_SESSION['user_import_mode'] > USER_IMPORT_NOT_EDIT))
        {
            // if user doesn't exists or should be duplicated then count as new user
            if($maxUserId === 0 || $_SESSION['user_import_mode'] == USER_IMPORT_DUPLICATE)
            {
                ++$countImportNewUser;
            }
            // existing users count as edited if mode is displace or complete
            elseif($maxUserId > 0 && $user->hasColumnsValueChanged())
            {
                ++$countImportEditUser;
            }

            // save user data
            $user->save();

            // assign role membership to user
            if($user->setRoleMembership($_SESSION['rol_id']))
            {
                ++$countImportEditRole;
            }

            // assign dependent role memberships to user
            foreach($depRoles as $depRole)
            {
                $user->setRoleMembership($depRole);
            }

        }
    }

    $line = next($_SESSION['file_lines']);
}

// initialize session parameters
$_SESSION['role']             = '';
$_SESSION['user_import_mode'] = '';
$_SESSION['file_lines']       = '';
$_SESSION['value_separator']  = '';

$gMessage->setForwardUrl(ADMIDIO_URL.FOLDER_MODULES.'/members/members.php');
$gMessage->show($gL10n->get('MEM_IMPORT_SUCCESSFUL', array($countImportNewUser, $countImportEditUser, $countImportEditRole)));
// => EXIT
