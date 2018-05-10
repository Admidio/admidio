<?php
/**
 ***********************************************************************************************
 * Various common functions
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
if (basename($_SERVER['SCRIPT_FILENAME']) === 'function.php')
{
    exit('This page may not be called directly!');
}

/**
 * Function checks if the user is a member of the role.
 * If **userId** is not set than this will be checked for the current user
 * @param string $roleName The name of the role where the membership of the user should be checked
 * @param int    $userId   The id of the user who should be checked if he is a member of the role.
 *                         If @userId is not set than this will be checked for the current user
 * @return bool Returns **true** if the user is a member of the role
 */
function hasRole($roleName, $userId = 0)
{
    global $gDb, $gCurrentUser, $gCurrentOrganization;

    if ($userId === 0)
    {
        $userId = (int) $gCurrentUser->getValue('usr_id');
    }

    $sql = 'SELECT mem_id
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND rol_name   = ? -- $roleName
               AND rol_valid  = 1
               AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )';
    $statement = $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, $roleName, (int) $gCurrentOrganization->getValue('org_id')));

    return $statement->rowCount() === 1;
}

/**
 * Function checks if the user is a member in a role of the current organization.
 * @param int $userId The id of the user who should be checked if he is a member of the current organization
 * @return bool Returns **true** if the user is a member
 */
function isMember($userId)
{
    global $gDb, $gCurrentOrganization;

    if ($userId === 0)
    {
        return false;
    }

    $sql = 'SELECT COUNT(*) AS count
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND rol_valid  = 1
               AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )';
    $statement = $gDb->queryPrepared($sql, array($userId, DATE_NOW, DATE_NOW, (int) $gCurrentOrganization->getValue('org_id')));

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
function isGroupLeader($userId, $roleId = 0)
{
    global $gDb, $gCurrentOrganization;

    if ($userId === 0)
    {
        return false;
    }

    $sql = 'SELECT mem_id
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = ? -- $userId
               AND mem_begin <= ? -- DATE_NOW
               AND mem_end    > ? -- DATE_NOW
               AND mem_leader = 1
               AND rol_valid  = 1
               AND (  cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   OR cat_org_id IS NULL )';
    $queryParams = array($userId, DATE_NOW, DATE_NOW, (int) $gCurrentOrganization->getValue('org_id'));

    if ($roleId > 0)
    {
        $sql .= ' AND mem_rol_id = ? -- $roleId';
        $queryParams[] = $roleId;
    }

    $statement = $gDb->queryPrepared($sql, $queryParams);

    return $statement->rowCount() > 0;
}

/**
 * diese Funktion gibt eine Seitennavigation in Anhaengigkeit der Anzahl Seiten zurueck
 * Teile dieser Funktion sind von generatePagination aus phpBB2
 * Beispiel:
 *     Seite: < Vorherige 1  2  3 ... 9  10  11 Naechste >
 *
 * @param string $baseUrl                  Basislink zum Modul (auch schon mit notwendigen Uebergabevariablen)
 * @param int    $itemsCount               Gesamtanzahl an Elementen
 * @param int    $itemsPerPage             Anzahl Elemente pro Seite
 * @param int    $pageStartItem            Mit dieser Elementnummer beginnt die aktuelle Seite
 * @param bool   $addPrevNextText          Links mit "Vorherige" "Naechste" anzeigen
 * @param string $queryParamName (optional) You can set a new name for the parameter that should be used as start parameter.
 * @return string
 */
function admFuncGeneratePagination($baseUrl, $itemsCount, $itemsPerPage, $pageStartItem, $addPrevNextText = true, $queryParamName = 'start')
{
    global $gL10n;

    if ($itemsCount === 0 || $itemsPerPage === 0)
    {
        return '';
    }

    $totalPagesCount = ceil($itemsCount / $itemsPerPage);

    if ($totalPagesCount <= 1)
    {
        return '';
    }

    /**
     * @param int    $start
     * @param int    $end
     * @param int    $page
     * @param string $url
     * @param string $paramName
     * @param int    $itemsPerPage
     * @return string
     */
    function getListElementsFromTo($start, $end, $page, $url, $paramName, $itemsPerPage)
    {
        $pageNavString = '';

        for ($i = $start; $i < $end; ++$i)
        {
            if ($i === $page)
            {
                $pageNavString .= getListElementString((string) $i, 'active');
            }
            else
            {
                $pageNavString .= getListElementString((string) $i, '', $url, $paramName, ($i - 1) * $itemsPerPage);
            }
        }

        return $pageNavString;
    }

    /**
     * @param string $linkText
     * @param string $className
     * @param string $url
     * @param string $paramName
     * @param int    $paramValue
     * @return string
     */
    function getListElementString($linkText, $className = '', $url = '', $paramName = '', $paramValue = null)
    {
        $classString = '';
        if ($className !== '')
        {
            $classString = ' class="'.$className.'"';
        }

        $urlString = '#';
        if ($url !== '' && $className === '')
        {
            $urlString = $url.'&'.$paramName.'='.$paramValue;
        }

        return '<li'.$classString.'><a href="'.$urlString.'">'.$linkText.'</a></li>';
    }

    $onPage = (int) floor($pageStartItem / $itemsPerPage) + 1;

    $pageNavigationString = '';

    if ($totalPagesCount > 7)
    {
        $initPageMax = ($totalPagesCount > 3) ? 3 : $totalPagesCount;

        $pageNavigationString .= getListElementsFromTo(1, $initPageMax + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);

        if ($totalPagesCount > 3)
        {
            $disabledLink = '<li class="disabled"><a>...</a></li>';

            if ($onPage > 1 && $onPage < $totalPagesCount)
            {
                $pageNavigationString .= ($onPage > 5) ? $disabledLink : '&nbsp;&nbsp;';

                $initPageMin = ($onPage > 4) ? $onPage : 5;
                $initPageMax = ($onPage < $totalPagesCount - 4) ? $onPage : $totalPagesCount - 4;

                $pageNavigationString .= getListElementsFromTo($initPageMin - 1, $initPageMax + 2, $onPage, $baseUrl, $queryParamName, $itemsPerPage);

                $pageNavigationString .= ($onPage < $totalPagesCount - 4) ? $disabledLink : '&nbsp;&nbsp;';
            }
            else
            {
                $pageNavigationString .= $disabledLink;
            }

            $pageNavigationString .= getListElementsFromTo($totalPagesCount - 2, $totalPagesCount + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);
        }
    }
    else
    {
        $pageNavigationString .= getListElementsFromTo(1, $totalPagesCount + 1, $onPage, $baseUrl, $queryParamName, $itemsPerPage);
    }

    if ($addPrevNextText)
    {
        $pageNavClassPrev = '';
        if ($onPage === 1)
        {
            $pageNavClassPrev = 'disabled';
        }

        $pageNavClassNext = '';
        if ($onPage === $totalPagesCount)
        {
            $pageNavClassNext = 'disabled';
        }

        $pageNavigationPrevText = getListElementString($gL10n->get('SYS_BACK'),      $pageNavClassPrev, $baseUrl, $queryParamName, ($onPage - 2) * $itemsPerPage);
        $pageNavigationNextText = getListElementString($gL10n->get('SYS_PAGE_NEXT'), $pageNavClassNext, $baseUrl, $queryParamName, $onPage * $itemsPerPage);

        $pageNavigationString = $pageNavigationPrevText.$pageNavigationString.$pageNavigationNextText;
    }

    $pageNavigationString = '<ul class="pagination">'.$pageNavigationString.'</ul>';

    return $pageNavigationString;
}

/**
 * Funktion gibt die maximale Pixelzahl zurück die der Speicher verarbeiten kann
 * @return int
 */
function admFuncProcessableImageSize()
{
    $memoryLimit = PhpIniUtils::getMemoryLimit();
    // if memory_limit is disabled in php.ini
    if ($memoryLimit === -1)
    {
        $memoryLimit = 128 * 1024 * 1024; // 128MB
    }

    // For each Pixel 3 Bytes are necessary (RGB)
    $bytesPerPixel = 3;
    // der Speicher muss doppelt zur Verfügung stehen
    // nach ein paar tests hat sich 2.5x als sicher herausgestellt
    $factor = 2.5;

    return (int) round($memoryLimit / ($bytesPerPixel * $factor));
}

/**
 * Verify the content of an array element if it's the expected datatype
 *
 * The function is designed to check the content of **$_GET** and **$_POST** elements and should be used at the
 * beginning of a script. If the value of the defined datatype is not valid then an error will be shown. If no
 * value was set then the parameter will be initialized. The function can be used with every array and their elements.
 * You can set several flags (like required value, datatype …) that should be checked.
 *
 * @param array<string,mixed> $array        The array with the element that should be checked
 * @param string              $variableName Name of the array element that should be checked
 * @param string              $datatype     The datatype like **string**, **numeric**, **int**, **float**, **bool**, **boolean**, **html**,
 *                                          **date** or **file** that is expected and which will be checked.
 *                                          Datatype **date** expects a date that has the Admidio default format from the
 *                                          preferences or the english date format **Y-m-d**
 * @param array<string,mixed> $options      (optional) An array with the following possible entries:
 *                                          - Value : A value that will be set if the variable has no value
 *                                          - **requireValue** : If set to **true** than a value is required otherwise the function
 *                                                              returns an error
 *                                          - **validValues** :  An array with all values that the variable could have. If another
 *                                                              value is found than the function returns an error
 *                                          - **directOutput** : If set to **true** the function returns only the error string, if set
 *                                                              to false a html message with the error will be returned
 * @return mixed|null Returns the value of the element or the error message if a test failed
 *
 * **Code example:**
 * ```
 * // numeric value that would get a default value 0 if not set
 * $getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', array('defaultValue' => 0));
 *
 * // string that will be initialized with text of id DAT_DATES
 * $getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
 *
 * // string initialized with actual and the only allowed values are actual and old
 * $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old')));
 * ```
 */
function admFuncVariableIsValid(array $array, $variableName, $datatype, array $options = array())
{
    global $gL10n, $gMessage, $gSettingsManager;

    // create array with all options
    $optionsDefault = array('defaultValue' => null, 'requireValue' => false, 'validValues' => null, 'directOutput' => null);
    $optionsAll     = array_replace($optionsDefault, $options);

    $errorMessage = '';
    $value = null;

    // set default value for each datatype if no value is given and no value was required
    if (array_key_exists($variableName, $array) && $array[$variableName] !== '')
    {
        $value = $array[$variableName];
    }
    else
    {
        if ($optionsAll['requireValue'])
        {
            // if value is required an no value is given then show error
            $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
        }
        elseif ($optionsAll['defaultValue'] !== null)
        {
            // if a default value was set then take this value
            $value = $optionsAll['defaultValue'];
        }
        else
        {
            // no value set then initialize the parameter
            if ($datatype === 'bool' || $datatype === 'boolean')
            {
                $value = false;
            }
            elseif ($datatype === 'numeric' || $datatype === 'int')
            {
                $value = 0;
            }
            elseif ($datatype === 'float')
            {
                $value = 0.0;
            }
            else
            {
                $value = '';
            }

            return $value;
        }
    }

    // check if parameter has a valid value
    // do a strict check with in_array because the function don't work properly
    if ($optionsAll['validValues'] !== null && !in_array($value, $optionsAll['validValues'], true))
    {
        $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
    }

    switch ($datatype)
    {
        case 'file':
            try
            {
                if ($value !== '')
                {
                    admStrIsValidFileName($value);
                }
            }
            catch (AdmException $e)
            {
                $errorMessage = $e->getText();
            }
            break;

        case 'date':
            // check if date is a valid Admidio date format
            $objAdmidioDate = \DateTime::createFromFormat($gSettingsManager->getString('system_date'), $value);

            if (!$objAdmidioDate)
            {
                // check if date has english format
                $objEnglishDate = \DateTime::createFromFormat('Y-m-d', $value);

                if (!$objEnglishDate)
                {
                    $errorMessage = $gL10n->get('LST_NOT_VALID_DATE_FORMAT', array($variableName));
                }
            }
            break;

        case 'bool':
        case 'boolean':
            $valid = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($valid === null)
            {
                $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
            }
            $value = $valid;
            break;

        case 'int':
        case 'float':
        case 'numeric':
            // numeric datatype should only contain numbers
            if (!is_numeric($value))
            {
                $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
            }
            else
            {
                if ($datatype === 'int')
                {
                    $value = filter_var($value, FILTER_VALIDATE_INT);
                }
                elseif ($datatype === 'float')
                {
                    $value = filter_var($value, FILTER_VALIDATE_FLOAT);
                }
                else
                {
                    // https://secure.php.net/manual/en/function.is-numeric.php#107326
                    $value += 0;
                }
            }
            break;

        case 'string':
            $value = strStripTags(htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
            break;

        case 'html':
            // check html string vor invalid tags and scripts
            $value = htmLawed(stripslashes($value), array('safe' => 1));
            break;
    }

    // wurde kein Fehler entdeckt, dann den Inhalt der Variablen zurueckgeben
    if ($errorMessage === '')
    {
        return $value;
    }

    if (isset($gMessage) && $gMessage instanceof Message)
    {
        if ($optionsAll['directOutput'])
        {
            $gMessage->showTextOnly(true);
        }

        $gMessage->show($errorMessage);
        // => EXIT
    }
    else
    {
        echo $errorMessage;
        exit();
    }

    return null;
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore all necessary data must be set in the function
 * parameters. If userId is not set then the function will show **deleted user**.
 * @param int         $userIdCreated   Id of the user who create the recordset.
 * @param string      $timestampCreate Date and time of the moment when the user create the recordset.
 * @param int         $userIdEdited    Id of the user last changed the recordset.
 * @param string|null $timestampEdited Date and time of the moment when the user last changed the recordset
 * @return string Returns a html string with usernames who creates item and edit item the last time
 */
function admFuncShowCreateChangeInfoById($userIdCreated, $timestampCreate, $userIdEdited = 0, $timestampEdited = null)
{
    global $gDb, $gProfileFields, $gL10n, $gSettingsManager;

    // only show info if system setting is activated
    if ((int) $gSettingsManager->get('system_show_create_edit') === 0)
    {
        return '';
    }

    // compose name of user who create the recordset
    $htmlCreateName = '';
    if ($timestampCreate)
    {
        if ($userIdCreated > 0)
        {
            $userCreate = new User($gDb, $gProfileFields, $userIdCreated);

            if ((int) $gSettingsManager->get('system_show_create_edit') === 1)
            {
                $htmlCreateName = $userCreate->getValue('FIRST_NAME') . ' ' . $userCreate->getValue('LAST_NAME');
            }
            else
            {
                $htmlCreateName = $userCreate->getValue('usr_login_name');
            }
        }
        else
        {
            $htmlCreateName = $gL10n->get('SYS_DELETED_USER');
        }
    }

    // compose name of user who edit the recordset
    $htmlEditName = '';
    if ($timestampEdited)
    {
        if ($userIdEdited > 0)
        {
            $userEdit = new User($gDb, $gProfileFields, $userIdEdited);

            if ((int) $gSettingsManager->get('system_show_create_edit') === 1)
            {
                $htmlEditName = $userEdit->getValue('FIRST_NAME') . ' ' . $userEdit->getValue('LAST_NAME');
            }
            else
            {
                $htmlEditName = $userEdit->getValue('usr_login_name');
            }
        }
        else
        {
            $htmlEditName = $gL10n->get('SYS_DELETED_USER');
        }
    }

    if ($htmlCreateName === '' && $htmlEditName === '')
    {
        return '';
    }

    // get html output from other function
    return admFuncShowCreateChangeInfoByName(
        $htmlCreateName, $timestampCreate,
        $htmlEditName, $timestampEdited,
        $userIdCreated, $userIdEdited
    );
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore all necessary data must be set in the function
 * parameters. If user name is not set then the function will show **deleted user**.
 * @param string      $userNameCreated Id of the user who create the recordset.
 * @param string      $timestampCreate Date and time of the moment when the user create the recordset.
 * @param string|null $userNameEdited  Id of the user last changed the recordset.
 * @param string|null $timestampEdited Date and time of the moment when the user last changed the recordset
 * @param int         $userIdCreated   (optional) The id of the user who create the recordset.
 *                                     If id is set than a link to the user profile will be created
 * @param int         $userIdEdited    (optional) The id of the user last changed the recordset.
 *                                     If id is set than a link to the user profile will be created
 * @return string Returns a html string with usernames who creates item and edit item the last time
 */
function admFuncShowCreateChangeInfoByName($userNameCreated, $timestampCreate, $userNameEdited = null, $timestampEdited = null, $userIdCreated = 0, $userIdEdited = 0)
{
    global $gL10n, $gValidLogin, $gSettingsManager;

    // only show info if system setting is activated
    if ((int) $gSettingsManager->get('system_show_create_edit') === 0)
    {
        return '';
    }

    $html = '';

    // compose name of user who create the recordset
    if ($timestampCreate)
    {
        $userNameCreated = trim($userNameCreated);

        if ($userNameCreated === '')
        {
            if($userIdCreated === 1)
            {
                $userNameCreated = $gL10n->get('SYS_SYSTEM');                
            }
            else
            {
                $userNameCreated = $gL10n->get('SYS_DELETED_USER');
            }
        }

        // if valid login and a user id is given than create a link to the profile of this user
        if ($gValidLogin && $userIdCreated > 0 && $userNameCreated !== $gL10n->get('SYS_SYSTEM'))
        {
            $userNameCreated = '<a href="' . safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_id' => $userIdCreated)) .
                               '">' . $userNameCreated . '</a>';
        }

        $html .= '<span class="admidio-info-created">' . $gL10n->get('SYS_CREATED_BY', array($userNameCreated, $timestampCreate)) . '</span>';
    }

    // compose name of user who edit the recordset
    if ($timestampEdited)
    {
        $userNameEdited = trim($userNameEdited);

        if ($userNameEdited === '')
        {
            if($userIdEdited === 1)
            {
                $userNameEdited = $gL10n->get('SYS_SYSTEM');                
            }
            else
            {
                $userNameEdited = $gL10n->get('SYS_DELETED_USER');
            }
        }

        // if valid login and a user id is given than create a link to the profile of this user
        if ($gValidLogin && $userIdEdited > 0 && $userNameEdited !== $gL10n->get('SYS_SYSTEM'))
        {
            $userNameEdited = '<a href="' . safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php', array('user_id' => $userIdEdited)) .
                              '">' . $userNameEdited . '</a>';
        }

        $html .= '<span class="info-edited">' . $gL10n->get('SYS_LAST_EDITED_BY', array($userNameEdited, $timestampEdited)) . '</span>';
    }

    if ($html === '')
    {
        return '';
    }

    return '<div class="admidio-info-created-edited">' . $html . '</div>';
}

/**
 * Search all visible files or directories in the specified directory.
 * @param string $directory  The directory where the files or directories should be searched.
 * @param string $searchType This could be **file**, **dir**, **both** or **all** and represent
 *                           the type of entries that should be searched.
 * @return false|array<string,string> Returns an array with all found entries or false if an error occurs.
 */
function admFuncGetDirectoryEntries($directory, $searchType = 'file')
{
    if (!is_dir($directory))
    {
        return false;
    }

    $dirHandle = @opendir($directory);
    if ($dirHandle === false)
    {
        return false;
    }

    $entries = array();

    while (($entry = readdir($dirHandle)) !== false)
    {
        if ($searchType === 'all')
        {
            $entries[$entry] = $entry; // $entries[] = $entry;
        }
        elseif (strpos($entry, '.') !== 0)
        {
            $resource = $directory . '/' . $entry;

            if ($searchType === 'both'
            || ($searchType === 'file' && is_file($resource))
            || ($searchType === 'dir'  && is_dir($resource)))
            {
                $entries[$entry] = $entry; // $entries[] = $entry;
            }
        }
    }
    closedir($dirHandle);

    asort($entries); // sort($entries);

    return $entries;
}

/**
 * Prefix url with "http://" if no protocol is defined and check if is valid url
 * @param $url string
 * @return false|string
 */
function admFuncCheckUrl($url)
{
    // Homepage url have to start with "http://"
    if (!StringUtils::strStartsWith($url, 'http://', false) && !StringUtils::strStartsWith($url, 'https://', false))
    {
        $url = 'http://' . $url;
    }

    // For Homepage only valid url chars are allowed
    if (!strValidCharacters($url, 'url'))
    {
        return false;
    }

    return $url;
}

/**
 * Escape all HTML, JavaScript, and CSS
 * @param string $input    The input string
 * @param string $encoding Define character encoding tue use
 * @return string Escaped string
 */
function noHTML($input, $encoding = 'UTF-8')
{
    return htmlentities($input, ENT_QUOTES | ENT_HTML5, $encoding);
}

/**
 * @param string              $path
 * @param array<string,mixed> $params
 * @param string              $anchor
 * @param bool                $escape
 * @return string
 */
function safeUrl($path, array $params = array(), $anchor = '', $escape = false)
{
    $paramsText = '';
    if (count($params) > 0)
    {
        $paramsText = '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    $anchorText = '';
    if ($anchor !== '')
    {
        $anchorText = '#' . rawurlencode($anchor);
    }

    $url = $path . $paramsText . $anchorText;

    if ($escape)
    {
        return noHTML($url);
    }

    return $url;
}

/**
 * This is a safe method for redirecting.
 * @param string $url        The URL where redirecting to. Must be a absolute URL. (www.example.org)
 * @param int    $statusCode The status-code which should be send. (301, 302, 303 (default), 307)
 * @see https://www.owasp.org/index.php/Open_redirect
 */
function admRedirect($url, $statusCode = 303)
{
    global $gLogger, $gMessage, $gL10n;

    $loggerObject = array('url' => $url, 'statusCode' => $statusCode);

    if (headers_sent())
    {
        $gLogger->error('REDIRECT: Header already sent!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_HEADER_ALREADY_SENT'));
        // => EXIT
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false)
    {
        $gLogger->error('REDIRECT: URL is not a valid URL!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_REDIRECT_URL_INVALID'));
        // => EXIT
    }
    if (!in_array($statusCode, array(301, 302, 303, 307), true))
    {
        $gLogger->error('REDIRECT: Status Code is not allowed!', $loggerObject);

        $gMessage->show($gL10n->get('SYS_STATUS_CODE_INVALID'));
        // => EXIT
    }

    // Check if $url starts with the Admidio URL
    if (strpos($url, ADMIDIO_URL) === 0)
    {
        $gLogger->info('REDIRECT: Redirecting to internal URL!', $loggerObject);

        // TODO check if user is authorized for url
        $redirectUrl = $url;
    }
    else
    {
        $gLogger->notice('REDIRECT: Redirecting to external URL!', $loggerObject);

        $redirectUrl = safeUrl(ADMIDIO_URL . '/adm_program/system/redirect.php', array('url' => $url));
    }

    header('Location: ' . $redirectUrl, true, $statusCode);
    exit();
}

/**
 * Calculates and formats the execution time
 * @param float $startTime The start time
 * @return string Returns the formated execution time
 */
function getExecutionTime($startTime)
{
    $stopTime = microtime(true);

    return number_format(($stopTime - $startTime) * 1000, 6, '.', '') . ' ms';
}
