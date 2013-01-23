<?php 
/******************************************************************************
 * Klasse fuer zum Abruf und Bearbeiten von Ankündigungen
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
  *****************************************************************************/
  
class ModuleWeblinks
{
    protected $getConditions;
        
    public function __construct($lnkId=0, $catId=0)
    {
        global $gValidLogin;
           
        //Bedingungen
        if($lnkId > 0)
        {
            $this->getConditions = 'AND lnk_id ='. $lnkId;
        }
        if($catId > 0)
        {
            $this->getConditions = 'AAND cat_id ='. $catId;
        }
        if($gValidLogin == false)
        {
            // if user isn't logged in, then don't show hidden categories
            $this->getConditions .= ' AND cat_hidden = 0 ';
        }       
    }
    
    //get number of available lnkouncements
    public function getWeblinksCount()
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

    public function getWeblinks($startElement=0, $limit=NULL)
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
        if($limit != 0)
        {
            $sql .= ' LIMIT '.$limit;
        }               
        if($startElement != 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }         
        
        $result = $gDb->query($sql);

        //array für Ergbenisse       
        $weblinks= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'stratElement'=>$startElement, 'totalCount'=>$this->getWeblinksCount(), 'weblinks'=>array());
       
        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {           
            $weblinks['weblinks'][] = $row; 
        }
       
        return $weblinks;
    }
}
?>