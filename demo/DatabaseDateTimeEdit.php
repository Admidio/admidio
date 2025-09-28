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

    public function updateBirthdays(): void
    {
        $dayInYear = 0;

        $sql = 'SELECT usf_id
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_name_intern = \'BIRTHDAY\' ';
        $birthdayFieldID = $this->db->queryPrepared($sql)->fetchColumn();

        $sql = 'SELECT usd_id, usd_value
                  FROM ' . TBL_USER_DATA . '
                 WHERE usd_usf_id = ?
                   AND usd_value IS NOT NULL ';

        $statement = $this->db->queryPrepared($sql, array($birthdayFieldID));
        while ($row = $statement->fetch()) {
            $dayInYear += 2;
            if ($dayInYear > 365) {
                $dayInYear = 1;
            }
            $year = random_int(1960, 2015);
            $newBirthday =  $this->addDaysToDate($year . '-01-01', $dayInYear);

            $sqlUpdate = 'UPDATE ' . TBL_USER_DATA . '
                             SET usd_value = ?
                           WHERE usd_id = ? ';

            $this->db->queryPrepared($sqlUpdate, array($newBirthday, $row['usd_id']));
        }
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
}
