<?php
namespace Admidio\Events\Repository;

use Admidio\Events\ValueObject\Participants;
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
     * Return the future events of the current organization whose reminder window is open and that
     * are not cancelled.
     *
     * @return array<int,int>
     * @throws \Admidio\Infrastructure\Exception
     */
    public function getDueEventIds(): array
    {
        global $gCurrentOrgId;

        $interval = DB_TYPE === Database::PDO_ENGINE_PGSQL
            ? 'CAST(? AS timestamp) + (dat_reminder_days || \' days\')::interval'
            : 'DATE_ADD(?, INTERVAL dat_reminder_days DAY)';

        $sql = 'SELECT dat_id
                  FROM ' . TBL_EVENTS . ' ev
            INNER JOIN ' . TBL_CATEGORIES . ' cat ON cat.cat_id = ev.dat_cat_id
                 WHERE cat.cat_org_id = ?
                   AND ev.dat_begin > ?
                   AND ev.dat_begin <= ' . $interval . '
                   AND ev.dat_reminder_days > 0
                   AND (ev.dat_recurrence_status IS NULL OR ev.dat_recurrence_status <> ?)
              ORDER BY ev.dat_begin';

        return array_map(
            'intval',
            $this->database->queryPrepared(
                $sql,
                array((int) $gCurrentOrgId, DATETIME_NOW, DATETIME_NOW, 'cancelled')
            )->fetchAll(\PDO::FETCH_COLUMN)
        );
    }

    /**
     * Return the users that should receive a reminder for the event.
     *
     * The recipients are the members of the authorized participation roles plus the members of the
     * event role with state INVITED or TENTATIVE. Users with state ATTENDING or REFUSED are excluded.
     * Only valid users and valid roles are returned. The caller resolves the email addresses through
     * Email::addRecipientsByUser(), so mail_send_to_all_addresses and address validation apply.
     *
     * @param int $eventRoleId Role of the event participants.
     * @param array<int,int> $authorizedRoleIds Roles that are allowed to participate.
     * @return array<int,array{usr_id:int,usr_uuid:string}>
     * @throws \Admidio\Infrastructure\Exception
     */
    public function getRecipientUsers(int $eventRoleId, array $authorizedRoleIds): array
    {
        $authorizedCondition = '1 = 0';
        $queryParams = array();

        if (count($authorizedRoleIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($authorizedRoleIds), '?'));
            $authorizedCondition = 'mem.mem_rol_id IN (' . $placeholders . ')';
            foreach ($authorizedRoleIds as $roleId) {
                $queryParams[] = (int) $roleId;
            }
        }

        $sql = 'SELECT DISTINCT usr_id, usr_uuid
                  FROM ' . TBL_USERS . ' usr
                 WHERE usr.usr_valid = true
                   AND (
                       EXISTS (
                           SELECT 1
                             FROM ' . TBL_MEMBERS . ' mem
                       INNER JOIN ' . TBL_ROLES . ' rol ON rol.rol_id = mem.mem_rol_id
                            WHERE mem.mem_usr_id = usr.usr_id
                              AND rol.rol_valid = true
                              AND ' . $authorizedCondition . '
                              AND mem.mem_begin <= ?
                              AND mem.mem_end > ?
                       )
                       OR EXISTS (
                           SELECT 1
                             FROM ' . TBL_MEMBERS . ' mem_event
                       INNER JOIN ' . TBL_ROLES . ' rol_event ON rol_event.rol_id = mem_event.mem_rol_id
                            WHERE mem_event.mem_usr_id = usr.usr_id
                              AND rol_event.rol_valid = true
                              AND mem_event.mem_rol_id = ?
                              AND mem_event.mem_begin <= ?
                              AND mem_event.mem_end > ?
                              AND (mem_event.mem_approved IS NULL OR mem_event.mem_approved IN (?, ?))
                       )
                   )
                   AND NOT EXISTS (
                       SELECT 1
                         FROM ' . TBL_MEMBERS . ' mem_no
                        WHERE mem_no.mem_usr_id = usr.usr_id
                          AND mem_no.mem_rol_id = ?
                          AND mem_no.mem_begin <= ?
                          AND mem_no.mem_end > ?
                          AND mem_no.mem_approved IN (?, ?)
                   )
              ORDER BY usr_id';

        $queryParams[] = DATETIME_NOW;
        $queryParams[] = DATETIME_NOW;
        $queryParams[] = $eventRoleId;
        $queryParams[] = DATETIME_NOW;
        $queryParams[] = DATETIME_NOW;
        $queryParams[] = Participants::STATE_INVITED;
        $queryParams[] = Participants::STATE_TENTATIVE;
        $queryParams[] = $eventRoleId;
        $queryParams[] = DATETIME_NOW;
        $queryParams[] = DATETIME_NOW;
        $queryParams[] = Participants::STATE_ATTENDING;
        $queryParams[] = Participants::STATE_REFUSED;

        $rows = $this->database->queryPrepared($sql, $queryParams)->fetchAll();
        return array_map(
            static fn(array $row): array => array(
                'usr_id' => (int) $row['usr_id'],
                'usr_uuid' => (string) $row['usr_uuid']
            ),
            $rows
        );
    }

    /**
     * Atomically claim the reminder for an event/user pair.
     *
     * @return bool true if this process claimed the reminder, false if it was already claimed.
     * @throws \Admidio\Infrastructure\Exception
     */
    public function claim(int $eventId, int $userId): bool
    {
        $sql = DB_TYPE === Database::PDO_ENGINE_PGSQL
            ? 'INSERT INTO ' . TBL_EVENT_REMINDER_SENT . ' (ers_dat_id, ers_usr_id, ers_timestamp_sent)
                    VALUES (?, ?, ?)
               ON CONFLICT DO NOTHING'
            : 'INSERT INTO ' . TBL_EVENT_REMINDER_SENT . ' (ers_dat_id, ers_usr_id, ers_timestamp_sent)
                    VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE ers_dat_id = VALUES(ers_dat_id)';

        return $this->database->queryPrepared($sql, array($eventId, $userId, DATETIME_NOW))->rowCount() === 1;
    }

    /**
     * Release a claim after sending the reminder failed.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    public function release(int $eventId, int $userId): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ? AND ers_usr_id = ?',
            array($eventId, $userId)
        );
    }

    /**
     * Remove the reminder state of one event, e.g. after its start or reminder days changed.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    public function deleteForEvent(int $eventId): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_EVENT_REMINDER_SENT . ' WHERE ers_dat_id = ?',
            array($eventId)
        );
    }

    /**
     * Remove the reminder state of all events of a recurrence series.
     *
     * @throws \Admidio\Infrastructure\Exception
     */
    public function deleteForSeries(int $recurrenceId): void
    {
        $this->database->queryPrepared(
            'DELETE FROM ' . TBL_EVENT_REMINDER_SENT . '
              WHERE ers_dat_id IN (SELECT dat_id FROM ' . TBL_EVENTS . ' WHERE dat_evr_id = ?)',
            array($recurrenceId)
        );
    }
}
