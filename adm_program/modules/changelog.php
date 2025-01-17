<?php
/**
 ***********************************************************************************************
 * Show history of generic database record changes
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * table            : The type of changes to be listed (name of the DB table, excluding the prefix)
 * id...............: If set only show the change history of that database record
 * uuid             : If set only show the change history of that database record
 * related_id       : If set only show the change history of objects related to that id (e.g. membership of a role/group)
 * filter_date_from : is set to actual date,
 *                    if no date information is delivered
 * filter_date_to   : is set to 31.12.9999,
 *                    if no date information is delivered
 ***********************************************************************************************
 */
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Organizations\Entity\Organization;
use Admidio\UI\Component\Form;
use Admidio\Users\Entity\User;
use Admidio\Users\Entity\UserRelationType;
use Admidio\Roles\Entity\Role;
use Admidio\Photos\Entity\Album;
use Admidio\Documents\Entity\Folder;
use Admidio\Categories\Entity\Category;




try {
    require_once(__DIR__ . '/../system/common.php');
    require(__DIR__ . '/../system/login_valid.php');

    // calculate default date from which the profile fields history should be shown
    $filterDateFrom = DateTime::createFromFormat('Y-m-d', DATE_NOW);
    $filterDateFrom->modify('-' . $gSettingsManager->getInt('contacts_field_history_days') . ' day');


    // Initialize and check the parameters
    $getTable = admFuncVariableIsValid($_GET, 'table','string');
    $getTables = ($getTable !== null && $getTable != "") ? array_map('trim', explode(",", $getTable)) : [];
    $getUuid = admFuncVariableIsValid($_GET, 'uuid', 'string');
    $getId = admFuncVariableIsValid($_GET, 'id', 'int');
    $getRelatedId = admFuncVariableIsValid($_GET, 'related_id', 'string');
    $getDateFrom = admFuncVariableIsValid($_GET, 'filter_date_from', 'date', array('defaultValue' => $filterDateFrom->format($gSettingsManager->getString('system_date'))));
    $getDateTo   = admFuncVariableIsValid($_GET, 'filter_date_to', 'date', array('defaultValue' => DATE_NOW));


    $tableString = array(
        'user_data' => 'SYS_PROFILE_FIELD',
        'users' =>  'SYS_PROFILE_FIELD',
        'members' => 'SYS_ROLE_MEMBERSHIPS',
        'user_fields' => 'ORG_PROFILE_FIELDS',
        'announcements' => 'SYS_ANNOUNCEMENTS',
        'events' => 'SYS_EVENTS',
        'rooms' => 'SYS_ROOM',
        'roles' => 'SYS_ROLES',
        'roles_rights' => 'SYS_ROLE_RIGHTS',
        'roles_rights_data' => 'SYS_ROLE_RIGHTS',
        
        'categories' => 'SYS_CATEGORIES',
        'category_report' => 'SYS_CATEGORY_REPORT',

        'guestbook' => 'GBO_GUESTBOOK',
        'guestbook_comments' => 'GBO_GUESTBOOK_COMMENTS',
    
        'links' => 'SYS_WEBLINKS',
    
        'texts' => 'SYS_SETTINGS',
        'folders' => 'SYS_FOLDER',
        'files' => 'SYS_FILE',
        'organizations' => 'SYS_ORGANIZATION',
        'menu' => 'SYS_MENU_ITEM',
    
        'user_relation_types' => 'SYS_USER_RELATION_TYPE',
        'user_relations' => 'SYS_USER_RELATIONS',
    
        'photos' => 'SYS_PHOTO_ALBUMS',
    
        'lists' => 'SYS_LIST',

        // 'list_columns' => '', // TODO_RK
    );
    $fieldString = array(
        'mem_begin' =>                 'SYS_MEMBERSHIP_START',
        'mem_end' =>                   'SYS_MEMBERSHIP_END',
        'mem_leader' =>                array('name' => 'SYS_LEADER', 'type' => 'BOOL'),
        'mem_approved' =>              array('name' => 'SYS_MEMBERSHIP_APPROVED', 'type' => 'BOOL'),
        'mem_count_guests' =>          'SYS_SEAT_AMOUNT',
        'mem_timestamp_change' =>      'SYS_CHANGED_AT',
        'mem_usr_id_change' =>         'SYS_CHANGED_BY',
        'mem_usr_id_create' =>         'SYS_CREATED_AT',
        'mem_timestamp_create' =>      'SYS_CREATED_BY',
        'mem_comment' =>               'SYS_COMMENT',


        'usr_password' =>              'SYS_PASSWORD',
        'usr_photo' =>                 'SYS_PROFILE_PHOTO',
        'usr_login_name' =>            'SYS_USERNAME',
        'usr_uuid' =>                  'SYS_UNIQUE_ID',
        'usr_timestamp_change' =>      'SYS_CHANGED_AT',
        'usr_usr_id_change' =>         'SYS_CHANGED_BY',
        'usr_usr_id_create' =>         'SYS_CREATED_AT',
        'usr_timestamp_create' =>      'SYS_CREATED_BY',


        'usf_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
        'usf_type' =>                  'SYS_TYPE',
        'usf_description' =>           'SYS_DESCRIPTION',
        'usf_description_inline' =>    array('name' => 'SYS_DESCRIPTION_INLINE_DESC', 'type' => 'BOOL'),
        'usf_default_value' =>         'SYS_DEFAULT_VALUE',
        'usf_regex' =>                 'SYS_REGULAR_EXPRESSION',
        'usf_disabled' =>              array('name' => 'SYS_DISABLED', 'type' => 'BOOL'),
        'usf_hidden' =>                array('name' => 'SYS_HIDDEN', 'type' => 'BOOL'),
        'usf_registration' =>          'ORG_FIELD_REGISTRATION',
        'usf_sequence' =>              'SYS_ORDER',
        'usf_icon' =>                  array('name' => 'SYS_ICON', 'type' => 'ICON'),
        'usf_url' =>                   array('name' => 'SYS_URL', 'type' => 'URL'),
        'usf_required_input' =>        array('name' => 'SYS_REQUIRED_INPUT', 'type' => 'BOOL'),

        'ann_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
        'ann_headline' =>              'SYS_HEADLINE',
        'ann_description' =>           'SYS_DESCRIPTION',

        'room_name' =>                 'SYS_NAME',
        'room_description' =>          'SYS_DESCRIPTION',
        'room_capacity' =>             'SYS_CAPACITY',
        'room_overhang' =>             'SYS_OVERHANG',
    
        'dat_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
        'dat_rol_id'=>                 array('name' => 'SYS_ROLE', 'type' => 'ROLE'),
        'dat_room_id' =>               'SYS_ROOM',
        'dat_begin' =>                 'SYS_START',
        'dat_end' =>                   'SYS_END',
        'dat_all_day' =>               array('name' => 'SYS_ALL_DAY', 'type' => 'BOOL'),
        'dat_headline' =>              'SYS_HEADLINE',
        'dat_description' =>           'SYS_DESCRIPTION',
        'dat_highlight' =>             array('name' => 'SYS_HIGHLIGHT_EVENT', 'type' => 'BOOL'),
        'dat_location' =>              'SYS_VENUE',
        'dat_country' =>               'SYS_COUNTRY',
        'dat_deadline' =>              'SYS_DEADLINE',
        'dat_max_members' =>           'SYS_MAX_PARTICIPANTS',
        'dat_allow_comments' =>        array('name' => 'SYS_ALLOW_USER_COMMENTS', 'type' => 'BOOL'),
        'dat_additional_guests' =>     array('name' => 'SYS_ALLOW_ADDITIONAL_GUESTS', 'type' => 'BOOL'),

        'rol_name' =>                  'SYS_NAME',
        'rol_description' =>           'SYS_DESCRIPTION',
        'rol_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
        'rol_mail_this_role' =>        array('name' => 'SYS_SEND_MAILS', 'type' => 'SELECT', 'entries' => array(0 => $gL10n->get('SYS_NOBODY'), 1 => $gL10n->get('SYS_ROLE_MEMBERS'), 2 => $gL10n->get('ORG_REGISTERED_USERS'), 3 => $gL10n->get('SYS_ALSO_VISITORS'))),

        'rol_view_memberships' =>      'SYS_VIEW_ROLE_MEMBERSHIPS',
        'rol_view_members_profiles' => 'SYS_VIEW_PROFILES_OF_ROLE_MEMBERS', 
        'rol_leader_rights' =>         'SYS_LEADER',
        'rol_lst_id' =>                'SYS_DEFAULT_LIST',
        'rol_default_registration' =>  array('name' => 'SYS_DEFAULT_ASSIGNMENT_REGISTRATION', 'type' => 'BOOL'),
        'rol_max_members' =>           'SYS_MAX_PARTICIPANTS',
        'rol_cost' =>                  'SYS_CONTRIBUTION',
        'rol_cost_period' =>           'SYS_CONTRIBUTION_PERIOD',
        'rol_assign_roles' =>          array('name' => 'SYS_RIGHT_ASSIGN_ROLES', 'type' => 'BOOL'),
        'rol_all_lists_view' =>        array('name' => 'SYS_RIGHT_ALL_LISTS_VIEW', 'type' => 'BOOL'),
        'rol_approve_users' =>         array('name' => 'SYS_RIGHT_APPROVE_USERS', 'type' => 'BOOL'),
        'rol_mail_to_all' =>           array('name' => 'SYS_RIGHT_MAIL_TO_ALL', 'type' => 'BOOL'),
        'rol_edit_user' =>             array('name' => 'SYS_RIGHT_EDIT_USER', 'type' => 'BOOL'),
        'rol_profile' =>               array('name' => 'SYS_RIGHT_PROFILE', 'type' => 'BOOL'),
        'rol_announcements' =>         array('name' => 'SYS_RIGHT_ANNOUNCEMENTS', 'type' => 'BOOL'),
        'rol_events' =>                array('name' => 'SYS_RIGHT_DATES', 'type' => 'BOOL'),
        'rol_photo' =>                 array('name' => 'SYS_RIGHT_PHOTOS', 'type' => 'BOOL'),
        'rol_documents_files' =>       array('name' => 'SYS_RIGHT_DOCUMENTS_FILES', 'type' => 'BOOL'),
        'rol_guestbook' =>             array('name' => 'SYS_RIGHT_GUESTBOOK', 'type' => 'BOOL'),
        'rol_guestbook_comments' =>    array('name' => 'SYS_RIGHT_GUESTBOOK_COMMENTS', 'type' => 'BOOL'),
        'rol_weblinks' =>              array('name' => 'SYS_RIGHT_WEBLINKS', 'type' => 'BOOL'),

        'rol_start_date' =>            'SYS_VALID_FROM',
        'rol_end_date' =>              'SYS_VALID_TO',
        'rol_start_time' =>            'SYS_TIME_FROM',
        'rol_end_time' =>              'SYS_TIME_TO',
        'rol_weekday' =>               'SYS_WEEKDAY',
        'rol_location' =>              'SYS_MEETING_POINT',

        'ror_name_intern' =>           'SYS_INTERNAL_NAME',
        'ror_table' =>                 'SYS_TABLE',
        // 'ror_ror_id_parent' =>         '',

        'gbo_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'gbo_name' =>                  'SYS_NAME',
        'gbo_text' =>                  'SYS_MESSAGE',
        'gbo_email' =>                 array('name' => 'SYS_EMAIL', 'type' => 'EMAIL'),
        'gbo_homepage' =>              array('name' => 'SYS_WEBSITE', 'type' => 'URL'),
        'gbo_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),

        'gbc_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'gbc_name' =>                  'SYS_NAME',
        'gbc_text' =>                  'SYS_MESSAGE',
        'gbc_email' =>                 array('name' => 'SYS_EMAIL', 'type' => 'EMAIL'),
        'gbc_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),

        'lnk_name' =>                  'SYS_LINK_NAME',
        'lnk_description' =>           'SYS_DESCRIPTION',
        'lnk_url' =>                   array('name' => 'SYS_LINK_ADDRESS', 'type' => 'IRL'),
        'lnk_cat_id' =>                array('name' => 'SYS_CATEGORY', 'type' => 'CATEGORY'),
        'lnk_counter' =>               'SYS_COUNTER',

        'txt_text' =>                  array('name' => 'SYS_TEXT', 'type' => 'TEXT_BIG'),
        'txt_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),

        'fol_name' =>                  'SYS_NAME',
        'fol_description' =>           'SYS_DESCRIPTION',
        'fol_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'fol_fol_id_parent' =>         array('name' => 'SYS_FOLDER', 'type' => 'FOLDER'), // TODO_RK: Find a translatable string for parent folder (currenly just use "folder")
        'fol_path' =>                  'SYS_PATH',
        'fol_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),
        'fol_public' =>                array('name' => 'SYS_VISIBLE', 'type' => 'BOOL'),

        'fil_name' =>                  'SYS_NAME',
        'fil_description' =>           'SYS_DESCRIPTION',
        'fil_fol_id' =>                array('name' => 'SYS_FOLDER', 'type' => 'FOLDER'),
        'fil_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'fil_locked' =>                array('name' => 'SYS_LOCKED', 'type' => 'BOOL'),
        // 'fil_counter' =>               '', // not logged!

        'pho_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'pho_name'  =>                 'SYS_ALBUM',
        'pho_description' =>           'SYS_DESCRIPTION',
        'pho_pho_id_parent' =>         array('name' => 'SYS_PARENT_ALBUM', 'type' => 'ALBUM'),
        'pho_quantity'  =>             'SYS_QUANTITY',
        'pho_begin' =>                 'SYS_START',
        'pho_end' =>                   'SYS_END',
        'pho_photographers' =>         'SYS_PHOTOS_BY',
        'pho_locked' =>                array('name' => 'SYS_LOCK_ALBUM', 'type' => 'BOOL'),

        'org_shortname' =>             'SYS_NAME_ABBREVIATION',
        'org_longname' =>              'SYS_NAME',
        'org_org_id_parent' =>         array('name' => 'ORG_PARENT_ORGANIZATION', 'type'=> 'ORG'),
        'org_homepage' =>              array('name' => 'SYS_HOMEPAGE', 'type'=> 'URL'),

        'men_name' =>                  'SYS_NAME',
        'men_name_intern' =>           'SYS_INTERNAL_NAME',
        'men_description' =>           'SYS_DESCRIPTION',
        'men_men_id_parent' =>         'SYS_MENU_LEVEL',
        'men_com_id' =>                'SYS_MODULE_RIGHTS',
        //'men_node' =>                  '', // men_node cannot be set by the user (section headings in the frontend)!
        'men_order' =>                 'SYS_ORDER', 
        'men_standard' =>              $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_MENU_ITEM'))),
        'men_url' =>                   array('name' => 'SYS_URL', 'type' => 'URL'),
        'men_icon' =>                  array('name' => 'SYS_ICON', 'type' => 'ICON'),

        'urt_name' => 'SYS_NAME',
        'urt_name_male' => 'SYS_MALE',
        'urt_name_female' => 'SYS_FEMALE',
        'urt_edit_user' =>  array('name' => 'SYS_EDIT_USER_IN_RELATION', 'type' => 'BOOL'),
        'urt_id_inverse' =>  array('name' => 'SYS_OPPOSITE_RELATIONSHIP', 'type' => 'RELATION_TYPE'),

        'crt_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'crt_name' =>                  'SYS_NAME',
        'crt_col_fields' =>            'SYS_COLUMN_SELECTION',
        'crt_selection_role' =>        array('name' => 'SYS_ROLE_SELECTION', 'type' => 'ROLE'),
        'crt_selection_cat' =>         array('name' => 'SYS_CAT_SELECTION', 'type' => 'CATEGORY'),
        'crt_number_col' =>            array('name' => $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')', 'type' => 'BOOL'),

        'lst_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        'lst_usr_id' =>                array('name' => 'SYS_USER', 'type' => 'USER'),
        'lst_name' =>                  'SYS_NAME',
        'lst_global' =>                array('name' => 'SYS_CONFIGURATION_ALL_USERS', 'type' => 'BOOL'),
        'lsc_number' =>                'SYS_NUMBER',
        'lsc_filter' =>                'SYS_CONDITION',
        'lsc_sort' =>                  'SYS_ORDER',

        'cat_name' =>                  'SYS_NAME',
        'cat_name_intern' =>           'SYS_INTERNAL_NAME',
        'cat_org_id' =>                array('name' => 'SYS_ORGANIZATION', 'type' => 'ORG'),
        //'cat_type' =>                  '', // Holds indicators like USF, ROL, LNK, EVT, ANN, 
        'cat_system' =>                array('name' => 'SYS_SSYSTEM', 'type' => 'BOOL'),
        'cat_default' =>               array('name' => $gL10n->get('SYS_DEFAULT_VAR', array($gL10n->get('SYS_CATEGORY'))), 'type' => 'BOOL'),
        'cat_sequence' =>              'SYS_ORDER',
    );


    // create a user object. Will fill it later if we encounter a user id
    $user = new User($gDb, $gProfileFields);

    // set headline of the script
    if (in_array("users", $getTables) && ($getId || $getUuid)) {
        if ($getUuid) {
            $user->readDataByUuid($getUuid);
        } elseif ($getId) {
            $user->readDataById($getId);
        }
        if ($getId || $getUuid) {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
        } else {
            $headline = $gL10n->get('SYS_CHANGE_HISTORY');
        }
    //} elseif (in_array('members', $getTables)) {

    //} elseif (in_array('user_fields', $getTables)) {


    // } elseif ($getUuid !== '') {
    //     $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    // } elseif ($getRoleId > 0) {
    //     $headline = $gL10n->get('SYS_CHANGE_HISTORY_OF', array($user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME')));
    } else {
        // TODO_RK: Implement Titles for other types of history
        $headline = $gL10n->get('SYS_CHANGE_HISTORY');  
    }

    // if profile log is activated and current user is allowed to edit users
    // then the profile field history will be shown otherwise show error
    // TODO_RK: Which user shall be allowed to view the history (probably depending on the type the table)
    if (!$gSettingsManager->getBool('profile_log_edit_fields')
        || ($getUuid === '' && !$gCurrentUser->editUsers())
        || ($getUuid !== '' && !$gCurrentUser->hasRightEditProfile($user))) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    }



    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // add page to navigation history
    $gNavigation->addUrl(CURRENT_URL, $headline);

    // filter_date_from and filter_date_to can have different formats
    // now we try to get a default format for intern use and html output
    $objDateFrom = DateTime::createFromFormat('Y-m-d', $getDateFrom);
    if ($objDateFrom === false) {
        // check if date has system format
        $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateFrom);
        if ($objDateFrom === false) {
            $objDateFrom = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    $objDateTo = DateTime::createFromFormat('Y-m-d', $getDateTo);
    if ($objDateTo === false) {
        // check if date has system format
        $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $getDateTo);
        if ($objDateTo === false) {
            $objDateTo = DateTime::createFromFormat($gSettingsManager->getString('system_date'), '1970-01-01');
        }
    }

    // DateTo should be greater than DateFrom
    if ($objDateFrom > $objDateTo) {
        throw new Exception('SYS_DATE_END_BEFORE_BEGIN');
    }

    $dateFromIntern = $objDateFrom->format('Y-m-d');
    $dateFromHtml = $objDateFrom->format($gSettingsManager->getString('system_date'));
    $dateToIntern = $objDateTo->format('Y-m-d');
    $dateToHtml = $objDateTo->format($gSettingsManager->getString('system_date'));


    // create sql conditions
    $sqlConditions = '';
    $queryParamsConditions = array();

    if (!is_null($getTables) && count($getTables) > 0) {
        // Add each table as a separate condition, joined by OR:
        $sqlConditions .= ' AND ( ' .  implode(' OR ', array_map(fn($tbl) => '`log_table` = ?', $getTables)) . ' ) ';
        $queryParamsConditions = array_merge($queryParamsConditions, $getTables);
    }

    if (!is_null($getId) && $getId > 0) {
        $sqlConditions .= ' AND (`log_record_id` = ? )';
        $queryParamsConditions[] = $getId;
    }
    if (!is_null($getUuid) && $getUuid) {
        $sqlConditions .= ' AND (`log_record_uuid` = ? )';
        $queryParamsConditions[] = $getUuid;
    }
    if (!is_null($getRelatedId) && $getRelatedId > 0) {
        $sqlConditions .= ' AND (`log_related_id` = ? )';
        $queryParamsConditions[] = $getRelatedId;
    }



    $sql = 'SELECT log_id as id, log_table as table_name, 
        log_record_id as record_id, log_record_uuid as uuid, log_record_name as name, log_record_linkid as link_id,
        log_related_id as related_id, log_related_name as related_name,
        log_field as field, log_field_name as field_name, 
        log_action as action,
        log_value_new as value_new, log_value_old as value_old, 
        log_usr_id_create as usr_id_create, usr_create.usr_uuid as uuid_usr_create, create_last_name.usd_value AS create_last_name, create_first_name.usd_value AS create_first_name, 
        log_timestamp_create as timestamp
        FROM ' . TBL_LOG . ' 
        -- Extract data of the creating user...
        INNER JOIN '.TBL_USERS.' usr_create 
                ON usr_create.usr_id = log_usr_id_create
        INNER JOIN '.TBL_USER_DATA.' AS create_last_name
                ON create_last_name.usd_usr_id = log_usr_id_create
               AND create_last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
        INNER JOIN '.TBL_USER_DATA.' AS create_first_name
                ON create_first_name.usd_usr_id = log_usr_id_create
               AND create_first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
        WHERE
               `log_timestamp_create` BETWEEN ? AND ? -- $dateFromIntern and $dateToIntern
        ' . $sqlConditions . '
        ORDER BY `log_id` DESC';

    $queryParams = [
        $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
        $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
        $dateFromIntern . ' 00:00:00',
        $dateToIntern . ' 23:59:59',
    ];


    function createLink(string $text, string $module, int|string $id, string $uuid = '') {
        $url = '';
        switch ($module) {
            case 'users': // Fall through
            case 'user_data':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $uuid)); break;
            case 'announcements':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/announcements/announcements_new.php', array('ann_uuid' => $uuid)); break;
            case 'categories' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/categories.php', array('mode' => 'edit', 'uuid' => $uuid)); break; // Note: the type is no longer needed (only recommended, but we don't have it in the changelog DB)
            case 'category_report' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/category-report/preferences.php'); break;
            case 'events' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/events/events_new.php', array('dat_uuid' => $uuid)); break;
            case 'files' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('file_uuid' => $uuid)); break;
            case 'folders' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php', array('folder_uuid' => $uuid)); break;
            case 'guestbook' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_new.php', array('gbo_uuid' => $uuid)); break;
            case 'guestbook_comments' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/guestbook/guestbook_comment_new.php', array('gbc_uuid' => $uuid)); break;
            case 'links' :
                $url = SecurityUtils::encodeUrl( ADMIDIO_URL.FOLDER_MODULES.'/links/links_new.php', array('link_uuid' => $uuid)); break;
            case 'lists' :
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
            case 'list_columns':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/mylist.php', array('active_role' => 1, 'list_uuid' => $uuid)); break;
            case 'members':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('role_list' => $uuid)); break;
            case 'menu':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu/menu_new.php', array('menu_uuid' => $uuid)); break;
            // case 'organizations': // There is currently no edit page for other organizations! One needs to log in to the other org!
            //     $url = SecurityUtils::encodeUrl(); break;
            case 'photos':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/photos/photos.php', array('photo_uuid' => $uuid)); break;
            // case 'preferences': // There is just one preferences page, but no way to link to individual sections or preference items!
            //     $url = SecurityUtils::encodeUrl(); break;
            // case 'registrations':
            //     $url = SecurityUtils::encodeUrl(); break;
            case 'roles':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/lists_show.php', array('role_list' => $uuid)); break;
            case 'roles_rights':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $uuid)); break;
            case 'roles_rights_data':
                // The log_record_linkid contains the table and the uuid encoded as 'table':'UUID' => split and call Create linke with the new table!
                if (strpos($id, ':') !== false) {
                    // Split into table and UUID
                    [$table, $id] = explode(':', $id, 2);
                } else {
                    $table = ''; // Table is empty
                }
                return createLink($text, $table, $id, $id); break;
            case 'role_dependencies':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/groups-roles/groups_roles_new.php', array('role_uuid' => $uuid)); break;
            case 'rooms':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/rooms/rooms_new.php', array('room_uuid' => $uuid)); break;
            // case 'texts': // Texts can be modified in the preferences, but there is no direct link to the notifications sections, where the texts are located at the end!
            //     $url = SecurityUtils::encodeUrl(); break;
            case 'user_fields':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile-fields.php', array('mode' => 'edit', 'uuid' => $uuid)); break;
            case 'user_relations': // For user relations, we don't link to the modification of the individual relation, but to the user1
                // $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/userrelations_new.php', array('user_uuid' => $uuid)); break;
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $uuid)); break;
            case 'user_relation_types':
                $url = SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/userrelations/relationtypes_new.php', array('urt_uuid' => $uuid)); break;
        }
        if ($url != '') {
            return '<a href="'.$url.'">'.$text.'</a>';
        } else {
            return $text;
        }
    }

    function formatValue($value, $type) {
        global $gSettingsManager, $gCurrentUserUUID, $gDb, $gProfileFields;
        // if value is empty or null, then do nothing
        if ($value != '') {
            // create html for each field type
            $value = SecurityUtils::encodeHTML(StringUtils::strStripTags($value));
            $htmlValue = $value;
        
            switch ($type) {
                case 'BOOL':
                    if ($value == 1) {
                        $htmlValue = '<span class="fa-stack">
                            <i class="fas fa-square-full fa-stack-1x"></i>
                            <i class="fas fa-check-square fa-stack-1x fa-inverse"></i>
                        </span>';
                    } else {
                        $htmlValue = '<span class="fa-stack">
                            <i class="fas fa-square-full fa-stack-1x"></i>
                            <i class="fas fa-square fa-stack-1x fa-inverse"></i>
                        </span>';
                    }
                    break;
                case 'DATE':
                    if ($value !== '') {
                        // date must be formatted
                        $date = DateTime::createFromFormat('Y-m-d', $value);
                        if ($date instanceof DateTime) {
                            $htmlValue = $date->format($gSettingsManager->getString('system_date'));
                        }
                    }
                    break;
                case 'EMAIL':
                    // the value in db is only the position, now search for the text
                    if ($value !== '') {
                        if (!$gSettingsManager->getBool('enable_mail_module')) {
                            $emailLink = 'mailto:' . $value;
                        } else {
                            $emailLink = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $gCurrentUserUUID));
                        }
                        $htmlValue = '<a href="' . $emailLink . '" title="' . $value . '" style="overflow: visible; display: inline;">' . $value . '</a>';
                    }
                    break;

                case 'URL':
                    if ($value !== '') {
                        $displayValue = $value;
                    
                        // trim "http://", "https://", "//"
                        if (str_contains($displayValue, '//')) {
                            $displayValue = substr($displayValue, strpos($displayValue, '//') + 2);
                        }
                        // trim after the 35th char
                        if (strlen($value) > 35) {
                            $displayValue = substr($displayValue, 0, 35) . '...';
                        }
                        $htmlValue = '<a href="' . $value . '" target="_blank" title="' . $value . '">' . $displayValue . '</a>';
                    }
                    break;
                case 'TEXT_BIG':
                    $htmlValue = nl2br($value);
                    break;
                case 'ICON':
                    $htmlValue = '<div class="fas '.$value.'"> '. $value.'</div>';
                    break;
                case 'ORG':
                    $org = new Organization($gDb, $value);
                    $htmlValue = createLink($org->readableName(), 'organizations', $org->getValue('org_id'), $org->getValue('org_uuid'));
                    break;
                case 'RELATION_TYPE':
                    $org = new UserRelationType($gDb, $value);
                    $htmlValue = createLink($org->readableName(), 'user_relation_types', $org->getValue('urt_id'), $org->getValue('urt_uuid'));
                    break;
                case 'ALBUM':
                    $album = new Album($gDb, $value);
                    $htmlValue = createLink($album->readableName(), 'photos', $album->getValue('pho_id'), $album->getValue('pho_uuid'));
                    break;
                case 'FOLDER':
                    $folder = new Folder($gDb, $value);
                    $htmlValue = createLink($folder->readableName(), 'folders', $folder->getValue('fol_id'), $folder->getValue('fol_uuid'));
                    break;
                case 'ROLE':
                    $role = new Role($gDb, $value);
                    $htmlValue = createLink($role->readableName(), 'roles', $role->getValue('rol_id'), $role->getValue('rol_uuid'));
                    break;
                case 'CATEGORY':
                    $cat = new Category($gDb, $value);
                    $htmlValue = createLink($cat->readableName(), 'categories', $cat->getValue('cat_id'), $cat->getValue('cat_uuid'));
                    break;
                case 'USER':
                    $usr = new User($gDb, $gProfileFields, $value);
                    $htmlValue = createLink($usr->readableName(), 'users', $usr->getValue('usr_id'), $usr->getValue('usr_uuid'));
                    break;
            }
        
            $value = $htmlValue;
        }
        // special case for type BOOL and no value is there, then show unchecked checkbox
        else {
            if ($type === 'BOOL') {
                $value = '<span class="fa-stack">
                    <i class="fas fa-square-full fa-stack-1x"></i>
                    <i class="fas fa-square fa-stack-1x fa-inverse"></i>
                </span>';
            
            }    
        }
        return $value;
    }



    $fieldHistoryStatement = $gDb->queryPrepared($sql, array_merge($queryParams, $queryParamsConditions));

    if ($fieldHistoryStatement->rowCount() === 0) {
        // message is shown, so delete this page from navigation stack
        $gNavigation->deleteLastUrl();

        // show message if there were no changes
        $gMessage->show($gL10n->get('SYS_NO_CHANGES_LOGGED'));
    }

    // create html page object
    $page = new HtmlPage('admidio-history', $headline);

    // Logic for hiding certain columns:
    // If we have only one table name given, hide the table column
    // If we view the user profile field changes page, hide the column, too
    $showTableColumn = true;
    if (count($getTables) == 1) {
        $showTableColumn = false;
    }
    // If none of the related-to values is set, hide the related_to column
    $showRelatedColumn = true;
    $noShowRelatedTables = ['user_fields', 'users', 'user_data'];
    if (false/*TODO_RK*/) {
        $showRelatedColumn = false;
    }

    $form = new Form(
        'adm_navbar_filter_form',
        'sys-template-parts/form.filter.tpl',
        ADMIDIO_URL . FOLDER_MODULES . '/changelog.php',
        $page,
        array('type' => 'navbar', 'setFocus' => false)
    );

    // create filter menu with input elements for start date and end date
    $form->addInput('table', '', $getTable, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('uuid', '', $getUuid, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('id', '', $getId, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('related_id', '', $getRelatedId, array('property' => Form::FIELD_HIDDEN));
    $form->addInput('filter_date_from', $gL10n->get('SYS_START'), $dateFromHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addInput('filter_date_to', $gL10n->get('SYS_END'), $dateToHtml, array('type' => 'date', 'maxLength' => 10));
    $form->addSubmitButton('adm_button_send', $gL10n->get('SYS_OK'));
    $form->addToHtmlPage();

    $table = new HtmlTable('history_table', $page, true, true);


    /* For now, simply show all column of the changelog table. As time permits, we can improve this by hiding unneccessary columns and by better naming columns depending on the table.
     * 
     * Columns to be displayed / hidden:
     *   0. If there is only one value in the table column, hide it and display it in the title of the page.
     *   1. If there is a single ID or UUID, the record name is not displayed. It should be shown in the title of the page.
     *   2. If there is a single related-to ID, and the table is memberships, the role name should already be displayed in the title, so don't show it again.
     *   3. If none of the entries have a related ID, hide the related ID column.
     */
    $columnHeading = array();

    $table->setDatatablesOrderColumns(array(array(8, 'desc')));
    if ($showTableColumn) {
        $columnHeading[] = $gL10n->get('SYS_TABLE');
    }
    $columnHeading[] = $gL10n->get('SYS_NAME');
    if ($showRelatedColumn) {
        $columnHeading[] = $gL10n->get('SYS_RELATED_TO');
    }
    $columnHeading[] = $gL10n->get('SYS_FIELD');
    $columnHeading[] = $gL10n->get('SYS_NEW_VALUE');
    $columnHeading[] = $gL10n->get('SYS_PREVIOUS_VALUE');
    $columnHeading[] = $gL10n->get('SYS_EDITED_BY');
    $columnHeading[] = $gL10n->get('SYS_CHANGED_AT');

    $table->addRowHeadingByArray($columnHeading);

    while ($row = $fieldHistoryStatement->fetch()) {
        $fieldInfo = $row['field_name'];
        $fieldInfo = array_key_exists($fieldInfo, $fieldString) ? $fieldString[$fieldInfo] : $fieldInfo;


        $timestampCreate = DateTime::createFromFormat('Y-m-d H:i:s', $row['timestamp']);
        $columnValues    = array();

        // 1. Column showing DB table name (only if more then one tables are shown; One table should be displayed in the headline!)
        if ($showTableColumn) {
            $tblLabel = $row['table_name'];
            $tblLabel = array_key_exists($tblLabel, $tableString) ? $tableString[$tblLabel] : $tblLabel;
            // TODO_RK: If possible, add link to listing page of the corresponding DB record type
            $columnValues[] = Language::translateIfTranslationStrId($tblLabel);
        }


        // 2. Name column: display name and optionally link it with the linkID or the recordID 
        //    Some tables need special-casing, though
        $rowLinkId = ($row['link_id']>0) ? $row['link_id'] : $row['record_id'];
        $rowName = $row['name'] ?? '';
        $rowName = Language::translateIfTranslationStrId($rowName);
        if ($row['table_name'] == 'members') {
            $columnValues[] = createLink($rowName, 'users', $rowLinkId, $row['uuid'] ?? '');
        } else {
            $columnValues[] = createLink($rowName, $row['table_name'], $rowLinkId, $row['uuid'] ?? '');
        }

        // 3. Optional Related-To column, e.g. for group memberships, we show the user as main name and the group as related
        //    Similarly, files/folders, organizations, guestbook comments, etc. show their parent as related
        if ($showRelatedColumn) {
            $relatedName = $row['related_name'];
            $relatedTable = $row['table_name'];
            if ($row['table_name'] == 'members') {
                $relatedTable = 'roles';
            }
            if ($row['table_name'] == 'guestbook_comments') {
                $relatedTable = 'guestbook';
            }
            if ($row['table_name'] == 'files') {
                $relatedTable = 'folders';
            }
            if ($row['table_name'] == 'roles_rights_data') {
                $relatedTable = 'roles';
            }
            if ($row['table_name'] == 'list_columns') {
                // The related item is either a user field or a column name mem_ or usr_ -> in the latter case, convert it to a translatable string and translate
                if (!empty($relatedName) && (str_starts_with($relatedName, 'mem_') || str_starts_with($relatedName, 'usr_'))) {
                    $relatedName = $fieldString[$relatedName];
                    if (is_array($relatedName)) {
                        $relatedName = $relatedName['name'];
                    }
                    $relatedName = Language::translateIfTranslationStrId($relatedName);
                }
                $relatedTable = 'user_fields';
            }
            if (!empty($relatedName)) {
                $relID = 0;
                $relUUID = '';
                $rid = $row['related_id'];
                if (empty($rid)) {
                    // do nothing
                    $columnValues[] = $relatedName;
                } elseif (ctype_digit($rid)) { // numeric related_ID -> Interpret it as ID
                    $relID = (int)$row['related_id'];
                    $columnValues[] = createLink($relatedName, $relatedTable, $relID, $relUUID);
                } else { // non-numeric related_ID -> Interpret it as UUID
                    $relUUID = $row['related_id'];
                    $columnValues[] = createLink($relatedName, $relatedTable, $relID, $relUUID);
                }
            } else {
                $columnValues[] = '';
            }
        }

        // 4. The field that was changed. For record creation/deletion, show an indicator, too.
        if ($row['action'] == "DELETED") {
            $columnValues[] = '<em>['.$gL10n->get('SYS_DELETED').']</em>';
        } elseif ($row['action'] == 'CREATED') {
            $columnValues[] = '<em>['.$gL10n->get('SYS_CREATED').']</em>';
        } elseif (!empty($fieldInfo)) {
            // Note: Even for user fields, we don't want to use the current user field name from the database, but the name stored in the log table from the time the change was done!.
            $fieldName = (is_array($fieldInfo) && isset($fieldInfo['name'])) ? $fieldInfo['name'] : $fieldInfo;
            $columnValues[] = Language::translateIfTranslationStrId($fieldName); // TODO_RK: Use field_id to link to the field -> Target depends on the table!!!!
        } else {
            $table->setDatatablesOrderColumns(array(array(5, 'desc')));
        }


        // 5. Show new and old values; For some tables we know further details about formatting
        $valueNew = $row['value_new'];
        $valueOld = $row['value_old'];
        if ($row['table_name'] == 'user_data') {
            // Format the values depending on the user field type:
            $valueNew = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueNew);
            $valueOld = $gProfileFields->getHtmlValue($gProfileFields->getPropertyById((int) $row['field'], 'usf_name_intern'), $valueOld);
        } elseif (is_array($fieldInfo) && isset($fieldInfo['type'])) {
            $valueNew = formatValue($valueNew, $fieldInfo['type']);
            $valueOld = formatValue($valueOld, $fieldInfo['type']);
        }

        $columnValues[] = (!empty($valueNew)) ? $valueNew : '&nbsp;';
        $columnValues[] = (!empty($valueOld)) ? $valueOld : '&nbsp;';

        // 6. User and date of the change
        $columnValues[] = createLink($row['create_last_name'].', '.$row['create_first_name'], 'users', 0, $row['uuid_usr_create']);
        // $columnValues[] = '<a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/profile/profile.php', array('user_uuid' => $row['uuid_usr_create'])).'">'..'</a>';
        $columnValues[] = $timestampCreate->format($gSettingsManager->getString('system_date').' '.$gSettingsManager->getString('system_time'));
        $table->addRowByArray($columnValues);
    }

    $page->addHtml($table->show());
    $page->show();
} catch (Exception $e) {
    $gMessage->show($e->getMessage());
}
