<?php 
/*****************************************************************************/
/** @class ModuleLists
 *  @brief Class manages lists viewable for user
 *
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
  
class ModuleLists
{
    private $roleStatus;
    private $memberStatus;
    private $category;
    
    /** creates an new ModuleLists object
     *  @param int $category sets Category-ID for role selection. Default: 0 (all roles)
     */              
    public function __construct($category=0)
    {   
       $this->setCategory($category);
       $this->setMemberStatus();
       $this->setRoleStatus();
    }
    
    /** Sets the role status for role selection
     *  @param int status 1=active(default) 0=inactive 
     */
    public function setRoleStatus($status = 1)
    {
        if($status == 0)
        {
            $this->roleStatus = 0;
        }
        else
        {
            $this->roleStatus = 1;    
        }
    }
    
    /** Sets the status of role members to be shown
     *  @param string status active(default), inactive, both
     */
    public function setMemberStatus($status='active')
    {
        switch ($status)
        {
            case 'active':
            default:
                $this->memberStatus = 'active';
                break;
            case 'inactive':
                $this->memberStatus = 'inactive';
                break;
            case 'both':
                $this->memberStatus = 'both';
                break;
        }
    }
    
    /** Evaluates memberStatus an returns appropriate SQL conditions
     * @return string SQL for member status
     */
    private function getMemberStatusSql()
    {
        switch ($this->memberStatus)
        {
            case 'active':
            default:    
                $sql = ' AND mem_begin <= \''.DATE_NOW.'\'
                         AND mem_end   >= \''.DATE_NOW.'\' ';
                break;
            case 'inactive':
                $sql = ' AND mem_end < \''.DATE_NOW.'\' ';
                break;
            case 'both':
                $sql ='';
                break;
        }            
        return $sql;
    }
    
    /** Sets category for role selection
     *  @param int $category sets Category-ID for role selection. Default: 0 (all roles)
     */
    public function setCategory($catId = 0)
    {
        $this->category = $catId;    
    }    
    
    /** returns SQL condition
     *  @return SQL condition for category id
     * 
     */
    private function getCategorySql()
    {
        if($this->category > 0)
        {
           return ' AND cat_id  = '.$this->category;   
        }
        return '';
    }
    
    /** assembles SQL roles visible for current user
     *  @return string SQL condition visible for current user
     */
    private function getVisibleRolesSql()
    {
        global $gCurrentUser;
        // create a list with all rol_ids that the user is allowed to view
        $visibleRoles = implode(',', $gCurrentUser->getAllVisibleRoles());
        if(strlen($visibleRoles) > 0)
        {
            return ' AND rol_id IN ('.$visibleRoles.')';
        }
        else
        {
            return ' AND rol_id = 0 ';
        }
    }
    
    /** Function returns a set of lists with corresponding informations
     *  @param $startElement Start element of result. First (and default) is 0.
     *  @param $limit Number of elements returned max. Default NULL will take number from peferences.
     *  @return array with list and corresponding informations
     */
    public function getLists($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gPreferences;
        global $gDb;
        global $gValidLogin;
        
         //Parameter        
        if($limit == NULL)
        {
            // Roles per page
            $limit = $gPreferences['lists_roles_per_page'];
        }
        
        //assemble conditions
        $sql_conditions = $this->getCategorySql().$this->getVisibleRolesSql();
        
        //provoke empty result for not logged in users
        if($gValidLogin == false)
        {
            $sql_conditions .= ' AND cat_hidden = 0 ';
        }
               
        $sql = 'SELECT rol.*, cat.*, 
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id '.$this->getMemberStatusSql().' AND mem_leader = 0) as num_members,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id '.$this->getMemberStatusSql().' AND mem_leader = 1) as num_leader,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id AND mem_end < \''. DATE_NOW.'\') as num_former
          FROM '. TBL_ROLES. ' rol, '. TBL_CATEGORIES. ' cat
         WHERE rol_valid   = '.$this->roleStatus.'
           AND rol_visible = 1
           AND rol_cat_id = cat_id 
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
               '.$sql_conditions.'
         ORDER BY cat_sequence, rol_name';

        // If is there a limit then specify one
        if($limit > 0)
        {
            $sql .= ' LIMIT '.$limit;
        }               
        if($startElement != 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }

        $result = $gDb->query($sql);
        
        $lists = array();
        while($row = $gDb->fetch_array($result))
        {
            $lists[]=$row;
        }        
        return $lists;
    }
    
    /** Function to get total number of lists limited by current conditions.
     *  @return int Number of lists. 
     */
    public function countLists()
    {
        global $gCurrentOrganization;
        global $gDb;
        global $gValidLogin;
        
        //assemble conditions
        $sql_conditions = $this->getCategorySql().$this->getVisibleRolesSql();
        //provoke empty result for not logged in users
        if($gValidLogin == false)
        {
            $sql_conditions= ' AND cat_hidden = 0 ';
        }
        
        $sql = 'SELECT COUNT(*) AS numrows 
          FROM '. TBL_ROLES. ' rol, '. TBL_CATEGORIES. ' cat
         WHERE rol_valid   = '.$this->roleStatus.'
           AND rol_visible = 1
           AND rol_cat_id = cat_id 
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
               '.$sql_conditions;

        $result = $gDb->query($sql);
        $row = $gDb->fetch_array($result);
        
        return $row['numrows'];
    }
    
    /** Function to get list configurations accessible by current user 
     *  @return array with accessible list configurations
     */
    public function getListConfigurations()
    {
        global $gCurrentOrganization;
        global $gCurrentUser;
        global $gDb;
        
        $sql = 'SELECT lst_id, lst_name, lst_global FROM '. TBL_LISTS. '
                 WHERE lst_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                   AND (  lst_usr_id = '. $gCurrentUser->getValue('usr_id'). '
                       OR lst_global = 1)
                   AND lst_name IS NOT NULL
                 ORDER BY lst_global ASC, lst_name ASC';
        $result = $gDb->query($sql);
        $configurations=array();
        while($row = $gDb->fetch_array($result))
        {
            $configurations[]=$row;
        }        
        return $configurations;
    }           
}
?>