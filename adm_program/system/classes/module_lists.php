<?php 
/*****************************************************************************/
/** @class ModuleLists
 *  @brief Class manages lists viewable for user
 *
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2012 The Admidio Team
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
       $this->set_category($category);
       $this->set_member_status();
       $this->set_role_status();
    }
    
    /** Sets the role status for role selection
     *  @param int status 1=active(default) 0=inactive 
     */
    public function set_role_status($status = 1)
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
    public function set_member_status($status='active')
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
    private function get_member_status_sql()
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
    public function set_category($catId = 0)
    {
        $this->category = $catId;    
    }    
    
    /** returns SQL condition
     *  @return SQL condition for category id
     * 
     */
    private function get_category_sql()
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
    private function get_visible_roles_sql()
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
    public function get_lists($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gPreferences;
        global $gDb;
        global $gValidLogin;
        
         //Parameter        
        if($limit == NULL)
        {
            $limit = $gPreferences['lists_roles_per_page'];
        }
        
        //assemble conditions
        $sql_conditions = $this->get_category_sql().$this->get_visible_roles_sql();
        //provoke empty result for not logged in users
        if($gValidLogin == false)
        {
            $sql_conditions= ' AND cat_hidden = 0 ';
        }
               
        $sql = 'SELECT rol.*, cat.*, 
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id '.$this->get_member_status_sql().' AND mem_leader = 0) as num_members,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id '.$this->get_member_status_sql().' AND mem_leader = 1) as num_leader,
               (SELECT COUNT(*) FROM '. TBL_MEMBERS. ' mem 
                 WHERE mem.mem_rol_id = rol.rol_id AND mem_end < \''. DATE_NOW.'\') as num_former
          FROM '. TBL_ROLES. ' rol, '. TBL_CATEGORIES. ' cat
         WHERE rol_valid   = '.$this->roleStatus.'
           AND rol_visible = 1
           AND rol_cat_id = cat_id 
           AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
               OR cat_org_id IS NULL )
               '.$sql_conditions.'
         ORDER BY cat_sequence, rol_name
         LIMIT '.$limit.' OFFSET '.$startElement;

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
    public function count_lists()
    {
        global $gCurrentOrganization;
        global $gDb;
        global $gValidLogin;
        
        //assemble conditions
        $sql_conditions = $this->get_category_sql().$this->get_visible_roles_sql();
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
    public function get_list_configurations()
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