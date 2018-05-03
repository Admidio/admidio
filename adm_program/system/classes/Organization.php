<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Handle organization data of Admidio and is connected to database table adm_organizations
 *
 * This class creates the organization object and manages the access to the
 * organization specific preferences of the table adm_preferences. There
 * are also some method to read the relationship of organizations if the
 * database contains more then one organization.
 *
 * **Code example:**
 * ```
 * // create object and read the value of the language preference
 * $organization    = new Organization($gDb, $organizationId);
 * $settingsManager =& $organization->getSettingsManager();
 * $language        = $settingsManager->get('system_language');
 * // language = 'de'
 * ```
 ***********************************************************************************************
 */
class Organization extends TableAccess
{
    /**
     * @var bool Flag will be set if the class had already search for child organizations
     */
    protected $bCheckChildOrganizations = false;
    /**
     * @var array<int,string> Array with all child organizations of this organization
     */
    protected $childOrganizations = array();
    /**
     * @var SettingsManager Manager for organization preferences
     */
    protected $settingsManager;
    /**
     * @var int Number of all organizations in database
     */
    protected $countOrganizations = 0;

    /**
     * Constructor that will create an object of a recordset of the table adm_organizations.
     * If the id is set than the specific organization will be loaded.
     * @param Database   $database     Object of the class Database. This should be the default global object **$gDb**.
     * @param int|string $organization The recordset of the organization with this id will be loaded.
     *                                 The organization can be the table id or the organization shortname.
     *                                 If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $organization = '')
    {
        parent::__construct($database, TBL_ORGANIZATIONS, 'org');

        if(is_numeric($organization))
        {
            $this->readDataById($organization);
        }
        else
        {
            $this->readDataByColumns(array('org_shortname' => $organization));
        }

        if((int) $this->getValue('org_id') > 0)
        {
            $this->settingsManager = new SettingsManager($database, (int) $this->getValue('org_id'));
        }
    }

    /**
     * @return SettingsManager
     */
    public function &getSettingsManager()
    {
        if(!$this->settingsManager instanceof SettingsManager)
        {
            $this->settingsManager = new SettingsManager($this->db, (int) $this->getValue('org_id'));
        }

        return $this->settingsManager;
    }

    /**
     * Initialize all necessary data of this object.
     * @return void
     */
    public function clear()
    {
        parent::clear();

        $this->bCheckChildOrganizations = false;
        $this->childOrganizations       = array();
        $this->countOrganizations       = 0;

        if ($this->settingsManager instanceof SettingsManager)
        {
            $this->settingsManager->clearAll();
        }
    }

    /**
     * Reads the number of all records of this table. In addition to the parent method
     * this method will cache the value and will return the cached value on multiple calls.
     * @return int Number of all organizations in database.
     */
    public function countAllRecords()
    {
        if($this->countOrganizations === 0)
        {
            $this->countOrganizations = parent::countAllRecords();
        }
        return $this->countOrganizations;
    }

    /**
     * Creates all necessary data for a new organization. This method can only be called once for an organization.
     * It will create the basic categories, lists, roles, systemmails etc.
     * @param int $userId The id of the administrator who creates the new organization.
     *                    This will be the first valid user of the new organization.
     */
    public function createBasicData($userId)
    {
        global $gL10n, $gProfileFields;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = $this->db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int) $systemUserStatement->fetchColumn();

        // create all systemmail texts and write them into table adm_texts
        $systemmailsTexts = array(
            'SYSMAIL_REGISTRATION_USER'      => $gL10n->get('SYS_SYSMAIL_REGISTRATION_USER'),
            'SYSMAIL_REGISTRATION_WEBMASTER' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_ADMINISTRATOR'),
            'SYSMAIL_REFUSE_REGISTRATION'    => $gL10n->get('SYS_SYSMAIL_REFUSE_REGISTRATION'),
            'SYSMAIL_NEW_PASSWORD'           => $gL10n->get('SYS_SYSMAIL_NEW_PASSWORD'),
            'SYSMAIL_ACTIVATION_LINK'        => $gL10n->get('SYS_SYSMAIL_ACTIVATION_LINK')
        );
        $text = new TableText($this->db);

        $orgId = $this->getValue('org_id');

        foreach($systemmailsTexts as $key => $value)
        {
            // convert <br /> to a normal line feed
            $value = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', chr(13).chr(10), $value);

            $text->clear();
            $text->setValue('txt_org_id', $orgId);
            $text->setValue('txt_name', $key);
            $text->setValue('txt_text', $value);
            $text->save();
        }

        // create default category for roles, events and weblinks
        $sql = 'INSERT INTO '.TBL_CATEGORIES.'
                       (cat_org_id, cat_type, cat_name_intern, cat_name, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, \'ROL\', \'COMMON\', \'SYS_COMMON\', 1, 1, ?, ?)';
        $queryParams = array($orgId, $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);
        $categoryCommon = $this->db->lastInsertId();

        $sql = 'INSERT INTO '.TBL_CATEGORIES.'
                       (cat_org_id, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, \'ROL\', \'GROUPS\',    \'INS_GROUPS\',    0, 0, 2, ?, ?)
                     , (?, \'ROL\', \'COURSES\',   \'INS_COURSES\',   0, 0, 3, ?, ?)
                     , (?, \'ROL\', \'TEAMS\',     \'INS_TEAMS\',     0, 0, 4, ?, ?)
                     , (?, \'ROL\', \'EVENTS\',    \'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\', 0, 1, 5, ?, ?)
                     , (?, \'LNK\', \'COMMON\',    \'SYS_COMMON\',    1, 0, 1, ?, ?)
                     , (?, \'LNK\', \'INTERN\',    \'INS_INTERN\',    0, 0, 2, ?, ?)
                     , (?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    1, 0, 1, ?, ?)
                     , (?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', 0, 0, 2, ?, ?)
                     , (?, \'DAT\', \'COMMON\',    \'SYS_COMMON\',    1, 0, 1, ?, ?)
                     , (?, \'DAT\', \'TRAINING\',  \'INS_TRAINING\',  0, 0, 2, ?, ?)
                     , (?, \'DAT\', \'COURSES\',   \'INS_COURSES\',   0, 0, 3, ?, ?)';
        $queryParams = array(
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW,
            $orgId, $systemUserId, DATETIME_NOW
        );
        $this->db->queryPrepared($sql, $queryParams);

        // if the second organization is added than also create global categories
        if($this->countAllRecords() === 2)
        {
            $categoryAnnouncement = new TableCategory($this->db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new TableCategory($this->db);
            $categoryEvents->setValue('cat_type', 'DAT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new TableCategory($this->db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }

        // insert root folder name for download module
        $sql = 'INSERT INTO '.TBL_FOLDERS.'
                       (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_usr_id, fol_timestamp)
                VALUES (?, \'DOWNLOAD\', ?, ?, 0, 1, ?, ?)';
        $queryParams = array($orgId, TableFolder::getRootFolderName(), FOLDER_DATA, $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);

        // now create default roles

        // Create role administrator
        $roleAdministrator = new TableRoles($this->db);
        $roleAdministrator->setValue('rol_cat_id', $categoryCommon, false);
        $roleAdministrator->setValue('rol_name', $gL10n->get('SYS_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_assign_roles', 1);
        $roleAdministrator->setValue('rol_approve_users', 1);
        $roleAdministrator->setValue('rol_announcements', 1);
        $roleAdministrator->setValue('rol_dates', 1);
        $roleAdministrator->setValue('rol_download', 1);
        $roleAdministrator->setValue('rol_guestbook', 1);
        $roleAdministrator->setValue('rol_guestbook_comments', 1);
        $roleAdministrator->setValue('rol_photo', 1);
        $roleAdministrator->setValue('rol_weblinks', 1);
        $roleAdministrator->setValue('rol_edit_user', 1);
        $roleAdministrator->setValue('rol_mail_to_all', 1);
        $roleAdministrator->setValue('rol_mail_this_role', 3);
        $roleAdministrator->setValue('rol_profile', 1);
        $roleAdministrator->setValue('rol_this_list_view', 1);
        $roleAdministrator->setValue('rol_all_lists_view', 1);
        $roleAdministrator->setValue('rol_administrator', 1);
        $roleAdministrator->save();

        // Create role member
        $roleMember = new TableRoles($this->db);
        $roleMember->setValue('rol_cat_id', $categoryCommon, false);
        $roleMember->setValue('rol_name', $gL10n->get('SYS_MEMBER'));
        $roleMember->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_MEMBER'));
        $roleMember->setValue('rol_mail_this_role', 2);
        $roleMember->setValue('rol_profile', 1);
        $roleMember->setValue('rol_this_list_view', 1);
        $roleMember->setValue('rol_default_registration', 1);
        $roleMember->save();

        // Create role board
        $roleManagement = new TableRoles($this->db);
        $roleManagement->setValue('rol_cat_id', $categoryCommon, false);
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

        // Create membership for user in role 'Administrator' and 'Members'
        $member = new TableMembers($this->db);
        $member->startMembership((int) $roleAdministrator->getValue('rol_id'), $userId);
        $member->startMembership((int) $roleMember->getValue('rol_id'), $userId);

        // create object with current user field structure
        $gProfileFields = new ProfileFields($this->db, $orgId);

        // create default list configurations
        $addressList = new ListConfiguration($this->db);
        $addressList->setValue('lst_name', $gL10n->get('INS_ADDRESS_LIST'));
        $addressList->setValue('lst_org_id', $orgId);
        $addressList->setValue('lst_global', 1);
        $addressList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $addressList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $addressList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $addressList->addColumn(4, $gProfileFields->getProperty('STREET', 'usf_id'));
        $addressList->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $addressList->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
        $addressList->save();

        // set addresslist to default configuration
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value  = ? -- $addressList->getValue(\'lst_id\')
                 WHERE prf_org_id = ? -- $orgId
                   AND prf_name   = \'lists_default_configuration\'';
        $this->db->queryPrepared($sql, array($addressList->getValue('lst_id'), $orgId));

        $phoneList = new ListConfiguration($this->db);
        $phoneList->setValue('lst_name', $gL10n->get('INS_PHONE_LIST'));
        $phoneList->setValue('lst_org_id', $orgId);
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
        $contactList->setValue('lst_org_id', $orgId);
        $contactList->setValue('lst_global', 1);
        $contactList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $contactList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $contactList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactList->addColumn(4, $gProfileFields->getProperty('STREET', 'usf_id'));
        $contactList->addColumn(5, $gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $contactList->addColumn(6, $gProfileFields->getProperty('CITY', 'usf_id'));
        $contactList->addColumn(7, $gProfileFields->getProperty('PHONE', 'usf_id'));
        $contactList->addColumn(8, $gProfileFields->getProperty('MOBILE', 'usf_id'));
        $contactList->addColumn(9, $gProfileFields->getProperty('EMAIL', 'usf_id'));
        $contactList->save();

        $formerList = new ListConfiguration($this->db);
        $formerList->setValue('lst_name', $gL10n->get('INS_MEMBERSHIP'));
        $formerList->setValue('lst_org_id', $orgId);
        $formerList->setValue('lst_global', 1);
        $formerList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $formerList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $formerList->addColumn(3, $gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $formerList->addColumn(4, 'mem_begin');
        $formerList->addColumn(5, 'mem_end');
        $formerList->save();

        $participantList = new ListConfiguration($this->db);
        $participantList->setValue('lst_name', $gL10n->get('SYS_PARTICIPANTS'));
        $participantList->setValue('lst_org_id', $orgId);
        $participantList->setValue('lst_global', 1);
        $participantList->addColumn(1, $gProfileFields->getProperty('LAST_NAME', 'usf_id'), 'ASC');
        $participantList->addColumn(2, $gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 'ASC');
        $participantList->addColumn(3, 'mem_approved');
        $participantList->addColumn(4, 'mem_comment');
        $participantList->addColumn(5, 'mem_count_guests');
        $participantList->save();

        // set participant list to default configuration in date module settings
        $sql = 'UPDATE '.TBL_PREFERENCES.'
                   SET prf_value = ? -- $participantList->getValue(\'lst_id\')
                 WHERE prf_name   = \'dates_default_list_configuration\'
                   AND prf_org_id = ? -- $orgId';
        $this->db->queryPrepared($sql, array($participantList->getValue('lst_id'), $orgId));
    }

    /**
     * Create a comma separated list with all organization ids of children,
     * parent and this organization that is prepared for use in SQL
     * @param bool $shortname If set to true then a list of all shortnames will be returned
     * @return string Returns a string with a comma separated list of all organization
     *                ids that are parents or children and the own id
     */
    public function getFamilySQL($shortname = false)
    {
        $organizations = $this->getOrganizationsInRelationship();

        if($shortname)
        {
            /**
             * @param string $value
             * @return string
             */
            function addQuotationMarks($value)
            {
                return '\''.$value.'\'';
            }

            $organizationShortnames = array_values($organizations);
            $organizationShortnames[] = $this->getValue('org_shortname');
            $organizationShortnames = array_map('addQuotationMarks', $organizationShortnames);
            return implode(',', $organizationShortnames);
        }

        $organizationIds = array_keys($organizations);
        $organizationIds[] = $this->getValue('org_id');
        return implode(',', $organizationIds);
    }

    /**
     * Read all child and parent organizations of this organization and returns an array with them.
     * @param bool $child    If set to **true** (default) then all child organizations will be in the array
     * @param bool $parent   If set to **true** (default) then the parent organization will be in the array
     * @param bool $longname If set to **true** then the value of the array will be the **org_longname**
     *                       otherwise it will be **org_shortname**
     * @return array<int,string> Returns an array with all child and parent organizations e.g. array('org_id' => 'org_shortname')
     */
    public function getOrganizationsInRelationship($child = true, $parent = true, $longname = false)
    {
        $sqlWhere = array();
        $queryParams = array();

        if ($child)
        {
            $sqlWhere[] = 'org_org_id_parent = ?';
            $queryParams[] = $this->getValue('org_id');
        }
        $orgParentId = (int) $this->getValue('org_org_id_parent');
        if ($parent && $orgParentId > 0)
        {
            $sqlWhere[] = 'org_id = ?';
            $queryParams[] = $orgParentId;
        }

        $sql = 'SELECT org_id, org_longname, org_shortname
                  FROM '.TBL_ORGANIZATIONS.'
                 WHERE '.implode(' OR ', $sqlWhere);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $childOrganizations = array();
        while ($row = $pdoStatement->fetch())
        {
            $orgId = (int) $row['org_id'];
            if ($longname)
            {
                $childOrganizations[$orgId] = $row['org_longname'];
            }
            else
            {
                $childOrganizations[$orgId] = $row['org_shortname'];
            }
        }
        return $childOrganizations;
    }

    /**
     * @return array<int,string> Returns an array with all child organizations
     */
    protected function getChildOrganizations()
    {
        if(!$this->bCheckChildOrganizations)
        {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getOrganizationsInRelationship(true, false);
            $this->bCheckChildOrganizations = true;
        }

        return $this->childOrganizations;
    }

    /**
     * Method checks if the organization is configured as a child organization in the recordset.
     * @return bool Return **true** if the organization is a child of another organization
     */
    public function isChildOrganization()
    {
        return $this->getValue('org_org_id_parent') > 0;
    }

    /**
     * Method checks if the organization is configured as a parent organization in the recordset.
     * @return bool Return **true** if the organization is the parent of a least one other organization
     */
    public function isParentOrganization()
    {
        return count($this->getChildOrganizations()) > 0;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if($checkValue)
        {
            // org_shortname shouldn't be edited
            if($columnName === 'org_shortname' && !$this->newRecord)
            {
                return false;
            }
            elseif($columnName === 'org_homepage' && $newValue !== '')
            {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false)
                {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * @return array<string,mixed>
     */
    public function getDbColumns()
    {
        return $this->dbColumns;
    }
}
