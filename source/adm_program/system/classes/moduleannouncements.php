<?php
/*****************************************************************************/
/** @class ModuleAnnouncements
 *  @brief This class reads announcement recordsets from database
 *
 *  This class reads all available recordsets from table announcements
 *  and returns an Array with results, recordsets and validated parameters from $_GET Array.
 *  @par Returned Array
 *  @code
 *  Array
 *  (
 *      [numResults] => 3
 *      [limit] => 10
 *      [totalCount] => 3
 *      [recordset] => Array
 *          (
 *              [0] => Array
 *                  (
 *                      [0] => 3
 *                      [ann_id] => 3
 *                      [1] => DEMO
 *                      [ann_org_shortname] => DEMO
 *                      [2] => 1
 *                      [ann_global] => 1
 *                      [3] => Willkommen im Demobereich
 *                      [ann_headline] => Willkommen im Demobereich
 *                      [4] => <p>In diesem Bereich kannst du mit Admidio herumspielen und schauen, ....</p>
 *                      [ann_description] => <p>In diesem Bereich kannst du mit Admidio herumspielen und schauen, ....</p>
 *                      [5] => 1
 *                      [ann_usr_id_create] => 1
 *                      [6] => 2013-07-18 00:00:00
 *                      [ann_timestamp_create] => 2013-07-18 00:00:00
 *                      [7] =>
 *                      [ann_usr_id_change] =>
 *                      [8] =>
 *                      [ann_timestamp_change] =>
 *                      [9] => Paul Webmaster
 *                      [create_name] => Paul Webmaster
 *                      [10] =>
 *                      [change_name] =>
 *                  )
 *          )
 *      [parameter] => Array
 *          (
 *              [active_role] => 1
 *              [calendar-selection] => 1
 *              [cat_id] => 0
 *              [category-selection] => 0,
 *              [date] => ''
 *              [daterange] => Array
 *                  (
 *                      [english] => Array
 *                          (
 *                              [start_date] => 2013-09-16 // current date
 *                              [end_date] => 9999-12-31
 *                          )
 *
 *                      [system] => Array
 *                          (
 *                              [start_date] => 16.09.2013 // current date
 *                              [end_date] => 31.12.9999
 *                          )
 *                  )
 *              [headline] => Ankündigungen
 *              [id] => 0
 *              [mode] => Default
 *              [order] => 'ASC'
 *              [startelement] => 0
 *              [view_mode] => Default
 *          )
 *  )
 *  @endcode
 */ 
/******************************************************************************
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ******************************************************************************/
  
class ModuleAnnouncements extends Modules
{    
    protected $getConditions;   ///< String with SQL condition
    
    /**
     *  Constructor setting default module headline as constant
     *  and initializing all parameters  
     */    
    public function __construct()
    {   
        global $gL10n;
        // define constant for headline
        define('HEADLINE', $gL10n->get('ANN_ANNOUNCEMENTS'));
        // get parent instance with all parameters from $_GET Array
        parent::__construct();
        // set SQL condition
        $this->setCondition($this->id, $this->date);           
    }
    
    /**
     * Get number of available announcements
     * @Return Returns the total count and push it in the array
     */
    public function getDataSetCount()
    {     
        global $gCurrentOrganization;
        global $gDb;
        
        $sql = 'SELECT COUNT(1) as count 
                  FROM '. TBL_ANNOUNCEMENTS. '
                 WHERE (  ann_org_shortname = \''. $gCurrentOrganization->getValue('org_shortname'). '\'
                    OR (   ann_global   = 1
                   AND ann_org_shortname IN ('.$gCurrentOrganization->getFamilySQL(true).') ))
                       '.$this->getConditions.'';
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);             
        return $row['count'];
    }

    /**
     * Get all records and push it to the array 
     * @return Returns the Array with results, recordsets and validated parameters from $_GET Array
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
            $announcementsPerPage = $gPreferences['announcements_per_page'];
        }
        
        if($gPreferences['system_show_create_edit'] == 1)
        {
            // show firstname and lastname of create and last change user
            $additionalFields = '
                cre_firstname.usd_value || \' \' || cre_surname.usd_value as create_name,
                cha_firstname.usd_value || \' \' || cha_surname.usd_value as change_name ';
            $additionalTables = '
              LEFT JOIN '. TBL_USER_DATA .' cre_surname
                ON cre_surname.usd_usr_id = ann_usr_id_create
               AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
              LEFT JOIN '. TBL_USER_DATA .' cre_firstname
                ON cre_firstname.usd_usr_id = ann_usr_id_create
               AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
              LEFT JOIN '. TBL_USER_DATA .' cha_surname
                ON cha_surname.usd_usr_id = ann_usr_id_change
               AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
              LEFT JOIN '. TBL_USER_DATA .' cha_firstname
                ON cha_firstname.usd_usr_id = ann_usr_id_change
               AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
        }
        else
        {
            // show username of create and last change user
            $additionalFields = ' cre_username.usr_login_name as create_name,
                                  cha_username.usr_login_name as change_name ';
            $additionalTables = '
              LEFT JOIN '. TBL_USERS .' cre_username
                ON cre_username.usr_id = ann_usr_id_create
              LEFT JOIN '. TBL_USERS .' cha_username
                ON cha_username.usr_id = ann_usr_id_change ';
        }
                               
        //read announcements from database
        $sql = 'SELECT ann.*, '.$additionalFields.'
                  FROM '. TBL_ANNOUNCEMENTS. ' ann
                       '.$additionalTables.'
                 WHERE (  ann_org_shortname = \''. $gCurrentOrganization->getValue('org_shortname'). '\'
                    OR (   ann_global   = 1
                   AND ann_org_shortname IN ('.$gCurrentOrganization->getFamilySQL(true).') ))
                       '.$this->getConditions.' 
                 ORDER BY ann_timestamp_create DESC';

        // Check if limit was set
        if($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }               
        if($startElement != 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }  

        $result = $gDb->query($sql);

        //array for results       
        $announcements= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'totalCount'=>$this->getDataSetCount());
        
        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {       
            $announcements['recordset'][] = $row; 
        }
       
        // Push parameter to array
        $announcements['parameter'] = $this->getParameter();
        return $announcements;
    }
    
    /**
     * Set SQL condition for ID and required date 
     */
    private function setCondition($annId=0, $date='')
    {
        //Bedingungen
        if($annId > 0)
        {
            $this->getConditions = 'AND ann_id ='. $annId;
        }
        // Search announcements to date 
        elseif(strlen($date) > 0)
        {
            $this->getConditions = 'AND DATE_FORMAT(ann_timestamp_create, \'%Y-%m-%d\') = \''.$this->date.'\'';
        }
    }
}      
?>