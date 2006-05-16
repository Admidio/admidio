<?php
/******************************************************************************
 * Listen anzeigen
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * typ    : Listenselect (mylist, address, telephone, former)
 * mode   : Ausgabeart   (html, print, csv)
 * rol_id : Rolle, für die die Funktion dargestellt werden soll
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

$mode   = strStripTags($_GET["mode"]);
$type   = strStripTags($_GET["typ"]);
$rol_id = strStripTags($_GET["rol_id"]);

if($mode != "csv-ms"
&& $mode != "csv-ms-2k"
&& $mode != "csv-oo"
&& $mode != "html"
&& $mode != "print")
{
    // Dem aufgerufenen Skript wurde die notwendige Variable nicht richtig übergeben !
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=mode";
    header($location);
    exit();
}

if($rol_id <= 0)
{
    // Dem aufgerufenen Skript wurde die notwendige Variable nicht richtig übergeben !
    $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=rolle";
    header($location);
    exit();
}

if($mode == "csv-ms")
{
    $separator    = ";"; // Microsoft XP und neuer braucht ein Semicolon
    $value_quotes = "\"";
    $mode         = "csv";
}
else if($mode == "csv-ms-2k")
{
    $separator    = ","; // Microsoft 2000 und aelter braucht ein Komma
    $value_quotes = "\"";
    $mode         = "csv";
}
else if($mode == "csv-oo")
{
    $separator    = ",";    // für CSV-Dateien
    $value_quotes = "\"";   // Werte muessen mit Anfuehrungszeichen eingeschlossen sein
    $mode         = "csv";
}
else
{
    $separator    = ",";    // für CSV-Dateien
    $value_quotes = "";
}

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('usr_last_name'  => 'Nachname',
                      'usr_first_name' => 'Vorname',
                      'usr_address'    => 'Adresse',
                      'usr_zip_code'   => 'PLZ',
                      'usr_city'       => 'Ort',
                      'usr_country'    => 'Land',
                      'usr_phone'      => 'Telefon',
                      'usr_mobile'     => 'Handy',
                      'usr_fax'        => 'Fax',
                      'usr_email'      => 'E-Mail',
                      'usr_homepage'   => 'Homepage',
                      'usr_birthday'   => 'Geburtstag',
                      'usr_gender'     => 'Geschlecht',
                      'mem_begin'      => 'Beginn',
                      'mem_end'        => 'Ende',
                      'mem_leader'     => 'Leiter'
                      );

if($mode == "html")
{
    $class_table  = "tableList";
    $class_header = "tableHeader";
    $class_row    = "";
}
else if($mode == "print")
{
    $class_table  = "tableListPrint";
    $class_header = "tableHeaderPrint";
    $class_row    = "tableRowPrint";
}

$main_sql  = "";   // enthält das Haupt-Sql-Statement für die Liste
$str_csv   = "";   // enthält die komplette CSV-Datei als String
$leiter    = 0;    // Gruppe besitzt Leiter

// Rollenname auslesen
$sql = "SELECT *
          FROM ". TBL_ROLES. "
         WHERE rol_id     = {0} ";
$sql    = prepareSQL($sql, array($rol_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

$role_row = mysql_fetch_object($result);

// das geweilige Sql-Statement zusammenbauen
// !!!! Das erste Feld muss immer usr_id sein !!!!
// !!!! wenn Gruppen angezeigt werden, muss mem_leader = 0 gesetzt sein !!!!

switch($type)
{
    case "mylist":
        session_start();
        $main_sql = $_SESSION['mylist_sql'];
        break;

    case "address":
        $main_sql = "SELECT usr_id, usr_last_name, usr_first_name, usr_birthday, usr_address, usr_zip_code, usr_city
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = $role_row->rol_valid
                      AND mem_leader = 0
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY usr_last_name, usr_first_name ";
      break;

    case "telephone":
        $main_sql = "SELECT usr_id, usr_last_name, usr_first_name, usr_phone, usr_mobile, usr_email, usr_fax
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = $role_row->rol_valid
                      AND mem_leader = 0
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY usr_last_name, usr_first_name ";
      break;

    case "former":
        $main_sql = "SELECT usr_id, usr_last_name, usr_first_name, usr_birthday, mem_begin, mem_end
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = 0
                      AND mem_leader = 0
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY mem_end DESC, usr_last_name, usr_first_name ";
      break;
      
    default:
        // Dem aufgerufenen Skript wurde die notwendige Variable nicht richtig übergeben !
        $location = "location: $g_root_path/adm_program/system/err_msg.php?err_code=invalid_variable&err_text=typ";
        header($location);
        exit();
}

// pruefen, ob die Rolle Leiter hat, wenn nicht, dann Standardliste anzeigen

if(substr_count(str_replace(" ", "", $main_sql), "mem_valid=0") > 0)
{
    $former = 0;
}
else
{
    $former = 1;
}

$sql = "SELECT mem_leader
             FROM ". TBL_ROLES. ", ". TBL_MEMBERS. "
            WHERE rol_id     = {0}
              AND mem_rol_id = rol_id
              AND mem_valid  = $former
              AND mem_leader = 1 ";
$sql    = prepareSQL($sql, array($rol_id));
$result = mysql_query($sql, $g_adm_con);
db_error($result);

if(mysql_num_rows($result) > 0)
{
    // Gruppe besitzt Leiter
    $pos = strpos($main_sql, "mem_leader");
    if($pos > 0)
    {
        $leiter   = 1;
        // mem_leader = 0 durch mem_leader = 1 ersetzen
        $tmp_sql  = strtolower($main_sql);
        $next_pos = strpos($tmp_sql, "and", $pos);
        if($next_pos === false)
            $next_pos = strpos($tmp_sql, "order", $pos);
        $leiter_sql = substr($main_sql, 0, $pos). " mem_leader = 1 ". substr($main_sql, $next_pos);
    }
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
    $main_sql = prepareSQL($main_sql, array($rol_id));
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

if($mode != "csv")
{
    // Html-Kopf wird geschrieben
    echo "
    <!-- (c) 2004 - 2006 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
    <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
    <html>
    <head>
        <title>$g_current_organization->longname - Liste - $role_row->rol_name</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if gte IE 5.5000]>
        <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->

        <script language=\"JavaScript\" type=\"text/javascript\"><!--\n
            function exportList(element)
            {
                var sel_list = element.value;

                if(sel_list.length > 1)
                {
                    self.location.href = 'lists_show.php?typ=$type&rol_id=$rol_id&mode=' + sel_list;
                }
            }
        //--></script>";

        if($mode == "print")
        {
            echo "<style type=\"text/css\">
                @page { size:landscape; }
            </style>";
        }
        else
        {
            require("../../../adm_config/header.php");
        }
    echo "</head>";

    if($mode == "print")
    {
        echo "<body class=\"bodyPrint\">";
    }
    else
    {
        require("../../../adm_config/body_top.php");
    }

    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <h1>$role_row->rol_name</h1>";

    if($mode != "print")
    {
        echo "<p>";
        if($role_row->rol_mail_login == 1)
        {
            echo "<span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/mail/mail.php?rolle=$role_row->rol_name\"><img 
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle; cursor: pointer;\" 
                border=\"0\" alt=\"E-Mail an Mitglieder\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/mail/mail.php?rolle=$role_row->rol_name\">E-Mail an Mitglieder</a>
            </span>
            &nbsp;&nbsp;&nbsp;";
        }
        
        echo "<span class=\"iconLink\">
            <a class=\"iconLink\" href=\"#\" onclick=\"window.open('lists_show.php?typ=$type&amp;mode=print&amp;rol_id=$rol_id', '_blank')\"><img
            class=\"iconLink\" src=\"$g_root_path/adm_program/images/print.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"". $_GET["headline"]. "-Feed abonnieren\"></a>
            <a class=\"iconLink\" href=\"#\" onclick=\"window.open('lists_show.php?typ=$type&amp;mode=print&amp;rol_id=$rol_id', '_blank')\">Druckvorschau</a>
        </span>
        
        &nbsp;&nbsp;
        
        <img class=\"iconLink\" src=\"$g_root_path/adm_program/images/database_out.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"". $_GET["headline"]. "-Feed abonnieren\">
        <select size=\"1\" name=\"list$i\" onchange=\"exportList(this)\">
            <option value=\"\" selected=\"selected\">Exportieren nach ...</option>
            <option value=\"csv-ms\">Microsoft Excel</option>
            <option value=\"csv-ms-2k\">Microsoft Excel 97/2000</option>
            <option value=\"csv-oo\">CSV-Datei (OpenOffice)</option>
        </select>
        </p>";
    }
}

// bei einer Gruppe muessen 2 Tabellen angezeigt werden
// erst die der Leiter und dann die der Gruppenmitglieder
if($leiter == 1)
{
    $max_count = 2;
}
else
{
    $max_count = 1;
}

for($j = 0; $j < $max_count; $j++)
{
    if($leiter == 1)
    {
        // wenn Leiter vorhanden, dann müssen SQL-Statements hier getrennt aufgerufen werden
        if($j == 0)   // Leiter
        {
            $leiter_sql = prepareSQL($leiter_sql, array($rol_id));
            $result_lst = mysql_query($leiter_sql, $g_adm_con);
        }
        else
        {
            $main_sql = prepareSQL($main_sql, array($rol_id));
            $result_lst = mysql_query($main_sql, $g_adm_con);
        }
        db_error($result_lst, true);
    }

    if(mysql_num_rows($result_lst) > 0)
    {
        if($mode == "csv")
        {
            if($j == 0 && $leiter == 1) 
            {
                $str_csv = $str_csv. "Leiter\n\n";
            }
            if($j == 1) 
            {
                $str_csv = $str_csv. "\n\nTeilnehmer\n\n";
            }
        }
        else
        {
            if($j == 0 && $leiter == 1) 
            {
                echo "<h2>Leiter</h2>";
            }
            // erste Tabelle abschliessen
            if($j == 1) 
            {
                echo "</table><br /><h2>Teilnehmer</h2>";
            }

            // Tabellenkopf schreiben
            echo "<table class=\"$class_table\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>";
        }

        // Spalten-Überschriften
        for($i = 0; $i < count($arr_fields); $i++)
        {
            if($mode == "csv")
            {
                if($i > 0) 
                {
                    $str_csv = $str_csv. $separator;
                }
                if($i == 0)
                {
                    $str_csv = $str_csv. $value_quotes. "Nr.". $value_quotes;
                }
                else
                {
                    $str_csv = $str_csv. $value_quotes. $arr_col_name[$arr_fields[$i]]. $value_quotes;
                }
            }
            else
            {
                echo "<th class=\"$class_header\" align=\"left\">&nbsp;";
                if($i == 0)
                {
                    echo "Nr.";
                }
                else
                {
                    echo $arr_col_name[$arr_fields[$i]];
                }
                echo "</th>\n";
            }
        }  // End-For

        if($mode == "csv")
        {
            $str_csv = $str_csv. "\n";
        }
        else
        {
            echo "</tr>\n";
        }

        $irow       = 1;

        while($row = mysql_fetch_array($result_lst))
        {
            if($mode == "html")
            {
                echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\" onMouseOut=\"this.className='listMouseOut'\"
                style=\"cursor: pointer\" onClick=\"window.location.href='$g_root_path/adm_program/modules/profile/profile.php?user_id=$row[0]'\">\n";
            }
            else if($mode == "print")
            {
                echo "<tr>\n";
            }

            // Felder zu Datensatz
            for($i = 0; $i < count($arr_fields); $i++)
            {
                if($mode != "csv")
                {
                    echo "<td  class=\"$class_row\" align=\"left\">&nbsp;";
                }

                if($i == 0)
                {
                    // erste Spalte zeigt lfd. Nummer an
                    if($mode == "csv")
                    {
                        $str_csv = $str_csv. $value_quotes. "$irow". $value_quotes;
                    }
                    else
                    {
                        echo $irow. "</td>\n";
                    }
                }
                else
                {
                    $content = "";
                    if(strlen($row[$i]) > 0)
                    {
                        // Felder nachformatieren
                        switch($arr_fields[$i])
                        {
                            case "usr_email":
                                // E-Mail als Link darstellen
                                if($mode == "html")
                                {
                                    if($g_current_organization->mail_extern == 1)
                                    {
                                        $content = "<a href=\"mailto:". $row[$i]. "\">". $row[$i]. "</a>";
                                    }
                                    else
                                    {
                                        $content = "<a href=\"../mail/mail.php?usr_id=". $row[0]. "\">". $row[$i]. "</a>";
                                    }
                                }
                                else
                                {
                                    $content = $row[$i];
                                }
                                break;

                            case "usr_birthday":
                            case "mem_begin":
                            case "mem_end":
                                // Datum 00.00.0000 unterdruecken
                                $content = mysqldatetime("d.m.y", $row[$i]);
                                if($content == "00.00.0000")
                                {
                                    $content = "";
                                }
                                break;

                            case "usr_homepage":
                                // Homepage als Link darstellen
                                $row[$i] = stripslashes($row[$i]);
                                if(substr_count(strtolower($row[$i]), "http://") == 0)
                                {
                                    $row[$i] = "http://". $row[$i];
                                }

                                if($mode == "html")
                                {
                                    $content = "<a href=\"". $row[$i]. "\" target=\"_top\">". substr($row[$i], 7). "</a>";
                                }
                                else
                                {
                                    $content = substr($row[$i], 7);
                                }
                                break;

                            case "usr_gender":
                                // Geschlecht anzeigen
                                if($row[$i] == 1)
                                {
                                    if($mode == "csv")
                                    {
                                        $content = "männlich";
                                    }
                                    else
                                    {
                                        $content = "m&auml;nnlich";
                                    }
                                }
                                elseif($row[$i] == 2)
                                {
                                    $content = "weiblich";
                                }
                                else
                                {
                                    $content = "&nbsp;";
                                }
                                break;

                            default:
                                $content = $row[$i];
                                break;
                        }
                    }

                    if($mode == "csv")
                    {
                        if($i > 0) 
                        {
                            $str_csv = $str_csv. $separator;
                        }
                        $str_csv = $str_csv. $value_quotes. "$content". $value_quotes;
                    }
                    else
                    {
                        echo $content. "</td>\n";
                    }
                }
            }

            if($mode == "csv")
            {
                $str_csv = $str_csv. "\n";
            }
            else
            {
                echo "</tr>\n";
            }

            $irow++;
        }  // End-While (jeder gefundene User)
    }  // End-If (Rows > 0)
}  // End-For (Leiter, Teilnehmer)

if($mode == "csv")
{
    // nun die erstellte CSV-Datei an den User schicken
    $filename = $g_organization. "-". str_replace(" ", "_", str_replace(".", "", $role_row->rol_name)). ".csv";
    header("Content-Type: text/comma-separated-values; charset=ISO-8859-1");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $str_csv;
}
else
{
    echo "</table>";

    if($mode != "print")
    {
        echo "<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"javascript:history.back()\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"". $_GET["headline"]. "-Feed abonnieren\"></a>
                <a class=\"iconLink\" href=\"javascript:history.back()\">Zur&uuml;ck</a>
            </span>
        </p>
        </div>";        
        require("../../../adm_config/body_bottom.php");
    }
    else
    {
        echo "</div>";
    }
        
    echo "</body>
    </html>";
}

?>