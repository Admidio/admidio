<?php
/******************************************************************************
 * RSS - Feed fuer Termine
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 *
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
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
require("../../../adm_config/config.php");
require("../../system/function.php");
require("../../system/date.php");
require("../../system/string.php");
require("../../system/session_check.php");
require("../../system/rss_class.php");

// Nachschauen ob RSS ueberhaupt aktiviert ist...
if($g_orga_property['ag_enable_rss'] != 1)
{
   $location = "location: $g_root_path/adm_program/system/err_msg.php?url=home&err_code=rss_disabled";
   header($location);
   exit();
}

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$sql = "SELECT * FROM adm_gruppierung
         WHERE ag_shortname = '$g_organization'
            OR ag_mother    = '$g_organization' ";
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$organizations = "";
$i             = 0;

while($row = mysql_fetch_object($result))
   {
      if($i > 0) $organizations = $organizations. ", ";

      if($row->ag_shortname == $g_organization)
         $organizations = $organizations. "'$row->ag_mother'";
      else
         $organizations = $organizations. "'$row->ag_shortname'";

      $i++;
   }




// aktuelle Termine aus DB holen die zur Orga passen
$sql = "SELECT * FROM adm_termine
                     WHERE (  at_ag_shortname = '$g_organization'
                        OR (   at_global   = 1
                           AND at_ag_shortname IN ($organizations) ))
                       AND (  at_von >= sysdate()
                           OR at_bis >= sysdate() )
                     ORDER BY at_von ASC
                     LIMIT 10 ";

      $sql    = prepareSQL($sql, array($_GET['start']));
      $result = mysql_query($sql, $g_adm_con);
      db_error($result);



// ab hier wird der RSS-Feed zusammengestellt

// Ein RSSfeed-Objekt erstellen
$rss=new RSSfeed("http://$g_orga_property[ag_homepage]","$g_orga_property[ag_homepage] - Die naechsten 10 Termine","Die 10 nächsten Termine");

// Dem RSSfeed-Objekt jetzt die RSSitems zusammenstellen und hinzufuegen
while($row = mysql_fetch_object($result))
      {
        // Den Autor des Termins ermitteln
      	$sql     = "SELECT * FROM adm_user WHERE au_id = $row->at_au_id";
        $result2 = mysql_query($sql, $g_adm_con);
        db_error($result2);
        $user = mysql_fetch_object($result2);

        // Die Attribute fuer das Item zusammenstellen
        $title			= mysqldatetime("d.m.y", $row->at_von). " ". $row->at_ueberschrift;

        $link			= "$g_root_path/adm_program/modules/dates/dates.php?dateid=". $row->at_id;

        $description 	= "<b>$row->at_ueberschrift</b> <br />". mysqldatetime("d.m.y", $row->at_von);

        if (mysqldatetime("h:i", $row->at_von) != "00:00")
               {
                  $description =  $description. " um ".mysqldatetime("h:i", $row->at_von). " Uhr";
               }

        if($row->at_von != $row->at_bis)
               {
                  $description =  $description. "<br /> bis <br />";

                  if(mysqldatetime("d.m.y", $row->at_von) != mysqldatetime("d.m.y", $row->at_bis))
                  {
                     $description = $description. mysqldatetime("d.m.y", $row->at_bis);

                     if (mysqldatetime("h:i", $row->at_bis) != "00:00")
                        $description = $description. " um ";
                  }

                  if (mysqldatetime("h:i", $row->at_bis) != "00:00")
                     $description = $description. mysqldatetime("h:i", $row->at_bis). " Uhr";
               }

        if ($row->at_ort != "")
               {
                  $description = $description. "<br /><br />Treffpunkt:&nbsp;". strSpecialChars2Html($row->at_ort);
               }

        $description = $description. "<br /><br />". nl2br(strSpecialChars2Html($row->at_beschreibung));
        $description = $description. "<br /><br /><a href=\"$link\">Link auf $g_orga_property[ag_homepage]</a>";
        $description = $description. "<br /><br /><i>Angelegt von ". strSpecialChars2Html($user->au_vorname). " ". strSpecialChars2Html($user->au_name). "</i>";




        $pubDate		= date(r,strtotime($row->at_timestamp));



		// Item hinzufuegen
		$rss->add_Item($title, $description, $pubDate, $link);

      }


// jetzt nur noch den Feed generieren lassen
$rss->build_feed();

?>