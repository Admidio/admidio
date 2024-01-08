<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/*
 * Handle organization data of Admidio and is connected to database table adm_organizations
 *
 * This class creates the organization object and manages the access to the
 * organization specific preferences of the table adm_preferences. There
 * are also some method to read the relationship of organizations if the
 * database contains more as one organization.
 *
 * **Code example**
 * ```
 * // create object and read the value of the language preference
 * $organization    = new Organization($gDb, $organizationId);
 * $settingsManager =& $organization->getSettingsManager();
 * $language        = $settingsManager->get('system_language');
 * // language = 'de'
 * ```
 ***********************************************************************************************
 */
use Ramsey\Uuid\Uuid;

class Organization extends TableAccess
{
    /**
     * @var bool Flag will be set if the class had already searched for child organizations
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
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int|string $organization The recordset of the organization with this id will be loaded.
     *                                 The organization can be the table id or the organization shortname.
     *                                 If id isn't set than an empty object of the table is created.
     * @throws AdmException
     * @throws Exception
     */
    public function __construct(Database $database, $organization = '')
    {
        parent::__construct($database, TBL_ORGANIZATIONS, 'org');

        if (is_numeric($organization)) {
            $this->readDataById($organization);
        } else {
            $this->readDataByColumns(array('org_shortname' => $organization));
        }

        if ((int)$this->getValue('org_id') > 0) {
            $this->settingsManager = new SettingsManager($database, (int)$this->getValue('org_id'));
            $this->settingsManager->resetAll();
        }
    }

    /**
     * Initialize all necessary data of this object.
     * @return void
     * @throws Exception
     */
    public function clear()
    {
        parent::clear();

        $this->bCheckChildOrganizations = false;
        $this->childOrganizations = array();
        $this->countOrganizations = 0;

        if ($this->settingsManager instanceof SettingsManager) {
            $this->settingsManager->clearAll();
        }
    }

    /**
     * Reads the number of all records of this table. In addition to the parent method
     * this method will cache the value and will return the cached value on multiple calls.
     * @return int Number of all organizations in database.
     * @throws Exception
     */
    public function countAllRecords(): int
    {
        if ($this->countOrganizations === 0) {
            $this->countOrganizations = parent::countAllRecords();
        }
        return $this->countOrganizations;
    }

    /**
     * Creates all necessary data for a new organization. This method can only be called once for an organization.
     * It will create the basic categories, lists, roles, systemmails etc.
     * @param int $userId The id of the administrator who creates the new organization.
     *                    This will be the first valid user of the new organization.
     * @throws AdmException
     * @throws Exception
     */
    public function createBasicData(int $userId)
    {
        global $gL10n, $gProfileFields;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = $this->db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        // create all systemmail texts and write them into table adm_texts
        $systemmailsTexts = array(
            'SYSMAIL_REGISTRATION_CONFIRMATION' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_CONFIRMATION'),
            'SYSMAIL_REGISTRATION_NEW' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_ADMINISTRATOR'),
            'SYSMAIL_REGISTRATION_APPROVED' => $gL10n->get('SYS_SYSMAIL_REGISTRATION_USER'),
            'SYSMAIL_REGISTRATION_REFUSED' => $gL10n->get('SYS_SYSMAIL_REFUSE_REGISTRATION'),
            'SYSMAIL_NEW_PASSWORD' => $gL10n->get('SYS_SYSMAIL_NEW_PASSWORD'),
            'SYSMAIL_PASSWORD_RESET' => $gL10n->get('SYS_SYSMAIL_PASSWORD_RESET')
        );
        $text = new TableText($this->db);

        $orgId = (int)$this->getValue('org_id');

        foreach ($systemmailsTexts as $key => $value) {
            $text->clear();
            $text->setValue('txt_org_id', $orgId);
            $text->setValue('txt_name', $key);
            $text->setValue('txt_text', $value);
            $text->save();
        }

        // create default category for roles, events and weblinks
        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, ?, \'ROL\', \'COMMON\', \'SYS_COMMON\', true, 1, ?, ?)';
        $queryParams = array($orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);
        $categoryCommon = $this->db->lastInsertId();

        $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                       (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                VALUES (?, ?, \'ROL\', \'GROUPS\',    \'INS_GROUPS\',    false, false, 2, ?, ?)
                     , (?, ?, \'ROL\', \'COURSES\',   \'INS_COURSES\',   false, false, 3, ?, ?)
                     , (?, ?, \'ROL\', \'TEAMS\',     \'INS_TEAMS\',     false, false, 4, ?, ?)
                     , (?, ?, \'ROL\', \'EVENTS\',    \'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\', false, true, 5, ?, ?)
                     , (?, ?, \'LNK\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'LNK\', \'INTERN\',    \'INS_INTERN\',    false, false, 2, ?, ?)
                     , (?, ?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', false, false, 2, ?, ?)
                     , (?, ?, \'DAT\', \'COMMON\',    \'SYS_COMMON\',    true, false, 1, ?, ?)
                     , (?, ?, \'DAT\', \'TRAINING\',  \'INS_TRAINING\',  false, false, 2, ?, ?)
                     , (?, ?, \'DAT\', \'COURSES\',   \'INS_COURSES\',   false, false, 3, ?, ?)';
        $queryParams = array(
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW,
            $orgId, Uuid::uuid4(), $systemUserId, DATETIME_NOW
        );
        $this->db->queryPrepared($sql, $queryParams);

        // if the second organization is added than also create global categories
        if ($this->countAllRecords() === 2) {
            $categoryAnnouncement = new TableCategory($this->db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new TableCategory($this->db);
            $categoryEvents->setValue('cat_type', 'EVT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new TableCategory($this->db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }

        // insert root folder name for documents & files module
        $sql = 'INSERT INTO ' . TBL_FOLDERS . '
                       (fol_org_id, fol_uuid, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_usr_id, fol_timestamp)
                VALUES (?, ?, \'DOCUMENTS\', ?, ?, false, true, ?, ?)';
        $queryParams = array($orgId, Uuid::uuid4(), TableFolder::getRootFolderName('documents', $this->getValue('org_shortname')), FOLDER_DATA, $systemUserId, DATETIME_NOW);
        $this->db->queryPrepared($sql, $queryParams);

        // now create default roles

        // Create role administrator
        $roleAdministrator = new TableRoles($this->db);
        $roleAdministrator->saveChangesWithoutRights();
        $roleAdministrator->setValue('rol_cat_id', $categoryCommon, false);
        $roleAdministrator->setValue('rol_name', $gL10n->get('SYS_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_ADMINISTRATOR'));
        $roleAdministrator->setValue('rol_assign_roles', 1);
        $roleAdministrator->setValue('rol_approve_users', 1);
        $roleAdministrator->setValue('rol_announcements', 1);
        $roleAdministrator->setValue('rol_events', 1);
        $roleAdministrator->setValue('rol_documents_files', 1);
        $roleAdministrator->setValue('rol_guestbook', 1);
        $roleAdministrator->setValue('rol_guestbook_comments', 1);
        $roleAdministrator->setValue('rol_photo', 1);
        $roleAdministrator->setValue('rol_weblinks', 1);
        $roleAdministrator->setValue('rol_edit_user', 1);
        $roleAdministrator->setValue('rol_mail_to_all', 1);
        $roleAdministrator->setValue('rol_mail_this_role', 3);
        $roleAdministrator->setValue('rol_profile', 1);
        $roleAdministrator->setValue('rol_all_lists_view', 1);
        $roleAdministrator->setValue('rol_administrator', 1);
        $roleAdministrator->save();

        // Create role member
        $roleMember = new TableRoles($this->db);
        $roleMember->saveChangesWithoutRights();
        $roleMember->setValue('rol_cat_id', $categoryCommon, false);
        $roleMember->setValue('rol_name', $gL10n->get('SYS_MEMBER'));
        $roleMember->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_MEMBER'));
        $roleMember->setValue('rol_mail_this_role', 2);
        $roleMember->setValue('rol_profile', 1);
        $roleMember->setValue('rol_default_registration', 1);
        $roleMember->save();

        // Create role board
        $roleManagement = new TableRoles($this->db);
        $roleManagement->saveChangesWithoutRights();
        $roleManagement->setValue('rol_cat_id', $categoryCommon, false);
        $roleManagement->setValue('rol_name', $gL10n->get('INS_BOARD'));
        $roleManagement->setValue('rol_description', $gL10n->get('INS_DESCRIPTION_BOARD'));
        $roleManagement->setValue('rol_announcements', 1);
        $roleManagement->setValue('rol_events', 1);
        $roleManagement->setValue('rol_weblinks', 1);
        $roleManagement->setValue('rol_edit_user', 1);
        $roleManagement->setValue('rol_mail_to_all', 1);
        $roleManagement->setValue('rol_mail_this_role', 2);
        $roleManagement->setValue('rol_profile', 1);
        $roleManagement->setValue('rol_all_lists_view', 1);
        $roleManagement->save();

        // Create membership for user in role 'Administrator' and 'Members'
        $membershipAdministrator = new TableMembers($this->db);
        $membershipAdministrator->startMembership($roleAdministrator->getValue('rol_id'), $userId, false);
        $membershipMember = new TableMembers($this->db);
        $membershipMember->startMembership($roleMember->getValue('rol_id'), $userId, false);

        // create object with current user field structure
        $gProfileFields = new ProfileFields($this->db, $orgId);

        // create default list configurations
        $addressList = new ListConfiguration($this->db);
        $addressList->setValue('lst_name', $gL10n->get('INS_ADDRESS_LIST'));
        $addressList->setValue('lst_org_id', $orgId);
        $addressList->setValue('lst_global', 1);
        $addressList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $addressList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $addressList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('STREET', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $addressList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $addressList->save();

        $phoneList = new ListConfiguration($this->db);
        $phoneList->setValue('lst_name', $gL10n->get('INS_PHONE_LIST'));
        $phoneList->setValue('lst_org_id', $orgId);
        $phoneList->setValue('lst_global', 1);
        $phoneList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $phoneList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $phoneList->addColumn((int)$gProfileFields->getProperty('PHONE', 'usf_id'));
        $phoneList->addColumn((int)$gProfileFields->getProperty('MOBILE', 'usf_id'));
        $phoneList->addColumn((int)$gProfileFields->getProperty('EMAIL', 'usf_id'));
        $phoneList->save();

        $contactDetailsList = new ListConfiguration($this->db);
        $contactDetailsList->setValue('lst_name', $gL10n->get('SYS_CONTACT_DETAILS'));
        $contactDetailsList->setValue('lst_org_id', $orgId);
        $contactDetailsList->setValue('lst_global', 1);
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('STREET', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('POSTCODE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('PHONE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('MOBILE', 'usf_id'));
        $contactDetailsList->addColumn((int)$gProfileFields->getProperty('EMAIL', 'usf_id'));
        $contactDetailsList->save();

        $formerList = new ListConfiguration($this->db);
        $formerList->setValue('lst_name', $gL10n->get('INS_MEMBERSHIP'));
        $formerList->setValue('lst_org_id', $orgId);
        $formerList->setValue('lst_global', 1);
        $formerList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $formerList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $formerList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $formerList->addColumn('mem_begin');
        $formerList->addColumn('mem_end');
        $formerList->save();

        $participantList = new ListConfiguration($this->db);
        $participantList->setValue('lst_name', $gL10n->get('SYS_PARTICIPANTS'));
        $participantList->setValue('lst_org_id', $orgId);
        $participantList->setValue('lst_global', 1);
        $participantList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $participantList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $participantList->addColumn('mem_approved');
        $participantList->addColumn('mem_comment');
        $participantList->addColumn('mem_count_guests');
        $participantList->save();

        $contactsList = new ListConfiguration($this->db);
        $contactsList->setValue('lst_name', $gL10n->get('SYS_CONTACTS'));
        $contactsList->setValue('lst_org_id', $orgId);
        $contactsList->setValue('lst_global', 1);
        $contactsList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
        $contactsList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
        $contactsList->addColumn('usr_login_name');
        $contactsList->addColumn((int)$gProfileFields->getProperty('GENDER', 'usf_id'));
        $contactsList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
        $contactsList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
        $contactsList->addColumn('usr_timestamp_change');
        $contactsList->save();

        // create default category report configuration
        $categoryReportColumns = 'p' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('STREET', 'usf_id') . ',' .
            'p' . $gProfileFields->getProperty('CITY', 'usf_id') . ',' .
            'r' . $roleAdministrator->getValue('rol_id') . ',' .
            'r' . $roleManagement->getValue('rol_id') . ',' .
            'r' . $roleMember->getValue('rol_id');
        $categoryReport = new TableAccess($this->db, TBL_CATEGORY_REPORT, 'crt');
        $categoryReport->setValue('crt_org_id', $orgId);
        $categoryReport->setValue('crt_name', $gL10n->get('SYS_GENERAL_ROLE_ASSIGNMENT'));
        $categoryReport->setValue('crt_col_fields', $categoryReportColumns);
        $categoryReport->setValue('crt_number_col', 0);
        $categoryReport->save();

        // set new default configuration to the module settings
        $organizationSettings = new SettingsManager($this->db, $orgId);
        $organizationSettings->getAll();
        $organizationSettings->set('system_notifications_role', $roleAdministrator->getValue('rol_uuid'));
        $organizationSettings->set('groups_roles_default_configuration', $addressList->getValue('lst_id'));
        $organizationSettings->set('events_list_configuration', $participantList->getValue('lst_id'));
        $organizationSettings->set('contacts_list_configuration', $contactsList->getValue('lst_id'));
        $organizationSettings->set('category_report_default_configuration', $categoryReport->getValue('crt_id'));
        if ($this->countAllRecords() > 1) {
            $organizationSettings->set('system_organization_select', true);
        }
    }

    /**
     * Create an organization object depending on an optional organization shortname string. If an
     * organization shortname is set than this organization will be read otherwise the organization
     * with the minimum ID will be read.
     * @param Database $db Object of the class Database. This should be the default global object **$gDb**.
     * @param string $organization The organization shortname. If this is set than this organization
     *                                 will be read otherwise the organization with the minimum ID.
     * @return Organization Returns an organization object.
     * @throws AdmException
     * @throws Exception
     */
    public static function createDefaultOrganizationObject(Database $db, string $organization = ''): Organization
    {
        if($organization !== '') {
            $organizationObject = new Organization($db, $organization);
        } else {
            $sql = 'SELECT MIN(org_id) as organization_id FROM ' . TBL_ORGANIZATIONS;
            $pdoStatement = $db->queryPrepared($sql, array(), false);
            $row = $pdoStatement->fetch();
            $organizationObject = new Organization($db, (int) $row['organization_id']);
        }

        return $organizationObject;
    }

    /**
     * @return array<int,string> Returns an array with all child organizations
     * @throws Exception
     */
    protected function getChildOrganizations(): array
    {
        if (!$this->bCheckChildOrganizations) {
            // Daten erst einmal aus DB einlesen
            $this->childOrganizations = $this->getOrganizationsInRelationship(true, false);
            $this->bCheckChildOrganizations = true;
        }

        return $this->childOrganizations;
    }

    /**
     * Create a comma separated list with all organization ids of children,
     * parent and this organization that is prepared for use in SQL
     * @param bool $shortname If set to true then a list of all shortnames will be returned
     * @return string Returns a string with a comma separated list of all organization
     *                ids that are parents or children and the own id
     * @throws Exception
     */
    public function getFamilySQL(bool $shortname = false): string
    {
        $organizations = $this->getOrganizationsInRelationship();

        if ($shortname) {
            /**
             * @param string $value
             * @return string
             */
            function addQuotationMarks(string $value): string
            {
                return '\''.$value.'\'';
            }

            $organizationShortnames = array_values($organizations);
            $organizationShortnames[] = $this->getValue('org_shortname');
            $organizationShortnames = array_map('addQuotationMarks', $organizationShortnames);
            return implode(',', $organizationShortnames);
        }

        $organizationIds = array_keys($organizations);
        $organizationIds[] = (int) $this->getValue('org_id');
        return implode(',', $organizationIds);
    }

    /**
     * Read all child and parent organizations of this organization and returns an array with them.
     * @param bool $child If set to **true** (default) then all child organizations will be in the array
     * @param bool $parent If set to **true** (default) then the parent organization will be in the array
     * @param bool $longname If set to **true** then the value of the array will be the **org_longname**
     *                       otherwise it will be **org_shortname**
     * @return array<int,string> Returns an array with all child and parent organizations e.g. array('org_id' => 'org_shortname')
     * @throws Exception
     */
    public function getOrganizationsInRelationship(bool $child = true, bool $parent = true, bool $longname = false): array
    {
        $sqlWhere = array();
        $queryParams = array();

        if ($child) {
            $sqlWhere[] = 'org_org_id_parent = ?';
            $queryParams[] = (int) $this->getValue('org_id');
        }
        $orgParentId = (int) $this->getValue('org_org_id_parent');
        if ($parent && $orgParentId > 0) {
            $sqlWhere[] = 'org_id = ?';
            $queryParams[] = $orgParentId;
        }

        $sql = 'SELECT org_id, org_longname, org_shortname
                  FROM '.TBL_ORGANIZATIONS.'
                 WHERE '.implode(' OR ', $sqlWhere);
        $pdoStatement = $this->db->queryPrepared($sql, $queryParams);

        $childOrganizations = array();
        while ($row = $pdoStatement->fetch()) {
            $orgId = (int) $row['org_id'];
            if ($longname) {
                $childOrganizations[$orgId] = $row['org_longname'];
            } else {
                $childOrganizations[$orgId] = $row['org_shortname'];
            }
        }
        return $childOrganizations;
    }

    /**
     * @return SettingsManager
     */
    public function &getSettingsManager(): SettingsManager
    {
        if (!$this->settingsManager instanceof SettingsManager) {
            $this->settingsManager = new SettingsManager($this->db, (int) $this->getValue('org_id'));
            $this->settingsManager->resetAll();
        }

        return $this->settingsManager;
    }

    /**
     * Method checks if the organization is configured as a child organization in the recordset.
     * @return bool Return **true** if the organization is a child of another organization
     */
    public function isChildOrganization(): bool
    {
        return $this->getValue('org_org_id_parent') > 0;
    }

    /**
     * Method checks if the organization is configured as a parent organization in the recordset.
     * @return bool Return **true** if the organization is the parent of at least one other organization
     * @throws Exception
     */
    public function isParentOrganization(): bool
    {
        return count($this->getChildOrganizations()) > 0;
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method **save** to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed $newValue The new value that should be stored in the database field
     * @param bool $checkValue The value will be checked if it's valid. If set to **false** than the value will not be checked.
     * @return bool Returns **true** if the value is stored in the current object and **false** if a check failed
     * @throws AdmException
     */
    public function setValue(string $columnName, $newValue, bool $checkValue = true): bool
    {
        if ($checkValue) {
            // org_shortname shouldn't be edited
            if ($columnName === 'org_shortname' && !$this->newRecord) {
                return false;
            } elseif ($columnName === 'org_homepage' && $newValue !== '') {
                $newValue = admFuncCheckUrl($newValue);

                if ($newValue === false) {
                    return false;
                }
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * @return array<string,mixed>
     */
    public function getDbColumns(): array
    {
        return $this->dbColumns;
    }
}
