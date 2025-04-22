<?php
namespace Admidio\Roles\Entity;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Roles\ValueObject\ConditionParser;
use Admidio\Users\Entity\User;
use DateTime;
use ModuleEvents;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Roles\Entity\ListColumns;

/**
 * @brief Class manages the list configuration
 *
 * This class creates a list configuration object. With this object it's possible
 * to manage the configuration in the database. You can easily create new lists,
 * add new columns or remove columns. The object will only list columns of the configuration
 * which the current user is allowed to view.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ListConfiguration extends Entity
{
    /**
     * @var array<int,Entity> Array with all columns of the current list
     */
    protected array $columns = array();
    /**
     * @var array<int,Entity> array with all column names of the sql statement that belong to the select clause
     */
    protected array $columnsSqlNames = array();
    /**
     * @var array<int,string> Array with the usr_id as key and the first name, last name as values
     */
    protected array $arrUserNames = array();
    /**
     * @var boolean Flag if only the name of the user (first name, last name) should be shown and all other fields
     * should be removed.
     */
    protected bool $showOnlyNames = false;
    /**
     * @var boolean Flag if leaders should be shown. If there is more than one role this flag is set to **false**.
     * should be removed.
     */
    protected bool $showLeaders = false;


    /**
     * Constructor that will create an object to handle the configuration of lists.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $listID The ID of the recordset that should be loaded. If id isn't set than an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, $listID = 0)
    {
        parent::__construct($database, TBL_LISTS, 'lst', $listID);

        if ($listID > 0) {
            $this->readColumns();
        }
    }

    /**
     * Add new column to column array. The number of the column will be the maximum number of the current
     * array plus one. The special field usr_uuid could only be added by users with the right to edit all users.
     * @param int|string $field Usf-Id of a profile field or the name of a special field.
     * @param int $number Optional the number of the column. This is useful if the list already exists
     *                           and maybe the profile field changed the position within the list.
     * @param string $sort Optional the value **ASC** for ascending and **DESC** for descending.
     * @param string $filter Optional a filter for the values of that column.
     * @return bool Returns true if the field was added to the column list.
     * @throws Exception
     */
    public function addColumn($field, int $number = 0, string $sort = '', string $filter = ''): bool
    {
        global $gCurrentUser;

        if ($number === 0) {
            // current number of the new column
            $number = count($this->columns) + 1;
        }

        // can join max. 61 tables
        // Passed parameters must be set carefully
        if (strlen($field) === 0 || $field === 0 || count($this->columns) >= 57) {
            return false;
        }

        // uuid could only be added by an administrator
        if ($field === 'usr_uuid' && !$gCurrentUser->isAdministratorUsers()) {
            return false;
        }

        // If column doesn't exist create object
        if (!array_key_exists($number, $this->columns)) {
            $this->columns[$number] = new ListColumns($this->db);
            $this->columns[$number]->setValue('lsc_lst_id', (int)$this->getValue('lst_id'));
        }

        // Assign content of column
        if (is_numeric($field)) {
            $this->columns[$number]->setValue('lsc_usf_id', $field);
            $this->columns[$number]->setValue('lsc_special_field', '');
        } else {
            $this->columns[$number]->setValue('lsc_usf_id', '');
            $this->columns[$number]->setValue('lsc_special_field', $field);
        }

        $this->columns[$number]->setValue('lsc_number', $number);
        $this->columns[$number]->setValue('lsc_sort', $sort);
        $this->columns[$number]->setValue('lsc_filter', $filter);

        return true;
    }

    public function clear()
    {
        $this->columns = array();
        $this->columnsSqlNames = array();

        parent::clear();
    }

    /**
     * Return count of columns
     * @return int
     */
    public function countColumns(): int
    {
        return count($this->columns);
    }

    /**
     * Convert the content of the column independence of the output format.
     * Therefore, the method will check which datatype the column has and which format the
     * output should have.
     * @param int $columnNumber Number of the column for which the content should be converted. The column number starts with 1.
     * @param string $format The following formats are possible 'html', 'print', 'csv', 'xlsx', 'ods' or 'pdf'
     * @param string $content The content that should be converted.
     * @param string $userUuid Uuid of the user for which the content should be converted. This is not the login user.
     * @param bool $setSortValue If set to **true** a special sort value for checkboxes will be set, when using server side processing set to **false**.
     * @return string Returns the converted content.
     * @throws Exception
     */
    public function convertColumnContentForOutput(int $columnNumber, string $format, string $content, string $userUuid, bool $setSortValue = true)
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager;

        $column = $this->getColumnObject($columnNumber);
        $usfId = 0;

        if ($column->getValue('lsc_usf_id') > 0) {
            // check if customs field and remember
            $usfId = (int)$column->getValue('lsc_usf_id');
        }

        // in some cases the content must have a special output format

        if ($usfId > 0 && $usfId === (int)$gProfileFields->getProperty('COUNTRY', 'usf_id')) {
            $content = $gL10n->getCountryName($content);
        } elseif ($column->getValue('lsc_special_field') === 'usr_photo') {
            // show user photo
            if (in_array($format, array('html', 'print'), true)) {
                $content = '<img src="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile_photo_show.php', array('user_uuid' => $userUuid)) . '"
                    style="max-width: 200px; width: 100%; object-fit: cover;" alt="' . $gL10n->get('SYS_PROFILE_PHOTO') . '" />';
            }
            if (in_array($format, array('csv', 'xlsx', 'ods', 'pdf'), true) && $content != null) {
                $content = $gL10n->get('SYS_PROFILE_PHOTO');
            }
        } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX') {
            if (in_array($format, array('csv', 'xlsx', 'ods', 'pdf'), true)) {
                if ($content == 1) {
                    $content = $gL10n->get('SYS_YES');
                } else {
                    $content = $gL10n->get('SYS_NO');
                }
            } elseif ($content != 1) {
                $content = 0;
            }
        } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'DATE'
            || $column->getValue('lsc_special_field') === 'mem_begin'
            || $column->getValue('lsc_special_field') === 'mem_end') {
            if (strlen($content) > 0) {
                // date must be formatted
                $date = DateTime::createFromFormat('Y-m-d', $content);
                $content = $date->format($gSettingsManager->getString('system_date'));
            }
        } elseif (in_array($format, array('csv', 'xlsx', 'ods', 'pdf'), true)
            && ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'DROPDOWN'
                || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'RADIO_BUTTON')) {
            if (strlen($content) > 0) {
                // show selected text of option field or combobox
                $arrListValues = $gProfileFields->getPropertyById($usfId, 'usf_value_list', 'text');
                $content = $arrListValues[$content];
            }
        } elseif (in_array($column->getValue('lsc_special_field'), array('usr_timestamp_create', 'usr_timestamp_change', 'mem_timestamp_change'))) {
            if (strlen($content) > 0) {
                // date must be formatted
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $content);
                $content = $date->format($gSettingsManager->getString('system_date') . ' ' . $gSettingsManager->getString('system_time'));
            }
        } elseif ($column->getValue('lsc_special_field') === 'mem_approved') {
            // Assign Integer to Language strings
            switch ((int)$content) {
                case ModuleEvents::MEMBER_APPROVAL_STATE_INVITED:
                    $text = $gL10n->get('SYS_EVENT_PARTICIPATION_INVITED');
                    $htmlText = '<i class="bi bi-calendar2-check-fill admidio-icon-chain"></i>' . $text;
                    $buttonClass = '';
                    break;
                case ModuleEvents::MEMBER_APPROVAL_STATE_ATTEND:
                    $text = $gL10n->get('SYS_EVENT_PARTICIPATION_ATTEND');
                    $htmlText = '<i class="bi bi-check-circle-fill admidio-icon-chain"></i>' . $text;
                    $buttonClass = 'admidio-event-approval-state-attend';
                    break;
                case ModuleEvents::MEMBER_APPROVAL_STATE_TENTATIVE:
                    $text = $gL10n->get('SYS_EVENT_PARTICIPATION_TENTATIVE');
                    $htmlText = '<i class="bi bi-question-circle-fill admidio-icon-chain"></i>' . $text;
                    $buttonClass = 'admidio-event-approval-state-tentative';
                    break;
                case ModuleEvents::MEMBER_APPROVAL_STATE_REFUSED:
                    $text = $gL10n->get('SYS_EVENT_PARTICIPATION_CANCELED');
                    $htmlText = '<i class="bi bi-x-circle-fill admidio-icon-chain"></i>' . $text;
                    $buttonClass = 'admidio-event-approval-state-cancel';
                    break;
                default:
                    $text = '';
                    $htmlText = '';
                    $buttonClass = '';
            }

            if (in_array($format, array('csv', 'xlsx', 'ods'))) {
                $content = $text;
            } else {
                if ($format === 'html') {
                    $content = '<span class="' . $buttonClass . '">' . $htmlText . '</span>';
                } else {
                    $content = $htmlText;
                }
            }
        } elseif (in_array($column->getValue('lsc_special_field'), array('usr_usr_id_create', 'usr_usr_id_change', 'mem_usr_id_change')) && (int)$content) {
            // Get User Information and store information in array
            $userId = (int)$content;

            if (array_key_exists($userId, $this->arrUserNames)) {
                $content = $this->arrUserNames[$userId];
            } else {
                $user = new User($gDb, $gProfileFields, $userId);
                $content = $user->getValue('LAST_NAME') . ', ' . $user->getValue('FIRST_NAME');
                $this->arrUserNames[$userId] = $content;
            }
        }

        // format value for csv export
        if (in_array($format, array('csv', 'xlsx', 'ods'))) {
            // replace tab and line feed
            $content = preg_replace("/\t/", "\\t", $content);
            // replace special chars in Excel so no app or function could be implicit executed
            $outputContent = preg_replace("/^[@=]/", "#", $content);
        } // pdf should show only text and not much html content
        elseif ($format === 'pdf') {
            $outputContent = $content;
        } // create output in html layout
        else {
            // firstname and lastname get a link to the profile
            if ($format === 'html'
                && ($usfId === (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id')
                    || $usfId === (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'))) {
                $htmlValue = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $userUuid);
                $outputContent = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userUuid)) . '">' . $htmlValue . '</a>';
            } else {
                // within print mode no links should be set
                if ($format === 'print'
                    && ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'EMAIL'
                        || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'PHONE'
                        || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'URL')) {
                    $outputContent = $content;
                } else {
                    // checkbox must set a sorting value
                    if ($setSortValue && $gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX') {
                        $outputContent = array('value' => $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $userUuid), 'order' => $content);
                    } else {
                        $outputContent = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $content, $userUuid);
                    }
                }
            }
        }

        return $outputContent;
    }

    /**
     * Deletes the selected list with all associated fields.
     * After that the class will be initialize.
     * @return bool **true** if no error occurred
     * @throws Exception SYS_ERROR_DELETE_DEFAULT_LIST
     */
    public function delete(): bool
    {
        global $gSettingsManager, $gL10n;

        $lstId = (int) $this->getValue('lst_id');

        // if this list is the default configuration of a module than it couldn't be deleted
        if ($lstId === $gSettingsManager->getInt('groups_roles_default_configuration')) {
            throw new Exception('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_GROUPS_ROLES')));
        }
        if ($lstId === $gSettingsManager->getInt('events_list_configuration')) {
            throw new Exception('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_EVENTS')));
        }
        if ($lstId === $gSettingsManager->getInt('contacts_list_configuration')) {
            throw new Exception('SYS_ERROR_DELETE_DEFAULT_LIST', array($this->getValue('lst_name'), $gL10n->get('SYS_CONTACTS')));
        }

        $this->db->startTransaction();

        // Delete all columns of the list
        $sql = 'SELECT lsc_id
                  FROM '.TBL_LIST_COLUMNS.'
                 WHERE lsc_lst_id = ? -- $lstId';
        $listColumnsStatement = $this->db->queryPrepared($sql, array($lstId));

        while ($lscId = $listColumnsStatement->fetchColumn()) {
            $listColumn = new ListColumns($this->db, $lscId);
            $listColumn->delete();
        }

        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * Delete pointed columns out of configuration
     * @param int $number
     * @param bool $all Define all columns to be deleted
     * @return bool
     * @throws Exception
     */
    public function deleteColumn(int $number, bool $all = false): bool
    {
        if ($number > $this->countColumns()) {
            return false;
        }

        if ($all) {
            // Delete all columns starting with number
            for ($newColumnNumber = $this->countColumns(); $newColumnNumber >= $number; --$newColumnNumber) {
                $this->columns[$newColumnNumber]->delete();
                array_pop($this->columns);
            }
        } else {
            // only one column is deleted and following are going one step up
            for ($newColumnNumber = $number, $max = $this->countColumns(); $newColumnNumber < $max; ++$newColumnNumber) {
                $newColumn = $this->columns[$newColumnNumber];
                $oldColumn = $this->columns[$newColumnNumber + 1];
                $newColumn->setValue('lsc_usf_id', $oldColumn->getValue('lsc_usf_id'));
                $newColumn->setValue('lsc_special_field', $oldColumn->getValue('lsc_special_field'));
                $newColumn->setValue('lsc_sort', $oldColumn->getValue('lsc_sort'));
                $newColumn->setValue('lsc_filter', $oldColumn->getValue('lsc_filter'));
                $newColumn->save();
            }
            $this->columns[$newColumnNumber]->delete();
            array_pop($this->columns);
        }

        return true;
    }

    /**
     * Returns an array with all alignments (center, left or right) from all columns of this list.
     * @return array Array with alignments from all columns of this list configuration.
     * @throws Exception
     */
    public function getColumnAlignments(): array
    {
        global $gProfileFields;

        $arrColumnAlignments = array();

        // Array to assign names to tables
        $arrSpecialColumnNames = array(
            'usr_login_name' => 'left',
            'usr_photo' => 'left',
            'usr_usr_id_create' => 'left',
            'usr_timestamp_create' => 'left',
            'usr_usr_id_change' => 'left',
            'usr_timestamp_change' => 'left',
            'usr_uuid' => 'left',
            'mem_begin' => 'left',
            'mem_end' => 'left',
            'mem_leader' => 'left',
            'mem_approved' => 'left',
            'mem_usr_id_change' => 'left',
            'mem_timestamp_change' => 'left',
            'mem_comment' => 'left',
            'mem_count_guests' => 'right'
        );

        for ($columnNumber = 1, $iMax = $this->countColumns(); $columnNumber <= $iMax; ++$columnNumber) {
            $column = $this->getColumnObject($columnNumber);

            // Find name of the field
            if ($column->getValue('lsc_usf_id') > 0) {
                $usfId = (int)$column->getValue('lsc_usf_id');

                if ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'CHECKBOX'
                    || $gProfileFields->getPropertyById($usfId, 'usf_name_intern') === 'GENDER') {
                    $arrColumnAlignments[] = 'center';
                } elseif ($gProfileFields->getPropertyById($usfId, 'usf_type') === 'NUMBER'
                    || $gProfileFields->getPropertyById($usfId, 'usf_type') === 'DECIMAL') {
                    $arrColumnAlignments[] = 'right';
                } else {
                    $arrColumnAlignments[] = 'left';
                }
            } else {
                $arrColumnAlignments[] = $arrSpecialColumnNames[$column->getValue('lsc_special_field')];
            }
        } // End-For

        return $arrColumnAlignments;
    }

    /**
     * Returns an array with all column names of this list. The names within the array are translated
     * to the current language.
     * @return array Array with all column names of this list configuration.
     * @throws Exception
     */
    public function getColumnNames(): array
    {
        global $gL10n, $gProfileFields;

        $arrColumnNames = array();

        // Array to assign names to tables
        $arrSpecialColumnNames = array(
            'usr_login_name' => $gL10n->get('SYS_USERNAME'),
            'usr_photo' => $gL10n->get('SYS_PHOTO'),
            'usr_usr_id_create' => $gL10n->get('SYS_CREATED_BY'),
            'usr_timestamp_create' => $gL10n->get('SYS_CREATED_AT'),
            'usr_usr_id_change' => $gL10n->get('SYS_CHANGED_BY'),
            'usr_timestamp_change' => $gL10n->get('SYS_CHANGED_AT'),
            'usr_uuid' => $gL10n->get('SYS_UNIQUE_ID'),
            'mem_begin' => $gL10n->get('SYS_START'),
            'mem_end' => $gL10n->get('SYS_END'),
            'mem_leader' => $gL10n->get('SYS_LEADERS'),
            'mem_approved' => $gL10n->get('SYS_PARTICIPATION_STATUS'),
            'mem_usr_id_change' => $gL10n->get('SYS_CHANGED_BY'),
            'mem_timestamp_change' => $gL10n->get('SYS_CHANGED_AT'),
            'mem_comment' => $gL10n->get('SYS_COMMENT'),
            'mem_count_guests' => $gL10n->get('SYS_SEAT_AMOUNT')
        );

        for ($columnNumber = 1, $iMax = $this->countColumns(); $columnNumber <= $iMax; ++$columnNumber) {
            $column = $this->getColumnObject($columnNumber);

            // Find name of the field
            if ($column->getValue('lsc_usf_id') > 0) {
                $arrColumnNames[] = $gProfileFields->getPropertyById((int)$column->getValue('lsc_usf_id'), 'usf_name');
            } else {
                $arrColumnNames[] = $arrSpecialColumnNames[$column->getValue('lsc_special_field')];
            }
        } // End-For

        return $arrColumnNames;
    }

    /**
     * Returns an array with all column names of the sql statement that belong to the select clause.
     * This will be the internal profile field name e.g. **LAST_NAME** or the db column name
     * of the special field e.g. **mem_begin**
     * @return array Array with all column names of this sql select clause.
     * @throws Exception
     */
    public function getColumnNamesSql(): array
    {
        global $gProfileFields;

        if (count($this->columnsSqlNames) === 0) {
            foreach ($this->columns as $listColumn) {
                if ((int)$listColumn->getValue('lsc_usf_id') > 0) {
                    // get internal profile field name
                    $this->columnsSqlNames[] = $gProfileFields->getPropertyById($listColumn->getValue('lsc_usf_id'), 'usf_name_intern');
                } else {
                    // Special fields like usr_photo, mem_begin ...
                    $this->columnsSqlNames[] = $listColumn->getValue('lsc_special_field');
                }
            }
        }

        return $this->columnsSqlNames;
    }

    /**
     * Returns the column object with the corresponding number.
     * The numbers will start with 1 and end with the count of all columns.
     * If that column doesn't exist the method try to repair the
     * column list. If that doesn't help then **null** will be returned.
     * @param int $number The internal number of the column. The column number start with 1.
     *                    This will be the position of the column in the list.
     * @return Entity|null Returns a Entity object of the database table **adm_list_columns**.
     * @throws Exception
     */
    public function getColumnObject(int $number): ?Entity
    {
        if (array_key_exists($number, $this->columns)) {
            return $this->columns[$number];
        }

        // column not found, then try to repair list
        $this->repair();
        if (array_key_exists($number, $this->columns)) {
            return $this->columns[$number];
        }

        return null;
    }

    /**
     * Returns an array with all list columns and a search condition for each column. Especially the null value
     * will be replaced with a default value. This array can then be used to add it to the main sql statement.
     * @return array<int,string> Returns an array with all list columns and a search condition for each column.
     * @throws Exception
     */
    public function getSearchConditions(): array
    {
        global $gProfileFields;

        $arrSearchConditions = array();

        foreach ($this->columns as $listColumn) {
            $lscUsfId = (int)$listColumn->getValue('lsc_usf_id');

            // custom profile field
            if ($lscUsfId > 0) {
                switch ($gProfileFields->getPropertyById($lscUsfId, 'usf_type')) {
                    case 'CHECKBOX':
                        break;

                    case 'DROPDOWN': // fallthrough
                    case 'RADIO_BUTTON':
                        // create "case when" with all values of the profile field value list
                        $condition = ' CASE ';
                        $arrListValues = $gProfileFields->getPropertyById($lscUsfId, 'usf_value_list', 'text');

                        foreach ($arrListValues as $key => $value) {
                            $condition .= ' WHEN ' . $gProfileFields->getPropertyById($lscUsfId, 'usf_name_intern') . ' = \'' . $key . '\' THEN \'' . $value . '\' ';
                        }

                        $condition .= ' ELSE \' \' END ';
                        $arrSearchConditions[] = $condition;
                        break;

                    case 'NUMBER': // fallthrough
                    case 'DECIMAL':
                        $arrSearchConditions[] = 'COALESCE(' . $gProfileFields->getPropertyById($lscUsfId, 'usf_name_intern') . ', 0)';
                        break;

                    case 'DATE':
                        $arrSearchConditions[] = 'COALESCE(' . $gProfileFields->getPropertyById($lscUsfId, 'usf_name_intern') . ', \'1900-02-01\')';
                        break;

                    default:
                        $arrSearchConditions[] = 'LOWER(COALESCE(' . $gProfileFields->getPropertyById($lscUsfId, 'usf_name_intern') . ', \'\'))';
                }
            } else {
                switch ($listColumn->getValue('lsc_special_field')) {
                    case 'mem_begin': // fallthrough
                    case 'mem_end': // fallthrough
                    case 'usr_timestamp_create': // fallthrough
                    case 'usr_timestamp_change': // fallthrough
                    case 'mem_timestamp_change':
                        $arrSearchConditions[] = 'COALESCE(' . $listColumn->getValue('lsc_special_field') . ', \'1900-02-01\')';
                        break;

                    default:
                        $arrSearchConditions[] = 'COALESCE(' . $listColumn->getValue('lsc_special_field') . ', \'\')';
                        break;
                }
            }
        }

        return $arrSearchConditions;
    }

    /**
     * Prepare SQL of the current list configuration. Therefore, all roles of the array and there users will be selected
     * and joined with the columns of the list configuration. The time period of the membership will be considered and
     * could be influenced with parameters. There is also a possibility to join users of a relationship and hide special
     * columns of event roles. Each profile field of the select list will have their internal profile field name as column
     * name. The special field will still have their database column name.
     * @param array $options (optional) An array with the following possible entries:
     *                                 - **showAllMembersThisOrga** : Set to true all users with an active membership
     *                                   to at least one role of the current organization will be shown.
     *                                   This setting could be combined with **showFormerMembers** or **showRelationTypes**.
     *                                 - **showAllMembersDatabase** : Set to true all users of the database will be shown
     *                                   independent of the membership to roles or organizations
     *                                 - **showRolesMembers** : An array with all roles ids could be set and only members
     *                                   of this roles will be shown.
     *                                   This setting could be combined with **showFormerMembers** or **showRelationTypes**.
     *                                 - **showFormerMembers** : Set to true if roles members or members of the organization
     *                                   should be shown and also former members should be listed
     *                                 - **showRelationTypes** : An array with relation types. The sql will be expanded with
     *                                   all users who are in such a relationship to the selected role users.
     *                                 - **showUserUUID** : If set to true the first column of the SQL will be the usr_uuid.
     *                                 - **showLeaderFlag** : If set to true the first columns of the SQL will be
     *                                   the flag if a user is a leader in the role or not.
     *                                 - **useConditions** : false - Don't add additional conditions to the SQL
     *                                                       true  - Conditions will be added as stored in the settings
     *                                 - **useOrderBy** : false - Don't add the sorting to the SQL
     *                                                 true  - Sorting is added as stored in the settings
     *                                 - **startDate** : The start date if memberships that should be considered. The time period of
     *                                   the membership must be at least one day after this date.
     *                                 - **endDate** : The end date if memberships that should be considered.The time period of
     *                                   the membership must be at least one day before this date.
     * @return string Returns a valid sql that represents all users with the columns of the list configuration.
     * @throws Exception
     */
    public function getSQL(array $options = array()): string
    {
        global $gL10n, $gProfileFields;

        // create array with all options
        $optionsDefault = array(
            'showAllMembersThisOrga' => false,
            'showAllMembersDatabase' => false,
            'showRolesMembers' => array(),
            'showFormerMembers' => false,
            'showUserUUID' => false,
            'showLeaderFlag' => false,
            'showRelationTypes' => array(),
            'useConditions' => true,
            'useOrderBy' => true,
            'startDate' => null,
            'endDate' => null
        );
        $optionsAll = array_replace($optionsDefault, $options);

        $arrSqlColumns = array();
        $arrSqlColumnNames = array('usr_uuid');
        $arrOrderByColumns = array();
        $sqlColumnNames = '';
        $sqlIdColumns = '';
        $sqlMemLeader = '';
        $sqlOrderBys = '';
        $sqlJoin = '';
        $sqlWhere = '';
        $columnNumber = 0;
        $this->showLeaders = $optionsAll['showLeaderFlag'];

        // if there is more than 1 role, don't show the leaders
        if (count($optionsAll['showRolesMembers']) > 1) {
            $this->showLeaders = false;
        }

        foreach ($this->columns as $listColumn) {
            $lscUsfId = (int)$listColumn->getValue('lsc_usf_id');
            $userFieldType = $gProfileFields->getPropertyById($lscUsfId, 'usf_type');
            $columnNumber++;

            $tableAlias = '';
            if ($lscUsfId > 0) {
                // dynamic profile field
                $tableAlias = 'row' . $listColumn->getValue('lsc_number') . 'id' . $lscUsfId;

                // define JOIN - Syntax
                $sqlJoin .= ' LEFT JOIN ' . TBL_USER_DATA . ' ' . $tableAlias . '
                                     ON ' . $tableAlias . '.usd_usr_id = usr_id
                                    AND ' . $tableAlias . '.usd_usf_id = ' . $lscUsfId;

                // usf_id is prefix for the table
                $dbColumnName = $tableAlias . '.usd_value';
                $sqlColumnName = $gProfileFields->getPropertyById($lscUsfId, 'usf_name_intern');
            } else {
                // Special fields like usr_photo, mem_begin ...
                $dbColumnName = $listColumn->getValue('lsc_special_field');
                $sqlColumnName = $listColumn->getValue('lsc_special_field');
            }

            if (in_array($sqlColumnName, $arrSqlColumnNames)) {
                $arrSqlColumns[] = $dbColumnName . ' AS ' . $sqlColumnName . $columnNumber;
            } else {
                $arrSqlColumns[] = $dbColumnName . ' AS ' . $sqlColumnName;
            }

            // create a valid sort
            if ($listColumn->getValue('lsc_sort') != '') {
                if (strpos($dbColumnName, ' AS') > 0) {
                    $sortColumnName = substr($dbColumnName, 0, strpos($dbColumnName, ' AS'));
                } else {
                    $sortColumnName = $dbColumnName;
                }

                if ($userFieldType === 'NUMBER' || $userFieldType === 'DECIMAL') {
                    // if a field has numeric values then there must be a cast because database
                    // column is varchar. A varchar sort of 1,10,2 will be with cast 1,2,10
                    if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
                        $columnType = 'numeric';
                    } else {
                        // mysql
                        $columnType = 'unsigned';
                    }
                    $arrOrderByColumns[] = ' CAST(' . $sortColumnName . ' AS ' . $columnType . ') ' . $listColumn->getValue('lsc_sort');
                } else {
                    $arrOrderByColumns[] = $sortColumnName . ' ' . $listColumn->getValue('lsc_sort');
                }
            }

            // Handle the conditions for the columns
            if ($optionsAll['useConditions'] && (string)$listColumn->getValue('lsc_filter') !== '') {
                $value = $listColumn->getValue('lsc_filter');

                // custom profile field
                if ($lscUsfId > 0) {
                    switch ($userFieldType) {
                        case 'CHECKBOX':
                            $type = 'checkbox';

                            // 'yes' or 'no' will be replaced with 1 or 0, so that you can compare it with the database value
                            $arrCheckboxValues = array($gL10n->get('SYS_YES'), $gL10n->get('SYS_NO'), 'true', 'false');
                            $arrCheckboxKeys = array(1, 0, 1, 0);
                            $value = str_replace(array_map('StringUtils::strToLower', $arrCheckboxValues), $arrCheckboxKeys, StringUtils::strToLower($value));
                            break;

                        case 'DROPDOWN': // fallthrough
                        case 'RADIO_BUTTON':
                            $type = 'int';

                            // replace all field values with their internal numbers
                            $arrListValues = $gProfileFields->getPropertyById($lscUsfId, 'usf_value_list', 'text');
                            $value = array_search(StringUtils::strToLower($value), array_map('StringUtils::strToLower', $arrListValues), true);
                            break;

                        case 'NUMBER': // fallthrough
                        case 'DECIMAL':
                            $type = 'int';
                            break;

                        case 'DATE':
                            $type = 'date';
                            break;

                        default:
                            $type = 'string';
                    }
                } else {
                    switch ($listColumn->getValue('lsc_special_field')) {
                        case 'mem_begin': // fallthrough
                        case 'mem_end':
                            $type = 'date';
                            break;

                        case 'usr_photo':
                            $type = '';
                            break;

                        default:
                            $type = 'string';
                    }
                }

                $parser = new ConditionParser();

                // if profile field then add not exists condition
                if ($lscUsfId > 0) {
                    $parser->setNotExistsStatement('SELECT 1
                                                      FROM ' . TBL_USER_DATA . ' ' . $tableAlias . 's
                                                     WHERE ' . $tableAlias . 's.usd_usr_id = usr_id
                                                       AND ' . $tableAlias . 's.usd_usf_id = ' . $lscUsfId);
                }

                // now transform condition into SQL
                if (strpos($dbColumnName, ' AS') > 0) {
                    $columnName = substr($dbColumnName, 0, strpos($dbColumnName, ' AS'));
                } else {
                    $columnName = $dbColumnName;
                }
                $sqlWhere .= $parser->makeSqlStatement($value, $columnName, $type, $gProfileFields->getPropertyById($lscUsfId, 'usf_name')); // TODO Exception handling
            }
        }

        if (count($arrSqlColumns) > 0) {
            $sqlColumnNames = ', ' . implode(', ', $arrSqlColumns);
            $sqlColumnNames = substr($sqlColumnNames, 1);
        }

        // add sorting if option is set and sorting columns are stored
        if ($optionsAll['useOrderBy']) {
            $sqlOrderBys = implode(', ', $arrOrderByColumns);

            // if roles should be shown than sort by leaders
            if ($this->showLeaders) {
                if (strlen($sqlOrderBys) > 0) {
                    $sqlOrderBys = 'mem_leader DESC, ' . $sqlOrderBys;
                } else {
                    $sqlOrderBys = 'mem_leader DESC';
                }
            }

            if (strlen($sqlOrderBys) > 0) {
                $sqlOrderBys = ' ORDER BY ' . $sqlOrderBys;
            }
        }

        if (count($optionsAll['showRolesMembers']) > 0) {
            $sqlRoleIds = '(\'' . implode('\', \'', $optionsAll['showRolesMembers']) . '\')';
        } else {
            $sqlRoleIds = '(SELECT rol_uuid
                              FROM ' . TBL_CATEGORIES . '
                             INNER JOIN ' . TBL_ROLES . ' ON rol_cat_id = cat_id
                             WHERE (  cat_org_id = ' . $GLOBALS['gCurrentOrgId'] . '
                                   OR cat_org_id IS NULL )
                               AND cat_name_intern <> \'EVENTS\'
                            )';
        }

        // Set state of membership
        if ($optionsAll['showFormerMembers']) {
            $sqlMemberStatus = 'AND mem_end < \'' . DATE_NOW . '\'
                AND NOT EXISTS (
                   SELECT 1
                     FROM ' . TBL_MEMBERS . ' AS act
                    WHERE act.mem_rol_id = mem.mem_rol_id
                      AND act.mem_usr_id = mem.mem_usr_id
                      AND \'' . DATE_NOW . '\' BETWEEN act.mem_begin AND act.mem_end
                )';
        } else {
            if ($optionsAll['startDate'] === null) {
                $sqlMemberStatus = 'AND mem_begin <= \'' . DATE_NOW . '\'';
            } else {
                $sqlMemberStatus = 'AND mem_begin <= \'' . $optionsAll['endDate'] . ' 23:59:59\'';
            }

            if ($optionsAll['endDate'] === null) {
                $sqlMemberStatus .= ' AND mem_end >= \'' . DATE_NOW . '\'';
            } else {
                $sqlMemberStatus .= ' AND mem_end >= \'' . $optionsAll['startDate'] . ' 00:00:00\'';
            }
        }

        // check if mem_leaders should be shown
        if ($this->showLeaders) {
            $sqlMemLeader = ' mem_leader, ';
        }

        // add columns usr_id, usr_uuid, mem_leaders to the sql
        if ($optionsAll['showUserUUID']) {
            $sqlIdColumns = ' usr_uuid, ';
        }

        $sqlUserJoin = 'INNER JOIN ' . TBL_USERS . '
                                ON usr_id = mem_usr_id';
        $sqlRelationTypeWhere = '';
        if (count($optionsAll['showRelationTypes']) > 0) {
            $sqlUserJoin = 'INNER JOIN ' . TBL_USER_RELATION_TYPES . '
                                    ON urt_uuid IN (\'' . implode('\', \'', $optionsAll['showRelationTypes']) . '\')
                            INNER JOIN ' . TBL_USER_RELATIONS . '
                                    ON ure_usr_id1 = mem_usr_id
                                   AND ure_urt_id = urt_id
                            INNER JOIN ' . TBL_USERS . '
                                    ON usr_id = ure_usr_id2';
        }

        // Set SQL-Statement
        if ($optionsAll['showAllMembersDatabase']) {
            $sql = 'SELECT DISTINCT ' . $sqlMemLeader . $sqlIdColumns . $sqlColumnNames . '
                      FROM ' . TBL_USERS . '
                           ' . $sqlJoin . '
                     WHERE usr_valid = true ' .
                $sqlWhere .
                $sqlOrderBys;
        } else {
            $sql = 'SELECT DISTINCT ' . $sqlMemLeader . $sqlIdColumns . $sqlColumnNames . '
                      FROM ' . TBL_MEMBERS . ' mem
                INNER JOIN ' . TBL_ROLES . '
                        ON rol_id = mem_rol_id
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                           ' . $sqlUserJoin . '
                           ' . $sqlJoin . '
                     WHERE usr_valid = true
                       AND rol_valid = true
                       AND rol_uuid IN ' . $sqlRoleIds . '
                           ' . $sqlRelationTypeWhere . '
                       AND (  cat_org_id = ' . $GLOBALS['gCurrentOrgId'] . '
                           OR cat_org_id IS NULL )
                           ' . $sqlMemberStatus .
                $sqlWhere .
                $sqlOrderBys;
        }

        return $sql;
    }

    /**
     * Return **true** if it's only one role and this role has leaders. The SQL if this list then has an
     * additional column with the leader state of each role member.
     * @return bool Return **true** if it's only one role and this role has leaders.
     */
    public function isShowingLeaders(): bool
    {
        return $this->showLeaders;
    }

    /**
     * Read data of responsible columns and store in object. Only columns of profile fields which the current
     * user is allowed to view will be stored in the object. If only the role membership should be shown than
     * remove all columns except first name, last name and assignment timestamps.
     * @throws Exception
     */
    public function readColumns()
    {
        global $gCurrentUser, $gProfileFields;

        $this->columns = array();

        $sql = 'SELECT *
                  FROM ' . TBL_LIST_COLUMNS . '
                 WHERE lsc_lst_id = ? -- $this->getValue(\'lst_id\')
              ORDER BY lsc_number';
        $lscStatement = $this->db->queryPrepared($sql, array((int)$this->getValue('lst_id')));

        if ($lscStatement->rowCount() === 0) {
            throw new Exception('List-Configuration was not found.');
        }

        while ($lscRow = $lscStatement->fetch()) {
            $usfId = (int)$lscRow['lsc_usf_id'];

            // only add columns to the array if the current user is allowed to view them
            if ($usfId === 0
                || $gProfileFields->isVisible($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), $gCurrentUser->isAdministratorUsers())) {
                // if only names should be shown, then check if it's a name field
                if (!$this->showOnlyNames
                    || ($usfId > 0 && in_array($gProfileFields->getPropertyById($usfId, 'usf_name_intern'), array('FIRST_NAME', 'LAST_NAME')))
                    || ($usfId === 0 && in_array($lscRow['lsc_special_field'], array('mem_begin', 'mem_end', 'mem_leader', 'mem_usr_id_change', 'mem_timestamp_change', 'mem_approved', 'mem_comment', 'mem_count_guests')))) {
                    // some user fields should only be viewed by users that could edit roles
                    if (!in_array($lscRow['lsc_special_field'], array('usr_login_name', 'usr_usr_id_create', 'usr_timestamp_create', 'usr_usr_id_change', 'usr_timestamp_change', 'usr_login_name', 'usr_uuid'))
                        || $gCurrentUser->isAdministratorUsers()) {
                        $lscNumber = (int)$lscRow['lsc_number'];
                        $this->columns[$lscNumber] = new ListColumns($this->db);
                        $this->columns[$lscNumber]->setArray($lscRow);
                    }
                }
            }
        }
    }

    /**
     * Reads a record out of the table in database selected by the unique uuid column in the table.
     * The name of the column must have the syntax table_prefix, underscore and uuid. E.g. usr_uuid.
     * Per default all columns of the default table will be read and stored in the object.
     * Not every Admidio table has an uuid. Please check the database structure before you use this method.
     * @param string $uuid Unique uuid that should be searched.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     * @see Entity#readDataByColumns
     * @see Entity#readData
     */
    public function readDataByUuid(string $uuid): bool
    {
        $returnValue = parent::readDataByUuid($uuid);

        if ($returnValue) {
            $this->readColumns();
        }

        return $returnValue;
    }

    /**
     * Removes a column from the list configuration array, but only in the memory and not in database.
     * @param string $columnNameOrUsfId Accept the usfId or the name of the special field that should be removed.
     * @throws Exception
     */
    public function removeColumn(string $columnNameOrUsfId)
    {
        $currentNumber = 1;

        // check for every column if the number is expected otherwise set new number
        foreach ($this->columns as $number => $listColumn) {
            if ($listColumn->getValue('lsc_special_field') === $columnNameOrUsfId
                || $listColumn->getValue('lsc_usf_id') === (int)$columnNameOrUsfId) {
                unset($this->columns[$number]);
            } else {
                // set new number to the columns after the removed column
                if ($currentNumber < $number) {
                    $this->columns[$currentNumber] = $listColumn;
                    unset($this->columns[$number]);
                }
                $currentNumber++;
            }
        }
    }

    /**
     * The method will clear all column data of this object and restore all
     * columns from the database. Then the column number will be renewed for all columns.
     * This is in some cases a necessary fix if a column number was lost.
     * @throws Exception
     */
    public function repair()
    {
        // restore columns from database
        $this->columns = array();
        $this->readColumns();
        $newColumnNumber = 1;

        // check for every column if the number is expected otherwise set new number
        foreach ($this->columns as $number => $listColumn) {
            if ($number !== $newColumnNumber) {
                $this->columns[$number]->setValue('lsc_number', $newColumnNumber);
                $this->columns[$number]->save();
            }
            ++$newColumnNumber;
        }

        // now restore columns with new numbers
        $this->columns = array();
        $this->readColumns();
    }

    /**
     * @param bool $updateFingerPrint
     * @return bool
     * @throws Exception
     */
    public function save(bool $updateFingerPrint = true): bool
    {
        $this->db->startTransaction();

        $this->setValue('lst_timestamp', DATETIME_NOW);
        $this->setValue('lst_usr_id', $GLOBALS['gCurrentUserId']);

        if ($this->newRecord && empty($this->getValue('lst_org_id'))) {
            $this->setValue('lst_org_id', $GLOBALS['gCurrentOrgId']);
        }

        // if "lst_global" isn't set explicit to "1", set it to "0"
        if ((int) $this->getValue('lst_global') !== 1) {
            $this->setValue('lst_global', 0);
        }

        $returnValue = parent::save($updateFingerPrint);

        // save columns
        foreach ($this->columns as $listColumn) {
            if ((int)$listColumn->getValue('lsc_lst_id') === 0) {
                $listColumn->setValue('lsc_lst_id', (int)$this->getValue('lst_id'));
            }
            $listColumn->save($updateFingerPrint);
        }

        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * Set a mode that only first name and last name will be returned if the sql is called or columns should be
     * returned. This is useful is a role has the setting that no profile information should be shown, but the
     * membership could be viewed.
     * @return void
     * @throws Exception
     */
    public function setModeShowOnlyNames()
    {
        $this->showOnlyNames = true;

        if (count($this->columns) > 0) {
            $this->readColumns();
        }
    }

   /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * The Lists table also contains mem_rol_id and mem_usr_id, which cannot be changed,
     * so their initial setting on creation should not be logged. Instead, they will be used
     * when displaying the log entry.
     *
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['lst_timestamp']);
    }
}
