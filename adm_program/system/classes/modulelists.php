<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ModuleLists
 * @brief Class manages lists viewable for user
 *
 * This class reads all available recordsets from table lists.
 * and returns an Array with results, recordsets and validated parameters from $_GET Array.
 * @par Returned Array
 * @code
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
 *                          [rol_download] => 0
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
 *                          [37] => 1
 *                          [rol_visible] => 1
 *                          [38] => 0
 *                          [rol_administrator] => 0
 *                          [39] => 3
 *                          [cat_id] => 3
 *                          [40] => 1
 *                          [cat_org_id] => 1
 *                          [41] => ROL
 *                          [cat_type] => ROL
 *                          [42] => COMMON
 *                          [cat_name_intern] => COMMON
 *                          [43] => Allgemein
 *                          [cat_name] => Allgemein
 *                          [44] => 0
 *                          [cat_hidden] => 0
 *                          [45] => 0
 *                          [cat_system] => 0
 *                          [46] => 0
 *                          [cat_default] => 0
 *                          [47] => 1
 *                          [cat_sequence] => 1
 *                          [48] => 1
 *                          [cat_usr_id_create] => 1
 *                          [49] => 2012-01-08 11:12:05
 *                          [cat_timestamp_create] => 2012-01-08 11:12:05
 *                          [50] =>
 *                          [cat_usr_id_change] =>
 *                          [51] =>
 *                          [cat_timestamp_change] =>
 *                          [52] => 145
 *                          [num_members] => 145
 *                          [53] => 0
 *                          [num_leader] => 0
 *                          [54] => 5
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
 * @endcode
 */
class ModuleLists extends Modules
{
    private $memberStatus;

    /**
     * creates an new ModuleLists object
     */
    public function __construct()
    {
        global $gL10n;
        // define constant for headline
        define('HEADLINE', $gL10n->get('LST_ACTIVE_ROLES'));

        // get parent instance with all parameters from $_GET Array
        parent::__construct();

        $this->setMemberStatus();
    }

    /**
     * Evaluates memberStatus an returns appropriate SQL conditions
     * @return string SQL for member status
     */
    private function getMemberStatusSql()
    {
        switch ($this->memberStatus)
        {
            case 'inactive':
                $sql = ' AND mem_end < \''.DATE_NOW.'\' ';
                break;
            case 'both':
                $sql ='';
                break;
            case 'active':
            default:
                $sql = ' AND mem_begin <= \''.DATE_NOW.'\'
                         AND mem_end   >= \''.DATE_NOW.'\' ';
        }
        return $sql;
    }

    /**
     * returns SQL condition
     * @return string SQL condition for category id
     */
    private function getCategorySql()
    {
        if($this->catId > 0)
        {
            return ' AND cat_id  = '.$this->catId;
        }
        return '';
    }

    /**
     * assembles SQL roles visible for current user
     * @return string SQL condition visible for current user
     */
    private function getVisibleRolesSql()
    {
        global $gCurrentUser;

        if(!$this->activeRole && $gCurrentUser->isAdministrator())
        {
            // if inactive roles should be shown, then show all of them to administrator
            return '';
        }

        // create a list with all rol_ids that the user is allowed to view
        $visibleRoles = implode(',', $gCurrentUser->getAllVisibleRoles());
        if($visibleRoles !== '')
        {
            return ' AND rol_id IN ('.$visibleRoles.')';
        }

        return ' AND rol_id = 0 ';
    }

    /**
     * Function returns a set of lists with corresponding information
     * @param int $startElement Start element of result. First (and default) is 0.
     * @param int $limit        Number of elements returned max. Default NULL will take number from preferences.
     * @return array with list and corresponding information
     */
    public function getDataSet($startElement = 0, $limit = null)
    {
        global $gCurrentOrganization, $gPreferences, $gValidLogin, $gDb;

        // Parameter
        if($limit === null)
        {
            // Roles per page
            $limit = $gPreferences['lists_roles_per_page'];
        }

        // assemble conditions
        $sqlConditions = $this->getCategorySql().$this->getVisibleRolesSql();

        // provoke empty result for not logged in users
        if(!$gValidLogin)
        {
            $sqlConditions .= ' AND cat_hidden = 0 ';
        }

        $sql = 'SELECT rol.*, cat.*,
                       (SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' mem
                         WHERE mem.mem_rol_id = rol.rol_id '.$this->getMemberStatusSql().'
                           AND mem_leader = 0) AS num_members,
                       (SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' mem
                         WHERE mem.mem_rol_id = rol.rol_id '.$this->getMemberStatusSql().'
                           AND mem_leader = 1) AS num_leader,
                       (SELECT COUNT(*) AS count
                          FROM '.TBL_MEMBERS.' mem
                         WHERE mem.mem_rol_id = rol.rol_id
                           AND mem_end < \''. DATE_NOW.'\') AS num_former
                  FROM '.TBL_ROLES.' rol
            INNER JOIN '.TBL_CATEGORIES.' cat
                    ON cat_id = rol_cat_id
                 WHERE rol_visible = 1
                   AND rol_valid   = '.(int) $this->activeRole.'
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                       '.$sqlConditions.'
              ORDER BY cat_sequence, rol_name';

        // If is there a limit then specify one
        if($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }
        if($startElement > 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }

        $listsStatement = $gDb->query($sql);

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
        global $gCurrentOrganization, $gValidLogin, $gDb;

        // assemble conditions
        $sqlConditions = $this->getCategorySql().$this->getVisibleRolesSql();
        // provoke empty result for not logged in users
        if(!$gValidLogin)
        {
            $sqlConditions = ' AND cat_hidden = 0 ';
        }

        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_ROLES.' rol, '.TBL_CATEGORIES.' cat
                 WHERE rol_valid   = '.(int) $this->activeRole.'
                   AND rol_visible = 1
                   AND rol_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                       '.$sqlConditions;
        $pdoStatement = $gDb->query($sql);

        return (int) $pdoStatement->fetchColumn();
    }

    /**
     * Function to get list configurations accessible by current user
     * @return array with accessible list configurations
     */
    public function getListConfigurations()
    {
        global $gCurrentOrganization, $gCurrentUser, $gDb;

        $sql = 'SELECT lst_id, lst_name, lst_global
                  FROM '.TBL_LISTS.'
                 WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   AND (  lst_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                       OR lst_global = 1)
                   AND lst_name IS NOT NULL
              ORDER BY lst_global ASC, lst_name ASC';
        $pdoStatement = $gDb->query($sql);

        $configurations = array();
        while($row = $pdoStatement->fetch())
        {
            $configurations[] = array($row['lst_id'], $row['lst_name'], $row['lst_global']);
        }
        return $configurations;
    }

    /**
     * Sets the status of role members to be shown
     * @param string $status active(default), inactive, both
     */
    public function setMemberStatus($status = 'active')
    {
        switch ($status)
        {
            case 'inactive':
                $this->memberStatus = 'inactive';
                break;
            case 'both':
                $this->memberStatus = 'both';
                break;
            case 'active':
            default:
                $this->memberStatus = 'active';
                break;
        }
    }
}
