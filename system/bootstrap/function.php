<?php
/**
 ***********************************************************************************************
 * Various common functions
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\Users\Entity\User;
use Ramsey\Uuid\Uuid;
use Admidio\Infrastructure\Exception;

if (basename($_SERVER['SCRIPT_FILENAME']) === 'function.php') {
    exit('This page may not be called directly!');
}

/**
 * Function checks if the user is a member of the role.
 * If **userId** is not set than this will be checked for the current user
 * @param string $roleName The name of the role where the membership of the user should be checked
 * @param int $userId The id of the user who should be checked if he is a member of the role.
 *                         If @userId is not set than this will be checked for the current user
 * @return bool Returns **true** if the user is a member of the role
 */
function hasRole(string $roleName, int $userId = 0): bool
{
    if ($userId === 0) {
        $userId = $GLOBALS['gCurrentUserId'];
    }

    $sql = 'SELECT mem_id
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND rol_name   = ? -- $roleName
               AND rol_valid  = true
               AND (  cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\'
                   OR cat_org_id IS NULL )';
    $statement = $GLOBALS['gDb']->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $roleName, $GLOBALS['gCurrentOrgId']));

    return $statement->rowCount() === 1;
}

/**
 * Function checks if the user is a member in a role of the current organization.
 * @param int $userId The id of the user who should be checked if he is a member of the current organization
 * @return bool Returns **true** if the user is a member
 */
function isMember(int $userId): bool
{
    if ($userId === 0) {
        return false;
    }

    $sql = 'SELECT COUNT(*) AS count
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND rol_valid  = true
               AND (  cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                   OR cat_org_id IS NULL )';
    $statement = $GLOBALS['gDb']->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $GLOBALS['gCurrentOrgId']));

    return $statement->fetchColumn() > 0;
}

/**
 * Function checks if the user is a group leader in a role of the current organization.
 * If you use the **roleId** parameter you can check if the user is group leader of that role.
 * @param int $userId The id of the user who should be checked if he is a group leader
 * @param int $roleId (optional) If set <> 0 than the function checks if the user is group leader of this role
 *                    otherwise it checks if the user is group leader in one role of the current organization
 * @return bool Returns **true** if the user is a group leader
 */
function isGroupLeader(int $userId, int $roleId = 0): bool
{
    if ($userId === 0) {
        return false;
    }

    $sql = 'SELECT mem_id
              FROM ' . TBL_MEMBERS . '
        INNER JOIN ' . TBL_ROLES . '
                ON rol_id = mem_rol_id
        INNER JOIN ' . TBL_CATEGORIES . '
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND mem_leader = true
               AND rol_valid  = true
               AND (  cat_org_id = ? -- $GLOBALS[\'gCurrentOrgId\']
                   OR cat_org_id IS NULL )';
    $queryParams = array($userId, DATE_NOW, DATE_NOW, $GLOBALS['gCurrentOrgId']);

    if ($roleId > 0) {
        $sql .= ' AND mem_rol_id = ? -- $roleId';
        $queryParams[] = $roleId;
    }

    $statement = $GLOBALS['gDb']->queryPrepared($sql, $queryParams);

    return $statement->rowCount() > 0;
}

/**
 * This function returns a page navigation depending on the number of pages.
 * Parts of this function are from generatePagination from phpBB2.
 * Example:
 *     Page: < Previous 1  2  3 ... 9  10  11 Next >
 *
 * @param string $baseUrl Basislink zum Modul
 * @param int $itemsCount Gesamtanzahl an Elementen
 * @param int $itemsPerPage Anzahl Elemente pro Seite
 * @param int $pageStartItem Mit dieser Elementnummer beginnt die aktuelle Seite
 * @param bool $addPrevNextText Show link with "Previous" and "Next"
 * @param string $queryParamName (optional) You can set a new name for the parameter that should be used as start parameter.
 * @return string
 * @throws Exception
 */
function admFuncGeneratePagination(string $baseUrl, int $itemsCount, int $itemsPerPage, int $pageStartItem, bool $addPrevNextText = true, string $queryParamName = 'start'): string
{
    global $gL10n;

    if ($itemsCount === 0 || $itemsPerPage === 0) {
        return '';
    }

    $totalPagesCount = (int)ceil($itemsCount / $itemsPerPage);

    if ($totalPagesCount <= 1) {
        return '';
    }

    /**
     * @param int $start
     * @param int $end
     * @param int $page
     * @param string $url
     * @param string $paramName
     * @param int $itemsPerPage
     * @return string
     */
    function getListElementsFromTo(int $start, int $end, int $page, string $url, string $paramName, int $itemsPerPage): string
    {
        $pageNavString = '';

        for ($i = $start; $i < $end; ++$i) {
            if ($i === $page) {
                $pageNavString .= getListElementString((string)$i, 'page-item active');
            } else {
                $pageNavString .= getListElementString((string)$i, 'page-item', $url, $paramName, ($i - 1) * $itemsPerPage);
            }
        }

        return $pageNavString;
    }

    /**
     * @param string $linkText
     * @param string $className
     * @param string $url
     * @param string $paramName
     * @param int|null $paramValue
     * @return string
     */
    function getListElementString(string $linkText, string $className = '', string $url = '', string $paramName = '', int $paramValue = 0): string
    {
        $urlString = '#';
        if ($url !== '') {
            $urlString = $url . '&' . $paramName . '=' . $paramValue;
        }

        return '<li class="page-item ' . $className . '"><a class="page-link" href="' . $urlString . '">' . $linkText . '</a></li>';
    }

    $onPage = (int)floor($pageStartItem / $itemsPerPage) + 1;

    $pageNavigationString = '';

    if ($totalPagesCount > 7) {
        $initPageMax = 3;

        $pageNavigationString .= getListElementsFromTo(1, $initPageMax + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);

        $disabledLink = '<li class="page-item disabled"><a>...</a></li>';

        if ($onPage > 1 && $onPage < $totalPagesCount) {
            $pageNavigationString .= ($onPage > 5) ? $disabledLink : '&nbsp;&nbsp;';

            $initPageMin = ($onPage > 4) ? $onPage : 5;
            $initPageMax = ($onPage < $totalPagesCount - 4) ? $onPage : $totalPagesCount - 4;

            $pageNavigationString .= getListElementsFromTo($initPageMin - 1, $initPageMax + 2, $onPage, $baseUrl, $queryParamName, $itemsPerPage);

            $pageNavigationString .= ($onPage < $totalPagesCount - 4) ? $disabledLink : '&nbsp;&nbsp;';
        } else {
            $pageNavigationString .= $disabledLink;
        }

        $pageNavigationString .= getListElementsFromTo($totalPagesCount - 2, $totalPagesCount + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);
    } else {
        $pageNavigationString .= getListElementsFromTo(1, $totalPagesCount + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);
    }

    if ($addPrevNextText) {
        $pageNavClassPrev = '';
        if ($onPage === 1) {
            $pageNavClassPrev = 'disabled';
        }

        $pageNavClassNext = '';
        if ($onPage === $totalPagesCount) {
            $pageNavClassNext = 'disabled';
        }

        $pageNavigationPrevText = getListElementString($gL10n->get('SYS_BACK'), $pageNavClassPrev, $baseUrl, $queryParamName, ($onPage - 2) * $itemsPerPage);
        $pageNavigationNextText = getListElementString($gL10n->get('SYS_PAGE_NEXT'), $pageNavClassNext, $baseUrl, $queryParamName, $onPage * $itemsPerPage);

        $pageNavigationString = $pageNavigationPrevText . $pageNavigationString . $pageNavigationNextText;
    }

    return '<nav><ul class="pagination">' . $pageNavigationString . '</ul></nav>';
}

/**
 * Verify the content of an array element if it's the expected datatype
 *
 * The function is designed to check the content of **$_GET** and **$_POST** elements and should be used at the
 * beginning of a script. If the value of the defined datatype is not valid then an error will be shown. If no
 * value was set then the parameter will be initialized. The function can be used with every array and their elements.
 * You can set several flags (like required value, datatype …) that should be checked.
 *
 * @param array<string,mixed> $array The array with the element that should be checked
 * @param string $variableName Name of the array element that should be checked
 * @param string $datatype The datatype like **string**, **uuid**, **numeric**, **int**, **float**, **bool**, **boolean**, **html**,
 *                                          **url**, **date**, **file**, **folder** or **array** that is expected and which will be checked.
 *                                          Datatype **date** expects a date that has the Admidio default format from the
 *                                          preferences or the english date format **Y-m-d**
 * @param array<string,mixed> $options (optional) An array with the following possible entries:
 *                                          - defaultValue : A value that will be set if the variable has no value
 *                                          - **requireValue** : If set to **true** than a value is required otherwise the function
 *                                                              returns an error
 *                                          - **validValues** :  An array with all values that the variable could have. If another
 *                                                              value is found than the function returns an error
 *                                          - **directOutput** : If set to **true** the function returns only the error string, if set
 *                                                              to false a html message with the error will be returned
 * @return mixed|null Returns the value of the element or the error message if a test failed
 *
 * **Code example**
 * ```
 * // numeric value that would get a default value 0 if not set
 * $getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', array('defaultValue' => 0));
 *
 * // string that will be initialized with text of id SYS_EVENTS
 * $getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('SYS_EVENTS')));
 *
 * // string initialized with actual and the only allowed values are actual and old
 * $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old')));
 * ```
 * @throws Exception
 */
function admFuncVariableIsValid(array $array, string $variableName, string $datatype, array $options = array())
{
    global $gSettingsManager;

    // create array with all options
    $optionsDefault = array('defaultValue' => null, 'requireValue' => false, 'validValues' => null, 'directOutput' => null);
    $optionsAll = array_replace($optionsDefault, $options);

    // set default value for each datatype if no value is given and no value was required
    if (array_key_exists($variableName, $array) && $array[$variableName] !== '') {
        if ($datatype === 'bool' || $datatype === 'boolean') {
            $value = (bool)$array[$variableName];
        } elseif ($datatype === 'numeric' || $datatype === 'int') {
            $value = (int)$array[$variableName];
        } elseif ($datatype === 'float') {
            $value = (float)$array[$variableName];
        } elseif ($datatype === 'array') {
            $value = $array[$variableName];
        } else {
            $value = (string)$array[$variableName];
        }
    } else {
        if ($optionsAll['requireValue']) {
            // if value is required and no value is given then show error
            throw new Exception('The mandatory parameter "' . $variableName . '" has no value!');
        } elseif ($optionsAll['defaultValue'] !== null) {
            // if a default value was set then take this value
            if (is_string($optionsAll['defaultValue'])) {
                $value = html_entity_decode($optionsAll['defaultValue']);
            } else {
                $value = $optionsAll['defaultValue'];
            }
        } else {
            // no value set then initialize the parameter
            if ($datatype === 'bool' || $datatype === 'boolean') {
                $value = false;
            } elseif ($datatype === 'numeric' || $datatype === 'int') {
                $value = 0;
            } elseif ($datatype === 'float') {
                $value = 0.0;
            } else {
                $value = '';
            }

            return $value;
        }
    }

    // check if parameter has a valid value
    // do a strict check with in_array because the function don't work properly
    if ($optionsAll['validValues'] !== null && !in_array($value, $optionsAll['validValues'], true)) {
        throw new Exception('The parameter "' . $variableName . '" has an invalid value!');
    }

    switch ($datatype) {
        case 'file': // fallthrough
        case 'folder':
            if ($value !== '') {
                StringUtils::strIsValidFileName($value, false);
            }
            break;

        case 'date':
            // check if date is a valid Admidio date format
            $objAdmidioDate = DateTime::createFromFormat($gSettingsManager->getString('system_date'), $value);

            if (!$objAdmidioDate) {
                // check if date has english format
                $objEnglishDate = DateTime::createFromFormat('Y-m-d', $value);

                if (!$objEnglishDate) {
                    throw new Exception('The date parameter "' . $variableName . '" has an invalid date format!');
                }
            }
            break;

        case 'bool': // fallthrough
        case 'boolean':
            $valid = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($valid === null) {
                throw new Exception('The boolean parameter "' . $variableName . '" has an invalid value!');
            }
            $value = $valid;
            break;

        case 'int': // fallthrough
        case 'float': // fallthrough
        case 'numeric':
            // numeric datatype should only contain numbers
            if (!is_numeric($value)) {
                throw new Exception('The numeric parameter ' . $variableName . ' has an invalid value!');
            } else {
                if ($datatype === 'int') {
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                } elseif ($datatype === 'float') {
                    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
                } else {
                    // https://www.php.net/manual/en/function.is-numeric.php#107326
                    $value += 0;
                }
            }
            break;

        case 'string':
            $value = SecurityUtils::encodeHTML(StringUtils::strStripTags($value));
            break;

        case 'html':
            // check html string vor invalid tags and scripts
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('Attr.AllowedFrameTargets', array('_blank', '_top', '_self', '_parent'));
            $config->set('Cache.SerializerPath', ADMIDIO_PATH . FOLDER_DATA . '/templates');

            $filter = new HTMLPurifier($config);
            $value = $filter->purify($value);
            break;

        case 'uuid':
            if (!Uuid::isValid($value)) {
                throw new Exception('The parameter "' . $variableName . '" is not a valid UUID!');
            }
            break;

        case 'url':
            if (!StringUtils::strValidCharacters($value, 'url')) {
                throw new Exception('The parameter "' . $variableName . '" has an invalid URL!');
            }
            break;
    }

    return $value;
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore, all necessary data must be set in the function
 * parameters. If userId is not set then the function will show **deleted user**.
 * @param int $userIdCreated ID of the user who create the recordset.
 * @param string $timestampCreate Date and time of the moment when the user create the recordset.
 * @param int $userIdEdited ID of the user last changed the recordset.
 * @param string $timestampEdited Date and time of the moment when the user last changed the recordset
 * @return string Returns a html string with usernames who creates item and edit item the last time
 * @throws Exception
 * @deprecated 5.0.0:5.1.0 "admFuncShowCreateChangeInfoById()" is deprecated, use "Entity::getNameOfCreatingUser()" instead.
 */
function admFuncShowCreateChangeInfoById(int $userIdCreated, string $timestampCreate, int $userIdEdited = 0, string $timestampEdited = ''): string
{
    global $gDb, $gProfileFields, $gL10n, $gSettingsManager;

    // only show info if system setting is activated
    if ((int)$gSettingsManager->get('system_show_create_edit') === 0) {
        return '';
    }

    // compose name of user who create the recordset
    $htmlCreateName = '';
    $userUuidCreated = '';
    if ($timestampCreate !== '') {
        if ($userIdCreated > 0) {
            $userCreate = new User($gDb, $gProfileFields, $userIdCreated);
            $userUuidCreated = $userCreate->getValue('usr_uuid');

            if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
                $htmlCreateName = $userCreate->getValue('FIRST_NAME') . ' ' . $userCreate->getValue('LAST_NAME');
            } else {
                $htmlCreateName = $userCreate->getValue('usr_login_name');
            }
        } else {
            $htmlCreateName = $gL10n->get('SYS_DELETED_USER');
        }
    }

    // compose name of user who edit the recordset
    $htmlEditName = '';
    $userUuidEdited = '';
    if ($timestampEdited !== '') {
        if ($userIdEdited > 0) {
            $userEdit = new User($gDb, $gProfileFields, $userIdEdited);
            $userUuidEdited = $userEdit->getValue('usr_uuid');

            if ((int)$gSettingsManager->get('system_show_create_edit') === 1) {
                $htmlEditName = $userEdit->getValue('FIRST_NAME') . ' ' . $userEdit->getValue('LAST_NAME');
            } else {
                $htmlEditName = $userEdit->getValue('usr_login_name');
            }
        } else {
            $htmlEditName = $gL10n->get('SYS_DELETED_USER');
        }
    }

    if ($htmlCreateName === '' && $htmlEditName === '') {
        return '';
    }

    // get html output from other function
    return admFuncShowCreateChangeInfoByName(
        $htmlCreateName,
        $timestampCreate,
        $htmlEditName,
        $timestampEdited,
        $userUuidCreated,
        $userUuidEdited
    );
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore, all necessary data must be set in the function
 * parameters. If username is not set then the function will show **deleted user**.
 * @param string $userNameCreated ID of the user who create the recordset.
 * @param string $timestampCreate Date and time of the moment when the user create the recordset.
 * @param string|null $userNameEdited ID of the user last changed the recordset.
 * @param string|null $timestampEdited Date and time of the moment when the user last changed the recordset
 * @param string $userUuidCreated (optional) The uuid of the user who create the recordset.
 *                                      If uuid is set than a link to the user profile will be created
 * @param string $userUuidEdited (optional) The uuid of the user last changed the recordset.
 *                                      If uuid is set than a link to the user profile will be created
 * @return string Returns a html string with usernames who creates item and edit item the last time
 * @throws Exception
 * @deprecated 5.0.0:5.1.0 "admFuncShowCreateChangeInfoByName()" is deprecated, use "Entity::getNameOfCreatingUser()" instead.
 */
function admFuncShowCreateChangeInfoByName(string $userNameCreated, string $timestampCreate, string $userNameEdited = '', string $timestampEdited = '', string $userUuidCreated = '', string $userUuidEdited = ''): string
{
    global $gL10n, $gValidLogin, $gSettingsManager;

    // only show info if system setting is activated
    if ((int)$gSettingsManager->get('system_show_create_edit') === 0) {
        return '';
    }

    $html = '';

    // compose name of user who create the recordset
    if ($timestampCreate !== '') {
        $userNameCreated = trim($userNameCreated);

        if ($userNameCreated === '') {
            $userNameCreated = $gL10n->get('SYS_DELETED_USER');
        }

        // if valid login and a user id is given than create a link to the profile of this user
        if ($gValidLogin && $userUuidCreated !== '' && $userNameCreated !== $gL10n->get('SYS_SYSTEM')) {
            $userNameCreated = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userUuidCreated)) .
                '">' . $userNameCreated . '</a>';
        }

        $html .= '<span class="admidio-info-created">' . $gL10n->get('SYS_CREATED_BY_AND_AT', array($userNameCreated, $timestampCreate)) . '</span>';
    }

    // compose name of user who edit the recordset
    if ($timestampEdited !== '') {
        $userNameEdited = trim($userNameEdited);

        if ($userNameEdited === '') {
            $userNameEdited = $gL10n->get('SYS_DELETED_USER');
        }

        // if valid login and a user id is given than create a link to the profile of this user
        if ($gValidLogin && $userUuidEdited !== '' && $userNameEdited !== $gL10n->get('SYS_SYSTEM')) {
            $userNameEdited = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_uuid' => $userUuidEdited)) .
                '">' . $userNameEdited . '</a>';
        }

        $html .= '<span class="info-edited">' . $gL10n->get('SYS_LAST_EDITED_BY', array($userNameEdited, $timestampEdited)) . '</span>';
    }

    if ($html === '') {
        return '';
    }

    return '<div class="admidio-info-created-edited">' . $html . '</div>';
}

/**
 * Prefix url with "http://" if no protocol is defined and check if is valid url
 * @param $url string
 * @return false|string
 */
function admFuncCheckUrl(string $url)
{
    // Homepage url have to start with "http://"
    if (!StringUtils::strStartsWith($url, 'http://', false) && !StringUtils::strStartsWith($url, 'https://', false)) {
        $url = 'http://' . $url;
    }

    // For Homepage only valid url chars are allowed
    if (!StringUtils::strValidCharacters($url, 'url')) {
        return false;
    }

    return $url;
}

/**
 * This is a safe method for redirecting.
 * @param string $url The URL where redirecting to. Must be an absolute URL. (www.example.org)
 * @param int $statusCode The status-code which should be sent. (301, 302, 303 (default), 307)
 * @throws Exception
 * @see https://www.owasp.org/index.php/Open_redirect
 */
function admRedirect(string $url, int $statusCode = 303)
{
    global $gLogger, $gMessage, $gL10n;

    $loggerObject = array('url' => $url, 'statusCode' => $statusCode);

    if (headers_sent()) {
        $gLogger->error('REDIRECT: Header already sent!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_HEADER_ALREADY_SENT'));
        // => EXIT
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        $gLogger->error('REDIRECT: URL is not a valid URL!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_REDIRECT_URL_INVALID'));
        // => EXIT
    }
    if (!in_array($statusCode, array(301, 302, 303, 307), true)) {
        $gLogger->error('REDIRECT: Status Code is not allowed!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_STATUS_CODE_INVALID'));
        // => EXIT
    }

    // Check if $url starts with the Admidio URL
    if (strpos($url, ADMIDIO_URL) === 0) {
        $gLogger->info('REDIRECT: Redirecting to internal URL!', $loggerObject);

        // TODO check if user is authorized for url
        $redirectUrl = $url;
    } else {
        $gLogger->notice('REDIRECT: Redirecting to external URL!', $loggerObject);

        $redirectUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_SYSTEM . '/redirect.php', array('url' => $url));
    }

    // Detect AJAX
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if ($isAjax) {
        //sent a custom header X-ADMIDIO-REDIRECT to handle he redirect with JavaScript
        $gLogger->info('REDIRECT: Redirecting via AJAX!', $loggerObject);
        header('X-ADMIDIO-REDIRECT: ' . $redirectUrl, true, $statusCode);
        exit();
    } else {
        header('Location: ' . $redirectUrl, true, $statusCode);
        exit();
    }
}

/**
 * Calculates and formats the execution time
 * @param float $startTime The start time
 * @return string Returns the formated execution time
 */
function getExecutionTime(float $startTime): string
{
    $stopTime = microtime(true);

    return number_format(($stopTime - $startTime) * 1000, 6, '.', '') . ' ms';
}

/**
 * Return the proper path for a file provided by themes
 * (main theme, optionally with a fallback theme that
 * provides all files/templates not overridden by the main
 * theme). As the fallback theme will be used, when the main
 * theme does not contain a file, each file needs to be
 * checked separately!
 * @param string filename desired file name relative to the theme path
 * @return string the path to the file including the theme /
 *                fallback theme, depening on which of them provides
 *                the file for real. If the file is not found in either 
 *                the theme and the fallback theme, the path inside
 *                the fallback theme is returned without error.
 */
function getThemedFile(string $filepath): string
{
    $themepath = THEME_PATH . $filepath;
    if (!file_exists($themepath) && defined('THEME_FALLBACK_PATH')) {
        $themepath = THEME_FALLBACK_PATH . $filepath;
    }
    return $themepath;
}


