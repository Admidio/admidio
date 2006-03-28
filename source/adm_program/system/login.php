<?php
/******************************************************************************
 * Loginseite
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * url: Seite, die nach erfolgreichem Login aufgerufen wird
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

require("common.php");

if(!array_key_exists("url", $_GET))
   $_GET['url'] = "";

echo "
<!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>$g_current_organization->longname - Login</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script language=\"JavaScript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->";
   
   require("../../adm_config/header.php");
echo "</head>";

require("../../adm_config/body_top.php");
   echo "<div align=\"center\">
   <br /><br /><br />
   <form action=\"login_check.php?url=". urlencode($_GET['url']). "\" name=\"Login\" method=\"post\">
      <div class=\"formHead\" style=\"width: 260px\">". strspace("Login"). "
      </div>
      <div class=\"formBody\" style=\"width: 260px\">
         <div style=\"margin-top: 7px;\">
            <div style=\"text-align: right; width: 110px; float: left;\">Benutzername:</div>
            <div style=\"text-align: left; margin-left: 120px;\">
               <input type=\"text\" name=\"loginname\" size=\"14\" maxlength=\"20\" />
            </div>
         </div>
         <div style=\"margin-top: 15px;\">
            <div style=\"text-align: right; width: 110px; float: left;\">Passwort:</div>
            <div style=\"text-align: left; margin-left: 120px;\">
               <input type=\"password\" name=\"passwort\" size=\"14\" maxlength=\"20\" />
            </div>
         </div>
         <div style=\"margin-top: 10px;\">
            <input type=\"checkbox\" style=\"vertical-align: middle;\" id=\"long_login\" name=\"long_login\" value=\"1\" />
            <label for=\"long_login\">nach 8 Stunden ausloggen</label>
            <img src=\"$g_root_path/adm_program/images/help.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
            onClick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=login','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
         </div>
         <div style=\"margin-top: 5px;\">
            <button name=\"login\" type=\"submit\" value=\"login\">
            <img src=\"$g_root_path/adm_program/images/key.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Login\">
            &nbsp;Login</button>
         </div>
      </div>
      
      <div class=\"formHead\" style=\"width: 260px\">". strspace("Registrieren"). "</div>
      <div class=\"formBody\" style=\"width: 260px\">
         <div style=\"margin-bottom: 7px;\">Du bist noch nicht registriert ?</div>
         <button name=\"Registrieren\" type=\"button\" value=\"Registrieren\"
            onclick=\"self.location.href='registration.php'\">
            <img src=\"$g_root_path/adm_program/images/register.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Registrieren\">
            &nbsp;Registrieren</button>
         <div style=\"font-size: 8pt; margin-top: 5px;\">
            Powered by <a href=\"http://www.admidio.org\" target=\"_blank\">Admidio ". getVersion(). "</a>
         </div>
      </div>
   </form>
   </div>";

   require("../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>