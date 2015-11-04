<?php
/*****************************************************************************/
/** @class ModuleWeblinks
 *  @brief Class manages weblinks viewable for user
 *
 *  This class reads all available recordsets from table links.
 *  and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *  @par Returned Array
 *  @code
 *  Array(
 *          [numResults] => 4
 *          [limit] => 0
 *          [totalCount] => 4
 *          [recordset] => Array
 *          (
 *              [0] => Array
 *                  (
 *                      [0] => 7
 *                      [cat_id] => 7
 *                      [1] => 1
 *                      [cat_org_id] => 1
 *                      [2] => LNK
 *                      [cat_type] => LNK
 *                      [3] => COMMON
 *                      [cat_name_intern] => COMMON
 *                      [4] => Allgemein
 *                      [cat_name] => Allgemein
 *                      [5] => 0
 *                      [cat_hidden] => 0
 *                      [6] => 0
 *                      [cat_system] => 0
 *                      [7] => 0
 *                      [cat_default] => 0
 *                      [8] => 1
 *                      [cat_sequence] => 1
 *                      [9] => 1
 *                      [cat_usr_id_create] => 1
 *                      [10] => 2012-01-08 11:12:05
 *                      [cat_timestamp_create] => 2012-01-08 11:12:05
 *                      [11] =>
 *                      [cat_usr_id_change] =>
 *                      [12] =>
 *                      [cat_timestamp_change] =>
 *                      [13] => 1
 *                      [lnk_id] => 1
 *                      [14] => 7
 *                      [lnk_cat_id] => 7
 *                      [15] => Beispielseite
 *                      [lnk_name] => Beispielseite
 *                      [16] => Auf dieser Seite gibt es nicht viele Neuigkeiten :(
 *                      [lnk_description] => Auf dieser Seite gibt es nicht viele Neuigkeiten :(
 *                      [17] => http://www.example.com
 *                      [lnk_url] => http://www.example.com
 *                      [18] => 6
 *                      [lnk_counter] => 6
 *                      [19] => 1
 *                      [lnk_usr_id_create] => 1
 *                      [20] => 2013-07-14 00:00:00
 *                      [lnk_timestamp_create] => 2013-07-14 00:00:00
 *                      [21] => 1
 *                      [lnk_usr_id_change] => 1
 *                      [22] => 2013-07-15 00:00:00
 *                      [lnk_timestamp_change] => 2013-07-15 00:00:00
 *                  )
 *      [parameter] => Array
 *          (
 *              [active_role] => 1
 *              [calendar-selection] => 1
 *              [cat_id] => 0
 *              [category-selection] => 1
 *              [date] =>
 *              [daterange] => Array
 *                                  (
 *                                      [english] => Array
 *                                                       (
 *                                                          [start_date] => 2013-09-25
 *                                                          [end_date] => 9999-12-31
 *                                                       )
 *
 *                                      [system] => Array
 *                                                      (
 *                                                          [start_date] => 25.09.2013
 *                                                          [end_date] => 31.12.9999
 *                                                      )
 *                                  )
 *              [headline] => Weblinks
 *              [id] => 0
 *              [mode] => Default
 *              [order] => ASC
 *              [startelement] => 0
 *              [view_mode] => Default
 *          )
 *  )
 *  @endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2015 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class ModuleWeblinks extends Modules
{
    protected $getConditions;       ///< String with SQL condition

    /**
     *  creates an new ModuleWeblink object
     */
    public function __construct()
    {
        global $gValidLogin;
        global $gL10n;

        // get parent instance with all parameters from $_GET Array
        parent::__construct();
    }

    /** Function returns a set of links with corresponding informations
     *  @param $startElement Start element of result. First (and default) is 0.
     *  @param $limit Number of elements returned max. Default NULL will take number from peferences.
     *  @return array with links and corresponding informations
     */

    public function getDataSet($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization, $gPreferences, $gProfileFields, $gDb, $gValidLogin;

        //Parameter
        if($limit == NULL)
        {
            $limit = $gPreferences['weblinks_per_page'];
        }

        //Bedingungen
        if($this->getParameter('id') > 0)
        {
            $this->getConditions = ' AND lnk_id = '. $this->getParameter('id');
        }
        if($this->getParameter('cat_id') > 0)
        {
            $this->getConditions = ' AND cat_id = '. $this->getParameter('cat_id');
        }
        if($gValidLogin == false)
        {
            // if user isn't logged in, then don't show hidden categories
            $this->getConditions .= ' AND cat_hidden = 0 ';
        }

        //Weblinks aus der DB fischen...
        $sql = 'SELECT cat.*, lnk.*
                  FROM '. TBL_CATEGORIES .' cat, '. TBL_LINKS. ' lnk
                 WHERE lnk_cat_id = cat_id
                   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   AND cat_type = \'LNK\'
                   '.$this->getConditions.'
                 ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC';
        if($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }
        if($startElement != 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }

        $weblinksStatement = $gDb->query($sql);

        //array for results
        $weblinks['recordset']  = $weblinksStatement->fetchAll();
        $weblinks['numResults'] = $weblinksStatement->rowCount();
        $weblinks['limit']      = $limit;
        $weblinks['totalCount'] = $this->getDataSetCount();

        // Push parameter to array
        $weblinks['parameter'] = $this->getParameters();

        return $weblinks;
    }

    /** Function to get total number of links filtered by current conditions.
     *  @return int Number of links.
     */
    public function getDataSetCount()
    {
        global $gCurrentOrganization;
        global $gDb;

        $sql = 'SELECT COUNT(*) AS count FROM '. TBL_LINKS. ', '. TBL_CATEGORIES .'
                WHERE lnk_cat_id = cat_id
                AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                AND cat_type = \'LNK\'
        '.$this->getConditions;
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);
        return $row['count'];
    }

    /** Returns a module specific headline
     *  @param $headline  The initiale headline of the module.
     *  @return Returns the full headline of the module
     */
    public function getHeadline($headline)
    {
        global $gDb;

        // set headline with category name
        if($this->getParameter('cat_id') > 0)
        {
            $category  = new TableCategory($gDb, $this->getParameter('cat_id'));
            $headline .= ' - '. $category->getValue('cat_name');
        }
        return $headline;
    }
}
