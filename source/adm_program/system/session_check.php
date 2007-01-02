<?php
/******************************************************************************
 * Script prüft, ob man eingeloggt ist und setzt die Zeitstempel neu
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
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

// Cookies einlesen
if(isset($_COOKIE['adm_session']))
{
    $g_session_id = $_COOKIE['adm_session'];
}
else
{
    $g_session_id = "";
}

$g_session_valid = false;

if(strlen($g_session_id) > 0)
{
    // Session auf Gueltigkeit pruefen

    $sql    = "SELECT * FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
    $sql    = prepareSQL($sql, array($g_session_id));
    $result = mysql_query($sql, $g_adm_con);

    db_error($result);

    $session_found = mysql_num_rows($result);
    $row           = mysql_fetch_object($result);

    if ($session_found == 1)
    {
        $valid    = false;
        $time_gap = time() - mysqlmaketimestamp($row->ses_timestamp);
        // wenn länger nichts gemacht wurde, als in Orga-Prefs eingestellt ist, dann ausloggen
        if ($time_gap < $g_preferences['logout_minutes'] * 60)
        {
            $valid = true;
        }

        if($valid)
        {
            $g_session_valid = true;

            // Datetime der Session muss aktualisiert werden

            $act_datetime   = date("Y-m-d H:i:s", time());

            $sql    = "UPDATE ". TBL_SESSIONS. " SET ses_timestamp = '$act_datetime'
                        WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
        }
        else
        {
            // User war zu lange inaktiv -> Session loeschen
            $g_current_user->clear();

            $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);

            db_error($result);
        }
    }
    else
    {
        $g_current_user->clear();

        if ($session_found != 0)
        {
            // ID mehrfach vergeben -> Fehler und IDs loeschen
            $sql    = "DELETE FROM ". TBL_SESSIONS. " WHERE ses_session LIKE {0}";
            $sql    = prepareSQL($sql, array($g_session_id));
            $result = mysql_query($sql, $g_adm_con);

            db_error($result);
        }
    }
}

?>