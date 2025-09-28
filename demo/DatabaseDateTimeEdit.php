<?php

use Admidio\Infrastructure\Database;

/**
 * @brief Edit date and datetime fields in the database
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class DatabaseDateTimeEdit
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;

    /**
     * Constructor creates the form element
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @throws Exception
     */
    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    public function addDaysToDate(string $date, int $days, bool $sub = false): string
    {
        $dateTimeObject = DateTime::createFromFormat('Y-m-d', $date);
        $daysOffset = new \DateInterval('P'.$days.'D');
        $newDate = $sub ? $dateTimeObject->sub($daysOffset) : $dateTimeObject->add($daysOffset);

        return $newDate->format('Y-m-d');
    }

    public function addDaysToDateTime(string $dateTime, int $days, bool $sub = false): string
    {
        $dateTimeObject = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime);
        $daysOffset = new \DateInterval('P'.$days.'D');
        $newDate = $sub ? $dateTimeObject->sub($daysOffset) : $dateTimeObject->add($daysOffset);

        return $newDate->format('Y-m-d H:i:s');
    }

    /**
     * @throws \Admidio\Infrastructure\Exception
     * @throws \Random\RandomException
     */
    public function updateDateTimeField(string $tableName, string $columnName, int $minDaysLimit, int $maxDaysLimit, bool $pastPeriod = false): void
    {
        $sql = 'SELECT ' . $columnName . '
                  FROM ' . $tableName . '
                 WHERE ' . $columnName . ' IS NOT NULL ';

        $statement = $this->db->queryPrepared($sql);
        while ($row = $statement->fetch()) {
            $days = random_int($minDaysLimit, $maxDaysLimit);
            $newDateTime = $this->addDaysToDateTime($row[$columnName], $days, $pastPeriod);

            $sqlUpdate = 'UPDATE ' . $tableName . '
                             SET ' . $columnName . ' = ?
                           WHERE ' . $columnName . ' = ? ';

            $this->db->queryPrepared($sqlUpdate, array($newDateTime, $row[$columnName]));
        }
    }

    /**
     * @throws \Admidio\Infrastructure\Exception
     * @throws \Random\RandomException
     */
    public function updateTwoRelativeDateTimeField(string $tableName, string $columnName, string $columnNameRelative, int $minDaysLimit, int $maxDaysLimit, bool $pastPeriod = false): void
    {
        $sql = 'SELECT ' . $columnName . ', ' . $columnNameRelative . '
                  FROM ' . $tableName . '
                 WHERE ' . $columnName . ' IS NOT NULL ';

        $statement = $this->db->queryPrepared($sql);
        while ($row = $statement->fetch()) {
            $firstDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $row[$columnName]);
            $relativeDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $row[$columnNameRelative]);
            $diff = $firstDateTime->diff($relativeDateTime);

            $days = random_int($minDaysLimit, $maxDaysLimit);
            $daysRelative = $days + $diff->days;

            $newDateTime = $this->addDaysToDateTime($row[$columnName], $days, $pastPeriod);
            $newDateTimeRelative = $this->addDaysToDateTime($row[$columnNameRelative], $daysRelative, $pastPeriod);

            $sqlUpdate = 'UPDATE ' . $tableName . '
                             SET ' . $columnName . ' = ?
                               , ' . $columnNameRelative . ' = ?
                           WHERE ' . $columnName . ' = ? ';

            $this->db->queryPrepared($sqlUpdate, array($newDateTime, $newDateTimeRelative, $row[$columnName]));
        }
    }

    public function updateDateTimeFields(): void
    {
        $this->updateDateTimeField(TBL_ANNOUNCEMENTS, 'ann_timestamp_create', 0, 15, true);
        $this->updateTwoRelativeDateTimeField(TBL_EVENTS, 'dat_begin', 'dat_end', 0, 60);
    }
}
