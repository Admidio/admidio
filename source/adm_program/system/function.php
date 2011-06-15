<?php
/******************************************************************************
 * Allgemeine Funktionen
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// Funktion prueft, ob ein User die uebergebene Rolle besitzt
// $role_name - Name der zu pruefenden Rolle
// $user_id   - Id des Users, fuer den die Mitgliedschaft geprueft werden soll

function hasRole($role_name, $user_id = 0)
{
    global $g_current_user, $g_current_organization, $g_db;

    if($user_id == 0)
    {
        $user_id = $g_current_user->getValue('usr_id');
    }
    elseif(is_numeric($user_id) == false)
    {
        return -1;
    }

    $sql    = 'SELECT mem_id
                 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                WHERE mem_usr_id = '.$user_id.'
                  AND mem_begin <= \''.DATE_NOW.'\'
                  AND mem_end    > \''.DATE_NOW.'\'
                  AND mem_rol_id = rol_id
                  AND rol_name   = \''.$role_name.'"
                  AND rol_valid  = 1 
                  AND rol_cat_id = cat_id
                  AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                      OR cat_org_id IS NULL ) ';
    $result = $g_db->query($sql);

    $user_found = $g_db->num_rows($result);

    if($user_found == 1)
    {
        return 1;
    }
    else
    {
        return 0;
    }
}

// Funktion prueft, ob der uebergebene User Mitglied in einer Rolle der Gruppierung ist

function isMember($user_id)
{
    global $g_current_organization, $g_db;
    
    if(is_numeric($user_id) && $user_id > 0)
    {
        $sql    = 'SELECT COUNT(*)
                     FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                    WHERE mem_usr_id = '.$user_id.'
                      AND mem_begin <= \''.DATE_NOW.'\'
                      AND mem_end    > \''.DATE_NOW.'\'
                      AND mem_rol_id = rol_id
                      AND rol_valid  = 1 
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
                          OR cat_org_id IS NULL ) ';
        $result = $g_db->query($sql);

        $row = $g_db->fetch_array($result);
        $row_count = $row[0];

        if($row_count > 0)
        {
            return true;
        }
    }
    return false;
}

// Funktion prueft, ob der angemeldete User Leiter einer Gruppe /Kurs ist
// Optionaler Parameter role_id prueft ob der angemeldete User Leiter der uebergebenen Gruppe / Kurs ist

function isGroupLeader($user_id, $role_id = 0)
{
    global $g_current_organization, $g_db;

    if(is_numeric($user_id) && $user_id >  0
    && is_numeric($role_id))
    {
        $sql    = 'SELECT mem_id
                     FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. '
                    WHERE mem_usr_id = '.$user_id.'
                      AND mem_begin <= \''.DATE_NOW.'\'
                      AND mem_end    > \''.DATE_NOW.'\'
                      AND mem_leader = 1
                      AND mem_rol_id = rol_id
                      AND rol_valid  = 1 
                      AND rol_cat_id = cat_id
                      AND (  cat_org_id = '. $g_current_organization->getValue('org_id').'
                          OR cat_org_id IS NULL ) ';
        if ($role_id > 0)
        {
            $sql .= '  AND mem_rol_id = '.$role_id;
        }
        $result = $g_db->query($sql);

        $edit_user = $g_db->num_rows($result);

        if($edit_user > 0)
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
    global $g_root_path, $g_l10n;

    if ( $num_items == 0)
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
                                class="navigationArrow" src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
                            <a href="' . $base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) . '">'.$g_l10n->get('SYS_BACK').'</a>&nbsp;&nbsp;' . $page_string;
        }

        if ( $on_page < $total_pages )
        {
            $page_string .= '&nbsp;&nbsp;<a href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '">'.$g_l10n->get('SYS_NEXT').'</a>
                            <a class="navigationArrow" href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '"><img 
                                 src="'. THEME_PATH. '/icons/forward.png" alt="'.$g_l10n->get('SYS_NEXT').'" /></a>';
        }

    }

    $page_string = '<div class="pageNavigation">'.$g_l10n->get('SYS_PAGE').':&nbsp;&nbsp;' . $page_string. '</div>';

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

// Funktion zur Versendung von Benachrichtigungs-Emails (bei neuen Einträgen)
function admFuncEmailNotification($receiptian, $reference, $message, $sender_name, $sender_mail)
{
	//Konfiguration Mail
	$empfaenger = $receiptian;
	$betreff = utf8_decode($reference);
	$nachricht = utf8_decode($message);
	$absender = utf8_decode($sender_name);
	$absendermail = $sender_mail;

	mail($empfaenger, $betreff, $nachricht, "From: $absender <$absendermail>");
	//echo "Empfänger: $empfaenger<br>Betreff: $betreff<br>Nachricht: $nachricht<br>Absender Name: $absender<br>Absender Mail: $absendermail";
}

// prueft ob der Array-Eintrag existiert und dem Datentyp entspricht, andernfalls wird ein Hinweis ausgegeben
// type        : 'string', 'numeric'
// validValues : array mit allen gueltigen Werten, die die Variable haben darf
function admFuncVariableIsValid($array, $variableName, $type, $defaultValue = null, $requireValue = false, $validValues = null)
{
	global $g_l10n, $g_message;
	
	$type = admStrToLower($type);

	if(isset($array[$variableName]))
	{
		if($type == 'boolean')
		{
			// Boolean darf nur 2 Werte haben
			$validValues = array(0, 1);
		}
		
		if($validValues != null)
		{
			// Variable muss einen gueltigen Wert haben
			if(in_array($array[$variableName], $validValues) == false)
			{
				$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
			}
		}

		if($type == 'numeric')
		{
			// Numerische Datentypen duerfen nur Zahlen beinhalten
			if (is_numeric($array[$variableName]) == false)
			{
				$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
			}
			return $array[$variableName];
		}
		elseif($type == 'string')
		{
			return strStripTags($array[$variableName]);
		}

		return $array[$variableName];
	}
	elseif($requireValue == true)
	{
		// Array-Eintrag existiert nicht, soll aber Pflicht sein
		$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
	}
	return $defaultValue;
}
?>