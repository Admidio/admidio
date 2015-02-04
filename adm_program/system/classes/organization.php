<?php 
/*****************************************************************************/
/** @class Organization
 *  @brief Handle organization data of Admidio and is connected to database table adm_organizations
 *
 *  This class creates the organization object and manages the access to the
 *  organization specific preferences of the table adm_preferences. There
 *  are also some method to read the relationship of organizations if the 
 *  database contains more then one organization.
 *  @par Examples
 *  @code // create object and read the value of the language preference
 *  $organization = new Organization($gDb, $organizationId);
 *  $preferences  = $organization->getPreferences();
 *  $language     = $preferences['system_language'];
 *  // language = 'de'@endcode
 */
/*****************************************************************************
 *
 *  Copyright    : (c) 2004 - 2013 The Admidio Team
 *  Homepage     : http://www.admidio.org
 *  License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

class Organization extends TableAccess
{
    protected $bCheckChildOrganizations;     ///< Flag will be set if the class had already search for child organizations
    protected $childOrganizations = array(); ///< Array with all child organizations of this organization
	protected $preferences = array();		 ///< Array with all preferences of this organization. Array key is the column @b prf_name and array value is the column @b prf_value.
    
	/** Constuctor that will create an object of a recordset of the table adm_organizations. 
	 *  If the id is set than the specific organization will be loaded.
	 *  @param $db           Object of the class database. This should be the default object $gDb.
	 *  @param $organization The recordset of the organization with this id will be loaded. 
	 *                       The organization can be the table id or the organization shortname. 
	 *                       If id isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $organization = '')
    {
        parent::__construct($db, TBL_ORGANIZATIONS, 'org');
		
		if(is_numeric($organization))
		{
			$this->readDataById($organization);
		}
		else
		{
			$this->readDataByColumns(array('org_shortname' => $organization));
		}
    }
    
	/** Initialize all neccessary data of this object.
	 */
    public function clear()
    {
        parent::clear();

        $this->bCheckChildOrganizations = false;
        $this->childOrganizations       = array();
		$this->preferences              = array();
    }
    
    /** Creates all necessary data for a new organization. This method can only be 
     *  called once for an organization. It will create the basic categories, lists,
     *  roles, systemmails etc.
     *  @param $userId The id of the webmaster who creates the new organization. 
     *                 This will be the first valid user of the new organization.
     */
    public function createBasicData($userId)
    {
        global $gL10n, $gProfileFields;
        
        // read id of system user from database
        $sql = 'SELECT usr_id FROM '.TBL_USERS.' 
                 WHERE usr_login_name LIKE \''.$gL10n->get('SYS_SYSTEM').'\' ';
        $this->db->query($sql);
        $row = $this->db->fetch_array();
        $systemUserId = $row['usr_id'];

        // create all systemmail texts and write them into table adm_texts
        $systemmailsTexts = array('SYSMAIL_REGISTRATION_USER' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_USER'),
                                  'SYSMAIL_REGISTRATION_WEBMASTER' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_WEBMASTER'),
                                  'SYSMAIL_REFUSE_REGISTRATION' => $gL10n->get('SYS_SYSMAIL_REFUSE_REGISTRATION'),
                                  'SYSMAIL_NEW_PASSWORD' => $gL10n->get('SYS_SYSMAIL_NEW_PASSWORD'),
                                  'SYSMAIL_ACTIVATION_LINK' => $gL10n->get('SYS_SYSMAIL_ACTIVATION_LINK'));
        $text = new TableText($this->db);

        foreach($systemmailsTexts as $key => $value)
        {
            // convert <br /> to a normal line feed
            $value = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/',chr(13).chr(10),$value);

            $text->clear();
            $text->setValue('txt_org_id', $this->getValue('org_id'));
            $text->setValue('txt_name', $key);
            $text->setValue('txt_text', $value);
            $text->save();
        }
        
        // create default category for roles, events and weblinks
        $sql = 'INSERT INTO '. TBL_CATEGORIES. ' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                               VALUES ('. $this->getValue('org_id'). ', \'ROL\', \'COMMON\', \'SYS_COMMON\', 0, 1, 1, '.$systemUserId.',\''. DATETIME_NOW.'\')';
        $this->db->query($sql);
        $categoryCommon = $this->db->insert_id();

        $sql = 'INSERT INTO '. TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                                         VALUES ('. $this->getValue('org_id').', \'ROL\', \'GROUPS\',  \'INS_GROUPS\', 0, 0, 0, 2, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'ROL\', \'COURSES\', \'INS_COURSES\', 0, 0, 0, 3, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'ROL\', \'TEAMS\',   \'INS_TEAMS\', 0, 0, 0, 4, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'LNK\', \'COMMON\',  \'SYS_COMMON\', 0, 1, 0, 1, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'LNK\', \'INTERN\',  \'INS_INTERN\', 1, 0, 0, 2, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'DAT\', \'COMMON\',  \'SYS_COMMON\', 0, 1, 0, 1, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'DAT\', \'TRAINING\',\'INS_TRAINING\', 0, 0, 0, 2, '.$systemUserId.',\''. DATETIME_NOW.'\')
                                              , ('. $this->getValue('org_id').', \'DAT\', \'COURSES\', \'INS_COURSES\', 0, 0, 0, 3, '.$systemUserId.',\''. DATETIME_NOW.'\') ';
        $this->db->query($sql);

        // create default folder for download module in database
        $sql = 'INSERT INTO '. TBL_FOLDERS. ' (fol_org_id, fol_type, fol_name, fol_path,
                                               fol_locked, fol_public, fol_timestamp)
                                        VALUES ('. $this->getValue('org_id'). ', \'DOWNLOAD\', \'download\', \'/adm_my_files\',
                                                0,1,\''.DATETIME_NOW.'\')';
        $this->db->query($sql);
        
        // now create default roles

        // Create role webmaster
        $roleWebmaster = new TableRoles($this->db);
        $roleWebmaster->setValue('rol_cat_id', $categoryCommon);
        $roleWebmaster->setValue('rol_name', $gL10n->get('SYS_WEBMASTER'));
        $roleWebmaster->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_WEBMASTER'));
        $roleWebmaster->setValue('rol_assign_roles', 1);
        $roleWebmaster->setValue('rol_approve_users', 1);
        $roleWebmaster->setValue('rol_announcements', 1);
        $roleWebmaster->setValue('rol_dates', 1);
        $roleWebmaster->setValue('rol_download', 1);
        $roleWebmaster->setValue('rol_guestbook', 1);
        $roleWebmaster->setValue('rol_guestbook_comments', 1);
        $roleWebmaster->setValue('rol_photo', 1);
        $roleWebmaster->setValue('rol_weblinks', 1);
        $roleWebmaster->setValue('rol_edit_user', 1);
        $roleWebmaster->setValue('rol_mail_to_all', 1);
        $roleWebmaster->setValue('rol_mail_this_role', 3);
        $roleWebmaster->setValue('rol_profile', 1);
        $roleWebmaster->setValue('rol_this_list_view', 1);
        $roleWebmaster->setValue('rol_all_lists_view', 1);
        $roleWebmaster->setValue('rol_webmaster', 1);
		$roleWebmaster->setValue('rol_inventory', 1);
        $roleWebmaster->save();

        // Create role member
        $roleMember = new TableRoles($this->db);
        $roleMember->setValue('rol_cat_id', $categoryCommon);
        $roleMember->setValue('rol_name', $gL10n->get('SYS_MEMBER'));
        $roleMember->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_MEMBER'));
        $roleMember->setValue('rol_mail_this_role', 2);
        $roleMember->setValue('rol_profile', 1);
        $roleMember->setValue('rol_this_list_view', 1);
        $roleMember->setValue('rol_default_registration', 1);
        $roleMember->save();

        // Create role board
        $roleManagement = new TableRoles($this->db);
        $roleManagement->setValue('rol_cat_id', $categoryCommon);
        $roleManagement->setValue('rol_name', $gL10n->get('INS_BOARD'));
        $roleManagement->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_BOARD'));
        $roleManagement->setValue('rol_announcements', 1);
        $roleManagement->setValue('rol_dates', 1);
        $roleManagement->setValue('rol_weblinks', 1);
        $roleManagement->setValue('rol_edit_user', 1);
        $roleManagement->setValue('rol_mail_to_all', 1);
        $roleManagement->setValue('rol_mail_this_role', 2);
        $roleManagement->setValue('rol_profile', 1);
        $roleManagement->setValue('rol_this_list_view', 1);
        $roleManagement->setValue('rol_all_lists_view', 1);
        $roleManagement->save();
        
        // Create membership for user in role 'Webmaster' and 'Members'
        $member = new TableMembers($this->db);
        $member->startMembership($roleWebmaster->getValue('rol_id'), $userId);
        $member->startMembership($roleMember->getValue('rol_id'),    $userId);

        // create object with current user field structure
        $gProfileFields = new ProfileFields($this->db, $this->getValue('org_id'));

        // create default list configurations
        $addressList = new ListConfiguration($this->db);
        $addressList->setValue('lst_name', $gL10n->get('INS_ADDRESS_LIST'));
        $addressList->setValue('lst_org_id', $this->getValue('org_id'));
        $addressList->setValue('lst_global', 1);
        $addressList->setValue('lst_default', 1);
        $addressList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $addressList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $addressList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $addressList->addColumn(4, $gProfileFields->getProperty('ADDRESS', 'usf_id'));
        $addressList->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $addressList->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
        $addressList->save();

        $phoneList = new ListConfiguration($this->db);
        $phoneList->setValue('lst_name', $gL10n->get('INS_PHONE_LIST'));
        $phoneList->setValue('lst_org_id', $this->getValue('org_id'));
        $phoneList->setValue('lst_global', 1);
        $phoneList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $phoneList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $phoneList->addColumn(3, $gProfileFields->getProperty('PHONE', 'usf_id'));
        $phoneList->addColumn(4, $gProfileFields->getProperty('MOBILE', 'usf_id'));
        $phoneList->addColumn(5, $gProfileFields->getProperty('EMAIL', 'usf_id'));
        $phoneList->addColumn(6, $gProfileFields->getProperty('FAX', 'usf_id'));
        $phoneList->save();

        $contactList = new ListConfiguration($this->db);
        $contactList->setValue('lst_name', $gL10n->get('SYS_CONTACT_DETAILS'));
        $contactList->setValue('lst_org_id', $this->getValue('org_id'));
        $contactList->setValue('lst_global', 1);
        $contactList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $contactList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $contactList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactList->addColumn(4, $gProfileFields->getProperty('ADDRESS', 'usf_id'));
        $contactList->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $contactList->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
        $contactList->addColumn(7, $gProfileFields->getProperty('PHONE', 'usf_id'));
        $contactList->addColumn(8, $gProfileFields->getProperty('MOBILE', 'usf_id'));
        $contactList->addColumn(9, $gProfileFields->getProperty('EMAIL', 'usf_id'));
        $contactList->save();

        $formerList = new ListConfiguration($this->db);
        $formerList->setValue('lst_name', $gL10n->get('INS_MEMBERSHIP'));
        $formerList->setValue('lst_org_id', $this->getValue('org_id'));
        $formerList->setValue('lst_global', 1);
        $formerList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'));
        $formerList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'));
        $formerList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $formerList->addColumn(4, 'mem_begin');
        $formerList->addColumn(5, 'mem_end', 'DESC');
        $formerList->save();
		
		// create object with current inventory field structure
		$gInventoryFields = new InventoryFields($this->db, $this->getValue('org_id'));
    }
    
	/** Create a comma separated list with all organization ids of children, 
	 *  parent and this organization that is prepared for use in SQL
	 *  @param $shortname If set to true then a list of all shortnames will be returned
	 *  @return Returns a string with a comma separated list of all organization 
	 *          ids that are parents or children and the own id
	 */
    public function getFamilySQL($shortname = false)
    {
        $organizationsId = '';
		$organizationsShortname = '';
        $arr_ref_orgas = $this->getOrganizationsInRelationship(true, true);

        foreach($arr_ref_orgas as $key => $value)
        {
            $organizationsShortname .= '\''.$value.'\',';
			$organizationsId .= $key.',';
        }

        $organizationsShortname .= '\''. $this->getValue('org_shortname'). '\'';
		$organizationsId .= $this->getValue('org_id');

		if($shortname)
		{
			return $organizationsShortname;
		}
		else
		{
			return $organizationsId;
		}
    }
    
	/** Read all child and parent organizations of this organization and returns
	 *  an array with them.
	 *  @param $child    If set to @b true (default) then all child organizations will be in the array
	 *  @param $parent   If set to @b true (default) then the parent organization will be in the array
	 *  @param $longname If set to @b true then the value of the array will be the @b org_longname
	 *                   otherwise it will be @b org_shortname
	 *  @return Returns an array with all child and parent organizations e.g. array('org_id' => 'org_shortname')
	 */
    public function getOrganizationsInRelationship($child = true, $parent = true, $longname = false)
    {
        $arr_child_orgas = array();
    
        $sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. '
                 WHERE ';
        if($child == true)
        {
            $sql .= ' org_org_id_parent = '. $this->getValue('org_id');
        }
        if($parent == true
        && $this->getValue('org_org_id_parent') > 0)
        {
            if($child == true)
            {
                $sql .= ' OR ';
            }
            $sql .= ' org_id = '. $this->getValue('org_org_id_parent');
        }
        $this->db->query($sql);
        
        while($row = $this->db->fetch_array())
        {
            if($longname == true)
            {
                $arr_child_orgas[$row['org_id']] = $row['org_longname'];
            }
            else
            {
                $arr_child_orgas[$row['org_id']] = $row['org_shortname'];
            }
        }
        return $arr_child_orgas;
    }
        
	/** Reads all preferences of the current organization out of the database
	 *  table adm_preferences. If the object has read the preferences than
	 *  the method will return the stored values of the object.
	 *  @return Returns an array with all preferences of this organization. 
	 *          Array key is the column @b prf_name and array value is the column @b prf_value.
	 */
    public function getPreferences()
    {
		if(count($this->preferences) == 0)
		{
			$sql    = 'SELECT * FROM '. TBL_PREFERENCES. '
						WHERE prf_org_id = '. $this->getValue('org_id');
			$result = $this->db->query($sql);

			while($prf_row = $this->db->fetch_array($result))
			{
				$this->preferences[$prf_row['prf_name']] = $prf_row['prf_value'];
			}
		}
        
        return $this->preferences;
    }
    
	/** Method checks if this organization is the parent of other organizations.
	 *  @return Return @b true if the organization has child organizations.
	 */
    public function hasChildOrganizations()
    {
        if($this->bCheckChildOrganizations == false)
        {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getOrganizationsInRelationship(true, false);
            $this->bCheckChildOrganizations = true;
        }

        if(count($this->childOrganizations) > 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
    
	/** Method checks if the organization is configured as a child
	 *  organization in the recordset.
	 *  @param $organization The @b org_shortname or @b org_id of the organization
	 *                       that should be set. If parameter isn't set than check
	 *                       the organization of this object.
	 *  @return Return @b true if the organization is a child of another organization
	 */
    public function isChildOrganization($organization = 0)
    {
        if($this->bCheckChildOrganizations == false)
        {
			// read parent organization from database
            $this->childOrganizations = $this->getOrganizationsInRelationship(true, false);
            $this->bCheckChildOrganizations = true;
        }
		
		// if no orga was set in parameter then check the orga of this object
		if($organization == 0)
		{
			$organization = $this->getValue('org_id');
		}
        
        if(is_numeric($organization))
        {
            // parameter was org_id
            $ret_code = array_key_exists($organization, $this->childOrganizations);
        }
        else
        {
            // parameter was org_shortname
            $ret_code = in_array($organization, $this->childOrganizations);
        }
        return $ret_code;
    }
    
	/** Writes all preferences of the array @b $preferences in the database 
	 *  table @b adm_preferences. The method will only insert or update 
	 *  changed preferences.
	 *  @param $preferences Array with all preferences that should be stored in 
	 *                      database. array('name_of_preference' => 'value')
	 *  @param $update      If set to @b false then no update will be done, only inserts
	 */
    public function setPreferences($preferences, $update = true)
    {
		$this->db->startTransaction();
        $db_preferences = $this->getPreferences();

        foreach($preferences as $key => $value)
        {
            if(array_key_exists($key, $db_preferences))
            {
                if($update == true
                && $value  != $db_preferences[$key])
                {
                    // Pref existiert in DB, aber Wert hat sich geaendert
                    $sql = 'UPDATE '. TBL_PREFERENCES. ' SET prf_value = \''.$value.'\'
                             WHERE prf_org_id = '. $this->getValue('org_id'). '
                               AND prf_name   = \''.$key.'\' ';
                    $this->db->query($sql);
                }
            }
            else
            {
                // Parameter existiert noch nicht in DB
                $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
                        VALUES   ('. $this->getValue('org_id'). ', \''.$key.'\', \''.$value.'\') ';
                $this->db->query($sql);
            }
        }
		$this->db->endTransaction();
    }
    
    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue   The new value that should be stored in the database field
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        // org_shortname shouldn't be edited
        if($columnName == 'org_shortname' && $this->new_record == false)
        {
            return false;
        }
        elseif($columnName == 'org_homepage' && strlen($newValue) > 0)
        {
			// Homepage darf nur gueltige Zeichen enthalten
			if (!strValidCharacters($newValue, 'url'))
			{
				return false;
			}
			// Homepage noch mit http vorbelegen
			if(strpos(admStrToLower($newValue), 'http://')  === false
			&& strpos(admStrToLower($newValue), 'https://') === false )
			{
				$newValue = 'http://'. $newValue;
			}
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
?>