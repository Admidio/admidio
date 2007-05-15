<?php
/******************************************************************************
 * Allgemeine Funktionen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

// Funktion fuer das Error-Handling der Datenbank

function db_error($result, $file = "", $line = "")
{
    global $g_root_path;
    global $g_message;

    if($result == false)
    {
        $error_string = "";
        if(strlen($file) > 0)
        {
            // nur den Dateinamen ohne Pfad anzeigen
            $file = substr($file, strrpos($file, "\\"));
            $file = substr($file, strrpos($file, "/"));
            $file = substr($file, 1);
            $error_string = $error_string. "<i>File:</i> <b>$file</b><br>";
        }
        if(strlen($line) > 0)
        {
            $error_string = $error_string. "<i>Line:</i> <b>$line</b><br>";
        }
        $error_string = $error_string. "<i>Errorcode:</i> <b>". mysql_errno(). "</b><br>". mysql_error();
        
        if(headers_sent() == false)
        {
            $g_message->show("mysql", $error_string);
        }
        else
        {
            echo "<div style=\"color: #CC0000;\">$error_string</div>";
        }
        exit();
    }
}

// die uebergebenen Variablen fuer den SQL-Code werden geprueft
// dadurch soll es nicht mehr moeglich sein, Code in ein Statement einzuschleusen
//
// Anwendungsbeispiel:
// $sqlQuery = 'SELECT col1, col2 FROM tab1 WHERE col1 = {1} AND col3 = {2} LIMIT {3}';
// $stm = mysql_query(prepareSQL($sqlQuery, array('username', 24.3, 20);

function prepareSQL($queryString, $paramArr)
{
    foreach (array_keys($paramArr) as $paramName)
    {
        // wenn Variable ein Leerstring ist, dann Null in DB schreiben
        if(strlen($paramArr[$paramName]) == 0)
        {
            $paramArr[$paramName] = 'NULL';
        }

        // Variablentyp pruefen und danach Formatieren
        if (is_int($paramArr[$paramName]))
        {
            // Integer
            $paramArr[$paramName] = (int)$paramArr[$paramName];
        }
        elseif (is_numeric($paramArr[$paramName]))
        {
            // Zahl
            $paramArr[$paramName] = (float)$paramArr[$paramName];
        }
        elseif (($paramArr[$paramName] != 'NULL') and ($paramArr[$paramName] != 'NOT NULL'))
        {
            // String, aber nicht NULL
            $paramArr[$paramName] = mysql_escape_string(stripslashes($paramArr[$paramName]));
            $paramArr[$paramName] = '\''.$paramArr[$paramName].'\'';
        }
    }
    return preg_replace('/\{(.*?)\}/ei','$paramArr[\'$1\']', $queryString);
}

// Funktion prueft, ob ein User die uebergebene Rolle besitzt
// Inhalt der Variable "$function" muss gleich dem DB-Feld "rolle.funktion" sein

function hasRole($function, $user_id = 0)
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    if($user_id == 0)
    {
        $user_id = $g_current_user->id;
    }

    $sql    = "SELECT *
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = {0}
                  AND mem_valid         = 1
                  AND mem_rol_id        = rol_id
                  AND rol_org_shortname = '$g_organization'
                  AND rol_name          = {1}
                  AND rol_valid         = 1 ";
    $sql    = prepareSQL($sql, array($user_id, $function));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $user_found = mysql_num_rows($result);

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

function isMember($user_id, $organization = "")
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    if(strlen($organization) == 0)
    {
        $organization = $g_organization;
    }

    $sql    = "SELECT COUNT(*)
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = {0}
                  AND mem_valid         = 1
                  AND mem_rol_id        = rol_id
                  AND rol_org_shortname = {1}
                  AND rol_valid         = 1 ";
    $sql    = prepareSQL($sql, array($user_id, $organization));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $row = mysql_fetch_row($result);
    $row_count = $row[0];

    if($row_count > 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}

// Funktion prueft, ob der angemeldete User Leiter einer Gruppe /Kurs ist
// Optionaler Parameter role_id prueft ob der angemeldete User Leiter der uebergebenen Gruppe / Kurs ist

function isGroupLeader($rol_id = 0)
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    $sql    = "SELECT *
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = $g_current_user->id
                  AND mem_valid         = 1
                  AND mem_leader        = 1
                  AND mem_rol_id        = rol_id
                  AND rol_org_shortname = '$g_organization'
                  AND rol_valid         = 1 ";
    if ($rol_id != 0)
    {
        $sql .= "  AND mem_rol_id           = {0}";
    }
    $sql    = prepareSQL($sql, array($rol_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $edit_user = mysql_num_rows($result);

    if($edit_user > 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}


// Funktion prueft, ob der angemeldete User Ankuendigungen anlegen darf

function editAnnouncements()
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    $sql    = "SELECT *
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = $g_current_user->id
                  AND mem_rol_id        = rol_id
                  AND mem_valid         = 1
                  AND rol_org_shortname = '$g_organization'
                  AND rol_announcements = 1
                  AND rol_valid         = 1 ";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $edit_announcements = mysql_num_rows($result);

    if($edit_announcements > 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}

// Funktion prueft, ob der angemeldete User Termine anlegen darf

function editDate()
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    $sql    = "SELECT *
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = $g_current_user->id
                  AND mem_rol_id        = rol_id
                  AND mem_valid         = 1
                  AND rol_org_shortname = '$g_organization'
                  AND rol_dates         = 1
                  AND rol_valid         = 1 ";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $edit_date = mysql_num_rows($result);

    if($edit_date > 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}

// Funktion prueft, ob der angemeldete User Fotos hochladen und verwalten darf

function editPhoto($organization = "")
{
    global $g_current_user;
    global $g_adm_con;
    global $g_organization;

    if(strlen($organization) == 0)
    {
        $organization = $g_organization;
    }

    $sql    = "SELECT *
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
                WHERE mem_usr_id        = $g_current_user->id
                  AND mem_rol_id        = rol_id
                  AND mem_valid         = 1
                  AND rol_org_shortname = {0}
                  AND rol_photo         = 1
                  AND rol_valid         = 1 ";
    $sql    = prepareSQL($sql, array($organization));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $edit_photo = mysql_num_rows($result);

    if($edit_photo > 0)
    {
        return true;
    }
    else
    {
        return false;
    }
}

// diese Funktion gibt eine Seitennavigation in Anhaengigkeit der Anzahl Seiten zurueck
// Beispiel:
//              Seite: < Vorherige 1  2  3 ... 9  10  11 Naechste >
// Uebergaben:
// base_url   : Basislink zum Modul (auch schon mit notwendigen Uebergabevariablen)
// num_items  : Gesamtanzahl an Elementen
// per_page   : Anzahl Elemente pro Seite
// start_item : Mit dieser Elementnummer beginnt die aktuelle Seite
// add_prevnext_text : Links mit "Vorherige" "Naechste" anzeigen

function generatePagination($base_url, $num_items, $per_page, $start_item, $add_prevnext_text = true)
{
    global $g_root_path;
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
            $page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
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
                    $page_string .= ($i == $on_page) ? '<b>' . $i . '</b>' : '<a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
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
                $page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>'  : '<a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
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
            $page_string .= ( $i == $on_page ) ? '<b>' . $i . '</b>' : '<a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $i - 1 ) * $per_page ) . '">' . $i . '</a>';
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
            $page_string = '<a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) . '"><img 
                                class="iconLink" src="'. $g_root_path. '/adm_program/images/back.png" style="vertical-align: middle;" border="0" alt="Vorherige"></a>
                            <a class="iconLink" href="' . $base_url . "&amp;start=" . ( ( $on_page - 2 ) * $per_page ) . '">Vorherige</a>&nbsp;&nbsp;' . $page_string;
        }

        if ( $on_page < $total_pages )
        {
            $page_string .= '&nbsp;&nbsp;<a class="iconLink" href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '">N&auml;chste</a>
                            <a class="iconLink" href="' . $base_url . "&amp;start=" . ( $on_page * $per_page ) . '"><img 
                                class="iconLink" src="'. $g_root_path. '/adm_program/images/forward.png" style="vertical-align: middle;" border="0" alt="N&auml;chste"></a>';
        }

    }

    $page_string = '<p><span class="iconLink">Seite:&nbsp;&nbsp;' . $page_string. '</span></p>';

    return $page_string;
}

// Diese Funktion erzeugt eine Combobox mit allen Rollen, die der Benutzer sehen darf
// Die Rollen werden dabei nach Kategorie gruppiert
//
// Uebergaben:
// field_id   : Id und Name der Select-Box

function generateRoleSelectBox($default_role = 0, $field_id = "")
{
    global $g_organization, $g_current_user, $g_adm_con;
    
    if(strlen($field_id) == 0)
    {
        $field_id = "rol_id";
    }
    $box_string = "
        <select size=\"1\" id=\"$field_id\" name=\"$field_id\">
            <option value=\"0\" selected=\"selected\">- Bitte w&auml;hlen -</option>";
            // Rollen selektieren

            // Webmaster und Moderatoren duerfen Listen zu allen Rollen sehen
            if($g_current_user->assignRoles())
            {
                $sql     = "SELECT * FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                             WHERE rol_org_shortname = '$g_organization'
                               AND rol_valid         = 1
                               AND rol_cat_id        = cat_id
                             ORDER BY cat_name, rol_name";
            }
            else
            {
                $sql     = "SELECT * FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. "
                             WHERE rol_org_shortname = '$g_organization'
                               AND rol_locked        = 0
                               AND rol_valid         = 1
                               AND rol_cat_id        = cat_id
                             ORDER BY cat_name, rol_name";
            }
            $result_lst = mysql_query($sql, $g_adm_con);
            db_error($result_lst,__FILE__,__LINE__);
            $act_category = "";

            while($row = mysql_fetch_object($result_lst))
            {
                if($act_category != $row->cat_name)
                {
                    if(strlen($act_category) > 0)
                    {
                        $box_string .= "</optgroup>";
                    }
                    $box_string .= "<optgroup label=\"$row->cat_name\">";
                    $act_category = $row->cat_name;
                }
                // wurde eine Rollen-Id uebergeben, dann Combobox mit dieser vorbelegen
                $selected = "";
                if($row->rol_id == $default_role)
                {
                    $selected = " selected ";
                }
                $box_string .= "<option $selected value=\"$row->rol_id\">$row->rol_name</option>";
            }
            $box_string .= "</optgroup>
        </select>";
    return $box_string;
}

// Funktion um Systemeinstellungen in die DB und das globale Array zu schreiben

function writeOrgaPreferences($name, $value)
{
    global $g_adm_con;
    global $g_current_organization;
    global $g_preferences;

    $sql = "SELECT * FROM ". TBL_PREFERENCES. "
             WHERE prf_name   = {0}
               AND prf_org_id = $g_current_organization->id ";
    $sql = prepareSQL($sql, array($name));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    if(mysql_num_rows($result) > 0)
    {
        $sql = "UPDATE ". TBL_PREFERENCES. " SET prf_value = {0}
                 WHERE prf_name   = {1}
                   AND prf_org_id = $g_current_organization->id ";
        $sql = prepareSQL($sql, array($value, $name));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
    }
    else
    {
        $sql = "INSERT INTO ". TBL_PREFERENCES. " (prf_org_id, prf_name, prf_value)
                                           VALUES ($g_current_organization->id, {0}, {1}) ";
        $sql = prepareSQL($sql, array($name, $value));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
    }
    
    // den Wert noch im globalen Array setzen
    $g_preferences[$name] = $value;
}

?>