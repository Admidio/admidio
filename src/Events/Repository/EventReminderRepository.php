<?php
namespace Admidio\Events\Repository;

use Admidio\Infrastructure\Database;

/**
 * Read and write the persisted event reminder state.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventReminderRepository
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Return all future events that have reminders enabled and are not cancelled.
     * @return array<int,int>
     * @throws \Admidio\Infrastructure\Exception
     */
    public function getDueEventIds(): array
    {
        $sql = 'SELECT dat_id
                  FROM ' . TBL_EVENTS . '
                 WHERE dat_begin >= ?
                   AND (dat_recurrence_status IS NULL OR dat_recurrence_status <> ?)
                   AND (dat_reminder_days IS NULL OR dat_reminder_days > 0)
              ORDER BY dat_begin';

        return array_map('intval', $this->database->queryPrepared($sql, array(DATE_NOW, 'cancelled'))->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Return all members of the authorized roles that have a valid email address and have not
     * received a reminder for the given target date yet.
     * @param int $eventId Event ID.
     * @param array<int,int> $authorizedRoleIds Roles that are allowed to participate.
     * @param string $targetDate Reminder date in format Y-m-d.
     * @return array<int,array<string,mixed>>
     * @throws \Admidio\Infrastructure\Exception
     */
    public function getRecipients(int $eventId, array $authorizedRoleIds, string $targetDate): array
    {
        global $gSettingsManager, $gProfileFields;

        if (count($authorizedRoleIds) === 0) {
            return array();
        }

        $sqlEmailField = '';
        if (!$gSettingsManager->getBool('mail_send_to_all_addresses')) {
            $sqlEmailField = ' AND field.usf_name_intern = \'EMAIL\' ';
        }

        $placeholders = implode(',', array_fill(0, count($authorizedRoleIds), '?'));
        $sql = 'SELECT DISTINCT usr_id, first_name.usd_value AS firstname, last_name.usd_value AS lastname, email.usd_value AS email
                  FROM ' . TBL_MEMBERS . ' mem
            INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
            INNER JOIN ' . TBL_USERS . ' ON usr_id = mem_usr_id
            INNER JOIN ' . TBL_USER_DATA . ' AS email ON email.usd_usr_id = usr_id AND LENGTH(email.usd_value) > 0
            INNER JOIN ' . TBL_USER_FIELDS . ' AS field ON field.usf_id = email.usd_usf_id AND field.usf_type = \'EMAIL\' ' . $sqlEmailField . '
             LEFT JOIN ' . TBL_USER_DATA . ' AS first_name ON first_name.usd_usr_id = usr_id AND first_name.usd_usf_id = ?
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_name ON last_name.usd_usr_id = usr_id AND last_name.usd_usf_id = ?
             LEFT JOIN ' . TBL_EVENT_REMINDER_SENT . ' AS reminder
                    ON reminder.ers_dat_id = ?
                   AND reminder.ers_usr_id = usr_id
                   AND DATE(reminder.ers_timestamp_sent) = ?
                 WHERE mem_rol_id IN (' . $placeholders . ')
                   AND mem_begin <= ?
                   AND mem_end > ?
                   AND reminder.ers_id IS NULL';

        $queryParams = array(
            (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $eventId,
            $targetDate
        );
        foreach ($authorizedRoleIds as $roleId) {
            $queryParams[] = (int) $roleId;
        }
        $queryParams[] = DATE_NOW;
        $queryParams[] = DATE_NOW;

        return $this->database->queryPrepared($sql, $queryParams)->fetchAll();
    }

    /**
     * Store that a reminder was sent successfully.
     * @throws \Admidio\Infrastructure\Exception
     */
    public function markSent(int $eventId, int $userId): void
    {
        $sql = 'SELECT ers_id
                  FROM ' . TBL_EVENT_REMINDER_SENT . '
                 WHERE ers_dat_id = ?
                   AND ers_usr_id = ?';
        $reminderId = $this->database->queryPrepared($sql, array($eventId, $userId))->fetchColumn();

        if ($reminderId !== false) {
            $sql = 'UPDATE ' . TBL_EVENT_REMINDER_SENT . '
                       SET ers_timestamp_sent = ?
                     WHERE ers_id = ?';
            $this->database->queryPrepared($sql, array(DATETIME_NOW, (int) $reminderId));
            return;
        }

        $sql = 'INSERT INTO ' . TBL_EVENT_REMINDER_SENT . '
                       (ers_dat_id, ers_usr_id, ers_timestamp_sent)
                VALUES (?, ?, ?)';
        $this->database->queryPrepared($sql, array($eventId, $userId, DATETIME_NOW));
    }
}
