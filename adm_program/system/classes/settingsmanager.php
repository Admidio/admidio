<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

class SettingsManager
{
    /**
     * @var Database
     */
    private $db;
    /**
     * @var int
     */
    private $orgId;
    /**
     * @var array<string,string>
     */
    private $settings = array();

    /**
     * SettingsManager constructor.
     * @param Database $database
     * @param int      $orgId
     */
    public function __construct(Database $database, $orgId)
    {
        $this->db = $database;
        $this->orgId = $orgId;
    }

    /**
     * @param string $name
     * @return bool
     */
    private static function isValidName($name)
    {
        return (bool) preg_match('/^[a-z][a-z_]*[a-z]$/', $name);
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private static function isValidValue($value)
    {
        return is_string($value) || is_numeric($value) || is_bool($value);
    }

    /**
     * @return array<string,string>
     */
    private function loadAll()
    {
        $sql = 'SELECT prf_name, prf_value
                  FROM '.TBL_PREFERENCES.'
                 WHERE prf_org_id = ? -- $this->orgId';
        $pdoStatement = $this->db->queryPrepared($sql, array($this->orgId));

        $settings = array();

        while ($row = $pdoStatement->fetch())
        {
            $settings[$row['prf_name']] = $row['prf_value'];
        }

        return $settings;
    }

    /**
     * @param string $name
     * @throws \UnexpectedValueException
     * @return mixed
     */
    private function load($name)
    {
        $sql = 'SELECT prf_value
                  FROM '.TBL_PREFERENCES.'
                 WHERE prf_org_id = ? -- $this->orgId
                   AND prf_name   = ? -- $name';
        $pdoStatement = $this->db->queryPrepared($sql, array($this->orgId, $name));

        if ($pdoStatement->rowCount() === 0)
        {
            throw new \UnexpectedValueException('Settings name "' . $name . '" does not exist!');
        }

        return $pdoStatement->fetchColumn();
    }

    /**
     * @param string $name
     * @param string $value
     */
    private function insert($name, $value)
    {
        $sql = 'INSERT INTO '.TBL_PREFERENCES.'
                            (prf_org_id, prf_name, prf_value)
                     VALUES (?, ?, ?) -- $orgId, $name, $value';
        $this->db->queryPrepared($sql, array($this->orgId, $name, $value));
    }

    /**
     * @param string $name
     * @param string $value
     */
    private function update($name, $value)
    {
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value  = ? -- $value
                 WHERE prf_org_id = ? -- $orgId
                   AND prf_name   = ? -- $name';
        $this->db->queryPrepared($sql, array($value, $this->orgId, $name));
    }

    /**
     *
     */
    public function clearAll()
    {
        $this->settings = array();
    }

    /**
     *
     */
    public function resetAll()
    {
        $this->settings = $this->loadAll();
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @return bool
     */
    public function has($name, $update = false)
    {
        if (!self::isValidName($name))
        {
            throw new \UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }

        if ($update || !array_key_exists($name, $this->settings))
        {
            try
            {
                $this->settings[$name] = $this->load($name);

                return true;
            }
            catch (\UnexpectedValueException $e)
            {
                return false;
            }
        }

        return array_key_exists($name, $this->settings);
    }

    /**
     * @param bool $update
     * @return array<string,string>
     */
    public function getAll($update = false)
    {
        if ($update || count($this->settings) === 0)
        {
            $this->resetAll();
        }

        return $this->settings;
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @return string
     */
    public function get($name, $update = false)
    {
        if (!self::isValidName($name))
        {
            throw new \UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }

        if ($this->has($name, $update))
        {
            return $this->settings[$name];
        }

        throw new \UnexpectedValueException('Settings name "' . $name . '" does not exist!');
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function getBool($name, $update = false)
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null)
        {
            throw new \InvalidArgumentException('Settings value of name "' . $name . '" is not of type bool!');
        }

        return $value;
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @return int
     */
    public function getInt($name, $update = false)
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_INT);

        if ($value === false)
        {
            throw new \InvalidArgumentException('Settings value of name "' . $name . '" is not of type int!');
        }

        return $value;
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @return float
     */
    public function getFloat($name, $update = false)
    {
        $value = $this->get($name, $update);
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        if ($value === false)
        {
            throw new \InvalidArgumentException('Settings value of name "' . $name . '" is not of type float!');
        }

        return $value;
    }

    /**
     * @param string $name
     * @param bool   $update
     * @throws \UnexpectedValueException
     * @return string
     */
    public function getString($name, $update = false)
    {
        return $this->get($name, $update);
    }

    /**
     * @param string $name
     * @param string $value
     * @param bool   $update
     */
    private function updateOrInsertSetting($name, $value, $update = true)
    {
        if ($this->has($name, true))
        {
            if ($update && $this->settings[$name] !== $value)
            {
                $this->update($name, $value);
            }
        }
        else
        {
            $this->insert($name, $value);
        }
    }

    /**
     * @param array<string,mixed> $settings
     * @param bool                $update
     * @throws \UnexpectedValueException
     */
    public function setMulti(array $settings, $update = true)
    {
        foreach ($settings as $name => $value)
        {
            if (!self::isValidName($name))
            {
                throw new \UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
            }
            if (!self::isValidValue($value))
            {
                throw new \UnexpectedValueException('Settings value "' . $value . '" is an invalid value!');
            }
        }

        $this->db->startTransaction();

        foreach ($settings as $name => $value)
        {
            $this->updateOrInsertSetting($name, (string) $value, $update);
        }

        $this->db->endTransaction();

        $this->settings = $this->loadAll();
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @param bool   $update
     * @throws \UnexpectedValueException
     */
    public function set($name, $value, $update = true)
    {
        if (!self::isValidName($name))
        {
            throw new \UnexpectedValueException('Settings name "' . $name . '" is an invalid string!');
        }
        if (!self::isValidValue($value))
        {
            throw new \UnexpectedValueException('Settings value "' . $value . '" is an invalid value!');
        }

        $this->updateOrInsertSetting($name, (string) $value, $update);

        $this->settings[$name] = $this->load($name);
    }
}
