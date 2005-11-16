<?php
/******************************************************************************
 * Anzeigen von Listen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * show: all     - (Default) Alle Rollen (auch Gruppen anzeigen)
 *       group   - Nur Gruppen anzeigen
 *       nogroup - Keine Gruppen anzeigen
 * type: normal  - (Default) aktuelle Mitglieder anzeigen
 *       former  - Ehemaligenlisten der Rollen anzeigen
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
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
require("../../system/tbl_user.php");
require("../../system/session_check.php");

// Rollen selektieren

if(!isset($_GET["show"]))
   $group = -1;
else
{
   if($_GET["show"] == "all")
      $group = -1;
   elseif($_GET["show"] == "group")
      $group = 1;
   elseif($_GET["show"] == "nogroup")
      $group = 0;
}

if(!isset($_GET["type"]))
   $member_valid = 1;
else
{
   if($_GET["type"] == "former")
      $member_valid = 0;
   else
      $member_valid = 1;
}

// Webmaster und Moderatoren dürfen Listen zu allen Rollen sehen
if(isModerator())
{
   $sql = "SELECT * FROM adm_rolle
            WHERE ar_ag_shortname = '$g_organization' 
              AND ar_valid        = 1 ";
   if($group >= 0)
      $sql .= " AND ar_gruppe = $group ";
      
   $sql .= " ORDER BY ar_funktion ";
}
else
{
   $sql = "SELECT * FROM adm_rolle
            WHERE ar_ag_shortname = '$g_organization'
              AND ar_r_locked     = 0
              AND ar_valid        = 1 ";
   if($group >= 0)
      $sql .= " AND ar_gruppe = $group ";

   $sql .= " ORDER BY ar_funktion ";
}
$result_lst = mysql_query($sql, $g_adm_con);
db_error($result_lst);
$list_found = mysql_num_rows($result_lst);

if($list_found == 0)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=nolist";
   header($location);
   exit();
}

echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
<head>
   <title>". $g_orga_property['ag_shortname']. " - Listen</title>
   <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">
   
   <!--[if gte IE 5.5000]>
   <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
   <![endif]-->
   
   <script language=\"JavaScript\" type=\"text/javascript\"><!--\n
      function showList(element, role)
      {
         var sel_list = element.value;

         if(sel_list == 'address')
            self.location.href = 'lists_show.php?typ=address&mode=html&rolle=' + role;
         else if(sel_list == 'telefon')
            self.location.href = 'lists_show.php?typ=telephone&mode=html&rolle=' + role;
         else if(sel_list == 'mylist')
            self.location.href = 'mylist.php?rolle=' + role";
            if($member_valid)
               echo ";";
            else
               echo " + '&former=1';";
         echo "
         else if(sel_list == 'former')
            self.location.href = 'lists_show.php?typ=former&mode=html&rolle=' + role;
      }
   //--></script>";
               
   require("../../../adm_config/header.php");
echo "</head>";

require("../../../adm_config/body_top.php");
   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   
   <div class=\"formHead\">";
      if($member_valid)
         echo strspace("Listen", 2);
      else
         echo strspace("Ehemalige", 2);
   echo "</div>
   
   <div class=\"formBody\">";
      $i = 0;

      while($row_lst = mysql_fetch_object($result_lst))
      {
         // Anzahl Datensaetze ermitteln
         $sql = "SELECT COUNT(*)
                   FROM adm_mitglieder
                  WHERE am_ar_id = $row_lst->ar_id
                    AND am_valid = $member_valid ";
         $result = mysql_query($sql, $g_adm_con);
         db_error($result);
         $row     = mysql_fetch_array($result);
         $anz_mgl = $row[0];

         if($i > 0)
            echo"<hr width=\"98%\" />";

         echo "
         <div style=\"margin-top: 6px;\">
            <div style=\"text-align: left; float: left;\">&nbsp;";
                  // Link nur anzeigen, wenn Rolle auch Mitglieder hat
                  if($anz_mgl > 0)
                  {
                     echo "<a href=\"lists_show.php?typ=";
                     if($member_valid)
                        echo "address";
                     else
                        echo "former";
                     echo "&amp;mode=html&amp;rolle=". urlencode($row_lst->ar_funktion). "\">$row_lst->ar_funktion</a>";
                  }
                  else
                     echo "<b>$row_lst->ar_funktion</b>";
                     
                  // Moderatoren duerfen Rollen editieren
                  if(isModerator())
                  {
                    echo "&nbsp;<a href=\"$g_root_path/adm_program/administration/roles/roles_new.php?ar_id=$row_lst->ar_id\"><img src=\"$g_root_path/adm_program/images/edit.png\" vspace=\"1\" style=\"vertical-align: middle;\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Einstellungen\" title=\"Einstellungen\"></a>";    
                    echo "&nbsp;<a href onClick=\"window.open('$g_root_path/adm_program/modules/lists/members.php?ar_id=$row_lst->ar_id&amp;popup=1','Titel','width=550,height=450,left=310,top=100,scrollbars=yes,resizable=yes')\"><img src=\"$g_root_path/adm_program/images/person.png\" vspace=\"1\" style=\"vertical-align: middle;\" align=\"top\" width=\"16\" height=\"16\" border=\"0\" alt=\"Mitglieder zuordnen\" title=\"Mitglieder zuordnen\"></a>";    
                  }
            echo "</div>
            <div style=\"text-align: right;\">";
               // Kombobox mit Listen nur anzeigen, wenn die Rolle Mitglieder hat
               if($anz_mgl > 0)
               {
                     echo "
                     <select size=\"1\" name=\"list$i\" onchange=\"showList(this, '". urlencode($row_lst->ar_funktion). "')\">
                        <option value=\"\" selected=\"selected\">Liste anzeigen ...</option>";
                        if(!$member_valid) echo "<option value=\"former\">Ehemaligenliste</option>";
                        echo "<option value=\"address\">Adressliste</option>
                        <option value=\"telefon\">Telefonliste</option>
                        <option value=\"mylist\">Eigene Liste ...</option>
                     </select>";
               }
               else
                  echo "&nbsp;";
            echo "</div>
         </div>";

         if(strlen($row_lst->ar_beschreibung) > 0)
         {
            echo "<div style=\"margin-top: 6px;\">
                     <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Beschreibung:</div>
                     <div style=\"margin-left: 160px; text-align: left;\">$row_lst->ar_beschreibung</div>
                  </div>";
         }

         if($member_valid)
         {
            if(strlen(mysqldate("d.m.y", $row_lst->ar_datum_von)) > 0)
            {
               echo "<div style=\"margin-top: 6px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Zeitraum:</div>
                        <div style=\"text-align: left;\">". mysqldate("d.m.y", $row_lst->ar_datum_von). " bis ". mysqldate("d.m.y", $row_lst->ar_datum_bis). "</div>";
               echo "</div>";
            }
            if($row_lst->ar_wochentag > 0
            || (  strcmp(mysqltime("h:i", $row_lst->ar_zeit_von), "00:00") != 0)
               && $row_lst->ar_zeit_von != NULL )
            {
               echo "<div style=\"margin-top: 6px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Gruppenstunde:</div>
                        <div style=\"text-align: left;\">". $arrDay[$row_lst->ar_wochentag-1];
                        if(strcmp(mysqltime("h:i", $row_lst->ar_zeit_von), "00:00") != 0)
                           echo " von ". mysqltime("h:i", $row_lst->ar_zeit_von). " bis ". mysqltime("h:i", $row_lst->ar_zeit_bis);
                        echo "</div>";
               echo "</div>";
            }
            if(strlen($row_lst->ar_ort) > 0)
            {
               echo "<div style=\"margin-top: 6px;\">
                        <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Treffpunkt:</div>
                        <div style=\"text-align: left;\">$row_lst->ar_ort</div>
                     </div>";
            }
         }
         echo "
         <div style=\"margin-top: 6px;\">
            <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Teilnehmer:</div>
            <div style=\"text-align: left;\">$anz_mgl";
               if($row_lst->ar_max_mitglieder > 0)
                  echo " von max. $row_lst->ar_max_mitglieder";
            echo "</div>
         </div>";
         if(strlen($row_lst->ar_beitrag) > 0)
         {
            echo "<div style=\"margin-top: 6px;\">
                     <div style=\"margin-left: 30px; width: 130px; text-align: left; float: left;\">Beitrag:</div>
                     <div style=\"margin-left: 160px; text-align: left;\">$row_lst->ar_beitrag &euro;</div>
                  </div>";
         }
         $i++;
      }
   echo "</div>
   
   </div>";
   
   require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>