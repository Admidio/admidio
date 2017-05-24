<?php
/**
 ***********************************************************************************************
 * Installation step: start_installation
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'start_installation.php')
{
    exit('This page may not be called directly!');
}

// Check if configuration file exists. This file must be copied to the base folder of the Admidio installation.
if (!is_file($pathConfigFile))
{
    showNotice(
        $gL10n->get('INS_CONFIGURATION_FILE_NOT_FOUND', 'config.php'),
        'installation.php?step=create_config',
        $gL10n->get('SYS_BACK'),
        'layout/back.png'
    );
    // => EXIT
}

// set execution time to 5 minutes because we have a lot to do :)
// there should be no error output because of safe mode
@set_time_limit(300);

// first check if session is filled (if installation was aborted then this is not filled)
// if previous dialogs were filled then check if the settings are equal to config file
if (isset($_SESSION['prefix'])
&&    ($g_tbl_praefix  !== $_SESSION['prefix']
    || $gDbType        !== $_SESSION['db_type']
    || $g_adm_srv      !== $_SESSION['db_host']
    || $g_adm_port     !== $_SESSION['db_port']
    || $g_adm_db       !== $_SESSION['db_database']
    || $g_adm_usr      !== $_SESSION['db_user']
    || $g_adm_pw       !== $_SESSION['db_password']
    || $g_organization !== $_SESSION['orga_shortname']))
{
    showNotice(
        $gL10n->get('INS_DATA_DO_NOT_MATCH', 'config.php'),
        'installation.php?step=create_config',
        $gL10n->get('SYS_BACK'),
        'layout/back.png'
    );
    // => EXIT
}

// read data from sql script db.sql and execute all statements to the current database
$sqlQueryResult = querySqlFile($db, 'db.sql');

if (is_string($sqlQueryResult))
{
    showNotice($sqlQueryResult, 'installation.php?step=create_config', $gL10n->get('SYS_BACK'), 'layout/back.png');
    // => EXIT
}

// create default data

// add system component to database
$component = new ComponentUpdate($db);
$component->setValue('com_type', 'SYSTEM');
$component->setValue('com_name', 'Admidio Core');
$component->setValue('com_name_intern', 'CORE');
$component->setValue('com_version', ADMIDIO_VERSION);
$component->setValue('com_beta', (string) ADMIDIO_VERSION_BETA);
$component->setValue('com_update_step', $component->getMaxUpdateStep());
$component->save();

// create a hidden system user for internal use
// all recordsets created by installation will get the create id of the system user
$gCurrentUser = new TableAccess($db, TBL_USERS, 'usr');
$gCurrentUser->setValue('usr_login_name', $gL10n->get('SYS_SYSTEM'));
$gCurrentUser->setValue('usr_valid', '0');
$gCurrentUser->setValue('usr_timestamp_create', DATETIME_NOW);
$gCurrentUser->save(false); // no registered user -> UserIdCreate couldn't be filled
$currUsrId = (int) $gCurrentUser->getValue('usr_id');

// create all modules components
$sql = 'INSERT INTO '.TBL_COMPONENTS.' (com_type, com_name, com_name_intern, com_version, com_beta)
        VALUES (\'MODULE\', \'ANN_ANNOUNCEMENTS\',       \'ANNOUCEMENTS\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'BAC_DATABASE_BACKUP\',     \'BACKUP\',       \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_CATEGORIES\',          \'CATEGORIES\',   \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'DAT_DATES\',               \'DATES\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'DOW_DOWNLOADS\',           \'DOWNLOADS\',    \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'GBO_GUESTBOOK\',           \'GUESTBOOK\',    \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'LNK_WEBLINKS\',            \'LINKS\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'LST_LISTS\',               \'LISTS\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'MEM_USER_MANAGEMENT\',     \'MEMBERS\',      \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_MESSAGES\',            \'MESSAGES\',     \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'PHO_PHOTOS\',              \'PHOTOS\',       \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_SETTINGS\',            \'PREFERENCES\',  \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'PRO_PROFILE\',             \'PROFILE\',      \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'SYS_REGISTRATION\',        \'REGISTRATION\', \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'ROL_ROLE_ADMINISTRATION\', \'ROLES\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')
             , (\'MODULE\', \'ROO_ROOM_MANAGEMENT\',     \'ROOMS\',        \''.ADMIDIO_VERSION.'\', '.ADMIDIO_VERSION_BETA.')';
$db->query($sql); // TODO add more params

// create organization independent categories
$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'MASTER_DATA\', \'SYS_MASTER_DATA\', 1, 1, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));
$categoryIdMasterData = $db->lastInsertId();

$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'SOCIAL_NETWORKS\', \'SYS_SOCIAL_NETWORKS\', 0, 2, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));
$categoryIdSocialNetworks = $db->lastInsertId();

$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'USF\', \'ADDIDIONAL_DATA\', \'INS_ADDIDIONAL_DATA\', 0, 0, 3, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));

// create inventory categories
$sql = 'INSERT INTO '.TBL_CATEGORIES.' (cat_org_id, cat_type, cat_name_intern, cat_name, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
        VALUES (NULL, \'INF\', \'MASTER_DATA\', \'SYS_MASTER_DATA\', 1, 1, ?, ?) -- $currUsrId, DATETIME_NOW';
$db->queryPrepared($sql, array($currUsrId, DATETIME_NOW));
$categoryIdMasterInventory = $db->lastInsertId();

// create roles rights
$sql = 'INSERT INTO '.TBL_ROLES_RIGHTS.' (ror_name_intern, ror_table)
        VALUES (\'folder_view\',   \'adm_folders\')
             , (\'folder_upload\', \'adm_folders\')';
$db->queryPrepared($sql);

// create profile fields of category master data
$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_value_list, usf_system, usf_disabled, usf_mandatory, usf_sequence, usf_usr_id_create, usf_timestamp_create)
        VALUES ('.$categoryIdMasterData.', \'TEXT\',         \'LAST_NAME\',  \'SYS_LASTNAME\',  NULL, NULL, 1, 1, 1, 1,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'FIRST_NAME\', \'SYS_FIRSTNAME\', NULL, NULL, 1, 1, 1, 2,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'STREET\',     \'SYS_STREET\',    NULL, NULL, 0, 0, 0, 3,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'POSTCODE\',   \'SYS_POSTCODE\',  NULL, NULL, 0, 0, 0, 4,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'CITY\',       \'SYS_CITY\',      NULL, NULL, 0, 0, 0, 5,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'TEXT\',         \'COUNTRY\',    \'SYS_COUNTRY\',   NULL, NULL, 0, 0, 0, 6,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'PHONE\',      \'SYS_PHONE\',     NULL, NULL, 0, 0, 0, 7,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'MOBILE\',     \'SYS_MOBILE\',    NULL, NULL, 0, 0, 0, 8,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'PHONE\',        \'FAX\',        \'SYS_FAX\',       NULL, NULL, 0, 0, 0, 9,  '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'DATE\',         \'BIRTHDAY\',   \'SYS_BIRTHDAY\',  NULL, NULL, 0, 0, 0, 10, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'RADIO_BUTTON\', \'GENDER\',     \'SYS_GENDER\',    NULL, \'male.png|SYS_MALE
female.png|SYS_FEMALE\', 0, 0, 0, 11, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'EMAIL\',        \'EMAIL\',      \'SYS_EMAIL\',     NULL, NULL, 1, 0, 1, 12, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdMasterData.', \'URL\',          \'WEBSITE\',    \'SYS_WEBSITE\',   NULL, NULL, 0, 0, 0, 13, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

// create profile fields of category social networks
$sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
        VALUES ('.$categoryIdSocialNetworks.', \'TEXT\', \'AOL_INSTANT_MESSENGER\', \'INS_AOL_INSTANT_MESSENGER\', NULL,                              \'aim.png\',         NULL,                                             0, 1, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'FACEBOOK\',              \'INS_FACEBOOK\',    \''.$gL10n->get('INS_FACEBOOK_DESC').'\',    \'facebook.png\',    \'https://www.facebook.com/#user_content#\',      0, 2, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'GOOGLE_PLUS\',           \'INS_GOOGLE_PLUS\', \''.$gL10n->get('INS_GOOGLE_PLUS_DESC').'\', \'google_plus.png\', \'https://plus.google.com/#user_content#/posts\', 0, 3, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'ICQ\',                   \'INS_ICQ\',         \''.$gL10n->get('INS_ICQ_DESC').'\',         \'icq.png\',         \'https://www.icq.com/people/#user_content#\',    0, 4, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'SKYPE\',                 \'INS_SKYPE\',       \''.$gL10n->get('INS_SKYPE_DESC').'\',       \'skype.png\',       NULL,                                             0, 5, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'TWITTER\',               \'INS_TWITTER\',     \''.$gL10n->get('INS_TWITTER_DESC').'\',     \'twitter.png\',     \'https://twitter.com/#user_content#\',           0, 6, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'XING\',                  \'INS_XING\',        \''.$gL10n->get('INS_XING_DESC').'\',        \'xing.png\',        \'https://www.xing.com/profile/#user_content#\',  0, 7, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , ('.$categoryIdSocialNetworks.', \'TEXT\', \'YAHOO_MESSENGER\',       \'INS_YAHOO_MESSENGER\', NULL,                                    \'yahoo.png\',       NULL,                                             0, 8, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

// create user relation types
$sql = 'INSERT INTO '.TBL_USER_RELATION_TYPES.' (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
        VALUES (1, \''.$gL10n->get('INS_PARENT').'\',      \''.$gL10n->get('INS_FATHER').'\',           \''.$gL10n->get('INS_MOTHER').'\',          null, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (2, \''.$gL10n->get('INS_CHILD').'\',       \''.$gL10n->get('INS_SON').'\',              \''.$gL10n->get('INS_DAUGHTER').'\',           1, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (3, \''.$gL10n->get('INS_SIBLING').'\',     \''.$gL10n->get('INS_BROTHER').'\',          \''.$gL10n->get('INS_SISTER').'\',             3, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (4, \''.$gL10n->get('INS_SPOUSE').'\',      \''.$gL10n->get('INS_HUSBAND').'\',          \''.$gL10n->get('INS_WIFE').'\',               4, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (5, \''.$gL10n->get('INS_COHABITANT').'\',  \''.$gL10n->get('INS_COHABITANT_MALE').'\',  \''.$gL10n->get('INS_COHABITANT_FEMALE').'\',  5, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (6, \''.$gL10n->get('INS_COMPANION').'\',   \''.$gL10n->get('INS_BOYFRIEND').'\',        \''.$gL10n->get('INS_GIRLFRIEND').'\',         6, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (7, \''.$gL10n->get('INS_SUPERIOR').'\',    \''.$gL10n->get('INS_SUPERIOR_MALE').'\',    \''.$gL10n->get('INS_SUPERIOR_FEMALE').'\', null, '.$currUsrId.', \''. DATETIME_NOW.'\')
             , (8, \''.$gL10n->get('INS_SUBORDINATE').'\', \''.$gL10n->get('INS_SUBORDINATE_MALE').'\', \''.$gL10n->get('INS_SUBORDINATE_FEMALE').'\', 7, '.$currUsrId.', \''. DATETIME_NOW.'\')';
$db->query($sql); // TODO add more params

$sql = 'UPDATE '.TBL_USER_RELATION_TYPES.'
           SET urt_id_inverse = 2
         WHERE urt_id = 1';
$db->queryPrepared($sql);

$sql = 'UPDATE '.TBL_USER_RELATION_TYPES.'
           SET urt_id_inverse = 8
         WHERE urt_id = 7';
$db->queryPrepared($sql);

// Inventoryfelder anlegen
$sql = 'INSERT INTO '.TBL_INVENT_FIELDS.' (inf_cat_id, inf_type, inf_name_intern, inf_name, inf_description, inf_system, inf_disabled, inf_mandatory, inf_sequence, inf_usr_id_create, inf_timestamp_create)
        VALUES (?, \'TEXT\',   \'ITEM_NAME\', \'SYS_ITEMNAME\', NULL, 1, 1, 1, 1, ?, ?) -- $categoryIdMasterInventory, $currUsrId, DATETIME_NOW
             , (?, \'NUMBER\', \'ROOM_ID\',   \'SYS_ROOM\',     NULL, 1, 1, 1, 2, ?, ?) -- $categoryIdMasterInventory, $currUsrId, DATETIME_NOW
             , (?, \'NUMBER\', \'PRICE\',     \'SYS_QUANTITY\', NULL, 0, 0, 0, 3, ?, ?) -- $categoryIdMasterInventory, $currUsrId, DATETIME_NOW';
$params = array(
    $categoryIdMasterInventory, $currUsrId, DATETIME_NOW,
    $categoryIdMasterInventory, $currUsrId, DATETIME_NOW,
    $categoryIdMasterInventory, $currUsrId, DATETIME_NOW
);
$db->queryPrepared($sql, $params);

disableSoundexSearchIfPgSql($db);

// create new organization
$gCurrentOrganization = new Organization($db, $_SESSION['orga_shortname']);
$gCurrentOrganization->setValue('org_longname', $_SESSION['orga_longname']);
$gCurrentOrganization->setValue('org_shortname', $_SESSION['orga_shortname']);
$gCurrentOrganization->setValue('org_homepage', ADMIDIO_URL);
$gCurrentOrganization->save();

// create administrator and assign roles
$administrator = new User($db);
$administrator->setValue('usr_login_name', $_SESSION['user_login']);
$administrator->setPassword($_SESSION['user_password']);
$administrator->setValue('usr_usr_id_create', $currUsrId);
$administrator->setValue('usr_timestamp_create', DATETIME_NOW);
$administrator->save(false); // no registered user -> UserIdCreate couldn't be filled

// write all preferences from preferences.php in table adm_preferences
require_once(ADMIDIO_PATH . '/adm_program/installation/db_scripts/preferences.php');

// set some specific preferences whose values came from user input of the installation wizard
$defaultOrgPreferences['email_administrator'] = $_SESSION['orga_email'];
$defaultOrgPreferences['system_language']     = $language;

// calculate the best cost value for your server performance
$benchmarkResults = PasswordHashing::costBenchmark(0.35, 'password', $gPasswordHashAlgorithm);
$defaultOrgPreferences['system_hashing_cost'] = $benchmarkResults['cost'];

// create all necessary data for this organization
$gCurrentOrganization->setPreferences($defaultOrgPreferences, false);
$gCurrentOrganization->createBasicData((int) $administrator->getValue('usr_id'));

// create default room for room module in database
$sql = 'INSERT INTO '.TBL_ROOMS.' (room_name, room_description, room_capacity, room_usr_id_create, room_timestamp_create)
        VALUES (?, ?, 15, ?, ?) -- $gL10n->get(\'INS_CONFERENCE_ROOM\'), $gL10n->get(\'INS_DESCRIPTION_CONFERENCE_ROOM\'), $currUsrId, DATETIME_NOW';
$params = array(
    $gL10n->get('INS_CONFERENCE_ROOM'),
    $gL10n->get('INS_DESCRIPTION_CONFERENCE_ROOM'),
    $currUsrId,
    DATETIME_NOW
);
$db->queryPrepared($sql, $params);

// first create a user object "current user" with administrator rights
// because administrator is allowed to edit firstname and lastname
$gCurrentUser = new User($db, $gProfileFields, $administrator->getValue('usr_id'));
$gCurrentUser->setValue('LAST_NAME',  $_SESSION['user_last_name']);
$gCurrentUser->setValue('FIRST_NAME', $_SESSION['user_first_name']);
$gCurrentUser->setValue('EMAIL',      $_SESSION['user_email']);
$gCurrentUser->save(false);

// now create a full user object for system user
$systemUser = new User($db, $gProfileFields, $currUsrId);
$systemUser->setValue('LAST_NAME', $gL10n->get('SYS_SYSTEM'));
$systemUser->save(false); // no registered user -> UserIdCreate couldn't be filled

// now set current user to system user
$gCurrentUser->readDataById($currUsrId);

// delete session data
session_unset();

// text for dialog
$text = $gL10n->get('INS_INSTALLATION_SUCCESSFUL').'<br /><br />'.$gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT');
if (!is_writable(ADMIDIO_PATH . FOLDER_DATA))
{
    $text = $text.'
        <div class="alert alert-warning alert-small" role="alert">
            <span class="glyphicon glyphicon-warning-sign"></span>
            '.$gL10n->get('INS_FOLDER_NOT_WRITABLE', 'adm_my_files').'
        </div>';
}

$gLogger->info('INSTALLATION: Installation successfully complete');

// show dialog with success notification
$form = new HtmlFormInstallation('installation-form', ADMIDIO_HOMEPAGE.'donate.php');
$form->setFormDescription(
    $text,
    '<div class="alert alert-success form-alert">
        <span class="glyphicon glyphicon-ok"></span>
        <strong>'.$gL10n->get('INS_INSTALLATION_WAS_SUCCESSFUL').'</strong>
    </div>'
);
$form->openButtonGroup();
$form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'layout/money.png'));
$form->addButton('main_page', $gL10n->get('SYS_LATER'), array('icon' => 'layout/application_view_list.png', 'link' => '../index.php'));
$form->closeButtonGroup();
echo $form->show();
