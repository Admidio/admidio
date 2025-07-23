<?php

namespace Plugins\Birthday\classes;

use Admidio\Roles\Service\RolesService;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Plugins\PluginAbstract;

use InvalidArgumentException;
use Exception;
use Throwable;
use DateTime;

/**
 ***********************************************************************************************
 * Birthday
 *
 * The plugin lists all users who have birthday in a defined timespan.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
class Birthday extends PluginAbstract
{
    private static bool $birthdayShowNames = false;
    /** 
     * Get the plugin configuration
     * @return array Returns the plugin configuration
     */
    public static function getPluginConfig() : array
    {
        // get the plugin config from the parent class
        $config = parent::getPluginConfig();

        // if the key equals 'birthday_roles_view_plugin' and the value is still the default value, retrieve the categories from the database
        if (array_key_exists('birthday_roles_view_plugin', $config) && $config['birthday_roles_view_plugin']['value'] === self::$defaultConfig['birthday_roles_view_plugin']['value']) {
            $config['birthday_roles_view_plugin']['value'] = self::getAvailableRoles(1, true);
        } 
        if (array_key_exists('birthday_roles_sql', $config) && $config['birthday_roles_sql']['value'] === self::$defaultConfig['birthday_roles_sql']['value']) {
            $config['birthday_roles_sql']['value'] = self::getAvailableRoles(1, true);
        }
        return $config;
    }
    
    /**
     * Get the plugin configuration values
     * @return array Returns the plugin configuration values
     */
    public static function getPluginConfigValues() : array
    {
        // get the plugin config values from the parent class
        $config = parent::getPluginConfigValues();

        // if the key equals 'birthday_roles_view_plugin' and the value is still the default value, retrieve the categories from the database
        if (array_key_exists('birthday_roles_view_plugin', $config) && $config['birthday_roles_view_plugin'] === self::$defaultConfig['birthday_roles_view_plugin']['value']) {
            $config['birthday_roles_view_plugin'] = self::getAvailableRoles(1, true);
        } 
        if (array_key_exists('birthday_roles_sql', $config) && $config['birthday_roles_sql'] === self::$defaultConfig['birthday_roles_sql']['value']) {
            $config['birthday_roles_sql'] = self::getAvailableRoles(1, true);
        }

        return $config;
    }

    /**
     * Get the available roles for the birthday plugin
     * @param int $roleType The type of roles to retrieve (0 for inactive, 1 for active, 2 for only event participation roles)
     * @param bool $onlyIds If true, only the IDs of the roles are returned
     * @return array Returns an array with the available roles
     */
    public static function getAvailableRoles($roleType = 1, bool $onlyIds = false): array {
        global $gDb;

        $allRolesSet = array();
        $rolesService = new RolesService($gDb);
        $data = $rolesService->findAll($roleType);

        foreach ($data as $rowViewRoles) {
            if ($onlyIds) {
                // If only the IDs are requested, return an array with the role IDs
                $allRolesSet[] = $rowViewRoles['rol_id'];
            } else {
                // Each role is now added to this array
                $allRolesSet[] = array(
                    $rowViewRoles['rol_id'], // ID 
                    $rowViewRoles['rol_name']
                );
            }
        }
        return $allRolesSet;
    }

    private static function getBirthdaysData() : array
    {
        global $gSettingsManager, $gCurrentUser, $gDb, $gL10n, $gProfileFields, $gValidLogin, $gDbType, $gCurrentOrgId;

        $config = self::getPluginConfigValues();

        // check if only members of configured roles could view birthday
        if ($gValidLogin) {
            if (isset($config['birthday_roles_view_plugin']) && count($config['birthday_roles_view_plugin']) > 0) {
                // current user must be member of at least one listed role
                if (count(array_intersect($config['birthday_roles_view_plugin'], $gCurrentUser->getRoleMemberships())) > 0) {
                    self::$birthdayShowNames = true;
                }
            }
        } else {
            if ($config['birthday_show_names_extern'] === 1) {
                // every visitor is allowed to view birthdays
                self::$birthdayShowNames = true;
            }
        }

        // Check if the role condition has been set
        if (isset($config['birthday_roles_sql']) && is_array($config['birthday_roles_sql']) && count($config['birthday_roles_sql']) > 0) {
            $sqlRol = 'IN (' . implode(',', $config['birthday_roles_sql']) . ')';
        } else {
            $sqlRol = 'IS NOT NULL';
        }

        // Check if the sort condition has been set
        if (!isset($config['birthday_sort_sql']) || $config['birthday_sort_sql'] === '') {
            $sqlSort = 'DESC';
        } else {
            $sqlSort = $config['birthday_sort_sql'];
        }

        // if no birthdays should be shown than disable future and former birthday periods
        if (!self::$birthdayShowNames) {
            $config['birthday_show_past'] = 0;
            $config['birthday_show_future'] = 0;
        }

        $fieldBirthday = $gProfileFields->getProperty('BIRTHDAY', 'usf_id');

        if ($gDbType === 'pgsql') {
                $sql = 'SELECT DISTINCT usr_id, usr_uuid, usr_login_name,
                                last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                                birthday.bday AS birthday, birthday.bdate,
                                EXTRACT(DAY FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\') - birthday.bdate) * (-1) AS days_to_bdate, -- DATE_NOW
                                EXTRACT(YEAR FROM bdate) - EXTRACT(YEAR FROM TO_TIMESTAMP(bday, \'YYYY-MM-DD\')) AS age,
                                email.usd_value AS email, gender.usd_value AS gender
                FROM ' . TBL_USERS . ' AS users
            INNER JOIN ( (SELECT usd_usr_id, usd_value AS bday,
                                TO_DATE(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\')) || TO_CHAR(TO_TIMESTAMP(bd1.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\') AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd1
                        WHERE EXTRACT(DAY FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\') - TO_TIMESTAMP(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\')) || TO_CHAR(TO_TIMESTAMP(bd1.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\')) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    UNION
                        (SELECT usd_usr_id, usd_value AS bday,
                                TO_DATE(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\'))-1 || TO_CHAR(TO_TIMESTAMP(bd2.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\') AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd2
                        WHERE EXTRACT(DAY FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\') - TO_TIMESTAMP(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\')- INTERVAL \'1 year\') || TO_CHAR(TO_TIMESTAMP(bd2.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\')) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    UNION
                        (SELECT usd_usr_id, usd_value AS bday,
                                TO_DATE(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\'))+1 || TO_CHAR(TO_TIMESTAMP(bd3.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\') AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd3
                        WHERE EXTRACT(DAY FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\') - TO_TIMESTAMP(EXTRACT(YEAR FROM TO_TIMESTAMP(?, \'YYYY-MM-DD\')+ INTERVAL \'1 year\') || TO_CHAR(TO_TIMESTAMP(bd3.usd_value, \'YYYY-MM-DD\'), \'-MM-DD\'), \'YYYY-MM-DD\')) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    ) AS birthday
                    ON birthday.usd_usr_id = usr_id
            LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS email
                    ON email.usd_usr_id = usr_id
                AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS gender
                    ON gender.usd_usr_id = usr_id
                AND gender.usd_usf_id = ? -- $gProfileFields->getProperty(\'GENDER\', \'usf_id\')
            LEFT JOIN ' . TBL_MEMBERS . '
                    ON mem_usr_id = usr_id
                AND mem_begin <= ? -- DATE_NOW
                AND mem_end    > ? -- DATE_NOW
            INNER JOIN ' . TBL_ROLES . '
                    ON mem_rol_id = rol_id
                AND rol_valid  = true
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON rol_cat_id = cat_id
                AND cat_org_id = ? -- $gCurrentOrgId
                WHERE usr_valid = true
                AND mem_rol_id ' . $sqlRol . '
            ORDER BY days_to_bdate ' . $sqlSort . ', last_name, first_name';
        } else {
                $sql = 'SELECT DISTINCT usr_id, usr_uuid, usr_login_name,
                                last_name.usd_value AS last_name, first_name.usd_value AS first_name,
                                birthday.bday AS birthday, birthday.bdate,
                                DATEDIFF(birthday.bdate, ?) AS days_to_bdate, -- DATE_NOW
                                YEAR(bdate) - YEAR(bday) AS age,
                                email.usd_value AS email, gender.usd_value AS gender
                FROM ' . TBL_USERS . ' AS users
            INNER JOIN ( (SELECT usd_usr_id, usd_value AS bday,
                                CONCAT(YEAR(?), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd1
                        WHERE DATEDIFF(CONCAT(YEAR(?), DATE_FORMAT(bd1.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    UNION
                        (SELECT usd_usr_id, usd_value AS bday,
                                CONCAT(YEAR(?)-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd2
                        WHERE DATEDIFF(CONCAT(YEAR(?)-1, DATE_FORMAT(bd2.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    UNION
                        (SELECT usd_usr_id, usd_value AS bday,
                                CONCAT(YEAR(?)+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')) AS bdate -- DATE_NOW
                            FROM ' . TBL_USER_DATA . ' AS bd3
                        WHERE DATEDIFF(CONCAT(YEAR(?)+1, DATE_FORMAT(bd3.usd_value, \'-%m-%d\')), ?) -- DATE_NOW,DATE_NOW
                        BETWEEN ? AND ? -- -$config[\'birthday_show_past\'] AND $config[\'birthday_show_future\']
                            AND usd_usf_id = ?) -- $fieldBirthday
                    ) AS birthday
                    ON birthday.usd_usr_id = usr_id
            LEFT JOIN ' . TBL_USER_DATA . ' AS last_name
                    ON last_name.usd_usr_id = usr_id
                AND last_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'LAST_NAME\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS first_name
                    ON first_name.usd_usr_id = usr_id
                AND first_name.usd_usf_id = ? -- $gProfileFields->getProperty(\'FIRST_NAME\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS email
                    ON email.usd_usr_id = usr_id
                AND email.usd_usf_id = ? -- $gProfileFields->getProperty(\'EMAIL\', \'usf_id\')
            LEFT JOIN ' . TBL_USER_DATA . ' AS gender
                    ON gender.usd_usr_id = usr_id
                AND gender.usd_usf_id = ? -- $gProfileFields->getProperty(\'GENDER\', \'usf_id\')
            LEFT JOIN ' . TBL_MEMBERS . '
                    ON mem_usr_id = usr_id
                AND mem_begin <= ? -- DATE_NOW
                AND mem_end    > ? -- DATE_NOW
            INNER JOIN ' . TBL_ROLES . '
                    ON mem_rol_id = rol_id
                AND rol_valid  = true
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON rol_cat_id = cat_id
                AND cat_org_id = ? -- $gCurrentOrgId
                WHERE usr_valid = true
                AND mem_rol_id ' . $sqlRol . '
            ORDER BY days_to_bdate ' . $sqlSort . ', last_name, first_name';
        }

        $queryParams = array(
            DATE_NOW,
            DATE_NOW, DATE_NOW, DATE_NOW, -$config['birthday_show_past'], $config['birthday_show_future'], $fieldBirthday,
            DATE_NOW, DATE_NOW, DATE_NOW, -$config['birthday_show_past'], $config['birthday_show_future'], $fieldBirthday,
            DATE_NOW, DATE_NOW, DATE_NOW, -$config['birthday_show_past'], $config['birthday_show_future'], $fieldBirthday,
            $gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            $gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $gProfileFields->getProperty('EMAIL', 'usf_id'),
            $gProfileFields->getProperty('GENDER', 'usf_id'),
            DATE_NOW,
            DATE_NOW,
            $gCurrentOrgId
            // TODO add more params
        );
        $birthdayStatement = $gDb->queryPrepared($sql, $queryParams);

        $numberBirthdays = $birthdayStatement->rowCount();

        $birthdayArray = array();

        if ($numberBirthdays > 0) {
            if (self::$birthdayShowNames) {
                // how many birthdays should be displayed (as a maximum)
                $birthdayCount = null;

                while (($row = $birthdayStatement->fetch()) && $birthdayCount < $config['birthday_show_display_limit']) {
                    // the display type of the name
                    switch ($config['birthday_show_names']) {
                        case 1:  // first name, last name
                            $plgShowName = $row['first_name'] . ' ' . $row['last_name'];
                            break;
                        case 2:  // last name, first name
                            $plgShowName = $row['last_name'] . ', ' . $row['first_name'];
                            break;
                        case 3:  // first name
                            $plgShowName = $row['first_name'];
                            break;
                        case 4:  // Loginname
                            $plgShowName = $row['usr_login_name'];
                            break;
                        default: // first name, last name
                            $plgShowName = $row['first_name'] . ' ' . $row['last_name'];
                    }

                    // from a specified age, only the last name with salutation is displayed for logged out visitors
                    if ($config['birthday_show_age_salutation'] > -1) {
                        if (!$gValidLogin && $config['birthday_show_age_salutation'] <= $row['age']) {
                            if ($row['gender'] > 1) {
                                $plgShowName = $gL10n->get('PLG_BIRTHDAY_WOMAN_VAR', array($row['last_name']));
                            } else {
                                $plgShowName = $gL10n->get('PLG_BIRTHDAY_MAN_VAR', array($row['last_name']));
                            }
                        }
                    }

                    // show name and e-mail link for registered users
                    if ($gValidLogin) {
                        $plgShowName = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $row['usr_uuid'])) . '"
                            title="' . $gL10n->get('SYS_SHOW_PROFILE') . '">' . $plgShowName . '</a>';

                        // E-Mail-Adresse ist hinterlegt und soll auch bei eingeloggten Benutzern verlinkt werden
                        if ((string)$row['email'] !== '' && $config['birthday_show_email_extern'] < 2) {
                            $plgShowName .= '
                                <a class="admidio-icon-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php', array('user_uuid' => $row['usr_uuid'])) . '">' .
                                '<i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_WRITE_EMAIL') . '"></i></a>';
                        }
                    } elseif ($config['birthday_show_email_extern'] === 1 && strlen($row['email']) > 0) {
                        $plgShowName .= '
                            <a class="admidio-icon-link" href="mailto:' . $row['email'] . '">' .
                            '<i class="bi bi-envelope-fill" data-bs-toggle="tooltip" title="' . $gL10n->get('SYS_WRITE_EMAIL') . '"></i></a>';
                    }

                    // set css class and string for birthday today, in the future or in the past
                    $birthdayDate = DateTime::createFromFormat('Y-m-d', $row['birthday']);

                    if ($row['days_to_bdate'] < 0) {
                        $plgCssClass = 'plgBirthdayNameHighlightAgo';
                        if ($row['days_to_bdate'] == -1) {
                            $birthdayText = 'PLG_BIRTHDAY_YESTERDAY';
                            $plgDays = ' ';
                        } else {
                            $birthdayText = 'PLG_BIRTHDAY_PAST';
                            $plgDays = -$row['days_to_bdate'];
                        }
                    } elseif ($row['days_to_bdate'] > 0) {
                        $plgCssClass = 'plgBirthdayNameHighlightFuture';
                        if ($row['days_to_bdate'] == 1) {
                            $birthdayText = 'PLG_BIRTHDAY_TOMORROW';
                            $plgDays = ' ';
                        } else {
                            $birthdayText = 'PLG_BIRTHDAY_FUTURE';
                            $plgDays = $row['days_to_bdate'];
                        }
                    } else {
                        $plgCssClass = 'plgBirthdayNameHighlight';
                        $birthdayText = 'PLG_BIRTHDAY_TODAY';
                        $plgDays = $row['age'];
                    }

                    // don't show age of birthday person if preference is set
                    if ($config['birthday_show_age'] === 0 || !$gValidLogin) {
                        $birthdayText .= '_NO_AGE';
                    }

                    $birthdayArray[] = array(
                        'userText' => $gL10n->get($birthdayText, array($plgShowName, $plgDays, $row['age'], $birthdayDate->format($gSettingsManager->getString('system_date'))))
                    );

                    // counting displayed birthdays
                    $birthdayCount++;
                }
            }
        }

        return $birthdayArray;
    }

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        // show the announcement list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $birthdayPlugin = new Overview($pluginFolder);

            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }

            if ($gSettingsManager->getInt('birthday_plugin_enabled') === 1 || ($gSettingsManager->getInt('birthday_plugin_enabled') === 2 && $gValidLogin)) {
                $birthdaysArray = self::getBirthdaysData();

                if (!empty($birthdaysArray)) {
                    if (self::$birthdayShowNames) {
                        $birthdayPlugin->assignTemplateVariable('birthdays', $birthdaysArray);
                    } else {
                        if (count($birthdaysArray) === 1) {
                            $birthdayPlugin->assignTemplateVariable('message',$gL10n->get('PLG_BIRTHDAY_ONE_MEMBER'));
                        } else {
                            $birthdayPlugin->assignTemplateVariable('message',$gL10n->get('PLG_BIRTHDAY_MORE_MEMBERS', array(count($birthdaysArray))));
                        }
                    }
                } else {                   
                    // If the configuration is set accordingly, a message is output if no member has a birthday today
                    if ($gSettingsManager->getBool('birthday_show_notice_none')) {
                        $birthdayPlugin->assignTemplateVariable('message',$gL10n->get('PLG_BIRTHDAY_NO_MEMBERS'));
                    }
                }
            } else {
                $birthdayPlugin->assignTemplateVariable('message',$gL10n->get('PLG_BIRTHDAY_NO_ENTRIES_VISITORS'));
            }
            if (isset($page)) {
                echo $birthdayPlugin->html('plugin.birthday.tpl');
            } else {
                $birthdayPlugin->showHtmlPage('plugin.birthday.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}