<?php
namespace Admidio\Events\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Utils\StringUtils;

/**
 * @brief Class manages access to database table adm_rooms
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Room extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_rooms.
     * If the id is set than the specific room will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $roomId The recordset of the room with this id will be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $roomId = 0)
    {
        parent::__construct($database, TBL_ROOMS, 'room', $roomId);
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *                    If the value was manipulated before with **setValue** than the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = ''): mixed
    {
        if ($columnName === 'room_description') {
            if (!isset($this->dbColumns['room_description'])) {
                $value = '';
            } elseif ($format === 'database') {
                $value = html_entity_decode(StringUtils::strStripTags($this->dbColumns['room_description']));
            } else {
                $value = $this->dbColumns['room_description'];
            }

            return $value;
        }

        return parent::getValue($columnName, $format);
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws Exception
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($columnName === 'room_description') {
            return parent::setValue($columnName, $newValue, false);
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
