<?php
/******************************************************************************
 * Various common functions 
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

 /** Autoloading function of class files. This function is automatically called
  *  by PHP. Therefore the class name must be the same as the file name except 
  *  for case sensitive.
  *  @param $className Name of the class for which the file should be loaded
  */
 function __autoload($className)
 {
    $fileName = SERVER_PATH. '/adm_program/system/classes/'.strtolower($className).'.php';
    require_once($fileName);
 }
 
 
/** Function checks if the user is a member of the role.
 *  If @b userId is not set than this will be checked for the current user
 *  @param $rolName	The name of the role where the membership of the user should be checked
 *  @param $userId 	The id of the user who should be checked if he is a member of the role.
 *  				If @userId is not set than this will be checked for the current user
 *  @return Returns @b true if the user is a member of the role
 */
function hasRole($roleName, $userId = 0)
{
    global $gCurrentUser, $gCurrentOrganization, $gDb;

    if($userId == 0)
    {
        $userId = $gCurrentUser->getValue('usr_id');
    }
    elseif(is_numeric($userId) == false)
    {
        return -1;
    }

    $sql    = 'SELECT mem_id
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_usr_id = '.$userId.'
                  AND mem_begin <= \''.DATE_NOW.'\'
                  AND mem_end    > \''.DATE_NOW.'\'
                  AND mem_rol_id = rol_id
                  AND rol_name   = \''.$roleName.'\'
                  AND rol_valid  = 1 
                  AND rol_cat_id = cat_id
                  AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                      OR cat_org_id IS NULL ) ';
    $result = $gDb->query($sql);

    if($gDb->num_rows($result) == 1)
    {
        return 1;
    }
    else
    {
        return 0;
    }
}

/** Function checks if the user is a member in a role of the current organization. 
 *  @param $userId 	The id of the user who should be checked if he is a member of the current organization
 *  @return Returns @b true if the user is a member
 */
function isMember($userId)
{
    global $gCurrentOrganization, $gDb;
    
    if(is_numeric($userId) && $userId > 0)
    {
        $sql    = 'SELECT COUNT(*)
                     FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                    WHERE mem_usr_id = '.$userId.'
                      AND mem_begin <= \''.DATE_NOW.'\'
                      AND mem_end    > \''.DATE_NOW.'\'
                      AND mem_rol_id = rol_id
                      AND rol_valid  = 1 
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                          OR cat_org_id IS NULL ) ';
        $result = $gDb->query($sql);

        $row = $gDb->fetch_array($result);
        $rowCount = $row[0];

        if($rowCount > 0)
        {
            return true;
        }
    }
    return false;
}

/** Function checks if the user is a group leader in a role of the current organization. 
 *  If you use the @b roleId parameter you can check if the user is group leader of that role.
 *  @param $userId 	The id of the user who should be checked if he is a group leader
 *  @param $roleId 	If set <> 0 than the function checks if the user is group leader of this role 
 *					otherwise it checks if the user is group leader in one role of the current organization
 *  @return Returns @b true if the user is a group leader
 */
function isGroupLeader($userId, $roleId = 0)
{
    global $gCurrentOrganization, $gDb;

    if(is_numeric($userId) && $userId >  0
    && is_numeric($roleId))
    {
        $sql    = 'SELECT mem_id
                     FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                    WHERE mem_usr_id = '.$userId.'
                      AND mem_begin <= \''.DATE_NOW.'\'
                      AND mem_end    > \''.DATE_NOW.'\'
                      AND mem_leader = 1
                      AND mem_rol_id = rol_id
                      AND rol_valid  = 1 
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id').'
                          OR cat_org_id IS NULL ) ';
        if ($roleId > 0)
        {
            $sql .= '  AND mem_rol_id = '.$roleId;
        }
        $result = $gDb->query($sql);

        if($gDb->num_rows($result) > 0)
        {
            return true;
        }
    }
    return false;
}

// diese Funktion gibt eine Seitennavigation in Anhaengigkeit der Anzahl Seiten zurueck
// Teile dieser Funktion sind von generatePagination aus phpBB2
// Beispiel:
//              Seite: < Vorherige 1  2  3 ... 9  10  11 Naechste >
// Uebergaben:
// base_url   : Basislink zum Modul (auch schon mit notwendigen Uebergabevariablen)
// num_items  : Gesamtanzahl an Elementen
// per_page   : Anzahl Elemente pro Seite
// start_item : Mit dieser Elementnummer beginnt die aktuelle Seite
// add_prevnext_text : Links mit "Vorherige" "Naechste" anzeigen

function admFuncGeneratePagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true)
{
    global $g_root_path, $gL10n;

    if ($num_items == 0 || $per_page == 0 )
    {
    	return '';
    }
    
    $total_pages = ceil($num_items/$per_page);    

    if ( $total_pages <= 1 )
    {
        return '';
    }

    $on_page = floor($start_item / $per_page) + 1;

    $page_string = '';
    if ( $total_pages > 7 )
    {
        $init_page_max = ( $total_pages > 3 ) ? 3 : $total_pages;

        for($i = 1; $i < $init_page_max + 1; $i++)
        {
            $page_string .= ( $i == $on_page ) ? '<span class="selected">'. $i. '</span>' : '<a href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
            if ( $i <  $init_page_max )
            {
                $page_string .= "&nbsp;&nbsp;";
            }
        }

        if ( $total_pages > 3 )
        {
            if ( $on_page > 1  && $on_page < $total_pages )
            {
                $page_string .= ( $on_page > 5 ) ? ' ... ' : '&nbsp;&nbsp;';

                $init_page_min = ( $on_page > 4 ) ? $on_page : 5;
                $init_page_max = ( $on_page < $total_pages - 4 ) ? $on_page : $total_pages - 4;

                for($i = $init_page_min - 1; $i < $init_page_max + 2; $i++)
                {
                    $page_string .= ($i == $on_page) ? '<span class="selected">'. $i. '</span>' : '<a href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
                    if ( $i <  $init_page_max + 1 )
                    {
                        $page_string .= '&nbsp;&nbsp;';
                    }
                }

                $page_string .= ( $on_page < $total_pages - 4 ) ? ' ... ' : '&nbsp;&nbsp;';
            }
            else
            {
                $page_string .= ' ... ';
            }

            for($i = $total_pages - 2; $i < $total_pages + 1; $i++)
            {
                $page_string .= ( $i == $on_page ) ? '<span class="selected">'. $i. '</span>'  : '<a href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
                if( $i <  $total_pages )
                {
                    $page_string .= "&nbsp;&nbsp;";
                }
            }
        }
    }
    else
    {
        for($i = 1; $i < $total_pages + 1; $i++)
        {
            $page_string .= ( $i == $on_page ) ? '<span class="selected">'. $i. '</span>' : '<a href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
            if ( $i <  $total_pages )
            {
                $page_string .= '&nbsp;&nbsp;';
            }
        }
    }

    if ( $add_prevnext_text )
    {
        if ( $on_page > 1 )
        {
            $page_string = '<a href="' . $base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) . '"><img 
                                class="navigationArrow" src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
                            <a href="' . $base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) . '">'.$gL10n->get('SYS_BACK').'</a>&nbsp;&nbsp;' . $page_string;
        }

        if ( $on_page < $total_pages )
        {
            $page_string .= '&nbsp;&nbsp;<a href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '">'.$gL10n->get('SYS_NEXT').'</a>
                            <a class="navigationArrow" href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '"><img 
                                 src="'. THEME_PATH. '/icons/forward.png" alt="'.$gL10n->get('SYS_NEXT').'" /></a>';
        }

    }

    $page_string = '<div class="pageNavigation">'.$gL10n->get('SYS_PAGE').':&nbsp;&nbsp;' . $page_string. '</div>';

    return $page_string;
}

//Berechnung der Maximalerlaubten Dateiuploadgröße in Byte
function admFuncMaxUploadSize()
{
    $post_max_size = trim(ini_get('post_max_size'));
    switch(admStrToLower(substr($post_max_size,strlen($post_max_size/1),1)))
    {
        case 'g':
            $post_max_size *= 1024;
        case 'm':
            $post_max_size *= 1024;
        case 'k':
            $post_max_size *= 1024;
    }
    $upload_max_filesize = trim(ini_get('upload_max_filesize'));
    switch(admStrToLower(substr($upload_max_filesize,strlen($upload_max_filesize/1),1)))
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

//Funktion gibt die maximale Pixelzahl zurück die der Speicher verarbeiten kann
function admFuncProcessableImageSize()
{
    $memory_limit = trim(ini_get('memory_limit'));
    //falls in php.ini nicht gesetzt
    if($memory_limit=='')
    {
       $memory_limit=='8M';
    }
    //falls in php.ini abgeschaltet
    if($memory_limit==-1)
    {
       $memory_limit=='128M';
    }
    switch(admStrToLower(substr($memory_limit,strlen($memory_limit/1),1)))
    {
     case 'g':
         $memory_limit *= 1024;
     case 'm':
         $memory_limit *= 1024;
     case 'k':
         $memory_limit *= 1024;
    }
    //Für jeden Pixel werden 3Byte benötigt (RGB)
    //der Speicher muss doppelt zur Verfügung stehen
    //nach ein paar tests hat sich 2,5Fach als sichrer herausgestellt
    return $memory_limit/(3*2.5); 
}

/// Verify the content of an array element if it's the expected datatype
/** The function is designed to check the content of @b $_GET and @b $_POST elements and should be used at the beginning of a script.
 *  But the function can also be used with every array and their elements. You can set several flags (like required value, datatype …) 
 *  that should be checked.
 *  @param $array 			The array with the element that should be checked
 *  @param $variableName 	Name of the array element that should be checked
 *  @param $datatype 		The datatype like @b string, @b numeric, @b boolean, @b html, @b date or @b file that is expected and which will be checked.
 *							Datatype @b date expects a date that has the Admidio default format from the preferences or the english date format @b Y-m-d
 *  @param $defaultValue 	A value that will be set if the variable has no value
 *  @param $requireValue 	If set to @b true than a value is required otherwise the function returns an error
 *  @param $validValues 	An array with all values that the variable could have. If another value is found than the function returns an error
 *  @param $directOutput 	If set to @b true the function returns only the error string, if set to false a html message with the error will be returned
 *  @return Returns the value of the element or the error message if a test failed 
 *  @par Examples
 *  @code   // numeric value that would get a default value 0 if not set
 *  $getDateId = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', 0);
 *
 *  // string that will be initialized with text of id DAT_DATES
 *  $getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $g_l10n->get('DAT_DATES'));
 *
 *  // string initialized with actual and the only allowed values are actual and old
 *  $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', 'actual', false, array('actual', 'old')); @endcode
 */
function admFuncVariableIsValid($array, $variableName, $datatype, $defaultValue = null, $requireValue = false, $validValues = null, $directOutput = false)
{
	global $gL10n, $gMessage, $gPreferences;
	
	$errorMessage = '';
	$datatype = admStrToLower($datatype);

    // only check if array entry exists and has a value
	if(isset($array[$variableName]) && strlen($array[$variableName]) > 0)
	{
		if($datatype == 'boolean')
		{
			// boolean type must be 0 or 1 otherwise throw error
			// do not check with in_array because this function don't work properly
			if($array[$variableName] != '0' && $array[$variableName] != '1'
            && $array[$variableName] != 'false' && $array[$variableName] != 'true')
			{
                $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
			}
		}
		elseif($validValues != null)
		{
			// check if parameter has a valid value
			// do a strict check with in_array because the function don't work properly
			if(in_array(admStrToUpper($array[$variableName]), $validValues, true) == false
			&& in_array(admStrToLower($array[$variableName]), $validValues, true) == false)
			{
                $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
			}
		}

        if($datatype == 'file')
        {
			try
			{
				admStrIsValidFileName($array[$variableName]);
			}
			catch(AdmException $e)
			{
				$errorMessage = $e->getText();
			}
        }
		elseif($datatype == 'date')
		{
			// check if date is a valid Admidio date format
			$objAdmidioDate = new DateTimeExtended($array[$variableName], $gPreferences['system_date'], 'date');
			
			if($objAdmidioDate->valid() == false)
			{
				// check if date has english format
				$objEnglishDate = new DateTimeExtended($array[$variableName], 'Y-m-d', 'date');
				
				if($objEnglishDate->valid() == false)
				{
					$errorMessage = $gL10n->get('LST_NOT_VALID_DATE_FORMAT');
				}
			}
		}
		elseif($datatype == 'numeric')
		{
			// numeric datatype should only contain numbers
			if (is_numeric($array[$variableName]) == false)
			{
                $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
			}
		}
		elseif($datatype == 'string')
		{
			$array[$variableName] = strStripTags(htmlspecialchars($array[$variableName], ENT_COMPAT, 'UTF-8'));
		}
        
        elseif($datatype == 'html')
        {
            // check html string vor invalid tags and scripts
            $array[$variableName] = htmLawed(stripslashes($array[$variableName]), array('safe' => 1));
        }
        
        // wurde kein Fehler entdeckt, dann den Inhalt der Variablen zurueckgeben
        if(strlen($errorMessage) == 0)
        {
            return $array[$variableName];
        }
	}
	elseif($requireValue == true)
	{
		// Array-Eintrag existiert nicht, soll aber Pflicht sein
        $errorMessage = $gL10n->get('SYS_INVALID_PAGE_VIEW');
	}

	if(strlen($errorMessage) > 0)
	{
	   if($directOutput == true)
	   {
	       echo $errorMessage;
	       exit();
	   }
	   else
	   {
	       $gMessage->show($errorMessage);
	   }
	}
	
	return $defaultValue;
}

/** Creates a html fragment with information about user and time when the recordset was created
 *  and when it was at last edited. Therefore all neccessary data must be set in the function
 *  parameters. If userid is not set then the function will show @b deleted @b user.
 *  @param $userIdCreated   Id of the user who create the recordset.
 *  @param $timestampCreate Date and time of the moment when the user create the recordset.
 *  @param $userIdEdited    Id of the user last changed the recordset.
 *  @param $timestampEdit   Date and time of the moment when the user last changed the recordset
 *  @return Returns a html string with usernames who creates item and edit item the last time
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
                    $htmlEditName = $userEdit->getValue('FIRST_NAME'). ' '. $userEdit->getValue('LAST_NAME');
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
    
        if(strlen($htmlCreateName) > 0 || strlen($htmlEditName) > 0)
        {
            // get html output from other function
            return admFuncShowCreateChangeInfoByName($htmlCreateName, $timestampCreate, $htmlEditName, $timestampEdited, $userIdCreated, $userIdEdited);
        }
    }
    
    return '';
}

/** Creates a html fragment with information about user and time when the recordset was created
 *  and when it was at last edited. Therefore all neccessary data must be set in the function
 *  parameters. If user name is not set then the function will show @b deleted @b user.
 *  @param $userNameCreated Id of the user who create the recordset.
 *  @param $timestampCreate Date and time of the moment when the user create the recordset.
 *  @param $userNameEdited  Id of the user last changed the recordset.
 *  @param $timestampEdit   Date and time of the moment when the user last changed the recordset
 *  @param $userIdCreated   Optional the id of the user who create the recordset. 
 *                          If id is set than a link to the user profile will be created
 *  @param $userIdEdited    Optional the id of the user last changed the recordset.
 *                          If id is set than a link to the user profile will be created
 *  @return Returns a html string with usernames who creates item and edit item the last time
 */
function admFuncShowCreateChangeInfoByName($userNameCreated, $timestampCreate, $userNameEdited, $timestampEdited, $userIdCreated = 0, $userIdEdited = 0)
{
    global $gDb, $gProfileFields, $gL10n, $gValidLogin, $g_root_path, $gPreferences;

    $html = '';

    // only show info if system setting is activated
    if($gPreferences['system_show_create_edit'] > 0)
    {
        // compose name of user who create the recordset
        if(strlen($timestampCreate) > 0)
        {
            $userNameCreated = trim($userNameCreated);
            
            if(strlen($userNameCreated) == 0)
            {
                $userNameCreated = $gL10n->get('SYS_DELETED_USER');
            }
    
            // if valid login and a user id is given than create a link to the profile of this user
            if($gValidLogin == true && $userIdCreated > 0 && $userNameCreated != $gL10n->get('SYS_SYSTEM'))
            {
                $userNameCreated = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.
                                    $userIdCreated.'">'.$userNameCreated.'</a>';
            }
            
            $html .= '<span class="admInformationCreated">'.$gL10n->get('SYS_CREATED_BY', $userNameCreated, $timestampCreate).'</span>';
        }
    
        // compose name of user who edit the recordset
        if(strlen($timestampEdited) > 0)
        {
            $userNameEdited = trim($userNameEdited);
        
            if(strlen($userNameEdited) == 0)
            {
                $userNameEdited = $gL10n->get('SYS_DELETED_USER');
            }

            // if valid login and a user id is given than create a link to the profile of this user
            if($gValidLogin == true && $userIdEdited > 0 && $userNameEdited != $gL10n->get('SYS_SYSTEM'))
            {
                $userNameEdited = '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.
                                    $userIdEdited.'">'.$userNameEdited.'</a>';
            }

            $html .= '<span class="admInformationEdited">'.$gL10n->get('SYS_LAST_EDITED_BY', $userNameEdited, $timestampEdited).'</span>';
        }
    
        if(strlen($html) > 0)
        {
            $html = '<div class="editInformation">'.$html.'</div>';
        }
    }
    return $html;
}
/** Returns the extension of a given filename
 * @param $filname given filename
 * @return Returns the extension including "."
 */
function admFuncGetFilenameExtension($filename)
{
    return strtolower(strrchr($filename, "."));
}
/** Returns the name of a given filename without extension
 * @param $filname given filename
 * @return Returns name without extension
 */
function admFuncGetFilenameWithoutExtension($filename)
{
    return str_replace(strrchr($filename, "."), '', $filename);
}
?>