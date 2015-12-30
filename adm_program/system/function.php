<?php
/**
 ***********************************************************************************************
 * Various common functions
 *
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Autoloading function of class files. This function will be later registered
 * for default autoload implementation. Therefore the class name must be the same
 * as the file name except for case sensitive.
 * @param string $className Name of the class for which the file should be loaded.
 * @return void|false Return @b false if the file for the class wasn't found.
 */
function admFuncAutoload($className)
{
    $fileName = SERVER_PATH. '/adm_program/system/classes/'.strtolower($className).'.php';

    if(file_exists($fileName))
    {
        include($fileName);
    }
    else
    {
        return false;
    }
}

// now register this function in this script so only function.php must be included for autoload
spl_autoload_register('admFuncAutoload');

/**
 * Function checks if the user is a member of the role.
 * If @b userId is not set than this will be checked for the current user
 * @param string $roleName The name of the role where the membership of the user should be checked
 * @param int    $userId   The id of the user who should be checked if he is a member of the role.
 *                         If @userId is not set than this will be checked for the current user
 * @return int|bool Returns @b true if the user is a member of the role
 */
function hasRole($roleName, $userId = 0)
{
    global $gCurrentUser, $gCurrentOrganization, $gDb;

    if($userId === 0)
    {
        $userId = $gCurrentUser->getValue('usr_id');
    }
    elseif(!is_numeric($userId))
    {
        return -1;
    }

    $sql = 'SELECT mem_id
              FROM '.TBL_MEMBERS.'
        INNER JOIN '.TBL_ROLES.'
                ON rol_id = mem_rol_id
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE mem_usr_id = '.$userId.'
               AND mem_begin <= \''.DATE_NOW.'\'
               AND mem_end    > \''.DATE_NOW.'\'
               AND rol_name   = \''.$roleName.'\'
               AND rol_valid  = 1
               AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                   OR cat_org_id IS NULL )';
    $statement = $gDb->query($sql);

    if($statement->rowCount() === 1)
    {
        return 1;
    }
    else
    {
        return 0;
    }
}

/**
 * Function checks if the user is a member in a role of the current organization.
 * @param  int  $userId The id of the user who should be checked if he is a member of the current organization
 * @return bool Returns @b true if the user is a member
 */
function isMember($userId)
{
    global $gCurrentOrganization, $gDb;

    if(is_numeric($userId) && $userId > 0)
    {
        $sql = 'SELECT COUNT(*)
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = '.$userId.'
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND rol_valid  = 1
                   AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )';
        $statement = $gDb->query($sql);

        $row = $statement->fetch();
        $rowCount = $row[0];

        if($rowCount > 0)
        {
            return true;
        }
    }
    return false;
}

/**
 * Function checks if the user is a group leader in a role of the current organization.
 * If you use the @b roleId parameter you can check if the user is group leader of that role.
 * @param int $userId  The id of the user who should be checked if he is a group leader
 * @param int $roleId  (optional) If set <> 0 than the function checks if the user is group leader of this role
 *                     otherwise it checks if the user is group leader in one role of the current organization
 * @return bool Returns @b true if the user is a group leader
 */
function isGroupLeader($userId, $roleId = 0)
{
    global $gCurrentOrganization, $gDb;

    if(is_numeric($userId) && $userId > 0 && is_numeric($roleId))
    {
        $sql = 'SELECT mem_id
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE mem_usr_id = '.$userId.'
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                   AND mem_leader = 1
                   AND rol_valid  = 1
                   AND (  cat_org_id = '.$gCurrentOrganization->getValue('org_id').'
                       OR cat_org_id IS NULL )';
        if ($roleId > 0)
        {
            $sql .= ' AND mem_rol_id = '.$roleId;
        }
        $statement = $gDb->query($sql);

        if($statement->rowCount() > 0)
        {
            return true;
        }
    }
    return false;
}

/**
 * diese Funktion gibt eine Seitennavigation in Anhaengigkeit der Anzahl Seiten zurueck
 * Teile dieser Funktion sind von generatePagination aus phpBB2
 * Beispiel:
 *     Seite: < Vorherige 1  2  3 ... 9  10  11 Naechste >
 *
 * @param string $base_url                 Basislink zum Modul (auch schon mit notwendigen Uebergabevariablen)
 * @param int    $num_items                Gesamtanzahl an Elementen
 * @param int    $per_page                 Anzahl Elemente pro Seite
 * @param int    $start_item               Mit dieser Elementnummer beginnt die aktuelle Seite
 * @param bool   $add_prevnext_text        Links mit "Vorherige" "Naechste" anzeigen
 * @param string $scriptParameterNameStart (optional) You can set a new name for the parameter that should be used as start parameter.
 * @return string
 */
function admFuncGeneratePagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true, $scriptParameterNameStart = 'start')
{
    global $gL10n;

    if ($num_items === 0 || $per_page === 0)
    {
        return '';
    }

    $total_pages = ceil($num_items / $per_page);

    if ($total_pages <= 1)
    {
        return '';
    }

    $on_page = floor($start_item / $per_page) + 1;

    $page_string = '';
    if ($total_pages > 7)
    {
        $init_page_max = ($total_pages > 3) ? 3 : $total_pages;

        for($i = 1; $i < $init_page_max + 1; ++$i)
        {
            if ($i === $on_page)
            {
                $page_string .= '<li class="active"><a href="#">'.$i.'</a></li>';
            }
            else
            {
                $page_string .= '<li><a href="'.$base_url.'&amp;'.$scriptParameterNameStart.'='.(($i - 1) * $per_page).'">'.$i.'</a></li>';
            }
        }

        if ($total_pages > 3)
        {
            if ($on_page > 1 && $on_page < $total_pages)
            {
                $page_string .= ($on_page > 5) ? ' ... ' : '&nbsp;&nbsp;';

                $init_page_min = ($on_page > 4) ? $on_page : 5;
                $init_page_max = ($on_page < $total_pages - 4) ? $on_page : $total_pages - 4;

                for($i = $init_page_min - 1; $i < $init_page_max + 2; ++$i)
                {
                    if ($i === $on_page)
                    {
                        $page_string .= '<li class="active"><a href="#">'.$i.'</a></li>';
                    }
                    else
                    {
                        $page_string .= '<li><a href="'.$base_url.'&amp;'.$scriptParameterNameStart.'='.(($i - 1) * $per_page).'">'.$i.'</a></li>';
                    }
                }

                $page_string .= ($on_page < $total_pages - 4) ? ' ... ' : '&nbsp;&nbsp;';
            }
            else
            {
                $page_string .= ' ... ';
            }

            for($i = $total_pages - 2; $i < $total_pages + 1; ++$i)
            {
                if ($i === $on_page)
                {
                    $page_string .= '<li class="active"><a href="#">'.$i.'</a></li>';
                }
                else
                {
                    $page_string .= '<li><a href="'.$base_url.'&amp;'.$scriptParameterNameStart.'='.(($i - 1) * $per_page).'">'.$i.'</a></li>';
                }
            }
        }
    }
    else
    {
        for($i = 1; $i < $total_pages + 1; ++$i)
        {
            if ($i === $on_page)
            {
                $page_string .= '<li class="active"><a href="#">'.$i.'</a></li>';
            }
            else
            {
                $page_string .= '<li><a href="'.$base_url.'&amp;'.$scriptParameterNameStart.'='.(($i - 1) * $per_page).'">'.$i.'</a></li>';
            }
        }
    }

    if ($add_prevnext_text)
    {
        if ($on_page > 1)
        {
            $page_string = '<li><a href="' . $base_url . '&amp;'.$scriptParameterNameStart.'=' . (($on_page - 2) * $per_page) . '">'.$gL10n->get('SYS_BACK').'</a></li>' . $page_string;
        }
        else
        {
            $page_string = '<li class="disabled"><a href="' . $base_url . '&amp;'.$scriptParameterNameStart.'=' . (($on_page - 2) * $per_page) . '">'.$gL10n->get('SYS_BACK').'</a></li>' . $page_string;
        }

        if ($on_page < $total_pages)
        {
            $page_string .= '<li><a href="' . $base_url . '&amp;'.$scriptParameterNameStart.'=' . ($on_page * $per_page) . '">'.$gL10n->get('SYS_PAGE_NEXT').'</a></li>';
        }
        else
        {
            $page_string .= '<li class="disabled"><a href="' . $base_url . '&amp;'.$scriptParameterNameStart.'='. ($on_page * $per_page) . '">'.$gL10n->get('SYS_PAGE_NEXT').'</a></li>';
        }
    }

    $page_string = '<ul class="pagination">' . $page_string . '</ul>';

    return $page_string;
}

/**
 * Berechnung der Maximalerlaubten Dateiuploadgröße in Byte
 * @return int
 */
function admFuncMaxUploadSize()
{
    $post_max_size = trim(ini_get('post_max_size'));
    switch(admStrToLower(substr($post_max_size, strlen($post_max_size/1), 1)))
    {
        case 'g':
            $post_max_size *= 1024;
        case 'm':
            $post_max_size *= 1024;
        case 'k':
            $post_max_size *= 1024;
    }
    $upload_max_filesize = trim(ini_get('upload_max_filesize'));
    switch(admStrToLower(substr($upload_max_filesize, strlen($upload_max_filesize/1), 1)))
    {
        case 'g':
            $upload_max_filesize *= 1024;
        case 'm':
            $upload_max_filesize *= 1024;
        case 'k':
            $upload_max_filesize *= 1024;
    }
    if($upload_max_filesize < $post_max_size)
    {
        return $upload_max_filesize;
    }
    else
    {
        return $post_max_size;
    }
}

/**
 * Funktion gibt die maximale Pixelzahl zurück die der Speicher verarbeiten kann
 * @return int
 */
function admFuncProcessableImageSize()
{
    $memory_limit = trim(ini_get('memory_limit'));
    // falls in php.ini nicht gesetzt
    if(!$memory_limit || $memory_limit === '')
    {
        $memory_limit = '8M';
    }
    // falls in php.ini abgeschaltet
    if($memory_limit === '-1')
    {
        $memory_limit = '128M';
    }
    switch(admStrToLower(substr($memory_limit, strlen($memory_limit/1), 1)))
    {
        case 'g':
            $memory_limit *= 1024;
        case 'm':
            $memory_limit *= 1024;
        case 'k':
            $memory_limit *= 1024;
    }
    // Für jeden Pixel werden 3Byte benötigt (RGB)
    // der Speicher muss doppelt zur Verfügung stehen
    // nach ein paar tests hat sich 2,5Fach als sichrer herausgestellt
    return $memory_limit / (3*2.5);
}

// Verify the content of an array element if it's the expected datatype
/**
 * The function is designed to check the content of @b $_GET and @b $_POST elements and should be used at the
 * beginning of a script. If the value of the defined datatype is not valid then an error will be shown. If no
 * value was set then the parameter will be initialized. The function can be used with every array and their elements.
 * You can set several flags (like required value, datatype …) that should be checked.
 *
 * @param array  $array        The array with the element that should be checked
 * @param string $variableName Name of the array element that should be checked
 * @param string $datatype     The datatype like @b string, @b numeric, @b int, @b float, @b bool, @b boolean, @b html,
 *                             @b date or @b file that is expected and which will be checked.
 *                             Datatype @b date expects a date that has the Admidio default format from the
 *                             preferences or the english date format @b Y-m-d
 * @param array $options       (optional) An array with the following possible entries:
 *                             - @b defaultValue : A value that will be set if the variable has no value
 *                             - @b requireValue : If set to @b true than a value is required otherwise the function
 *                                                 returns an error
 *                             - @b validValues :  An array with all values that the variable could have. If another
 *                                                 value is found than the function returns an error
 *                             - @b directOutput : If set to @b true the function returns only the error string, if set
 *                                                 to false a html message with the error will be returned
 * @return mixed|null Returns the value of the element or the error message if a test failed
 *
 * @par Examples
 * @code
 * // numeric value that would get a default value 0 if not set
 * $getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', array('defaultValue' => 0));
 *
 * // string that will be initialized with text of id DAT_DATES
 * $getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $g_l10n->get('DAT_DATES')));
 *
 * // string initialized with actual and the only allowed values are actual and old
 * $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old')));
 * @endcode
 */
function admFuncVariableIsValid($array, $variableName, $datatype, $options = array())
{
    global $gL10n, $gMessage, $gPreferences;

    // create array with all options
    $optionsDefault = array('defaultValue' => null, 'requireValue' => false, 'validValues' => null, 'directOutput' => null);
    $optionsAll     = array_replace($optionsDefault, $options);

    $errorMessage = '';
    $datatype = admStrToLower($datatype);
    $value = null;

    // set default value for each datatype if no value is given and no value was required
    if(array_key_exists($variableName, $array) && $array[$variableName] !== '')
    {
        $value = $array[$variableName];
    }
    else
    {
        if($optionsAll['requireValue'])
        {
            // if value is required an no value is given then show error
            $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
        }
        elseif($optionsAll['defaultValue'] !== null)
        {
            // if a default value was set then take this value
            $value = $optionsAll['defaultValue'];
        }
        else
        {
            // no value set then initialize the parameter
            if($datatype === 'bool' || $datatype === 'boolean')
            {
                $value = false;
            }
            elseif($datatype === 'numeric' || $datatype === 'int')
            {
                $value = 0;
            }
            elseif($datatype === 'float')
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

    if($optionsAll['validValues'] !== null)
    {
        // check if parameter has a valid value
        // do a strict check with in_array because the function don't work properly
        if(!in_array(admStrToUpper($value), $optionsAll['validValues'], true)
        && !in_array(admStrToLower($value), $optionsAll['validValues'], true))
        {
            $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
        }
    }

    switch ($datatype)
    {
        case 'file':
            try
            {
                if($value !== '')
                {
                    admStrIsValidFileName($value);
                }
            }
            catch(AdmException $e)
            {
                $errorMessage = $e->getText();
            }
            break;

        case 'date':
            // check if date is a valid Admidio date format
            $objAdmidioDate = DateTime::createFromFormat($gPreferences['system_date'], $value);

            if(!$objAdmidioDate)
            {
                // check if date has english format
                $objEnglishDate = DateTime::createFromFormat('Y-m-d', $value);

                if(!$objEnglishDate)
                {
                    $errorMessage = $gL10n->get('LST_NOT_VALID_DATE_FORMAT', $variableName);
                }
            }
            break;

        case 'bool':
        case 'boolean':
            $valid = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($valid === false)
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
                    $value = $value + 0;
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
    if($errorMessage === '')
    {
        return $value;
    }
    else
    {
        if(isset($gMessage))
        {
            if($optionsAll['directOutput'])
            {
                $gMessage->showTextOnly(true);
            }

            $gMessage->show($errorMessage);
        }
        else
        {
            echo $errorMessage;
            exit();
        }
    }

    return null;
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore all necessary data must be set in the function
 * parameters. If userid is not set then the function will show @b deleted @b user.
 * @param int     $userIdCreated   Id of the user who create the recordset.
 * @param string  $timestampCreate Date and time of the moment when the user create the recordset.
 * @param int     $userIdEdited    Id of the user last changed the recordset.
 * @param string  $timestampEdited Date and time of the moment when the user last changed the recordset
 * @return string Returns a html string with usernames who creates item and edit item the last time
 */
function admFuncShowCreateChangeInfoById($userIdCreated, $timestampCreate, $userIdEdited, $timestampEdited)
{
    global $gDb, $gProfileFields, $gL10n, $gPreferences;

    // only show info if system setting is activated
    if($gPreferences['system_show_create_edit'] > 0)
    {
        $htmlCreateName = '';
        $htmlEditName   = '';

        // compose name of user who create the recordset
        if(strlen($timestampCreate) > 0)
        {
            if($userIdCreated > 0)
            {
                $userCreate = new User($gDb, $gProfileFields, $userIdCreated);

                if($gPreferences['system_show_create_edit'] == 1)
                {
                    $htmlCreateName = $userCreate->getValue('FIRST_NAME'). ' '. $userCreate->getValue('LAST_NAME');
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
        if(strlen($timestampEdited) > 0)
        {
            if($userIdEdited > 0)
            {
                $userEdit = new User($gDb, $gProfileFields, $userIdEdited);

                if($gPreferences['system_show_create_edit'] == 1)
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

        if($htmlCreateName !== '' || $htmlEditName !== '')
        {
            // get html output from other function
            return admFuncShowCreateChangeInfoByName($htmlCreateName, $timestampCreate, $htmlEditName,
                                                     $timestampEdited, $userIdCreated, $userIdEdited);
        }
    }

    return '';
}

/**
 * Creates a html fragment with information about user and time when the recordset was created
 * and when it was at last edited. Therefore all necessary data must be set in the function
 * parameters. If user name is not set then the function will show @b deleted @b user.
 * @param string $userNameCreated Id of the user who create the recordset.
 * @param string $timestampCreate Date and time of the moment when the user create the recordset.
 * @param string $userNameEdited  Id of the user last changed the recordset.
 * @param string $timestampEdited Date and time of the moment when the user last changed the recordset
 * @param int    $userIdCreated   (optional) The id of the user who create the recordset.
 *                                If id is set than a link to the user profile will be created
 * @param int    $userIdEdited    (optional) The id of the user last changed the recordset.
 *                                If id is set than a link to the user profile will be created
 * @return string Returns a html string with usernames who creates item and edit item the last time
 */
function admFuncShowCreateChangeInfoByName($userNameCreated, $timestampCreate, $userNameEdited, $timestampEdited, $userIdCreated = 0, $userIdEdited = 0)
{
    global $gL10n, $gValidLogin, $g_root_path, $gPreferences;

    $html = '';

    // only show info if system setting is activated
    if($gPreferences['system_show_create_edit'] > 0)
    {
        // compose name of user who create the recordset
        if(strlen($timestampCreate) > 0)
        {
            $userNameCreated = trim($userNameCreated);

            if(strlen($userNameCreated) === 0)
            {
                $userNameCreated = $gL10n->get('SYS_DELETED_USER');
            }

            // if valid login and a user id is given than create a link to the profile of this user
            if($gValidLogin && $userIdCreated > 0 && $userNameCreated != $gL10n->get('SYS_SYSTEM'))
            {
                $userNameCreated = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.
                                    $userIdCreated.'">'.$userNameCreated.'</a>';
            }

            $html .= '<span class="admidio-info-created">'.$gL10n->get('SYS_CREATED_BY', $userNameCreated, $timestampCreate).'</span>';
        }

        // compose name of user who edit the recordset
        if(strlen($timestampEdited) > 0)
        {
            $userNameEdited = trim($userNameEdited);

            if(strlen($userNameEdited) === 0)
            {
                $userNameEdited = $gL10n->get('SYS_DELETED_USER');
            }

            // if valid login and a user id is given than create a link to the profile of this user
            if($gValidLogin && $userIdEdited > 0 && $userNameEdited != $gL10n->get('SYS_SYSTEM'))
            {
                $userNameEdited = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.
                                   $userIdEdited.'">'.$userNameEdited.'</a>';
            }

            $html .= '<span class="info-edited">'.$gL10n->get('SYS_LAST_EDITED_BY', $userNameEdited, $timestampEdited).'</span>';
        }

        if($html !== '')
        {
            $html = '<div class="admidio-admidio-info-created-edited">'.$html.'</div>';
        }
    }

    return $html;
}

/**
 * Returns the extension of a given filename
 * @param string $filename given filename
 * @return string Returns the extension including "."
 * @deprecated use pathinfo($filename, PATHINFO_EXTENSION) instead
 *             important: return extension without "."
 */
function admFuncGetFilenameExtension($filename)
{
    return strtolower(strrchr($filename, '.'));
}

/**
 * Returns the name of a given filename without extension
 * @param string $filename given filename
 * @return string Returns name without extension
 * @deprecated use pathinfo($filename, PATHINFO_FILENAME) instead
 */
function admFuncGetFilenameWithoutExtension($filename)
{
    return str_replace(strrchr($filename, '.'), '', $filename);
}

/**
 * Search all files or directories in the specified directory.
 * @param string $directory  The directory where the files or directories should be searched.
 * @param string $searchType This could be @b file, @b dir, @b both or @b all and represent
 *                           the type of entries that should be searched.
 * @return string[]|false Returns an array with all found entries or false if an error occurs.
 */
function admFuncGetDirectoryEntries($directory, $searchType = 'file')
{
    $dirHandle = opendir($directory);
    if ($dirHandle)
    {
        $entries = array();

        while (($entry = readdir($dirHandle)) !== false)
        {
            if ($searchType === 'all')
            {
                $entries[$entry] = $entry; // $entries[] = $entry;
            }
            elseif ($entry !== '.' && $entry !== '..')
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

    return false;
}
