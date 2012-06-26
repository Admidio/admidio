<?php 
/******************************************************************************
 * Klasse fuer zum Abruf und Bearbeiten von Ankündigungen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
  *****************************************************************************/
  
class Announcements
{
    protected $getConditions;
        
    public function __construct($annId=0, $date='')
    {   
        //Bedingungen
        if($annId > 0)
        {
            $this->getConditions = 'AND ann_id ='. $annId;
        }
        // Ankuendigungen an einem Tag suchen
        elseif(strlen($date) > 0)
        {
            $this->getConditions = ' AND DATE_FORMAT(ann_timestamp_create, \'%Y-%m-%d\') = \''.$date.'\'';        
        }             
    }
    
    //get number of available announcements
    public function getAnnouncementsCount()
    {     
        global $gCurrentOrganization;
        global $gDb;
        
        $sql = 'SELECT COUNT(1) as count 
                  FROM '. TBL_ANNOUNCEMENTS. '
                 WHERE (  ann_org_shortname = \''. $gCurrentOrganization->getValue('org_shortname'). '\'
                    OR (   ann_global   = 1
                   AND ann_org_shortname IN ('.$gCurrentOrganization->getFamilySQL().') ))
                       '.$conditions.'';
        $result = $gDb->query($sql);
        $row    = $gDb->fetch_array($result);             
        return $row['count'];
    }

    public function getAnnouncements($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gPreferences;
        global $gProfileFields;
        global $gDb;
        
        //Parameter        
        if($limit == NULL)
        {
            $limit = $gPreferences['announcements_per_page'];
        }    
                               
        //Ankuendigungen aus der DB fischen...
        $sql = 'SELECT ann.*, 
                       cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
                       cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
                  FROM '. TBL_ANNOUNCEMENTS. ' ann
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
                   AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                 WHERE (  ann_org_shortname = \''. $gCurrentOrganization->getValue('org_shortname'). '\'
                    OR (   ann_global   = 1
                   AND ann_org_shortname IN ('.$gCurrentOrganization->getFamilySQL().') ))
                       '.$this->getConditions.' 
                 ORDER BY ann_timestamp_create DESC
                 LIMIT '.$limit.' OFFSET '.$startElement;
        $result = $gDb->query($sql);

        //array für Ergbenisse       
        $announcements= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'stratElement'=>$startElement, 'totalCount'=>$this->getAnnouncementsCount(), 'anouncements'=>array());
       
        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {           
            $announcements['announcements'][] = $row; 
        }
       
        return $announcements;
    }
}
?>