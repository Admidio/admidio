<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class manages weblinks viewable for user
 *
 * This class reads all available recordsets from table links.
 * and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *
 * **Returned Array:**
 * ```
 * Array(
 *         [numResults] => 4
 *         [limit] => 0
 *         [totalCount] => 4
 *         [recordset] => Array
 *         (
 *             [0] => Array
 *                 (
 *                     [0] => 7
 *                     [cat_id] => 7
 *                     [1] => 1
 *                     [cat_org_id] => 1
 *                     [2] => LNK
 *                     [cat_type] => LNK
 *                     [3] => COMMON
 *                     [cat_name_intern] => COMMON
 *                     [4] => Allgemein
 *                     [cat_name] => Allgemein
 *                     [6] => 0
 *                     [cat_system] => 0
 *                     [7] => 0
 *                     [cat_default] => 0
 *                     [8] => 1
 *                     [cat_sequence] => 1
 *                     [9] => 1
 *                     [cat_usr_id_create] => 1
 *                     [10] => 2012-01-08 11:12:05
 *                     [cat_timestamp_create] => 2012-01-08 11:12:05
 *                     [11] =>
 *                     [cat_usr_id_change] =>
 *                     [12] =>
 *                     [cat_timestamp_change] =>
 *                     [13] => 1
 *                     [lnk_id] => 1
 *                     [14] => 7
 *                     [lnk_cat_id] => 7
 *                     [15] => Beispielseite
 *                     [lnk_name] => Beispielseite
 *                     [16] => Auf dieser Seite gibt es nicht viele Neuigkeiten :(
 *                     [lnk_description] => Auf dieser Seite gibt es nicht viele Neuigkeiten :(
 *                     [17] => https://www.example.com
 *                     [lnk_url] => https://www.example.com
 *                     [18] => 6
 *                     [lnk_counter] => 6
 *                     [19] => 1
 *                     [lnk_usr_id_create] => 1
 *                     [20] => 2013-07-14 00:00:00
 *                     [lnk_timestamp_create] => 2013-07-14 00:00:00
 *                     [21] => 1
 *                     [lnk_usr_id_change] => 1
 *                     [22] => 2013-07-15 00:00:00
 *                     [lnk_timestamp_change] => 2013-07-15 00:00:00
 *                 )
 *     [parameter] => Array
 *         (
 *             [active_role] => 1
 *             [calendar-selection] => 1
 *             [cat_id] => 0
 *             [category-selection] => 1
 *             [date] =>
 *             [daterange] => Array
 *                                 (
 *                                     [english] => Array
 *                                                      (
 *                                                         [start_date] => 2013-09-25
 *                                                         [end_date] => 9999-12-31
 *                                                      )
 *
 *                                     [system] => Array
 *                                                     (
 *                                                         [start_date] => 25.09.2013
 *                                                         [end_date] => 31.12.9999
 *                                                     )
 *                                 )
 *             [headline] => Weblinks
 *             [id] => 0
 *             [mode] => Default
 *             [order] => ASC
 *             [startelement] => 0
 *             [view_mode] => Default
 *         )
 * )
 * ```
 */
class ModuleWeblinks extends Modules
{
    /**
     * Function returns a set of links with corresponding information
     * @param int $startElement Start element of result. First (and default) is 0.
     * @param int $limit        Number of elements returned max. Default NULL will take number from preferences.
     * @return array<string,mixed> with links and corresponding information
     */
    public function getDataSet($startElement = 0, $limit = null)
    {
        global $gCurrentUser, $gSettingsManager, $gDb;

        // Parameter
        if ($limit === null) {
            $limit = $gSettingsManager->getInt('weblinks_per_page');
        }

        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('LNK'));
        $sqlConditions = $this->getSqlConditions();

        // Weblinks aus der DB fischen...
        $sql = 'SELECT *
                  FROM '.TBL_LINKS.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = lnk_cat_id
                 WHERE cat_id IN ('.Database::getQmForValues($catIdParams).')
                       '.$sqlConditions['sql'].'
              ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC';

        if ($limit > 0) {
            $sql .= ' LIMIT '.$limit;
        }
        if ($startElement > 0) {
            $sql .= ' OFFSET '.$startElement;
        }

        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, $sqlConditions['params'])); // TODO add more params

        // array for results
        return array(
            'recordset'  => $pdoStatement->fetchAll(),
            'numResults' => $pdoStatement->rowCount(),
            'limit'      => $limit,
            'totalCount' => $this->getDataSetCount(),
            'parameter'  => $this->getParameters()
        );
    }

    /**
     * Function to get total number of links filtered by current conditions.
     * @return int Number of links.
     */
    public function getDataSetCount()
    {
        global $gCurrentUser, $gDb;

        $catIdParams = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('LNK'));
        $sqlConditions = $this->getSqlConditions();

        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_LINKS.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = lnk_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($catIdParams) . ')
                       '.$sqlConditions['sql'];
        $pdoStatement = $gDb->queryPrepared($sql, array_merge($catIdParams, $sqlConditions['params']));

        return (int) $pdoStatement->fetchColumn();
    }

    /**
     * Add several conditions to an SQL string that could later be used as additional conditions in other SQL queries.
     * @return array<string,string|array<int,int>> Returns an array of a SQL string with additional conditions and it's query params.
     */
    private function getSqlConditions()
    {
        $sqlConditions = '';
        $params = array();

        $uuid  = $this->getParameter('lnk_uuid');
        $catId = (int) $this->getParameter('cat_id');

        // In case ID was permitted and user has rights
        if (!empty($uuid)) {
            $sqlConditions .= ' AND lnk_uuid = ? '; // $uuid
            $params[] = $uuid;
        }
        // show all weblinks from category
        elseif ($catId > 0) {
            $sqlConditions .= ' AND cat_id = ? '; // $catId
            $params[] = $catId;
        }

        return array(
            'sql'    => $sqlConditions,
            'params' => $params
        );
    }
}
