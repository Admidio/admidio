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
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
        
        // define constant for headline
        define('HEADLINE', $gL10n->get('LNK_WEBLINKS'));
        
        // get parent instance with all parameters from $_GET Array
        parent::__construct();
           
        //Bedingungen
        if($this->id > 0)
        {
            $this->getConditions = ' AND lnk_id = '. $this->id;
        }
        if($this->catId > 0)
        {
            $this->getConditions = ' AND cat_id = '. $this->catId;
        }
        if($gValidLogin == false)
        {
            // if user isn't logged in, then don't show hidden categories
            $this->getConditions .= ' AND cat_hidden = 0 ';
        }       
    }
    
    /** Function returns a set of links with corresponding informations
     *  @param $startElement Start element of result. First (and default) is 0.
     *  @param $limit Number of elements returned max. Default NULL will take number from peferences.
     *  @return array with links and corresponding informations
     */
    
    public function getDataSet($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gPreferences;
        global $gProfileFields;
        global $gDb;

        //Parameter
        if($limit == NULL)
        {
            $limit = $gPreferences['weblinks_per_page'];
        }

        //Ankuendigungen aus der DB fischen...
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

        $result = $gDb->query($sql);

        //array für Ergbenisse
        $weblinks= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'totalCount'=>$this->getDataSetCount());

        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {
            $weblinks['recordset'][] = $row;
        }

        // Push parameter to array
        $weblinks['parameter'] = $this->getParameter();

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
        '.$this->getConditions.'';
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);             
        return $row['count'];
    }
}
?>