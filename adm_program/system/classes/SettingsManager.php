<?php
/**
 ***********************************************************************************************
 * Class the manage the settings of an organization
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class SettingsManager
{
    /**
     * @var Database The Database object
     */
    private $db;
    /**
     * @var int The organization id
     */
    private $orgId;
    /**
     * @var array<string,string> An array with the settings with name and value
     */
    private $settings = array();
    /**
     * @var bool Indicator if settings was already full loaded
     */
    private $initFullLoad = false;
    /**
     * @var bool Indicator if exceptions should be thrown when reading settings
     */
    private $throwExceptions = true;

    /**
     * SettingsManager constructor.
     * @param Database $database
     * @param int $orgId
     */
    public function __construct(Database $database, int $orgId)
    {
        $this->db = $database;
        $this->orgId = $orgId;
    }

    /**
     * Only safe db and orgId on serialization
     * @return array<int,string> Returns the properties that should be serialized
     */
    public function __sleep()
    {
        return array('orgId', 'settings');
    }

    /**
     * A wakeup add the current database object to this class
     */
    public function __wakeup()
    {
        global $gDb;

        if ($gDb instanceof Database) {
            $this->db = $gDb;
        }
    }

    /**
     * Clears the loaded settings
     */
    public function clearAll()
    {
        $this->settings = array();
    }

    /**
     * Deletes a selected setting out of the database
     * @param string $name The chosen setting name
     * @throws Exception
     */
    private function delete(string $name)
    {
        $sql = 'DELETE FROM '.TBL_PREFERENCES.'
                 WHERE prf_org_id = ? -- $orgId
                   AND prf_name   = ? -- $name';
        $this->db->queryPrepared($sql, array($this->orgId, $name));
    }

    /**
     * Deletes a chosen setting out of the database
     * @param string $name The chosen setting name
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function del(string $name)
    {
        if (!self::isValidName($name) && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }
        if (!$this->has($name) && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" does not exist!');
        }

        $this->delete($name);
    }

    /**
     * If this method is called than this object will not throw any exception when reading settings. This is
     * useful before an Admidio update started because not all settings are available before update.
     * When writing a setting to the database exceptions will still be thrown.
     */
    public function disableExceptions()
    {
        $this->throwExceptions = false;
    }

    /**
     * Get all settings from the database
     * @param bool $update Set true to make a force reload of all settings from the database
     * @return array<string,string> Returns all settings
     * @throws Exception
     */
    public function getAll(bool $update = false): array
    {
        if ($update || !$this->initFullLoad) {
            $this->resetAll();
        }

        return $this->settings;
    }

    /**
     * Get a chosen setting from the database
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return string Returns the chosen setting value
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function get(string $name, bool $update = false): string
    {
        if (!self::isValidName($name) && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }
        if (!$this->has($name, $update) && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" does not exist!');
        }

        return $this->settings[$name];
    }

    /**
     * Get a chosen boolean setting from the database
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return bool Returns the chosen boolean setting value
     * @throws InvalidArgumentException Throws if the chosen setting value is not of type bool
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function getBool(string $name, bool $update = false): bool
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null && $this->throwExceptions) {
            throw new InvalidArgumentException('Settings value of name "' . $name . '" is not of type bool!');
        }

        return $value;
    }

    /**
     * Get a chosen integer setting from the database
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return int Returns the chosen integer setting value
     * @throws InvalidArgumentException Throws if the chosen setting value is not of type int
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function getInt(string $name, bool $update = false): int
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false && $this->throwExceptions) {
            throw new InvalidArgumentException('Settings value of name "' . $name . '" is not of type int!');
        }

        return $value;
    }

    /**
     * Get a chosen float setting from the database
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return float Returns the chosen float setting value
     * @throws InvalidArgumentException Throws if the chosen setting value is not of type float
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function getFloat(string $name, bool $update = false): float
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($value === false && $this->throwExceptions) {
            throw new InvalidArgumentException('Settings value of name "' . $name . '" is not of type float!');
        }

        return $value;
    }

    /**
     * Get a chosen string setting from the database
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return string Returns the chosen string setting value
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function getString(string $name, bool $update = false): string
    {
        return $this->get($name, $update);
    }

    /**
     * Checks if a setting exists
     * @param string $name   The chosen setting name
     * @param bool $update Set true to make a force reload of this setting from the database
     * @return bool Returns true if the setting exists
     * @throws UnexpectedValueException|Exception Throws if the settings name is invalid
     */
    public function has(string $name, bool $update = false): bool
    {
        if (!self::isValidName($name) && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }

        if ($update || !array_key_exists($name, $this->settings)) {
            try {
                $this->settings[$name] = $this->load($name);

                return true;
            } catch (UnexpectedValueException $e) {
                return false;
            }
        }

        return array_key_exists($name, $this->settings);
    }

    /**
     * Checks if the settings name is valid
     * @param string $name The settings name
     * @return bool Returns true if the settings name is valid
     */
    private static function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9](_?[a-z0-9])*$/', $name);
    }

    /**
     * Checks if the settings value is a valid type (bool, string, int, float)
     * @param mixed $value The settings value
     * @return bool Returns true if the settings value is true
     */
    private static function isValidValue($value): bool
    {
        return is_scalar($value);
    }

    /**
     * Loads all settings from the database
     * @return array<string,string> An array with all settings from the database
     * @throws Exception
     */
    private function loadAll(): array
    {
        $sql = 'SELECT prf_name, prf_value
                  FROM '.TBL_PREFERENCES.'
                 WHERE prf_org_id = ? -- $this->orgId';
        $pdoStatement = $this->db->queryPrepared($sql, array($this->orgId));

        $settings = array();

        while ($row = $pdoStatement->fetch()) {
            $settings[$row['prf_name']] = $row['prf_value'];
        }

        return $settings;
    }

    /**
     * Loads a specific setting from the database
     * @param string $name The setting name from the wanted value
     * @return string Returns the setting value
     * @throws UnexpectedValueException|Exception Throws if there is no setting to the given name found
     */
    private function load(string $name): string
    {
        $sql = 'SELECT prf_value
                  FROM '.TBL_PREFERENCES.'
                 WHERE prf_org_id = ? -- $this->orgId
                   AND prf_name   = ? -- $name';
        $pdoStatement = $this->db->queryPrepared($sql, array($this->orgId, $name));

        if ($pdoStatement->rowCount() === 0 && $this->throwExceptions) {
            throw new UnexpectedValueException('Settings name "' . $name . '" does not exist!');
        }

        return $pdoStatement->fetchColumn();
    }

    /**
     * Inserts a new setting into the database
     * @param string $name The chosen setting name
     * @param string $value The chosen setting value
     * @throws Exception
     */
    private function insert(string $name, string $value)
    {
        $sql = 'INSERT INTO '.TBL_PREFERENCES.'
                       (prf_org_id, prf_name, prf_value)
                VALUES (?, ?, ?) -- $orgId, $name, $value';
        $this->db->queryPrepared($sql, array($this->orgId, $name, $value));
    }

    /**
     * Clears and reload all settings
     * @throws Exception
     */
    public function resetAll()
    {
        $this->settings = $this->loadAll();
        $this->initFullLoad = true;
    }

    /**
     * Expects an array with setting name and value and will than add all the settings of the array to
     * the database. Checks the existence of each setting and perform an insert or update.
     * @param array<string,mixed> $settings Array with all setting names and values to set
     * @param bool $update   Set true to make a force reload of this setting from the database
     * @throws UnexpectedValueException|Exception Throws if one or more of the setting names are invalid or do not exist
     */
    public function setMulti(array $settings, bool $update = true)
    {
        foreach ($settings as $name => $value) {
            if (!self::isValidName($name)) {
                throw new UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
            }
            if (!self::isValidValue($value) && $this->throwExceptions) {
                throw new UnexpectedValueException('Settings value "' . $value . '" is an invalid value!');
            }
        }

        $this->db->startTransaction();

        foreach ($settings as $name => $value) {
            $this->updateOrInsertSetting($name, (string) $value, $update);
        }

        $this->db->endTransaction();

        $this->resetAll();
    }

    /**
     * Sets a chosen setting in the database
     * @param string $name   The chosen setting name
     * @param mixed  $value  The chosen setting value
     * @param bool $update Set true to make a force reload of this setting from the database
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid or does not exist
     */
    public function set(string $name, $value, bool $update = true)
    {
        if (!self::isValidName($name)) {
            throw new UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }
        if (!self::isValidValue($value)) {
            throw new UnexpectedValueException('Settings value "' . $value . '" is an invalid value!');
        }

        $this->updateOrInsertSetting($name, (string) $value, $update);

        $this->settings[$name] = $this->load($name);
    }

    /**
     * Updates an already available setting in the database
     * @param string $name The chosen setting name
     * @param string $value The chosen new setting value
     * @throws Exception
     */
    private function update(string $name, string $value)
    {
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value  = ? -- $value
                 WHERE prf_org_id = ? -- $orgId
                   AND prf_name   = ? -- $name';
        $this->db->queryPrepared($sql, array($value, $this->orgId, $name));
    }

    /**
     * Checks if the setting already exists and then inserts or updates this setting
     * @param string $name  The chosen setting name
     * @param string $value The chosen setting value
     * @param bool $update Set true to make a force reload of this setting from the database
     * @throws UnexpectedValueException|Exception Throws if the setting name is invalid
     */
    private function updateOrInsertSetting(string $name, string $value, bool $update = true)
    {
        if ($this->has($name, true)) {
            if ($update && $this->settings[$name] !== $value) {
                $this->update($name, $value);
            }
        } else {
            $this->insert($name, $value);
        }
    }
}
