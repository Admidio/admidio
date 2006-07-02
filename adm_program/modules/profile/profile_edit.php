<?php
/******************************************************************************
 * Profil bearbeiten
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * user_id : zeigt das Profil der ?bergebenen user_id an
 * new_user - 1 : Dialog um neue Benutzer hinzuzuf?gen.
 * url :     URL auf die danach weitergeleitet wird
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

require("../../system/common.php");
require("../../system/login_valid.php");

//pr?fen ob in Popup angezeigt wird oder Normal (default)
if($_GET['popup'] == 1)
{
    $popup = 1;
}
else 
{
    $popup = 0;
}

// pruefen, ob Modus neues Mitglied erfassen
if(!array_key_exists("new_user", $_GET))
{
    $a_new_user = false;
}
else
{
    $a_new_user = $_GET['new_user'];
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
{
    $url = urlencode($_GET['url']);
}
else
{
    $url = urlencode(getHttpReferer());
}

// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
if(!editUser() && $_GET['user_id'] != $g_current_user->id)
{
   $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
   header($location);
   exit();
}

// user_id und edit-Modus ermitteln
if($a_new_user)
{
   if(strlen($_GET['user_id']) > 0)
      $a_user_id = $_GET['user_id'];
   else
      $a_user_id = 0;
}
else
{
   $a_user_id = $_GET['user_id'];
   // jetzt noch schauen, ob User ?berhaupt Mitglied in der Gliedgemeinschaft ist
   $sql = "SELECT mem_id
             FROM ". TBL_MEMBERS. ", ". TBL_ROLES. "
            WHERE rol_org_shortname = '$g_organization'
              AND rol_valid        = 1
              AND mem_rol_id        = rol_id
              AND mem_valid        = 1
              AND mem_usr_id        = {0}";
   $sql    = prepareSQL($sql, array($_GET['user_id']));
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   if(mysql_num_rows($result) == 0)
   {
      $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=norights";
      header($location);
      exit();
   }
}

// User auslesen
if($a_user_id > 0)
{
   $user = new TblUsers($g_adm_con);
    $user->GetUser($a_user_id);
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Profil bearbeiten</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

   <!--[if lt IE 7]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
if($popup == 0)
   require("../../../adm_config/header.php");
echo "</head>";
if($popup == 0)
    require("../../../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <form action=\"profile_save.php?user_id=$a_user_id&amp;new_user=$a_new_user&amp;url=$url";
        if($a_new_user && $a_user_id > 0) 
        {
            echo "&amp;pw=$user->password";
        }
        echo "\" method=\"post\" name=\"ProfilAnzeigen\">
            <div class=\"formHead\">";
                if($a_user_id == $g_current_user->id)
                {
                    echo strspace("Mein Profil", 2);
                }
                else if($a_new_user)
                {
                    echo strspace("Neuer Benutzer", 2);
                }
                else
                {
                    echo strspace("Profil von ". $user->first_name. " ". $user->last_name, 1);
                }
            echo "</div>
            <div class=\"formBody\">
                <div>
                    <div style=\"text-align: right; width: 30%; float: left;\">Nachname:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_user_id == 0)
                        {
                            echo "<input type=\"text\" id=\"last_name\" name=\"last_name\" size=\"30\" maxlength=\"30\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" id=\"last_name\" name=\"last_name\" size=\"30\" maxlength=\"30\" value=\"$user->last_name\" ";
                            if(!hasRole('Webmaster'))
                            {
                                echo " class=\"readonly\" readonly ";
                            }
                            echo " />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Vorname:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_user_id == 0)
                        {
                            echo "<input type=\"text\" name=\"first_name\" size=\"30\" maxlength=\"30\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"first_name\" size=\"30\" maxlength=\"30\" value=\"$user->first_name\" ";
                            if(!hasRole('Webmaster'))
                            {
                                echo " class=\"readonly\" readonly ";
                            }
                            echo " />";
                        }
                    echo "</div>
                </div>";
                if(!$a_user_id == 0)
                {
                    echo "<div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">Benutzername:</div>
                        <div style=\"text-align: left; margin-left: 32%;\">
                            <input type=\"text\" name=\"login_name\" size=\"15\" maxlength=\"20\" value=\"$user->login_name\" ";
                            if(!hasRole('Webmaster'))
                            {
                                echo " class=\"readonly\" readonly ";
                            }
                            echo " />
                        </div>
                    </div>";

                    // eigenes Passwort aendern, nur Webmaster duerfen Passwoerter von anderen aendern
                    if(hasRole('Webmaster') || $g_current_user->id == $a_user_id )
                    {
                        echo "<div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">Passwort:</div>
                            <div style=\"text-align: left; margin-left: 32%;\">
                                <button name=\"password\" type=\"button\" value=\"Passwort &auml;ndern\" onclick=\"window.open('password.php?user_id=$a_user_id','Titel','width=350,height=260,left=310,top=200')\">
                                <img src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Passwort &auml;ndern\">
                                &nbsp;Passwort &auml;ndern</button>
                            </div>
                        </div>";
                    }
                }

                echo "<hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Adresse:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" id=\"address\" name=\"address\" size=\"40\" maxlength=\"50\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" id=\"address\" name=\"address\" size=\"40\" maxlength=\"50\" value=\"$user->address\" />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Postleitzahl:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"zip_code\" size=\"10\" maxlength=\"10\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"zip_code\" size=\"10\" maxlength=\"10\" value=\"$user->zip_code\" />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Ort:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"city\" size=\"20\" maxlength=\"30\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"city\" size=\"20\" maxlength=\"30\" value=\"$user->city\" />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Land:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        //Laenderliste oeffnen
                        $landlist = fopen("../../system/staaten.txt", "r");
                        echo "
                        <select size=\"1\" name=\"country\" />
                            <option value=\"\"";
                                if(strlen($g_preferences['default_country']) == 0
                                && strlen($user->country) == 0)
                                {
                                    echo " selected ";
                                }
                            echo "></option>";
                            if(strlen($g_preferences['default_country']) > 0)
                            {
                                echo "<option value=\"". $g_preferences['default_country']. "\">". $g_preferences['default_country']. "</option>
                                <option value=\"\">--------------------------------</option>\n";
                            }
                            
                            $land = trim(fgets($landlist));
                            while (!feof($landlist))
                            {
                                echo"<option value=\"$land\"";
                                     if($a_new_user && $land == $g_preferences['default_country'])
                                     {
                                        echo " selected ";
                                     }
                                     if(!$a_new_user && $land == $user->country)
                                     {
                                        echo " selected ";
                                     }
                                echo">$land</option>\n";
                                $land = trim(fgets($landlist));
                            }    
                        
                        echo"
                        </select>";
                    echo "</div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Telefon:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"phone\" size=\"15\" maxlength=\"20\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"phone\" size=\"15\" maxlength=\"20\" value=\"$user->phone\" />";
                        }
                        echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Tel.Nr.)</span>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Handy:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"mobile\" size=\"15\" maxlength=\"20\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"mobile\" size=\"15\" maxlength=\"20\" value=\"$user->mobile\" />";
                        }
                        echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Handynr.)</span>
                     </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Fax:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"fax\" size=\"15\" maxlength=\"20\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"fax\" size=\"15\" maxlength=\"20\" value=\"$user->fax\" />";
                        }
                        echo "&nbsp;<span style=\"font-family: Courier;\">(Vorwahl-Faxnr.)</span>
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">E-Mail:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_user_id == 0)
                        {
                            echo "<input type=\"text\" name=\"email\" size=\"40\" maxlength=\"50\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"email\" size=\"40\" maxlength=\"50\" value=\"$user->email\" />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Homepage:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"homepage\" size=\"40\" maxlength=\"50\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"homepage\" size=\"40\" maxlength=\"50\" value=\"$user->homepage\" />";
                        }
                    echo "</div>
                </div>

                <hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Geburtstag:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">";
                        if($a_new_user)
                        {
                            echo "<input type=\"text\" name=\"birthday\" size=\"10\" maxlength=\"10\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" name=\"birthday\" size=\"10\" maxlength=\"10\" value=\"". mysqldatetime('d.m.y', $user->birthday). "\" />";
                        }
                    echo "</div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 30%; float: left;\">Geschlecht:</div>
                    <div style=\"text-align: left; margin-left: 32%;\">
                        <input type=\"radio\" id=\"female\" name=\"gender\" value=\"2\"";
                            if($user->gender == 2)
                                echo " checked ";
                            echo "><label for=\"female\"><img src=\"$g_root_path/adm_program/images/female.png\" title=\"weiblich\" alt=\"weiblich\"></label>
                        &nbsp;
                        <input type=\"radio\" id=\"male\" name=\"gender\" value=\"1\"";
                            if($user->gender == 1)
                                echo " checked ";
                            echo "><label for=\"male\"><img src=\"$g_root_path/adm_program/images/male.png\" title=\"m&auml;nnlich\" alt=\"m&auml;nnlich\"></label>
                    </div>
                </div>";

                if(!$a_new_user)
                {
                    echo "<hr width=\"80%\" />";

                    // alle zugeordneten Messengerdaten einlesen
                    $sql = "SELECT usf_name, usd_value
                              FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                                ON usd_usf_id = usf_id
                               AND usd_usr_id = $user->id
                             WHERE usf_org_shortname IS NULL
                               AND usf_type   = 'MESSENGER'
                             ORDER BY usf_name ASC ";
                    $result_msg = mysql_query($sql, $g_adm_con);
                    db_error($result_msg, true);

                    while($row = mysql_fetch_object($result_msg))
                    {
                        echo "<div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 30%; float: left;\">
                                $row->usf_name:
                                <img src=\"$g_root_path/adm_program/images/";
                                if($row->usf_name == 'AIM')
                                {
                                    echo "aim.png";
                                }
                                elseif($row->usf_name == 'Google Talk')
                                {
                                    echo "google.gif";
                                }
                                elseif($row->usf_name == 'ICQ')
                                {
                                    echo "icq.png";
                                }
                                elseif($row->usf_name == 'MSN')
                                {
                                    echo "msn.png";
                                }
                                elseif($row->usf_name == 'Skype')
                                {
                                    echo "skype.png";
                                }
                                elseif($row->usf_name == 'Yahoo')
                                {
                                    echo "yahoo.png";
                                }
                                echo "\" style=\"vertical-align: middle;\" />
                            </div>
                            <div style=\"text-align: left; margin-left: 32%;\">";
                                if($a_new_user)
                                {
                                    echo "<input type=\"text\" name=\"". urlencode($row->usf_name). "\" size=\"20\" maxlength=\"50\" />";
                                }
                                else
                                {
                                    echo "<input type=\"text\" name=\"". urlencode($row->usf_name). "\" size=\"20\" maxlength=\"50\" value=\"$row->usd_value\" />";
                                }
                            echo "</div>
                        </div>";
                    }
                }

                // gruppierungsspezifische Felder einlesen
                if($a_new_user)
                {
                    $sql = "SELECT *
                              FROM ". TBL_USER_FIELDS. "
                             WHERE usf_org_shortname = '$g_organization'
                             ORDER BY usf_name ASC ";
                }
                else
                {
                    $sql = "SELECT *
                              FROM ". TBL_USER_FIELDS. " LEFT JOIN ". TBL_USER_DATA. "
                                ON usd_usf_id = usf_id
                               AND usd_usr_id = $user->id
                             WHERE usf_org_shortname = '$g_organization' ";
                    if(!isModerator())
                    {
                        $sql = $sql. " AND usf_locked = 0 ";
                    }
                    $sql = $sql. " ORDER BY usf_name ASC ";
                }
                $result_field = mysql_query($sql, $g_adm_con);
                db_error($result_field, true);

                if(mysql_num_rows($result_field) > 0)
                {
                    echo "<hr width=\"80%\" />";
                }

                while($row = mysql_fetch_object($result_field))
                {
                    echo "<div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 30%; float: left;\">
                            $row->usf_name:
                        </div>
                        <div style=\"text-align: left; margin-left: 32%;\">";
                            echo "<input type=\"";
                            if($row->usf_type == "CHECKBOX")
                            {
                                echo "checkbox";
                            }
                            else
                            {
                                echo "text";
                            }
                            echo "\" id=\"". urlencode($row->usf_name). "\" name=\"". urlencode($row->usf_name). "\" ";
                            if($row->usf_type == "CHECKBOX")
                            {
                                if($row->usd_value == 1)
                                {
                                    echo " checked ";
                                }
                                echo " value=\"1\" ";
                            }
                            else
                            {
                                if($row->usf_type == "NUMERIC")
                                {
                                    echo " size=\"10\" maxlength=\"15\" ";
                                }
                                elseif($row->usf_type == "TEXT")
                                {
                                    echo " size=\"30\" maxlength=\"30\" ";
                                }
                                elseif($row->usf_type == "TEXT_BIG")
                                {
                                    echo " size=\"40\" maxlength=\"255\" ";
                                }
                                
                                if(strlen($row->usd_value) > 0)
                                {
                                    echo " value=\"$row->usd_value\" ";
                                }
                            }
                            echo ">";
                        echo "</div>
                    </div>";
                }

                echo "<hr width=\"80%\" />

                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                    &nbsp;Zur&uuml;ck</button>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                    <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                    &nbsp;Speichern</button>
                </div>";

                if($user->usr_id_change > 0)
                {
                    // Angabe ?ber die letzten Aenderungen
                    $sql    = "SELECT usr_first_name, usr_last_name
                                 FROM ". TBL_USERS. "
                                WHERE usr_id = $user->usr_id_change ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);
                    $row = mysql_fetch_array($result);

                    echo "<div style=\"margin-top: 6px;\"><span style=\"font-size: 10pt\">
                        Letzte &Auml;nderung am ". mysqldatetime("d.m.y h:i", $user->last_change).
                        " durch $row[0] $row[1]</span>
                    </div>";
                }
            echo "</div>
        </form>
    </div>
    <script type=\"text/javascript\"><!--\n";
        if(hasRole('Webmaster') || $a_new_user)
        {
            echo "document.getElementById('last_name').focus();";
        }
        else
        {
            echo "document.getElementById('address').focus();";
        }
    echo "\n--></script>";    
if($popup == 0)
{
    require("../../../adm_config/body_bottom.php");
}
echo "</body>
</html>";

?>