<?php
/******************************************************************************
 * Organisationseinstellungen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * url:     URL auf die danach weitergeleitet wird
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

// nur Webmaster duerfen Organisationen bearbeiten
if(!hasRole("Webmaster"))
{
    $g_message->show("norights");
}

// wenn URL uebergeben wurde zu dieser gehen, ansonsten zurueck
if(array_key_exists('url', $_GET))
{
    $url = $_GET['url'];
}
else
{
    $url = urlencode(getHttpReferer());
}

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - bearbeiten</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <!--[if lt IE 7]>
    <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
    echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
		<h1>Organisationseinstellungen</h1>
		<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields.php\"><img
                 class=\"iconLink\" src=\"$g_root_path/adm_program/images/application_form.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Organisationsspezifische Profilfelder pflegen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/organization/fields.php\">Profilfelder pflegen</a>
            </span>
		</p>
        <form action=\"organization_function.php?org_id=$g_current_organization->id&amp;url=$url\" method=\"post\" name=\"orga_settings\">
            <div class=\"formBody\">
				<div>
				</div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 48%; float: left;\">Name (Abk.):</div>
                    <div style=\"text-align: left; margin-left: 50%;\">
                        <input type=\"text\" name=\"shortname\" class=\"readonly\" readonly size=\"10\" maxlength=\"10\" value=\"$g_current_organization->shortname\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 48%; float: left;\">Name (lang):</div>
                    <div style=\"text-align: left; margin-left: 50%;\">
                        <input type=\"text\" id=\"longname\" name=\"longname\" style=\"width: 200px;\" maxlength=\"60\" value=\"$g_current_organization->longname\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 48%; float: left;\">Homepage:</div>
                    <div style=\"text-align: left; margin-left: 50%;\">
                        <input type=\"text\" name=\"homepage\" style=\"width: 200px;\" maxlength=\"50\" value=\"$g_current_organization->homepage\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 48%; float: left;\">E-Mail Adresse des Administrator:</div>
                    <div style=\"text-align: left; margin-left: 50%;\">
                        <input type=\"text\" name=\"email_administrator\" style=\"width: 200px;\" maxlength=\"50\" value=\"". $g_preferences['email_administrator']. "\">
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=mail_admin','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                    </div>
                </div>
                <div style=\"margin-top: 6px;\">
                    <div style=\"text-align: right; width: 48%; float: left;\">Standard-Land:</div>
                    <div style=\"text-align: left; margin-left: 50%;\">";
                        //Laenderliste oeffnen
                        $landlist = fopen("../../system/staaten.txt", "r");
                        echo "
                        <select size=\"1\" name=\"default_country\">
                            <option value=\"\"";
                            if(strlen($g_preferences['default_country']) == 0)
                            {
                                echo " selected ";
                            }
                            echo ">- Bitte w&auml;hlen -</option>";
                            $land = utf8_decode(trim(fgets($landlist)));
                            while (!feof($landlist))
                            {    
                                echo"<option value=\"$land\"";
                                if($land == $g_preferences['default_country'])
                                {
                                    echo " selected ";
                                }
                                echo">$land</option>";
                                $land = utf8_decode(trim(fgets($landlist)));
                            }    
                        echo"</select>
                    </div>
                </div>";

                // Pruefung ob dieser Orga bereits andere Orgas untergeordnet sind
                $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. " WHERE org_org_id_parent = $g_current_organization->id";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);

                //Falls andere Orgas untergeordnet sind, darf diese Orga keiner anderen Orga untergeordnet werden
                if(mysql_num_rows($result)==0)
                {
                    $sql = "SELECT * FROM ". TBL_ORGANIZATIONS. "
                             WHERE org_id <> $g_current_organization->id
                               AND org_org_id_parent is NULL
                             ORDER BY org_longname ASC, org_shortname ASC ";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);

                    if(mysql_num_rows($result) > 0)
                    {
                        // Auswahlfeld fuer die uebergeordnete Organisation
                        echo "
                        <div style=\"margin-top: 6px;\">
                            <div style=\"text-align: right; width: 48%; float: left;\">&Uuml;bergeordnete Organisation:</div>
                            <div style=\"text-align: left; margin-left: 50%;\">
                                <select size=\"1\" name=\"parent\">
                                    <option value=\"0\" ";
                                    if(strlen($g_current_organization->org_id_parent) == 0)
                                    {
                                        echo " selected ";
                                    }
                                    echo ">keine</option>";

                                    while($row = mysql_fetch_object($result))
                                    {
                                        echo "<option value=\"$row->org_id\"";
                                            if($g_current_organization->org_id_parent == $row->org_id)
                                            {
                                                echo " selected ";
                                            }
                                            echo ">$row->org_shortname</option>";
                                    }
                                echo "</select>
                            </div>
                        </div>";
                    }
                }

                echo "
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen</div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Externes Mailprogramm:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"checkbox\" id=\"send_email_extern\" name=\"send_email_extern\" ";
                            if($g_preferences['send_email_extern'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=send_email_extern','Message','width=400,height=400,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">BBCode zulassen:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"checkbox\" id=\"enable_bbcode\" name=\"enable_bbcode\" ";
                            if($g_preferences['enable_bbcode'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">RSS-Feeds aktivieren:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"checkbox\" id=\"enable_rss\" name=\"enable_rss\" ";
                            if($g_preferences['enable_rss'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=enable_rss','Message','width=400,height=300,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>
                </div>

                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Maximale Dateigr&ouml;&szlig;e f&uuml;r&nbsp;&nbsp;
                        <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=file_size','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                    </div>
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">E-Mail-Attachments:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"max_email_attachment_size\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['max_email_attachment_size']. "\"> KB
                        </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Downloads:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"max_file_upload_size\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['max_file_upload_size']. "\"> KB
                        </div>
                    </div>
                </div>";

                //Einstellungen Photomodul
                echo"
                <div class=\"groupBox\" style=\"margin-top: 15px; text-align: left; width: 95%;\">
                    <div class=\"groupBoxHeadline\">Einstellungen Fotomodul&nbsp;&nbsp; </div>
                    
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Thumbnailzeilen:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_thumbs_row\" size=\"2\" maxlength=\"2\" value=\"". $g_preferences['photo_thumbs_row']. "\"> 
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_thumbs_row','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Thumbnailspalten:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_thumbs_column\" size=\"2\" maxlength=\"2\" value=\"". $g_preferences['photo_thumbs_column']. "\">
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_thumbs_column','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>
                    
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Skalierung Thumbnails:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_thumbs_scale\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['photo_thumbs_scale']. "\"> Pixel
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_thumbs_scale','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>
                                   
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Skalierung beim Hochladen:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_save_scale\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['photo_save_scale']. "\"> Pixel
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_save_scale','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>
                    
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">H&ouml;he der Vorschaubilder:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_preview_scale\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['photo_preview_scale']. "\"> Pixel
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_preview_scale','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>
                    
                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Max. Bildanzeigebreite:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_show_width\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['photo_show_width']. "\"> Pixel
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_show_size','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Max. Bildanzeigeh&ouml;he:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"text\" name=\"photo_show_height\" size=\"4\" maxlength=\"4\" value=\"". $g_preferences['photo_show_height']. "\"> Pixel
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_show_size','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\">
                         </div>
                    </div>

                    <div style=\"margin-top: 6px;\">
                        <div style=\"text-align: right; width: 47%; float: left;\">Bildtext einblenden:</div>
                        <div style=\"text-align: left; margin-left: 50%;\">
                            <input type=\"checkbox\" id=\"photo_image_text\" name=\"photo_image_text\" ";
                            if($g_preferences['photo_image_text'] == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_image_text','Message','width=400,height=400,left=310,top=200,scrollbars=yes')\">
                        </div>
                    </div>

                </div>

                <div style=\"margin-top: 6px;\">
                    <button name=\"speichern\" type=\"submit\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern</button>
                </div>
            </div>
        </form>
    </div>

    <script type=\"text/javascript\"><!--
        document.getElementById('longname').focus();
    --></script>";

    require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>