<?php
namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\PasswordUtils;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Import new users or modify existing users in the database
 *
 * This class extends the User class with some special functions for importing new users or modify
 * existing users. If values are set to the object, they will be checked if the values are valid.
 * The class will be more tolerant and transform some values of the import. Also, a special mode
 * could be set what should be done if a user already exists in the database.
 *
 * **Code example**
 * ```
 * // create a valid registration
 * $userImport = new UserImport($gDb, $gProfileFields);
 * $userImport->setImportMode(UserImport::USER_IMPORT_COMPLETE);
 * $userImport->readDataByFirstnameLastName('Franka', 'Schmidt');
 * $userImport->setValue('CITY', 'Berlin');
 * ...
 * // save user data and create new user
 * $userImport->save();
 * ```
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class UserImport extends User
{
    // create readable constants for user import mode
    public const USER_IMPORT_NOT_EDIT  = 1;
    public const USER_IMPORT_DUPLICATE = 2;
    public const USER_IMPORT_DISPLACE  = 3;
    public const USER_IMPORT_COMPLETE  = 4;

    /**
     * @var int Mode how the user will be imported. Details are described at the method setImportMode()
     */
    private int $importMode;
    /**
     * @var bool Flag if the user already exists (identified by firstname and lastname)
     */
    private bool $userExists = false;

    /**
     * Constructor that will create an object of a recordset of the users table.
     * If the id is set than this recordset will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param ProfileFields $userFields An object of the ProfileFields class with the profile field structure
     *                                      of the current organization. This could be the default object .
     * @param int $userId The id of the user who should be loaded. If id isn't set than an empty object
     *                                      with no specific user is created.
     * @throws Exception
     */
    public function __construct(Database $database, ProfileFields $userFields, int $userId = 0)
    {
        $this->importMode = self::USER_IMPORT_NOT_EDIT;

        parent::__construct($database, $userFields, $userId);
    }

    /**
     * Additional to the parent method some import parameters will be initialized
     * @return void
     * @throws Exception
     */
    public function clear(): void
    {
        parent::clear();

        $this->userExists = false;
    }

    /**
     * Reads a record out of the table in database selected by the two profile fields FIRST_NAME and LAST_NAME.
     * Per default all columns of the default table will be read and stored in the object.
     * @param string $firstName The first name of the user that should be imported.
     * @param string $lastName The last name of the user that should be imported.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     */
    public function readDataByFirstnameLastName(string $firstName, string $lastName): bool
    {
        // initialize the object, so that all fields are empty
        $this->clear();

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
                 WHERE usr_valid = true';
        $queryParams = array(
            $this->mProfileFieldsData->getProperty('LAST_NAME', 'usf_id'),
            $lastName,
            $this->mProfileFieldsData->getProperty('FIRST_NAME', 'usf_id'),
            $firstName
        );
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);
        $maxUserId = (int) $pdoStatement->fetchColumn();

        if ($maxUserId > 0) {
            $this->readDataById($maxUserId);
            $this->userExists = true;

            if ($this->importMode === self::USER_IMPORT_DISPLACE) {
                // delete all user data of profile fields
                $this->deleteUserFieldData();
            } elseif ($this->importMode === self::USER_IMPORT_DUPLICATE) {
                // save as new user
                $this->clear();
            }
        }

        return true;
    }

    /**
     * Set the mode how the user will be imported. What should be done if the user already exists and some
     * profile fields still have values?
     * @param int $mode The following modes could be set:
     *                  USER_IMPORT_NOT_EDIT  Existing users will not be edited.
     *                  USER_IMPORT_DUPLICATE If the user exists a new user will be created.
     *                  USER_IMPORT_DISPLACE  All profile field values of the import file will be added to the user.
     *                  USER_IMPORT_COMPLETE  Only profile fields that don't have a value will be added to the user.
     * @throws Exception
     */
    public function setImportMode(int $mode)
    {
        if ($mode > 0 && $mode < 5) {
            $this->importMode = $mode;

            if ($this->userExists) {
                if ($this->importMode === self::USER_IMPORT_DISPLACE) {
                    // delete all user data of profile fields
                    $this->deleteUserFieldData();
                } elseif ($this->importMode === self::USER_IMPORT_DUPLICATE) {
                    // save as new user
                    $this->clear();
                }
            }
        }
    }

    /**
     * Method will set username and password for the import user.
     * Therefore, the current user must be an administrator and if the import user already exists he should
     * not have username and password. The password must have the min length and should also have the
     * necessary password strength. If the login data meet all these criteria than the login data will
     * be added to the import user.
     * @param string $loginName The login name for the import user that should later be used to log in to this system.
     * @param string $password The password for the import user that should later be used to log in to this system.
     * @throws Exception An exception is thrown if the password doesn't meet the minimum requirements.
     * @throws Exception
     */
    public function setLoginData(string $loginName, string $password)
    {
        global $gCurrentUser, $gSettingsManager;

        if (!$gCurrentUser->isAdministrator()) {
            throw new Exception('Current user is not an administrator. Only administrators could import usernames and passwords.');
        } elseif (strlen($this->getValue('usr_login_name')) > 0) {
            throw new Exception('Contact ' . $this->getValue('FIRST_NAME'). ' '.$this->getValue('LAST_NAME') . ' already has a username and password.');
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception($this->getValue('FIRST_NAME') . ' ' .$this->getValue('LAST_NAME') . ' password doesn\'t meet the required minimum length of '.PASSWORD_MIN_LENGTH.' characters.');
        } elseif (PasswordUtils::passwordStrength($password, $this->getPasswordUserData()) < $gSettingsManager->getInt('password_min_strength')) {
            throw new Exception($this->getValue('FIRST_NAME') . ' ' .$this->getValue('LAST_NAME') . ' password doesn\'t meet the required minimum passwort strength.');
        } else {
            $this->setValue('usr_login_name', $loginName);
            $this->setPassword($password);
        }
    }

    /**
     * Set a new value for a column of the database table if the column has the prefix **usr_**
     * otherwise the value of the profile field of the table adm_user_data will set.
     * If the user log is activated than the change of the value will be logged in **adm_user_log**.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value or the
     *                           internal unique profile field name
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will
     *                           not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     *
     * **Code example**
     * ```
     * // set data of adm_users column
     * $gCurrentUser->getValue('usr_login_name', 'Admidio');
     * // reads data of adm_user_fields
     * $gCurrentUser->getValue('EMAIL', 'administrator@admidio.org');
     * ```
     * @throws Exception
     */
    public function setValue(string $columnName, mixed $newValue, bool $checkValue = true): bool
    {
        global $gL10n, $gLogger;

        // if user already exists and existing data should not be edited than do nothing
        if ($this->userExists && $this->importMode === self::USER_IMPORT_NOT_EDIT) {
            return false;
        }

        // users data from adm_users table
        if (str_starts_with($columnName, 'usr_')) {
            return parent::setValue($columnName, $newValue, $checkValue);
        } else {
            // convert the value of the import file to an Admidio expected value
            $validValue = '';

            if ($columnName === 'COUNTRY') {
                try {
                    $validValue = $gL10n->getCountryIsoCode($newValue);
                } catch (Exception $e) {
                    $gLogger->info($e->getMessage());
                }
            } else {
                switch ($this->mProfileFieldsData->getProperty($columnName, 'usf_type')) {
                    case 'CHECKBOX':
                        $columnValueToLower = StringUtils::strToLower($newValue);
                        if (in_array($columnValueToLower, array('y', 'yes', '1', 'j', StringUtils::strToLower($gL10n->get('SYS_YES'))), true)) {
                            $validValue = '1';
                        }
                        if (in_array($columnValueToLower, array('n', 'no', '0', '', StringUtils::strToLower($gL10n->get('SYS_NO'))), true)) {
                            $validValue = '0';
                        }
                        break;
                    case 'DROPDOWN': // fallthrough
                    case 'RADIO_BUTTON':
                        // save position of combobox
                        $arrOptions = $this->mProfileFieldsData->getProperty($columnName, 'ufo_usf_options', 'text');

                        for ($position = 1; $position <= count($arrOptions); $position++) {
                            if (StringUtils::strToLower($newValue) === StringUtils::strToLower(trim($arrOptions[$position]))) {
                                // if col_value is text than save position if text is equal to text of position
                                $validValue = $position;
                            } elseif (is_numeric($newValue) && !is_numeric($arrOptions[$position]) && $newValue > 0 && $newValue < 1000) {
                                // if col_value is numeric than save position if col_value is equal to position
                                $validValue = $newValue;
                            }
                        }
                        break;
                    case 'DROPDOWN_MULTISELECT':
                        // save position of combobox
                        $arrOptions = $this->mProfileFieldsData->getProperty($columnName, 'ufo_usf_options', 'text');
                        $validValue = '';

                        // split the value by comma and check each value
                        $values = explode(',', $newValue);
                        foreach ($values as $value) {
                            $value = trim($value);
                            for ($position = 1; $position <= count($arrOptions); $position++) {
                                if (StringUtils::strToLower($value) === StringUtils::strToLower(trim($arrOptions[$position]))) {
                                    // if col_value is text than save position if text is equal to text of position
                                    if ($validValue !== '') {
                                        $validValue .= ',';
                                    }
                                    $validValue .= $position;
                                } elseif (is_numeric($value) && !is_numeric($arrOptions[$position]) && $value > 0 && $value < 1000) {
                                    // if col_value is numeric than save position if col_value is equal to position
                                    if ($validValue !== '') {
                                        $validValue .= ',';
                                    }
                                    $validValue .= $value;
                                }
                            }
                        }
                        break;
                    case 'EMAIL':
                        if (StringUtils::strValidCharacters($newValue, 'email')) {
                            $validValue = substr($newValue, 0, 255);
                        }
                        break;
                    case 'INTEGER':
                        // number could contain dot and comma
                        if (is_numeric(strtr($newValue, ',.', '00'))) {
                            $validValue = $newValue;
                        }
                        break;
                    case 'TEXT':
                        $validValue = substr($newValue, 0, 100);
                        break;
                    default:
                        $validValue = substr($newValue, 0, 4000);
                }
            }

            // if user should be completed than also empty values must be set
            if ($validValue !== '' || $this->importMode === self::USER_IMPORT_COMPLETE) {
                return parent::setValue($columnName, $validValue, $checkValue);
            }
        }

        return false;
    }
}
