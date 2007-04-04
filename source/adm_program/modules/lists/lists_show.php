<?php
/******************************************************************************
 * Listen anzeigen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * type   : Listenselect (mylist, address, telephone, former)
 * mode   : Ausgabeart   (html, print, csv-ms, csv-ms-2k, csv-oo)
 * rol_id : Rolle, fuer die die Funktion dargestellt werden soll
 * start  : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen 
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

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/role_class.php");

// lokale Variablen der Uebergabevariablen initialisieren
$arr_mode   = array("csv-ms", "csv-ms-2k", "csv-oo", "html", "print");
$arr_type   = array("mylist", "address", "telephone", "former");
$req_rol_id = 0;
$req_start  = 0;

// Uebergabevariablen pruefen

$req_mode   = strStripTags($_GET["mode"]);
$req_type   = strStripTags($_GET["type"]);

if(in_array($req_mode, $arr_mode) == false)
{
    $g_message->show("invalid");
}

if(in_array($req_type, $arr_type) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["rol_id"]))
{
    if(is_numeric($_GET["rol_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_rol_id = $_GET["rol_id"];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_start = $_GET['start'];
}

//SESSION array für bilder initialisieren
$_SESSION['profilphoto'] = array();

if($req_mode == "csv-ms")
{
    $separator    = ";"; // Microsoft XP und neuer braucht ein Semicolon
    $value_quotes = "\"";
    $req_mode         = "csv";
}
else if($req_mode == "csv-ms-2k")
{
    $separator    = ","; // Microsoft 2000 und aelter braucht ein Komma
    $value_quotes = "\"";
    $req_mode         = "csv";
}
else if($req_mode == "csv-oo")
{
    $separator    = ",";    // fuer CSV-Dateien
    $value_quotes = "\"";   // Werte muessen mit Anfuehrungszeichen eingeschlossen sein
    $req_mode         = "csv";
}
else
{
    $separator    = ",";    // fuer CSV-Dateien
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
                      'usr_login_name' => 'Loginname',
                      'usr_photo'      => 'Foto',
                      'mem_begin'      => 'Beginn',
                      'mem_end'        => 'Ende',
                      'mem_leader'     => 'Leiter'
                      );

if($req_mode == "html")
{
    $class_table           = "tableList";
    $class_header          = "tableHeader";
    $class_sub_header      = "tableSubHeader";
    $class_sub_header_font = "tableSubHeaderFont";
    $class_row             = "";
}
else if($req_mode == "print")
{
    $class_table           = "tableListPrint";
    $class_header          = "tableHeaderPrint";
    $class_sub_header      = "tableSubHeaderPrint";
    $class_sub_header_font = "tableSubHeaderFontPrint";
    $class_row             = "tableRowPrint";
}

$main_sql  = "";   // enthaelt das Haupt-Sql-Statement fuer die Liste
$str_csv   = "";   // enthaelt die komplette CSV-Datei als String
$leiter    = 0;    // Gruppe besitzt Leiter

// Rollenobjekt erzeugen
$role = new Role($g_adm_con, $req_rol_id);

// Kategorie auslesen
$sql = "SELECT *
          FROM ". TBL_CATEGORIES. "
         WHERE cat_id     = {0} ";
$sql    = prepareSQL($sql, array($role->getValue("rol_cat_id")));
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);

$cat_row = mysql_fetch_object($result);

// Nummer der Spalte, ab der die Anzeigefelder anfangen (beginnend mit 0)
$start_column = 2;

// das jeweilige Sql-Statement zusammenbauen
// !!!! Das 1. Feld muss immer mem_leader und das 2. usr_id sein !!!!

switch($req_type)
{
    case "mylist":
        $main_sql = $_SESSION['mylist_sql'];
        break;

    case "address":
        $main_sql = "SELECT mem_leader, usr_id, usr_last_name, usr_first_name, usr_birthday, usr_address, usr_zip_code, usr_city
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = ". $role->getValue("rol_valid"). "
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY mem_leader DESC, usr_last_name ASC, usr_first_name ASC ";
      break;

    case "telephone":
        $main_sql = "SELECT mem_leader, usr_id, usr_last_name, usr_first_name, usr_phone, usr_mobile, usr_email, usr_fax
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = ". $role->getValue("rol_valid"). "
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY mem_leader DESC, usr_last_name ASC, usr_first_name ASC ";
      break;

    case "former":
        $main_sql = "SELECT mem_leader, usr_id, usr_last_name, usr_first_name, usr_birthday, mem_begin, mem_end
                     FROM ". TBL_ROLES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                    WHERE rol_org_shortname = '$g_organization'
                      AND rol_id     = {0}
                      AND rol_id     = mem_rol_id
                      AND mem_valid  = 0
                      AND mem_usr_id = usr_id
                      AND usr_valid  = 1
                    ORDER BY mem_leader DESC, mem_end DESC, usr_last_name ASC, usr_first_name ASC ";
      break;
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

// aus main_sql alle Felder ermitteln und in ein Array schreiben

// SELECT am Anfang entfernen
$str_fields = substr($main_sql, 7);
// ab dem FROM alles abschneiden
$str_fields = substr($str_fields, 0, strpos($str_fields, " FROM "));

$arr_fields = explode(",", $str_fields);

// Spaces entfernen
for($i = 0; $i < count($arr_fields); $i++)
{
    $arr_fields[$i] = trim($arr_fields[$i]);
}

// SQL-Statement der Liste ausfuehren und pruefen ob Daten vorhanden sind
$main_sql = prepareSQL($main_sql, array($req_rol_id));
$result_list = mysql_query($main_sql, $g_adm_con);
db_error($result_list,__FILE__,__LINE__);

$num_members = mysql_num_rows($result_list);

if($num_members == 0)
{
    // Es sind keine Daten vorhanden !
    $g_message->show("nodata");
}

if($num_members < $req_start)
{
    $g_message->show("invalid");
}

if($req_mode == "html" && $req_start == 0)
{
    // Url fuer die Zuruecknavigation merken, aber nur in der Html-Ansicht
    $_SESSION['navigation']->addUrl($g_current_url);
}

if($req_mode != "csv")
{
    // Html-Kopf wird geschrieben
    echo "
    <!-- (c) 2004 - 2007 The Admidio Team - http://www.admidio.org -->\n
    <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
    <html>
    <head>
        <title>$g_current_organization->longname - Liste - ". $role->getValue("rol_name"). "</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if lt IE 7]>
        <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->

        <script language=\"JavaScript\" type=\"text/javascript\"><!--\n
            function exportList(element)
            {
                var sel_list = element.value;

                if(sel_list.length > 1)
                {
                    self.location.href = 'lists_show.php?type=$req_type&rol_id=$req_rol_id&mode=' + sel_list;
                }
            }
        //--></script>";

        if($req_mode == "print")
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

    if($req_mode == "print")
    {
        echo "<body class=\"bodyPrint\">";
    }
    else
    {
        require("../../../adm_config/body_top.php");
    }

    echo "
    <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">
    <h1>". $role->getValue("rol_name"). "&nbsp;&#40;".$cat_row->cat_name."&#41;</h1>";

    //Beschreibung der Rolle einblenden
    if(strlen($role->getValue("rol_description")) > 0)
    {
        echo "<p>". $role->getValue("rol_description"). "</p>";
    }

    if($req_mode != "print")
    {
        if($req_type == "mylist")
        {
            $image = "application_form.png";
            $text  = "Konfiguration Eigene Liste";
        }
        else
        {
            $image = "application_view_list.png";
            $text  = "Listen&uuml;bersicht";            
        }

        echo "<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/$image\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Zur&uuml;ck\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/system/back.php\">$text</a>
            </span>
        </p>
        
        <p>";
        if($role->getValue("rol_mail_login") == 1 && $g_preferences['enable_mail_module'] == 1)
        {
            echo "<span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/mail/mail.php?rol_id=$req_rol_id\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/mail.png\" style=\"vertical-align: middle; cursor: pointer;\"
                border=\"0\" alt=\"E-Mail an Mitglieder\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/mail/mail.php?rol_id=$req_rol_id\">E-Mail an Mitglieder</a>
            </span>
            &nbsp;&nbsp;&nbsp;";
        }

        echo "<span class=\"iconLink\">
            <a class=\"iconLink\" href=\"#\" onclick=\"window.open('lists_show.php?type=$req_type&amp;mode=print&amp;rol_id=$req_rol_id', '_blank')\"><img
            class=\"iconLink\" src=\"$g_root_path/adm_program/images/print.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Druckvorschau\"></a>
            <a class=\"iconLink\" href=\"#\" onclick=\"window.open('lists_show.php?type=$req_type&amp;mode=print&amp;rol_id=$req_rol_id', '_blank')\">Druckvorschau</a>
        </span>

        &nbsp;&nbsp;

        <img class=\"iconLink\" src=\"$g_root_path/adm_program/images/database_out.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Exportieren\">
        <select size=\"1\" name=\"list$i\" onchange=\"exportList(this)\">
            <option value=\"\" selected=\"selected\">Exportieren nach ...</option>
            <option value=\"csv-ms\">Microsoft Excel</option>
            <option value=\"csv-ms-2k\">Microsoft Excel 97/2000</option>
            <option value=\"csv-oo\">CSV-Datei (OpenOffice)</option>
        </select>
        </p>";
    }
}

if($req_mode != "csv")
{
    // Tabellenkopf schreiben
    echo "<table class=\"$class_table\" style=\"width: 95%;\" cellpadding=\"2\" cellspacing=\"0\">
        <thead><tr>";
}

// Spalten-Ueberschriften
for($i = $start_column; $i < count($arr_fields); $i++)
{
    $align = "left";

    // den Namen des Feldes ermitteln
    if(strpos($arr_fields[$i], ".") > 0)
    {
        // benutzerdefiniertes Feld
        // die usf_id steht im Tabellen-Alias hinter dem f
        $usf_id = substr($arr_fields[$i], 1, strpos($arr_fields[$i], "."));
        $sql = "SELECT usf_name, usf_type FROM ". TBL_USER_FIELDS. "
                 WHERE usf_id = $usf_id ";
        $result_user_fields = mysql_query($sql, $g_adm_con);
        db_error($result_user_fields,__FILE__,__LINE__);

        $row = mysql_fetch_object($result_user_fields);
        $col_name = $row->usf_name;
        $arr_usf_types[$usf_id] = $row->usf_type;

        if($arr_usf_types[$usf_id] == "CHECKBOX")
        {
            $align = "center";
        }
        elseif($arr_usf_types[$usf_id] == "NUMERIC")
        {
            $align = "right";
        }
    }
    else
    {
        $col_name = $arr_col_name[$arr_fields[$i]];

        if($arr_fields[$i] == "usr_gender")
        {
            // Icon des Geschlechts zentriert darstellen
            $align = "center";
        }
    }

    if($req_mode == "csv")
    {
        if($i == $start_column)
        {
            // die Laufende Nummer noch davorsetzen
            $str_csv = $str_csv. $value_quotes. "Nr.". $value_quotes;
        }
        $str_csv = $str_csv. $separator. $value_quotes. $col_name. $value_quotes;
    }
    else
    {                
        if($i == $start_column)
        {
            // die Laufende Nummer noch davorsetzen
            echo "<th class=\"$class_header\" style=\"text-align: $align;\">&nbsp;Nr.</th>";
        }
        echo "<th class=\"$class_header\" style=\"text-align: $align;\">&nbsp;$col_name</th>\n";
    }
}  // End-For

if($req_mode == "csv")
{
    $str_csv = $str_csv. "\n";
}
else
{
    echo "</tr></thead><tbody>\n";
}

$irow        = $req_start + 1;  // Zahler fuer die jeweilige Zeile
$leader_head = -1;              // Merker um Wechsel zwischen Leiter und Mitglieder zu merken
if($req_mode == "html" && $g_preferences['lists_members_per_page'] > 0)
{
    $members_per_page = $g_preferences['lists_members_per_page'];     // Anzahl der Mitglieder, die auf einer Seite angezeigt werden
}
else
{
    $members_per_page = $num_members;
}

// jetzt erst einmal zu dem ersten relevanten Datensatz springen
if(!mysql_data_seek($result_list, $req_start))
{
    $g_message->show("invalid");
}

for($j = 0; $j < $members_per_page && $j + $req_start < $num_members; $j++)
{
    if($row = mysql_fetch_array($result_list))
    {
        if($req_mode != "csv")
        {
            // erst einmal pruefen, ob es ein Leiter ist, falls es Leiter in der Gruppe gibt, 
            // dann muss noch jeweils ein Gruppenkopf eingefuegt werden
            if($leader_head != $row['mem_leader']
            && ($row['mem_leader'] != 0 || $leader_head != -1))
            {
                if($row['mem_leader'] == 1)
                {
                    $title = "Leiter";
                }
                else
                {
                    $title = "Teilnehmer";
                }
                echo "<tr>
                    <td class=\"$class_sub_header\" colspan=\"". (count($arr_fields) + 1). "\">
                        <div class=\"$class_sub_header_font\" style=\"float: left;\">&nbsp;$title</div>
                    </td>
                </tr>";
                $leader_head = $row['mem_leader'];
            }
        }

        if($req_mode == "html")
        {
            echo "<tr class=\"listMouseOut\" onMouseOver=\"this.className='listMouseOver'\"
            onMouseOut=\"this.className='listMouseOut'\" style=\"cursor: pointer\"
            onClick=\"window.location.href='$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "'\">\n";
        }
        else if($req_mode == "print")
        {
            echo "<tr>\n";
        }

        // Felder zu Datensatz
        for($i = $start_column; $i < count($arr_fields); $i++)
        {
            if(strpos($arr_fields[$i], ".") > 0)
            {
                // pruefen, ob ein benutzerdefiniertes Feld und Kennzeichen merken
                $b_user_field = true;

                // die usf_id steht im Tabellen-Alias hinter dem f
                $usf_id = substr($arr_fields[$i], 1, strpos($arr_fields[$i], "."));
            }
            else
            {
                $b_user_field = false;
                $usf_id = 0;
            }

            if($req_mode != "csv")
            {
                $align = "left";
                if($b_user_field == true)
                {
                    if($arr_usf_types[$usf_id] == "CHECKBOX")
                    {
                        $align = "center";
                    }
                    elseif($arr_usf_types[$usf_id] == "NUMERIC")
                    {
                        $align = "right";
                    }
                }
                else
                {
                    if($arr_fields[$i] == "usr_gender")
                    {
                        $align = "center";
                    }
                }
                if($i == $start_column)
                {
                    // die Laufende Nummer noch davorsetzen
                    echo "<td  class=\"$class_row\" style=\"text-align: $align;\">&nbsp;$irow</th>";
                }
                echo "<td  class=\"$class_row\" style=\"text-align: $align;\">&nbsp;";
            }
            else
            {
                if($i == $start_column)
                {
                    // erste Spalte zeigt lfd. Nummer an
                    $str_csv = $str_csv. $value_quotes. "$irow". $value_quotes;
                }
            }

            $content = "";

            // Felder nachformatieren
            switch($arr_fields[$i])
            {
                case "usr_email":
                    // E-Mail als Link darstellen
                    if(strlen($row[$i]) > 0)
                    {
                        if($req_mode == "html")
                        {
                            if($g_preferences['enable_mail_module'] == 1)
                            {
                                $content = "<a href=\"$g_root_path/adm_program/modules/mail/mail.php?usr_id=". $row['usr_id']. "\">". $row[$i]. "</a>";
                            }
                            else
                            {
                                $content = "<a href=\"mailto:". $row[$i]. "\">". $row[$i]. "</a>";
                            }
                        }
                        else
                        {
                            $content = $row[$i];
                        }
                    }
                    break;

                case "usr_birthday":
                case "mem_begin":
                case "mem_end":
                    if(strlen($row[$i]) > 0)
                    {
                        // Datum 00.00.0000 unterdruecken
                        $content = mysqldatetime("d.m.y", $row[$i]);
                        if($content == "00.00.0000")
                        {
                            $content = "";
                        }
                    }
                    break;

                case "usr_homepage":
                    // Homepage als Link darstellen
                    if(strlen($row[$i]) > 0)
                    {
                        $row[$i] = stripslashes($row[$i]);
                        if(substr_count(strtolower($row[$i]), "http://") == 0)
                        {
                            $row[$i] = "http://". $row[$i];
                        }

                        if($req_mode == "html")
                        {
                            $content = "<a href=\"". $row[$i]. "\" target=\"_top\">". substr($row[$i], 7). "</a>";
                        }
                        else
                        {
                            $content = substr($row[$i], 7);
                        }
                    }
                    break;

                case "usr_gender":
                    // Geschlecht anzeigen
                    if($row[$i] == 1)
                    {
                        if($req_mode == "csv" || $req_mode == "print")
                        {
                            $content = utf8_decode("männlich");
                        }
                        else
                        {
                            $content = "<img src=\"$g_root_path/adm_program/images/male.png\"
                                        style=\"vertical-align: middle;\" alt=\"m&auml;nnlich\">";
                        }
                    }
                    elseif($row[$i] == 2)
                    {
                        if($req_mode == "csv" || $req_mode == "print")
                        {
                            $content = utf8_decode("weiblich");
                        }
                        else
                        {
                            $content = "<img src=\"$g_root_path/adm_program/images/female.png\"
                                        style=\"vertical-align: middle;\" alt=\"weiblich\">";
                        }
                    }
                    else
                    {
                        if($req_mode != "csv")
                        {
                            $content = "&nbsp;";
                        }
                    }
                    break;

                case "usr_photo":
                    // Benutzerfoto anzeigen
                    if(($req_mode == "html" || $req_mode == "print") && $row[$i] != NULL)
                    {
                        $_SESSION['profilphoto'][$row['usr_id']]=$row[$i];
                        $content = "<img src=\"photo_show.php?usr_id=".$row['usr_id']."\"
                                    style=\"vertical-align: middle;\" alt=\"Benutzerfoto\">";
                    }
                    if ($req_mode == "csv" && $row[$i] != NULL)
                    {
                        $content = "Profilfoto Online";
                    }
                    break;

                default:
                    if($b_user_field == true)
                    {                                
                        // benutzerdefiniertes Feld
                        if($arr_usf_types[$usf_id] == "CHECKBOX")
                        {
                            // Checkboxen werden durch ein Bildchen dargestellt
                            if($row[$i] == 1)
                            {
                                if($req_mode == "csv")
                                {
                                    $content = "ja";
                                }
                                else
                                {
                                    echo "<img src=\"$g_root_path/adm_program/images/checkbox_checked.gif\"
                                        style=\"vertical-align: middle;\" alt=\"on\">";
                                }
                            }
                            else
                            {
                                if($req_mode == "csv")
                                {
                                    $content = "nein";
                                }
                                else
                                {
                                    echo "<img src=\"$g_root_path/adm_program/images/checkbox.gif\"
                                        style=\"vertical-align: middle;\" alt=\"off\">";
                                }
                            }
                        }
                        else
                        {
                            $content = $row[$i];
                        }
                    }
                    else
                    {
                        $content = $row[$i];
                    }
                    break;
            }

            if($req_mode == "csv")
            {
                $str_csv = $str_csv. $separator. $value_quotes. "$content". $value_quotes;
            }

            else
            {
                echo $content. "</td>\n";
            }
        }

        if($req_mode == "csv")
        {
            $str_csv = $str_csv. "\n";
        }
        else
        {
            echo "</tr>\n";
        }

        $irow++;
    }
}  // End-While (jeder gefundene User)

if($req_mode == "csv")
{
    // nun die erstellte CSV-Datei an den User schicken
    $filename = $g_organization. "-". str_replace(" ", "_", str_replace(".", "", $role->getValue("rol_name"))). ".csv";
    header("Content-Type: text/comma-separated-values; charset=ISO-8859-1");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $str_csv;
}
else
{
    echo "</tbody></table>";

    if($req_mode != "print")
    {
        // Navigation mit Vor- und Zurueck-Buttons
        $base_url = "$g_root_path/adm_program/modules/lists/lists_show.php?type=$req_type&mode=$req_mode&rol_id=$req_rol_id";
        echo generatePagination($base_url, $num_members, $members_per_page, $req_start, TRUE);
    }

    //INFOBOX zur Gruppe
    //nur anzeigen wenn zusatzfelder gefüllt sind
    if(strlen($role->getValue("rol_start_date")) > 0
    || $role->getValue("rol_weekday") > 0
    || strlen($role->getValue("rol_start_time")) > 0
    || strlen($role->getValue("rol_location")) > 0
    || strlen($role->getValue("rol_cost")) > 0
    || strlen($role->getValue("rol_max_members")) > 0)
    {
        echo "
        <br />
        <table class=\"$class_table\" style=\"width: 400px;\" cellpadding=\"2\" cellspacing=\"0\">";
            //Kopf
            echo"
            <tr>
                <th class=\"$class_header\" colspan=\"2\">Infobox: ". $role->getValue("rol_name"). "</th>
            </tr>
            ";
            //Kategorie
            echo"
            <tr>
                <td>Kategorie:</td>
                <td>".$cat_row->cat_name."</td>
            </tr>";

            //Beschreibung
            if(strlen($role->getValue("rol_description")) > 0)
            {
                echo"<tr>
                    <td>Beschreibung:</td>
                    <td>".$role->getValue("rol_description")."</td>
                </tr>";
            }

            //Zeitraum
            if(strlen($role->getValue("rol_start_date")) > 0)
            {
                echo"<tr>
                    <td>Zeitraum:</td>
                    <td>". mysqldate("d.m.y", $role->getValue("rol_start_date")). " bis ". mysqldate("d.m.y", $role->getValue("rol_end_date")). "</td>
                </tr>";
            }

            //Termin
            if($role->getValue("rol_weekday") > 0 || strlen($role->getValue("rol_start_time")) > 0)
            {
                echo"<tr>
                    <td>Termin: </td>
                    <td>"; 
                        if($role->getValue("rol_weekday") > 0)
                        {
                            echo $arrDay[$role->getValue("rol_weekday")-1];
                        }
                        if(strlen($role->getValue("rol_start_time")) > 0)
                        {
                            echo " von ". mysqltime("h:i", $role->getValue("rol_start_time")). " bis ". mysqltime("h:i", $role->getValue("rol_end_time"));
                        }

                    echo"</td>
                </tr>";
            }

            //Treffpunkt
            if(strlen($role->getValue("rol_location")) > 0)
            {
                echo"<tr>
                    <td>Treffpunkt:</td>
                    <td>".$role->getValue("rol_location")."</td>
                </tr>";
            }

            //Beitrag
            if(strlen($role->getValue("rol_cost")) > 0)
            {
                echo"<tr>
                    <td>Beitrag:</td>
                    <td>". $role->getValue("rol_cost"). " &euro;</td>
                </tr>";
            }

            //maximale Teilnehmerzahl
            if(strlen($role->getValue("rol_max_members")) > 0)
            {
                echo"<tr>
                    <td>Max. Teilnehmer:</td>
                    <td>". $role->getValue("rol_max_members"). "</td>
                </tr>";
            }

        echo"</table>";
    }
    // Ende Infobox

    echo "</div>";
    
    if($req_mode != "print")
    {    
        require("../../../adm_config/body_bottom.php");
    }

    echo "</body>
    </html>";
}

?>