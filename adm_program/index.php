<?php
/******************************************************************************
 * Uebersicht ueber Admidio
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

// wenn noch nicht installiert, dann Install-Dialog anzeigen
if(!file_exists("../adm_config/config.php"))
{
   $location = "location: ../adm_install/index.php";
   header($location);
   exit();
}

include("system/common.php");
include("system/session_check.php");

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
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

   .textHeadSmall {
      font-size:     9pt;
      font-weight:   bold;
   }

   .textDescription {
      font-size:     8pt;
   }
   -->
   </style>

   <!--[if gte IE 5.5000]>
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
   </table>";

   if(isModerator())
   {
      echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\">
      <div class=\"formHead\">";
         echo strspace("Administration", 1);
      echo "</div>
      <div class=\"formBody\">
         <div style=\"text-align: left; width: 40; float: left;\">
            <a href=\"$g_root_path/adm_program/administration/new_user/new_user.php\">
               <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/ok_big.png\" border=\"0\" alt=\"Neue Web-Anmeldungen verwalten\" />
            </a>
         </div>
         <div style=\"text-align: left; margin-left: 45px;\">
            <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/new_user/new_user.php\">Neue Web-Anmeldungen verwalten</a></span><br />
            <span class=\"textDescription\">Besucher, die sich auf der Homepage registriert haben, k&ouml;nnen hier freigeschaltet oder abgelehnt werden.</span>
         </div>

         <div style=\"margin-top: 7px;\"></div>

         <div style=\"text-align: left; width: 40; float: left;\">
            <a href=\"$g_root_path/adm_program/administration/members/members.php\">
               <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/person_admin_big.png\" border=\"0\" alt=\"Benutzerverwaltung\" />
            </a>
         </div>
         <div style=\"text-align: left; margin-left: 45px;\">
            <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/members/members.php\">Benutzerverwaltung</a></span><br />
            <span class=\"textDescription\">Mitglieder (Benutzer) k&ouml;nnen entfernt und neue Mitglieder (Benutzer) k&ouml;nnen in der Datenbank anlegt werden.</span>
         </div>

         <div style=\"margin-top: 7px;\"></div>

         <div style=\"text-align: left; width: 40; float: left;\">
            <a href=\"$g_root_path/adm_program/administration/roles/roles.php\">
               <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/wand_big.png\" border=\"0\" alt=\"Rollenverwaltung\" />
            </a>
         </div>
         <div style=\"text-align: left; margin-left: 45px;\">
            <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/roles/roles.php\">Rollenverwaltung</a></span><br />
            <span class=\"textDescription\">Rollen (Gruppen / Kurse / Abteilungen) k&ouml;nnen hier erstellt oder bearbeitet werden.</span>
         </div>

         <div style=\"margin-top: 7px;\"></div>

         <div style=\"text-align: left; width: 40; float: left;\">
            <a href=\"$g_root_path/adm_program/administration/organization/organization.php\">
               <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/options_big.png\" border=\"0\" alt=\"Gruppierung bearbeiten\" />
            </a>
         </div>
         <div style=\"text-align: left; margin-left: 45px;\">
            <span class=\"textHead\"><a href=\"$g_root_path/adm_program/administration/organization/organization.php\">Gruppierung bearbeiten</a></span><br />
            <span class=\"textDescription\">Gruppierung (Verein / Organisation) und spezifische Profilfelder k&ouml;nnen hier bearbeitet werden.</span>
         </div>
      </div>
      </div>
      <br /><br />";
   }
   else
      echo "<br />";

   echo "
   <div class=\"formHead\">";
      echo strspace("Module", 1);
   echo "</div>
   <div class=\"formBody\">
      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/announcements/announcements.php\">
            <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/note_big.png\" border=\"0\" alt=\"Ank&uuml;ndigungen\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/announcements/announcements.php\">Ank&uuml;ndigungen</a></span><br />
         <span class=\"textDescription\">Hier k&ouml;nnen Ank&uuml;ndigungen (News / Aktuelles) angeschaut, erstellt und bearbeitet werden.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/download/download.php\">
            <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/download_big.png\" border=\"0\" alt=\"Download\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/download/download.php\">Download</a></span><br />
         <span class=\"textDescription\">Benutzer k&ouml;nnen Dateien aus bestimmten Verzeichnissen herunterladen.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/photos/photos.php\">
            <img style=\"position: relative; top: 2px;\" src=\"$g_root_path/adm_program/images/photo_big.png\" border=\"0\" alt=\"Fotos\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/photos/photos.php\">Fotos</a></span><br />
         <span class=\"textDescription\">Eine Fotoverwaltung bei der berechtigte Benutzer online Fotos hochladen k&ouml;nnen.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/lists/mylist.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/list_big.png\" border=\"0\" alt=\"Eigene Liste\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/lists/mylist.php\">Eigene Liste</a></span><br />
         <span class=\"textDescription\">Kriterien einer Benutzerliste selber zusammensetzen und anzeigen.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/mail/mail.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/mail_open_big.png\" border=\"0\" alt=\"E-Mail\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/mail/mail.php\">E-Mail</a></span><br />
         <span class=\"textDescription\">E-Mails an Rollen (Gruppen / Kurse / Abteilungen) schreiben.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/lists/lists.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/list_big.png\" border=\"0\" alt=\"Listen\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/lists/lists.php\">Listen</a></span>&nbsp;&nbsp;
         <span class=\"textHeadSmall\">&#91; <a href=\"$g_root_path/adm_program/modules/lists/lists.php?show=group\">Gruppen</a> &#124;
         <a href=\"$g_root_path/adm_program/modules/lists/lists.php?type=former\">Ehemalige</a> &#93;</span><br />
         <span class=\"textDescription\">Verschiedene Benutzerlisten der Rollen (Gruppen / Kurse / Abteilungen) anzeigen.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/system/login.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/key_big.png\" border=\"0\" alt=\"Login\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/system/login.php\">Login</a></span><br />
         <span class=\"textDescription\">Einen Benutzer am System anmelden.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/system/logout.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/error_big.png\" border=\"0\" alt=\"Logout\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/system/logout.php\">Logout</a></span><br />
         <span class=\"textDescription\">Den angemeldeten Benutzer vom System abmelden.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/profile/profile.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/person_big.png\" border=\"0\" alt=\"Profil\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/profile/profile.php\">Profil</a></span><br />
         <span class=\"textDescription\">Das eigene Profil anschauen und bearbeiten.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/system/registration.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/write_big.png\" border=\"0\" alt=\"Registrieren\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/system/registration.php\">Registrieren</a></span><br />
         <span class=\"textDescription\">Ein Besucher kann sich am System registrieren.</span>
      </div>

      <div style=\"margin-top: 7px;\"></div>

      <div style=\"text-align: left; width: 40; float: left;\">
         <a href=\"$g_root_path/adm_program/modules/dates/dates.php\">
            <img style=\"position: relative; top: 5px;\" src=\"$g_root_path/adm_program/images/history_big.png\" border=\"0\" alt=\"Termine\" />
         </a>
      </div>
      <div style=\"text-align: left; margin-left: 45px;\">
         <span class=\"textHead\"><a href=\"$g_root_path/adm_program/modules/dates/dates.php\">Termine</a></span>&nbsp;&nbsp;
         <span class=\"textHeadSmall\">&#91; <a href=\"$g_root_path/adm_program/modules/dates/dates.php?mode=old\">Vergangene Termine</a> &#93;</span><br />
         <span class=\"textDescription\">Hier k&ouml;nnen Termine angeschaut, erstellt und bearbeitet werden.</span>
      </div>
   </div>

   </div>";

   require("../adm_config/body_bottom.php");
echo "</body>
</html>";
?>