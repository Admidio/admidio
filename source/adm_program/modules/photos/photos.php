<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
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
	require("../../system/session_check.php");

	//erfassen der Veranstaltungen die zur Gruppierung gehören
   $sql = "   SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE (pho_org_shortname ='$g_organization')
            ORDER BY pho_begin DESC ";
   $result = mysql_query($sql, $g_adm_con);
   db_error($result);

   mysql_data_seek ($result, 0);
   //beginn HTML
   echo "
   <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>$g_current_organization->longname - Fotogalerien</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      require("../../../adm_config/header.php");
   echo "</head>";

   require("../../../adm_config/body_top.php");

   echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
   echo"<h1>Fotogalerien</h1>";
   //bei Seitenaufruf mit Moderationsrechten
   if($g_session_valid && editPhoto()){
      echo"
      <button name=\"verwaltung\" type=\"button\" value=\"up\" style=\"width: 187px;\"
         onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/event.php?aufgabe=new'\">
         <img src=\"$g_root_path/adm_program/images/edit.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Veranstaltung anlegen\">  Veranstaltung anlegen
        </button><br><br>";
   }


   //anlegen der Tabelle und ausgeb der Kopfzeile
   echo "
   <table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
      <tr>
         <th class=\"tableHeader\" style=\"text-align: left;\">&nbsp;Veranstaltung</th>
         <th class=\"tableHeader\" style=\"text-align: left;\">Datum</th>
         <th class=\"tableHeader\" style=\"text-align: center;\">Bilder</th>
         <th class=\"tableHeader\" style=\"text-align: center;\">Letze &Auml;nderung</th>";
         if ($g_session_valid && editPhoto()){
            echo"<th class=\"tableHeader\" style=\"text-align: center;\">Bearbeiten</th>";
         }
      echo"</tr>
   ";

//durchlaufen des Result-Tabelle und Ausgabe in Tabelle
   $bildersumme=0;//Summe der Bilder in den Unterordnern
   for($x=0; $adm_photo = mysql_fetch_array($result); $x++){
         $bildersumme=$bildersumme+$adm_photo[1];//erhöhen der Bildersumme
         $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];
         //Kontrollieren ob der entsprechende Ordner in adm_my_files existiert
         //wenn ja Zeile ausgeben
         if(file_exists($ordner) || ($g_session_valid && editPhoto())){
         echo "
         <tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\">
            <td style=\"text-align: left;\">&nbsp;";
				//Warnung für Für Leute mit Fotorechten
				if(!file_exists($ordner) && ($g_session_valid && editPhoto()))
					echo"<img src=\"$g_root_path/adm_program/images/warning16.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Warnhinweis\" title=\"Warnhinweis\"
                     onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=folder_not_found','Message','width=500, height=260, left=310,top=200,scrollbars=no')\">&nbsp;";
				echo"<a target=\"_self\" href=\"thumbnails.php?pho_id=".$adm_photo["pho_id"]."\">".$adm_photo["pho_name"]."</a></td>
            <td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo["pho_begin"])."</td>";//Anzeige beginn datum im deutschen Format
       		echo"<td style=\"text-align: center;\">".$adm_photo["pho_quantity"]."</td>
            <td style=\"text-align: center;\">".mysqldate("d.m.y", $adm_photo["pho_last_change"])."</td>";//Anzeige online seitdatum im deutschen Format
            if ($g_session_valid && editPhoto()){
               echo"<td style=\"text-align: center;\">";
                  if(file_exists($ordner)){
                  echo"
						<a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=".$adm_photo["pho_id"]."\">
                     <img src=\"$g_root_path/adm_program/images/photo.png\" border=\"0\" alt=\"Photoupload\" title=\"Photoupload\"></a>&nbsp;
                  <a href=\"$g_root_path/adm_program/modules/photos/event.php?pho_id=".$adm_photo["pho_id"]."&aufgabe=change\">
                     <img src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>&nbsp;";
            		}
                  $err_text= $adm_photo["pho_name"]."(Beginn: ".mysqldate("d.m.y", $adm_photo["pho_begin"]).")";
                  echo"
                  <a href=\"$g_root_path/adm_program/system/err_msg.php?err_code=delete_veranst&err_text=$err_text&err_head=Veranstaltung L&ouml;schen&button=2&url=". urlencode("$g_root_path/adm_program/modules/photos/event.php?aufgabe=delete&pho_id=".$adm_photo["pho_id"].""). "\">
                     <img src=\"$g_root_path/adm_program/images/delete.png\" border=\"0\" alt=\"Veranstaltung löschen\" title=\"Veranstaltung löschen\"></a>
               </td>";
            }
         echo"</tr>
         ";
         }//Ende Ordner existiert
   };//for
   // wenn keine Bilder vorhanden sind, dann eine Meldung ausgeben
   if($x==0)
   {
      echo "<tr><td>&nbsp;Es sind keine Bilder vorhanden.</td></tr>";
   }
//tabbelen Ende mit Ausgabe der Gesammtbilderzahl
   echo"
      <tr>
         <th class=\"tableHeader\" style=\"text-align: right;\" colspan=\"2\">Bilder Gesamt:</th>
         <th class=\"tableHeader\" style=\"text-align: center;\">$bildersumme</th>
         <th class=\"tableHeader\">&nbsp;</th>";
         if ($g_session_valid && editPhoto())echo"<th class=\"tableHeader\">&nbsp;</th>";
         echo"
         </tr>
   </table>
   ";
   echo "</div>";

//Seitenende
require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
?>