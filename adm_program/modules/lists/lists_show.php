<?php
/******************************************************************************
 * Listen anzeigen
 *
 * Copyright    : (c) 2004 - 2005 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * typ   : Listenselect (mylist, address, telephone, former)
 * mode  : Ausgabeart   (html, print, csv)
 * rolle : Rolle, für die die Funktion dargestellt werden soll
 *         (bei myList ist diese Variable nicht belegt)
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
require("../../system/tbl_user.php");
require("../../system/session_check_login.php");

if($_GET["mode"] == "csv-ms")
{
   $separator = ";";   // Microsoft braucht ein Semicolon
   $_GET["mode"] = "csv";
}
else if($_GET["mode"] == "csv-oo")
{
   $separator = ",";   // für CSV-Dateien
   $_GET["mode"] = "csv";
}
else
   $separator = ",";   // für CSV-Dateien

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('au_name'     => 'Nachname',
                      'au_vorname'  => 'Vorname',
                      'au_adresse'  => 'Adresse',
                      'au_plz'      => 'PLZ',
                      'au_ort'      => 'Ort',
                      'au_land'      => 'Land',
                      'au_tel1'     => 'Telefon',
                      'au_tel2'     => 'Telefon 2',
                      'au_mobil'    => 'Handy',
                      'au_fax'      => 'Fax',
                      'au_mail'     => 'E-Mail',
                      'au_geburtstag'   => 'Geburtstag',
                      'au_weburl'       => 'Homepage',
                      'am_start'        => 'Beginn',
                      'am_ende'         => 'Ende',
                      'am_leiter'       => 'Leiter'
                      );

if($_GET["mode"] == "html")
{
   $class_table  = "tableList";
   $class_header = "tableHeader";
   $class_row    = "";
}
else if($_GET["mode"] == "print")
{
   $class_table  = "tableListPrint";
   $class_header = "tableHeaderPrint";
   $class_row    = "tableRowPrint";
}

$main_sql  = "";   // enthält das Haupt-Sql-Statement für die Liste
$str_csv   = "";   // enthält die komplette CSV-Datei als String
$leiter    = 0;    // Gruppe besitzt Leiter

// das geweilige Sql-Statement zusammenbauen
// !!!! Das erste Feld muss immer au_id sein !!!!
// !!!! wenn Gruppen angezeigt werden, muss am_leiter = 0 gesetzt sein !!!!

switch($_GET["typ"])
{
   case "mylist":
      $sql      = "SELECT as_list_sql FROM adm_session
                    WHERE as_session = '$g_session_id' ";
      $result   = mysql_query($sql, $g_adm_con);
      db_error($result);
      $row      = mysql_fetch_array($result);
      $main_sql = $row[0];
      break;

   case "address":
      $main_sql = "SELECT au_id, au_name, au_vorname, au_geburtstag, au_adresse, au_plz, au_ort
                     FROM adm_rolle, adm_mitglieder, adm_user
                    WHERE ar_ag_shortname = '$g_organization'
                      AND ar_funktion     = {0}
                      AND ar_id     = am_ar_id
                      AND am_valid  = 1
                      AND am_leiter = 0
                      AND am_au_id  = au_id
                    ORDER BY au_name, au_vorname ";
      break;

   case "telephone":
      $main_sql = "SELECT au_id, au_name, au_vorname, au_tel1, au_tel2, au_mobil, au_mail
                     FROM adm_rolle, adm_mitglieder, adm_user
                    WHERE ar_ag_shortname = '$g_organization'
                      AND ar_funktion     = {0}
                      AND ar_id     = am_ar_id
                      AND am_valid  = 1
                      AND am_leiter = 0
                      AND am_au_id  = au_id
                    ORDER BY au_name, au_vorname ";
      break;

   case "former":
      $main_sql = "SELECT au_id, au_name, au_vorname, au_geburtstag, am_start, am_ende
                     FROM adm_rolle, adm_mitglieder, adm_user
                    WHERE ar_ag_shortname = '$g_organization'
                      AND ar_funktion     = {0}
                      AND ar_id     = am_ar_id
                      AND am_valid  = 0
                      AND am_leiter = 0
                      AND am_au_id  = au_id
                    ORDER BY am_ende DESC, au_name, au_vorname ";
      break;
}

// pruefen, ob die Rolle eine Gruppe ist und dann vorher ein SELECT für die Gruppenleiter erstellen
$sql = "SELECT ar_id, ar_gruppe
          FROM adm_rolle
         WHERE ar_ag_shortname = '$g_organization'
           AND ar_funktion     = {0} ";
$sql      = prepareSQL($sql, array($_GET['rolle']));
$result   = mysql_query($sql, $g_adm_con);
db_error($result);
$row      = mysql_fetch_array($result);
$gruppe   = $row[1];

if($gruppe == 1)
{
   // pruefen, ob die Gruppe Leiter hat, wenn nicht, dann Standardliste anzeigen

   if(substr_count(str_replace(" ", "", $main_sql), "am_valid=0") > 0)
      $former = 0;
   else
      $former = 1;

   $sql = "SELECT am_leiter
             FROM adm_mitglieder
            WHERE am_ar_id  = ". $row[0]. "
              AND am_valid  = $former
              AND am_leiter = 1 ";
   $result   = mysql_query($sql, $g_adm_con);
   db_error($result);

   if(mysql_num_rows($result) > 0)
   {
      // Gruppe besitzt Leiter
      $pos = strpos($main_sql, "am_leiter");
      if($pos > 0)
      {
         $leiter   = 1;
         // am_leiter = 0 durch am_leiter = 1 ersetzen
         $tmp_sql  = strtolower($main_sql);
         $next_pos = strpos($tmp_sql, "and", $pos);
         if($next_pos === false)
            $next_pos = strpos($tmp_sql, "order", $pos);
         $leiter_sql = substr($main_sql, 0, $pos). " am_leiter = 1 ". substr($main_sql, $next_pos);
      }
   }
   else
      $gruppe = 0;
}

// aus main_sql alle Felder ermitteln und in ein Array schreiben

// SELECT am Anfang entfernen
$str_fields = substr($main_sql, 7);
// ab dem FROM alles abschneiden
$pos        = strpos($str_fields, " FROM ");
$str_fields = substr($str_fields, 0, $pos);
$arr_fields = explode(",", $str_fields);

// Spaces entfernen
for($i = 0; $i < count($arr_fields); $i++)
{
   $arr_fields[$i] = trim($arr_fields[$i]);
}

// wenn die Gruppe keine Leiter besitzt, dann pruefen, ob ueberhaupt Datensaetze vorhanden sind
if($leiter == 0)
{
   // keine Leiter vorhanden -> SQL-Statement ausfuehren
   $main_sql = prepareSQL($main_sql, array($_GET['rolle']));
   $result_lst = mysql_query($main_sql, $g_adm_con);
   db_error($result_lst);

   if(mysql_num_rows($result_lst) == 0)
   {
      // Es sind keine Daten vorhanden !
      $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=nodata";
      header($location);
      exit();
   }
}

if($_GET["mode"] != "csv")
{
   // Html-Kopf wird geschrieben
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
   <head>
      <title>". $g_orga_property['ag_shortname']. " - Liste - ". $_GET["rolle"]. "</title>
      <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

      <!--[if gte IE 5.5000]>
      <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
      <![endif]-->";

      if($_GET["mode"] == "print")
      {
         echo "<style type=\"text/css\">
                  @page { size:landscape; }
               </style>";
      }
      if($_GET["mode"] != "print")
         require("../../../adm_config/header.php");
   echo "</head>";

   if($_GET["mode"] == "print")
      echo "<body class=\"bodyPrint\">";
   else
      require("../../../adm_config/body_top.php");

   echo "
   <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
   <h1>". $_GET["rolle"]. "</h1>";

   if($_GET["mode"] != "print")
   {
      echo "<p>
      <button name=\"print\" type=\"button\" value=\"print\" style=\"width: 140px;\"
      onclick=\"window.open('lists_show.php?typ=". $_GET["typ"]. "&amp;mode=print&amp;rolle=". $_GET["rolle"]."', '_blank')\">
      <img src=\"$g_root_path/adm_program/images/print.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Druckvorschau\">
      &nbsp;Druckvorschau</button>

      &nbsp;&nbsp;&nbsp;
      <button name=\"download-oo\" type=\"button\" value=\"download-oo\" style=\"width: 187px;\"
         onclick=\"self.location.href='lists_show.php?typ=". $_GET["typ"]. "&amp;mode=csv-oo&amp;rolle=". $_GET["rolle"]."'\">
         <img src=\"$g_root_path/adm_program/images/oo.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Open-Office &amp; Staroffice\">
         &nbsp;Open-Office &amp; Staroffice</button>

      &nbsp;&nbsp;&nbsp;
      <button name=\"download-excel\" type=\"button\" value=\"download-excel\" style=\"width: 140px;\"
         onclick=\"self.location.href='lists_show.php?typ=". $_GET["typ"]. "&amp;mode=csv-ms&amp;rolle=". $_GET["rolle"]."'\">
         <img src=\"$g_root_path/adm_program/images/excel.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"MS-Excel\">
         &nbsp;MS-Excel</button></p>";
   }
}

// bei einer Gruppe muessen 2 Tabellen angezeigt werden
// erst die der Leiter und dann die der Gruppenmitglieder
if($gruppe)
   $max_count = 2;
else
   $max_count = 1;

for($j = 0; $j < $max_count; $j++)
{
   if($leiter == 1)
   {
      // wenn Leiter vorhanden, dann müssen SQL-Statements hier getrennt aufgerufen werden
      if($j == 0 && $gruppe == 1)   // Leiter
      {
         $leiter_sql = prepareSQL($leiter_sql, array($_GET['rolle']));
         $result_lst = mysql_query($leiter_sql, $g_adm_con);
      }
      else
      {
         $main_sql = prepareSQL($main_sql, array($_GET['rolle']));
         $result_lst = mysql_query($main_sql, $g_adm_con);
      }
      db_error($result_lst, true);
   }

   if(mysql_num_rows($result_lst) > 0)
   {
      if($_GET["mode"] == "csv")
      {
         if($j == 0 && $gruppe == 1) $str_csv = $str_csv. "Leiter\n\n";
         if($j == 1) $str_csv = $str_csv. "\n\nTeilnehmer\n\n";
      }
      else
      {
         if($j == 0 && $gruppe == 1) echo "<h2>Leiter</h2>";
         // erste Tabelle abschliessen
         if($j == 1) echo "</table><br /><h2>Teilnehmer</h2>";

         // Tabellenkopf schreiben
         echo "<table class=\"$class_table\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
                  <tr>";
      }

      // Spalten-Überschriften
      for($i = 0; $i < count($arr_fields); $i++)
      {
         if($_GET["mode"] == "csv")
         {
            if($i > 0) $str_csv = $str_csv. $separator;
            if($i == 0)
               $str_csv = $str_csv. "\"Nr.\"";
            else
               $str_csv = $str_csv. "\"". $arr_col_name[$arr_fields[$i]]. "\"";
         }
         else
         {
            echo "<th class=\"$class_header\" align=\"left\">&nbsp;";
            if($i == 0)
               echo "Nr.";
            else
               echo $arr_col_name[$arr_fields[$i]];
            echo "</th>\n";
         }
      }  // End-For

      if($_GET["mode"] == "csv")
         $str_csv = $str_csv. "\n";
      else
         echo "</tr>\n";

      $irow       = 1;

      while($row = mysql_fetch_array($result_lst))
      {
         if($_GET["mode"] == "html")
         {
            echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\"
               style=\"cursor: pointer\" onClick=\"window.location.href='$g_root_path/adm_program/modules/profile/profile.php?user_id=$row[0]'\">\n";
         }
         else if($_GET["mode"] == "print")
         {
            echo "<tr>\n";
         }

         // Felder zu Datensatz
         for($i = 0; $i < count($arr_fields); $i++)
         {
            if($_GET["mode"] != "csv")
               echo "<td  class=\"$class_row\" align=\"left\">&nbsp;";

            if($i == 0)
            {
               // erste Spalte zeigt lfd. Nummer an
               if($_GET["mode"] == "csv")
                  $str_csv = $str_csv. "\"$irow\"";
               else
                  echo $irow. "</td>\n";
            }
            else
            {
               $content = "";
               if(strlen($row[$i]) > 0)
               {
                  // Felder nachformatieren
                  switch($arr_fields[$i])
                  {
                     case "au_mail":
                        if($_GET["mode"] == "html")
                        {
                           if($g_orga_property['ag_mail_extern'] == 1)
                              $content = "<a href=\"mailto:". $row[$i]. "\">". $row[$i]. "</a>";
                           else
                              $content = "<a href=\"../../adm_program/modules/mail/mail.php?au_id=". $row[0]. "\">". $row[$i]. "</a>";
                        }
                        else
                           $content = $row[$i];
                        break;

                     case "au_geburtstag":
                     case "am_start":
                     case "am_ende":
                        $content = mysqldatetime("d.m.y", $row[$i]);
                        if($content == "00.00.0000")
                           $content = "";
                        break;

                     case "au_weburl":
                        $row[$i] = stripslashes($row[$i]);
                        if(substr_count(strtolower($row[$i]), "http://") == 0)
                           $row[$i] = "http://". $row[$i];

                        if($_GET["mode"] == "html")
                           $content = "<a href=\"". $row[$i]. "\" target=\"_top\">". substr($row[$i], 7). "</a>";
                        else
                           $content = substr($row[$i], 7);
                        break;

                     default:
                        $content = $row[$i];
                        break;
                  }
               }

               if($_GET["mode"] == "csv")
               {
                  if($i > 0) $str_csv = $str_csv. $separator;
                  $str_csv = $str_csv. "\"$content\"";
               }
               else
                  echo $content. "</td>\n";
            }
         }

         if($_GET["mode"] == "csv")
            $str_csv = $str_csv. "\n";
         else
            echo "</tr>\n";

         $irow++;
      }  // End-While (jeder gefundene User)
   }  // End-If (Rows > 0)
}  // End-For (Leiter, Teilnehmer)

if($_GET["mode"] == "csv")
{
   // nun die erstellte CSV-Datei an den User schicken
   $filename = $g_organization. "-". str_replace(" ", "_", str_replace(".", "", $_GET["rolle"])). ".csv";
   header("Content-Type: application/force-download");
   header("Content-Type: application/download");
   header("Content-Type: text/csv; charset=ISO-8859-1");
   header("Content-Disposition: attachment; filename=$filename");
   echo $str_csv;
}
else
{
   echo "</table>";

   if($_GET["mode"] == "print")
   {
      if(!$_GET["typ"] == "mylist")
      {
         echo "<p><button name=\"zurueck\" type=\"button\" value=\"zurueck\" onclick=\"history.back()\">
               <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\">
               Zur&uuml;ck</button></p>";
      }
   }
   else
   {
      echo "<p>
      <button name=\"print\" type=\"button\" value=\"print\" style=\"width: 140px;\"
      onclick=\"window.open('lists_show.php?typ=". $_GET["typ"]. "&amp;mode=print&amp;rolle=". $_GET["rolle"]."', '_blank')\">
      <img src=\"$g_root_path/adm_program/images/print.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Druckvorschau\">
      &nbsp;Druckvorschau</button>

      &nbsp;&nbsp;&nbsp;
      <button name=\"download-oo\" type=\"button\" value=\"download-oo\" style=\"width: 187px;\"
         onclick=\"self.location.href='lists_show.php?typ=". $_GET["typ"]. "&amp;mode=csv-oo&amp;rolle=". $_GET["rolle"]."'\">
         <img src=\"$g_root_path/adm_program/images/oo.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Open-Office &amp; Staroffice\">
         &nbsp;Open-Office &amp; Staroffice</button>

      &nbsp;&nbsp;&nbsp;
      <button name=\"download-excel\" type=\"button\" value=\"download-excel\" style=\"width: 140px;\"
         onclick=\"self.location.href='lists_show.php?typ=". $_GET["typ"]. "&amp;mode=csv-ms&amp;rolle=". $_GET["rolle"]."'\">
         <img src=\"$g_root_path/adm_program/images/excel.png\" style=\"vertical-align: middle;\" align=\"top\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"MS-Excel\">
         &nbsp;MS-Excel</button></p>";
   }

   echo "</div>";
   if($_GET["mode"] != "print")
      require("../../../adm_config/body_bottom.php");
echo "</body>
</html>";
}

?>