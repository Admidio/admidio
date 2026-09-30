<?php
namespace Admidio\Events\Repository;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\DateTimeUtils;
use DateTime;

/**
 * Repository for filtered event lists.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventRepository
{
    private int $categoryId = 0;
    private string $categoryUuid = '';
    private string $eventUuid = '';
    private string $participationFilter = '';
    private string $mode = 'actual';
    private string $order = 'ASC';
    private ?DateTime $dateStart = null;
    private ?DateTime $dateEnd = null;

    public function __construct(private readonly Database $database)
    {
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->order = $mode === 'old' ? 'DESC' : 'ASC';
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function setCategoryUuid(string $categoryUuid): void
    {
        $this->categoryUuid = $categoryUuid;
    }

    public function setEventUuid(string $eventUuid): void
    {
        $this->eventUuid = $eventUuid;
    }

    public function setParticipationFilter(string $participationFilter): void
    {
        $this->participationFilter = $participationFilter;
    }

    /**
     * Set the date range in which events should be searched.
     * @throws Exception SYS_DATE_END_BEFORE_BEGIN
     */
    public function setDateRange(string $dateRangeStart = '', string $dateRangeEnd = ''): bool
    {
        if ($dateRangeStart === '') {
            $firstDate = '1970-01-01';
            $lastDate = ((int)date('Y') + 10) . '-12-31';

            switch ($this->mode) {
                case 'old':
                    $dateRangeStart = $firstDate;
                    $dateRangeEnd = DATE_NOW;
                    break;

                case 'all':
                    $dateRangeStart = $firstDate;
                    $dateRangeEnd = $lastDate;
                    break;

                default:
                    $dateRangeStart = DATE_NOW;
                    $dateRangeEnd = $lastDate;
                    break;
            }
        }

        $dateFrom = DateTimeUtils::parseDate($dateRangeStart);
        if ($dateFrom === null) {
            return false;
        }

        $dateTo = DateTimeUtils::parseDate($dateRangeEnd);
        if ($dateTo === null) {
            return false;
        }

        if ($dateFrom->getTimestamp() > $dateTo->getTimestamp()) {
            throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
        }

        $this->dateStart = $dateFrom;
        $this->dateEnd = $dateTo;

        return true;
    }

    public function getDateStart(string $format = 'Y-m-d'): string
    {
        $this->ensureDateRange();

        return $this->dateStart->format($format);
    }

    public function getDateEnd(string $format = 'Y-m-d'): string
    {
        $this->ensureDateRange();

        return $this->dateEnd->format($format);
    }

    /**
     * Read a page of visible events.
     * @return array{recordset: array<int,array<string,mixed>>, numResults: int, limit: int, totalCount: int}
     * @throws Exception
     */
    public function getDataSet(int $startElement = 0, int $limit = 0): array
    {
        global $gCurrentUser;

        $categoryIds = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));
        $additional = $this->getAdditionalSql();
        $conditions = $this->getSqlConditions();

        $sql = 'SELECT DISTINCT cat.*, dat.*, rol_uuid, mem.mem_usr_id AS member_date_role,
                       mem.mem_approved AS member_approval_state, mem.mem_leader, mem.mem_comment AS comment,
                       mem.mem_count_guests AS additional_guests,' . $additional['fields'] . ', cat_name AS category_name
                  FROM ' . TBL_EVENTS . ' AS dat
            INNER JOIN ' . TBL_CATEGORIES . ' AS cat
                    ON cat_id = dat_cat_id
             LEFT JOIN ' . TBL_ROLES . ' AS rol
                    ON rol_id = dat_rol_id
                       ' . $additional['tables'] . '
             LEFT JOIN ' . TBL_MEMBERS . ' AS mem
                    ON mem.mem_rol_id = dat_rol_id
                   AND mem.mem_usr_id = ?
                   AND mem.mem_begin <= ?
                   AND mem.mem_end    > ?
                 WHERE cat_id IN (' . Database::getQmForValues($categoryIds) . ')
                       ' . $conditions['sql'] . '
              ORDER BY dat_begin ' . $this->order;

        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($startElement > 0) {
            $sql .= ' OFFSET ' . $startElement;
        }

        $queryParams = array_merge(
            $additional['params'],
            array($GLOBALS['gCurrentUserId'], DATE_NOW, DATE_NOW),
            $categoryIds,
            $conditions['params']
        );
        $statement = $this->database->queryPrepared($sql, $queryParams);

        return array(
            'recordset' => $statement->fetchAll(),
            'numResults' => $statement->rowCount(),
            'limit' => $limit,
            'totalCount' => $this->getDataSetCount()
        );
    }

    /**
     * Return the number of visible events matching the current filters.
     * @throws Exception
     */
    public function getDataSetCount(): int
    {
        global $gCurrentUser;

        $categoryIds = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('EVT'));
        $conditions = $this->getSqlConditions();

        $sql = 'SELECT COUNT(DISTINCT dat_id) AS count
                  FROM ' . TBL_EVENTS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = dat_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($categoryIds) . ')
                       ' . $conditions['sql'];

        $statement = $this->database->queryPrepared($sql, array_merge($categoryIds, $conditions['params']));

        return (int)$statement->fetchColumn();
    }

    /**
     * @return array{sql: string, params: array<int,mixed>}
     * @throws Exception
     */
    private function getSqlConditions(): array
    {
        global $gCurrentUser;

        $sql = ' AND (dat_recurrence_status IS NULL OR dat_recurrence_status <> ?) ';
        $params = array('cancelled');

        if ($this->categoryUuid !== '') {
            $sql .= ' AND cat_uuid = ? ';
            $params[] = $this->categoryUuid;
        }

        if ($this->eventUuid !== '') {
            $sql .= ' AND dat_uuid = ? ';
            $params[] = $this->eventUuid;
        } else {
            $this->ensureDateRange();
            $sql .= ' AND dat_begin <= ? AND dat_end >= ? ';
            $params[] = $this->dateEnd->format('Y-m-d') . ' 23:59:59';
            $params[] = $this->dateStart->format('Y-m-d') . ' 00:00:00';

            if ($this->categoryId > 0) {
                $sql .= ' AND cat_id = ? ';
                $params[] = $this->categoryId;
            }
        }

        if ($GLOBALS['gCurrentUserId'] > 0) {
            if ($this->participationFilter === 'maybe_participate') {
                $roleMemberships = $gCurrentUser->getRoleMemberships();
                $sql .= '
                    AND dat_rol_id IS NOT NULL
                    AND EXISTS (SELECT 1
                                  FROM ' . TBL_ROLES_RIGHTS . '
                            INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . '
                                    ON rrd_ror_id = ror_id
                                 WHERE ror_name_intern = \'event_participation\'
                                   AND rrd_object_id = dat_id
                                   AND rrd_rol_id IN (' . Database::getQmForValues($roleMemberships) . ')) ';
                $params = array_merge($params, $roleMemberships);
            } elseif ($this->participationFilter === 'only_participate') {
                $sql .= '
                    AND dat_rol_id IS NOT NULL
                    AND dat_rol_id IN (SELECT mem_rol_id
                                         FROM ' . TBL_MEMBERS . ' AS mem2
                                        WHERE mem2.mem_usr_id = ?
                                          AND mem2.mem_begin <= dat_begin
                                          AND mem2.mem_end   >= dat_end) ';
                $params[] = $GLOBALS['gCurrentUserId'];
            }
        }

        return array('sql' => $sql, 'params' => $params);
    }

    /**
     * @return array{fields: string, tables: string, params: array<int,int>}
     * @throws Exception
     */
    private function getAdditionalSql(): array
    {
        global $gSettingsManager, $gProfileFields;

        if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
            $lastNameFieldId = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');
            $firstNameFieldId = (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id');

            return array(
                'fields' => '
                    cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
                    cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name,
                    cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ',
                'tables' => '
                    LEFT JOIN ' . TBL_USERS . ' AS cre_user
                           ON cre_user.usr_id = dat_usr_id_create
                    LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                           ON cre_surname.usd_usr_id = dat_usr_id_create
                          AND cre_surname.usd_usf_id = ?
                    LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                           ON cre_firstname.usd_usr_id = dat_usr_id_create
                          AND cre_firstname.usd_usf_id = ?
                    LEFT JOIN ' . TBL_USERS . ' AS cha_user
                           ON cha_user.usr_id = dat_usr_id_change
                    LEFT JOIN ' . TBL_USER_DATA . ' AS cha_surname
                           ON cha_surname.usd_usr_id = dat_usr_id_change
                          AND cha_surname.usd_usf_id = ?
                    LEFT JOIN ' . TBL_USER_DATA . ' AS cha_firstname
                           ON cha_firstname.usd_usr_id = dat_usr_id_change
                          AND cha_firstname.usd_usf_id = ?',
                'params' => array($lastNameFieldId, $firstNameFieldId, $lastNameFieldId, $firstNameFieldId)
            );
        }

        return array(
            'fields' => '
                cre_user.usr_login_name AS create_name,
                cha_user.usr_login_name AS change_name,
                cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid ',
            'tables' => '
                LEFT JOIN ' . TBL_USERS . ' AS cre_user
                       ON cre_user.usr_id = dat_usr_id_create
                LEFT JOIN ' . TBL_USERS . ' AS cha_user
                       ON cha_user.usr_id = dat_usr_id_change ',
            'params' => array()
        );
    }

    private function ensureDateRange(): void
    {
        if ($this->dateStart === null || $this->dateEnd === null) {
            $this->setDateRange();
        }
    }
}
