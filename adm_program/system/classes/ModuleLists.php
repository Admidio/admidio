<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class manages lists viewable for user
 *
 * This class reads all available recordsets from table lists.
 * and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *
 * **Returned array:**
 * ```
 * Array(
 *          [numResults] => 5
 *          [limit] => 10
 *          [totalCount] => 5
 *          [recordset] => Array
 *              (
 *                  [0] => Array
 *                      (
 *                          [0] => 2
 *                          [rol_id] => 2
 *                          [1] => 3
 *                          [rol_cat_id] => 3
 *                          [2] =>
 *                          [rol_lst_id] =>
 *                          [3] => Mitglieder
 *                          [rol_name] => Mitglieder
 *                          [4] => Alle Mitglieder der Organisation
 *                          [rol_description] => Alle Mitglieder der Organisation
 *                          [5] => 0
 *                          [rol_assign_roles] => 0
 *                          [6] => 0
 *                          [rol_approve_users] => 0
 *                          [7] => 0
 *                          [rol_announcements] => 0
 *                          [8] => 0
 *                          [rol_dates] => 0
 *                          [9] => 0
 *                          [rol_documents_files] => 0
 *                          [10] => 0
 *                          [rol_edit_user] => 0
 *                          [11] => 0
 *                          [rol_guestbook] => 0
 *                          [12] => 1
 *                          [rol_guestbook_comments] => 1
 *                          [13] => 0
 *                          [rol_mail_to_all] => 0
 *                          [14] => 2
 *                          [rol_mail_this_role] => 2
 *                          [15] => 0
 *                          [rol_photo] => 0
 *                          [16] => 1
 *                          [rol_profile] => 1
 *                          [17] => 0
 *                          [rol_weblinks] => 0
 *                          [18] => 2
 *                          [rol_this_list_view] => 2
 *                          [19] => 0
 *                          [rol_all_lists_view] => 0
 *                          [20] => 1
 *                          [rol_default_registration] => 1
 *                          [21] => 1
 *                          [rol_leader_rights] => 1
 *                          [22] =>
 *                          [rol_start_date] =>
 *                          [23] =>
 *                          [rol_start_time] =>
 *                          [24] =>
 *                          [rol_end_date] =>
 *                          [25] =>
 *                          [rol_end_time] =>
 *                          [26] => 0
 *                          [rol_weekday] => 0
 *                          [27] =>
 *                          [rol_location] =>
 *                          [28] =>
 *                          [rol_max_members] =>
 *                          [29] =>
 *                          [rol_cost] =>
 *                          [30] =>
 *                          [rol_cost_period] =>
 *                          [31] => 1
 *                          [rol_usr_id_create] => 1
 *                          [32] => 2008-05-03 16:26:36
 *                          [rol_timestamp_create] => 2008-05-03 16:26:36
 *                          [33] => 1
 *                          [rol_usr_id_change] => 1
 *                          [34] => 2008-05-03 16:26:36
 *                          [rol_timestamp_change] => 2008-05-03 16:26:36
 *                          [35] => 1
 *                          [rol_valid] => 1
 *                          [36] => 0
 *                          [rol_system] => 0
 *                          [37] => 0
 *                          [rol_administrator] => 0
 *                          [38] => 3
 *                          [cat_id] => 3
 *                          [39] => 1
 *                          [cat_org_id] => 1
 *                          [40] => ROL
 *                          [cat_type] => ROL
 *                          [41] => COMMON
 *                          [cat_name_intern] => COMMON
 *                          [42] => Allgemein
 *                          [cat_name] => Allgemein
 *                          [44] => 0
 *                          [cat_system] => 0
 *                          [45] => 0
 *                          [cat_default] => 0
 *                          [46] => 1
 *                          [cat_sequence] => 1
 *                          [47] => 1
 *                          [cat_usr_id_create] => 1
 *                          [48] => 2012-01-08 11:12:05
 *                          [cat_timestamp_create] => 2012-01-08 11:12:05
 *                          [49] =>
 *                          [cat_usr_id_change] =>
 *                          [50] =>
 *                          [cat_timestamp_change] =>
 *                          [51] => 145
 *                          [num_members] => 145
 *                          [52] => 0
 *                          [num_leader] => 0
 *                          [53] => 5
 *                          [num_former] => 5
 *                      )
 *
 *          [parameter] => Array
 *              (
 *                  [active_role] => 1
 *                  [calendar-selection] => 1
 *                  [cat_id] => 0
 *                  [category-selection] => 1
 *                  [date] =>
 *                  [daterange] => Array
 *                      (
 *                          [english] => Array
 *                              (
 *                                  [start_date] => 2013-09-24
 *                                  [end_date] => 9999-12-31
 *                              )
 *
 *                          [system] => Array
 *                              (
 *                                  [start_date] => 24.09.2013
 *                                  [end_date] => 31.12.9999
 *                              )
 *
 *                      )
 *
 *                  [headline] => Übersicht der aktiven Rollen
 *                  [id] => 0
 *                  [mode] => Default
 *                  [order] => ASC
 *                  [startelement] => 0
 *                  [view_mode] => Default
 *              )
 * )
 * ```
 */
class ModuleLists extends Modules
{
    public const ROLE_TYPE_INACTIVE = 0;
    public const ROLE_TYPE_ACTIVE = 1;
    public const ROLE_TYPE_EVENT_PARTICIPATION = 2;

    /**
     * creates an new ModuleLists object
     */
    public function __construct()
    {
        // get parent instance with all parameters from $_GET Array
        parent::__construct();
    }


    /**
     * returns SQL condition
     * @return string SQL condition for category id
     */
    private function getCategorySql()
    {
        if ($this->catId > 0) {
            return ' AND cat_id  = '.$this->catId;
        }
        return '';
    }

    /**
     * returns SQL condition that considered the role type
     * @return string SQL condition for role type
     */
    private function getRoleTypeSql()
    {
        $sql = '';

        switch ($this->roleType) {
            case ROLE_TYPE_INACTIVE:
                $sql = ' AND rol_valid   = false
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case ROLE_TYPE_ACTIVE:
                $sql = ' AND rol_valid   = true
                         AND cat_name_intern <> \'EVENTS\' ';
                break;

            case ROLE_TYPE_EVENT_PARTICIPATION:
                $sql = ' AND cat_name_intern = \'EVENTS\' ';
                break;
        }

        return $sql;
    }

    /**
     * assembles SQL roles visible for current user
     * @return string SQL condition visible for current user
     */
    private function getVisibleRolesSql()
    {
        global $gCurrentUser;

        if ($this->roleType == 0 && $gCurrentUser->isAdministrator()) {
            // if inactive roles should be shown, then show all of them to administrator
            return '';
        }

        // create a list with all rol_ids that the user is allowed to view
        $visibleRoles = implode(',', $gCurrentUser->getRolesViewMemberships());
        if ($visibleRoles !== '') {
            return ' AND rol_id IN ('.$visibleRoles.')';
        }

        return ' AND rol_id = 0 ';
    }

    /**
     * Function returns a set of lists with corresponding information
     * @param int $startElement Start element of result. First (and default) is 0.
     * @param int $limit        Number of elements returned max. Default NULL will take number from preferences.
     * @return array<string,mixed> with list and corresponding information
     */
    public function getDataSet($startElement = 0, $limit = null)
    {
        global $gSettingsManager, $gDb;

        // Parameter
        if ($limit === null) {
            // Roles per page
            $limit = $gSettingsManager->getInt('groups_roles_roles_per_page');
        }

        // assemble conditions
        $sqlConditions = $this->getCategorySql() . $this->getRoleTypeSql() . $this->getVisibleRolesSql();

        $sql = 'SELECT rol.*, cat.*,
                       COALESCE((SELECT COUNT(*) + SUM(mem_count_guests) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND (mem.mem_approved IS NULL
                            OR mem.mem_approved < 3)
                           AND mem.mem_leader = false), 0) AS num_members,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem.mem_begin  <= ? -- DATE_NOW
                           AND mem.mem_end     > ? -- DATE_NOW
                           AND mem.mem_leader = true), 0) AS num_leader,
                       COALESCE((SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' AS mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem_end < ?  -- DATE_NOW
                           AND NOT EXISTS (
                               SELECT 1
                                 FROM '.TBL_MEMBERS.' AS act
                                WHERE act.mem_rol_id = mem.mem_rol_id
                                  AND act.mem_usr_id = mem.mem_usr_id
                                  AND ? BETWEEN act.mem_begin AND act.mem_end -- DATE_NOW
                           )), 0) AS num_former -- DATE_NOW
                  FROM '.TBL_ROLES.' AS rol
            INNER JOIN '.TBL_CATEGORIES.' AS cat
                    ON cat_id = rol_cat_id
             LEFT JOIN '.TBL_DATES.' ON dat_rol_id = rol_id
                 WHERE (  cat_org_id = ? -- $gCurrentOrgId
                       OR cat_org_id IS NULL )
                       '.$sqlConditions;

        if ($this->roleType === ROLE_TYPE_EVENT_PARTICIPATION) {
            $sql .= ' ORDER BY cat_sequence, dat_begin DESC, rol_name ';
        } else {
            $sql .= ' ORDER BY cat_sequence, rol_name ';
        }

        // If is there a limit then specify one
        if ($limit > 0) {
            $sql .= ' LIMIT '.$limit;
        }
        if ($startElement > 0) {
            $sql .= ' OFFSET '.$startElement;
        }

        $listsStatement = $gDb->queryPrepared($sql,
            array(
                DATE_NOW,
                DATE_NOW,
                DATE_NOW,
                DATE_NOW,
                DATE_NOW,
                DATE_NOW,
                $GLOBALS['gCurrentOrgId']
            )
        ); // TODO add more params

        // array for results
        return array(
            'recordset'  => $listsStatement->fetchAll(),
            'numResults' => $listsStatement->rowCount(),
            'limit'      => $limit,
            'totalCount' => $this->getDataSetCount(),
            'parameter'  => $this->getParameters()
        );
    }

    /**
     * Function to get total number of lists limited by current conditions.
     * @return int Number of lists.
     */
    public function getDataSetCount()
    {
        global $gDb;

        // assemble conditions
        $sqlConditions = $this->getCategorySql() . $this->getRoleTypeSql() . $this->getVisibleRolesSql();

        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_ROLES.' AS rol
            INNER JOIN '.TBL_CATEGORIES.' AS cat
                    ON rol_cat_id = cat_id
                 WHERE (  cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                       OR cat_org_id IS NULL )
                       '.$sqlConditions;
        $pdoStatement = $gDb->queryPrepared($sql, array($GLOBALS['gCurrentOrgId'])); // TODO add more params

        return (int) $pdoStatement->fetchColumn();
    }
}
