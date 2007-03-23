<?php
/******************************************************************************
 * Uebersicht ueber Admidio
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

// wenn noch nicht installiert, dann Install-Dialog anzeigen
if(!file_exists("../adm_config/config.php"))
{
    $location = "Location: ../adm_install/index.php";
    header($location);
    exit();
}

include("system/common.php");

$webmasterRole = false;
if($g_current_user->isWebmaster())
{
    // der Installationsordner darf aus Sicherheitsgruenden nicht existieren
    if(!DEBUG && file_exists("../adm_install"))
    {
        $g_message->show("installFolderExists");
    }
    $webmasterRole = true;
}

echo "
<!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
    <title>$g_current_organization->longname - Admidio &Uuml;bersicht</title>
    <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

    <style type=\"text/css\">
    <!--
    .textHead {
        font-size:     14pt;
        font-weight:   bold;
    }

    -->
    </style>

    <!--[if lt IE 7]>
    <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
    <![endif]-->";

    require("../adm_config/header.php");
echo "</head>";

require("../adm_config/body_top.php");
    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
        <table border=\"0\">
            <tr>
                <td align=\"right\"><img src=\"../adm_program/images/admidio_logo_50.png\" border=\"0\" alt=\"Admidio\" /></td>
                <td align=\"left\"><span style=\"font-size: 22pt; font-weight: bold;\">&nbsp;-&nbsp;&Uuml;bersicht</span></td>
            </tr>
        </table>

        <div style=\"padding-top: 15px; padding-bottom: 5px;\">";
            if($g_session_valid == 1)
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/logout.php\"><img
                    src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Logout\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/logout.php\">Logout</a>
                </span>";
            }
            else
            {
                echo "<span class=\"iconLink\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/login.php\"><img
                    src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Login\"></a>
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/login.php\">Login</a>
                </span>";
                if($g_preferences['registration_mode'] > 0)
                {
                    echo "&nbsp;&nbsp;&nbsp;
                    <span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/registration.php\"><img
                        src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Registrieren\"></a>
                        <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/registration.php\">Registrieren</a>
                    </span>";
                }
            }
        echo "</div>";

        echo "<br />";

        echo "
        <div class=\"formHead\">Module</div>
        <div class=\"formBody\">";

            if($g_preferences['enable_announcements_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/announcements/announcements.php\">
                    <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/note_big.png\" border=\"0\" alt=\"Ank&uuml;ndigungen\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/announcements/announcements.php\">Ank&uuml;ndigungen</a></span><br />
                    <span class=\"smallFontSize\">Hier k&ouml;nnen Ank&uuml;ndigungen (News / Aktuelles) angeschaut, erstellt und bearbeitet werden.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            if($g_preferences['enable_download_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/download/download.php\">
                    <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/download_big.png\" border=\"0\" alt=\"Download\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/download/download.php\">Download</a></span><br />
                    <span class=\"smallFontSize\">Benutzer k&ouml;nnen Dateien aus bestimmten Verzeichnissen herunterladen.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            if($g_preferences['enable_mail_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/mail/mail.php\">
                    <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/mail_open_big.png\" border=\"0\" alt=\"E-Mail\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/mail/mail.php\">E-Mail</a></span><br />
                    <span class=\"smallFontSize\">E-Mails an Rollen (Gruppen / Kurse / Abteilungen) schreiben.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            if($g_preferences['enable_photo_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/photos/photos.php\">
                    <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/photo_big.png\" border=\"0\" alt=\"Fotos\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/photos/photos.php\">Fotos</a></span><br />
                    <span class=\"smallFontSize\">Eine Fotoverwaltung bei der berechtigte Benutzer online Fotos hochladen k&ouml;nnen.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            if($g_preferences['enable_guestbook_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/guestbook/guestbook.php\">
                    <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/guestbook_big.png\" border=\"0\" alt=\"G&auml;stebuch\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/guestbook/guestbook.php\">G&auml;stebuch</a></span><br />
                    <span class=\"smallFontSize\">Hier k&ouml;nnen Besucher ihre Gr&uuml;&szlig;e und Anmerkungen eintragen.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            echo "
            <div style=\"text-align: left; width: 40; float: left;\">
                <a href=\"$g_root_path/adm_program/modules/lists/lists.php\">
                <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/list_big.png\" border=\"0\" alt=\"Listen\" />
                </a>
            </div>
            <div style=\"text-align: left; margin-left: 45px;\">
                <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/lists/lists.php\">Listen</a></span>&nbsp;&nbsp;
                &#91; <a href=\"$g_root_path/adm_program/modules/lists/mylist.php\">Eigene Liste</a>&nbsp;|
                <a href=\"$g_root_path/adm_program/modules/lists/lists.php?active_role=0\">Inaktive Rollen</a> &#93;<br />
                <span class=\"smallFontSize\">Verschiedene Benutzerlisten der Rollen (Gruppen / Kurse / Abteilungen) anzeigen.</span>
            </div>

            <div style=\"margin-top: 7px;\"></div>";


            echo "
            <div style=\"text-align: left; width: 40; float: left;\">
                <a href=\"$g_root_path/adm_program/modules/profile/profile.php\">
                <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/person_big.png\" border=\"0\" alt=\"Profil\" />
                </a>
            </div>
            <div style=\"text-align: left; margin-left: 45px;\">
                <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/profile/profile.php\">Profil</a></span><br />
                <span class=\"smallFontSize\">Das eigene Profil anschauen und bearbeiten.</span>
            </div>

            <div style=\"margin-top: 7px;\"></div>";

            if($g_preferences['enable_dates_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/dates/dates.php\">
                    <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/history_big.png\" border=\"0\" alt=\"Termine\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/dates/dates.php\">Termine</a></span>&nbsp;&nbsp;
                    &#91; <a href=\"$g_root_path/adm_program/modules/dates/dates.php?mode=old\">Vergangene Termine</a> &#93;<br />
                    <span class=\"smallFontSize\">Hier k&ouml;nnen Termine angeschaut, erstellt und bearbeitet werden.</span>
                </div>

                <div style=\"margin-top: 7px;\"></div>";
            }

            if($g_preferences['enable_weblinks_module'] == 1)
            {
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/modules/links/links.php\">
                    <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/globe_big.png\" border=\"0\" alt=\"Weblinks\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/links/links.php\">Weblinks</a></span><br />
                    <span class=\"smallFontSize\">Hier k&ouml;nnen Links zu interessanten Webseiten abgelegt werden.</span>
                </div>";
            }

            // Wenn das Forum aktiv ist, dieses auch in der Ã?bersicht anzeigen.
            if($g_forum_integriert)
            {
                echo "<div style=\"margin-top: 7px;\"></div>";

                if($g_forum->session_valid)
                {
                    $forumstext = "Sie sind als <b>".$g_forum->user."</b> im Forum <b>".$g_forum->sitename."</b> angemeldet ".$g_forum->neuePM_Text;
                }
                else
                {
                    $forumstext = "Der virtuelle Treffpunkt zum Austausch von Gedanken und Erfahrungen.";
                }
                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"http://". $_SERVER['HTTP_HOST']. "/$g_forum->path/index.php\">
                    <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/forum_big.png\" border=\"0\" alt=\"Forum\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"http://". $_SERVER['HTTP_HOST']. "/$g_forum->path/index.php\">Forum</a></span><br />
                    <span class=\"smallFontSize\">$forumstext</span>
                </div>";
            }
        echo "
        </div>";

        if(isModerator() || $g_current_user->editUser())
        {
            echo "<br /><br />

            <div class=\"formHead\">Administration</div>
            <div class=\"formBody\">";
                if($webmasterRole && $g_preferences['registration_mode'] > 0)
                {
                    echo "
                    <div style=\"text-align: left; width: 40; float: left;\">
                        <a href=\"$g_root_path/adm_program/administration/new_user/new_user.php\">
                        <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/ok_big.png\" border=\"0\" alt=\"Neue Web-Anmeldungen verwalten\" />
                        </a>
                    </div>
                    <div style=\"text-align: left; margin-left: 45px;\">
                        <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/new_user/new_user.php\">Neue Web-Anmeldungen verwalten</a></span><br />
                        <span class=\"smallFontSize\">Besucher, die sich auf der Homepage registriert haben, k&ouml;nnen hier freigeschaltet oder abgelehnt werden.</span>
                    </div>

                    <div style=\"margin-top: 7px;\"></div>";
                }

                echo "
                <div style=\"text-align: left; width: 40; float: left;\">
                    <a href=\"$g_root_path/adm_program/administration/members/members.php\">
                    <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/person_admin_big.png\" border=\"0\" alt=\"Benutzerverwaltung\" />
                    </a>
                </div>
                <div style=\"text-align: left; margin-left: 45px;\">
                    <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/members/members.php\">Benutzerverwaltung</a></span><br />
                    <span class=\"smallFontSize\">Mitglieder (Benutzer) k&ouml;nnen entfernt und neue Mitglieder (Benutzer) k&ouml;nnen in der Datenbank anlegt werden.</span>
                </div>";

                if(isModerator())
                {
                    echo "
                    <div style=\"margin-top: 7px;\"></div>

                    <div style=\"text-align: left; width: 40; float: left;\">
                        <a href=\"$g_root_path/adm_program/administration/roles/roles.php\">
                        <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/wand_big.png\" border=\"0\" alt=\"Rollenverwaltung\" />
                        </a>
                    </div>
                    <div style=\"text-align: left; margin-left: 45px;\">
                        <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/roles/roles.php\">Rollenverwaltung</a></span><br />
                        <span class=\"smallFontSize\">Rollen (Gruppen / Kurse / Abteilungen) k&ouml;nnen hier erstellt oder bearbeitet werden.</span>
                    </div>";
                }

                if($webmasterRole)
                {
                    echo "
                    <div style=\"margin-top: 7px;\"></div>

                    <div style=\"text-align: left; width: 40; float: left;\">
                        <a href=\"$g_root_path/adm_program/administration/organization/organization.php\">
                        <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/options_big.png\" border=\"0\" alt=\"Organisationseinstellungen\" />
                        </a>
                    </div>
                    <div style=\"text-align: left; margin-left: 45px;\">
                        <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/organization/organization.php\">Organisationseinstellungen</a></span><br />
                        <span class=\"smallFontSize\">Einstellungen f&uuml;r die Organisation, spezifische Profilfelder und Rollenkategorien k&ouml;nnen hier bearbeitet werden.</span>
                    </div>";
                }
            echo "</div>";
        }
    echo "</div>";

    require("../adm_config/body_bottom.php");
echo "</body>
</html>";
?>