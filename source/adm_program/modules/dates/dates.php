<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * mode: actual - (Default) Alle aktuellen und zukuenftige Termine anzeigen
 *       old    - Alle bereits erledigten
 * start        - Angabe, ab welchem Datensatz Termine angezeigt werden sollen
 * headline     - Ueberschrift, die ueber den Terminen steht
 *                (Default) Termine
 * id           - Nur einen einzigen Termin anzeigen lassen.
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
require("../../system/bbcode.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_mode     = "actual";
$req_start    = 0;
$req_headline = "Termine";
$req_id       = 0;

// Uebergabevariablen pruefen

if(isset($_GET['mode']))
{
    if($_GET['mode'] != "actual" && $_GET['mode'] != "old")
    {
        $g_message->show("invalid");
    }
    $req_mode = $_GET['mode'];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET['start']) == false)
    {
        $g_message->show("invalid");
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET["headline"]);
}

if(isset($_GET['id']))
{
    if(is_numeric($_GET['id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_id = $_GET['id'];
}

if($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode
    $bbcode = new ubbParser();
}

unset($_SESSION['dates_request']);
$act_date = date("Y.m.d 00:00:00", time());
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl($g_current_url);

// Html-Kopf ausgeben
$g_layout['title'] = $req_headline;
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"$g_current_organization->longname - Termine\"
        href=\"$g_root_path/adm_program/modules/dates/rss_dates.php\">";
};

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">";
    if($req_mode == "old")
    {
        echo "Vergangene ". $req_headline;
    }
    else
    {
        echo $req_headline;
    }
echo "</h1>";


// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$arr_ref_orgas = $g_current_organization->getReferenceOrganizations();
$organizations = "";
$i             = 0;

while($orga = current($arr_ref_orgas))
{
    if($i > 0)
    {
        $organizations = $organizations. ", ";
    }
    $organizations = $organizations. "'$orga'";
    next($arr_ref_orgas);
    $i++;
}

// damit das SQL-Statement nachher nicht auf die Nase faellt, muss $organizations gefuellt sein
if(strlen($organizations) == 0)
{
    $organizations = "'$g_current_organization->shortname'";
}

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...
if($req_id > 0)
{
    $sql = "SELECT * FROM ". TBL_DATES. "
             WHERE ( dat_id = {0}
                   AND ((dat_global   = 1 AND dat_org_shortname IN ($organizations))
                        OR dat_org_shortname = '$g_organization'))";
    $sql    = prepareSQL($sql, array($req_id));
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
    //fuer alter Termine...
    if($req_mode == "old")
    {
        $sql    = "SELECT * FROM ". TBL_DATES. "
                    WHERE (  dat_org_shortname = '$g_organization'
                       OR (   dat_global   = 1
                          AND dat_org_shortname IN ($organizations) ))
                      AND dat_begin < '$act_date'
                      AND dat_end   < '$act_date'
                    ORDER BY dat_begin DESC
                    LIMIT {0}, 10 ";
        $sql    = prepareSQL($sql, array($req_start));
    }
    //... ansonsten fuer neue Termine
    else
    {
        $sql    = "SELECT * FROM ". TBL_DATES. "
                    WHERE (  dat_org_shortname = '$g_organization'
                       OR (   dat_global   = 1
                          AND dat_org_shortname IN ($organizations) ))
                      AND (  dat_begin >= '$act_date'
                          OR dat_end   >= '$act_date' )
                    ORDER BY dat_begin ASC
                    LIMIT {0}, 10 ";
        $sql    = prepareSQL($sql, array($req_start));
    }
}


$dates_result = mysql_query($sql, $g_adm_con);
db_error($dates_result,__FILE__,__LINE__);

// Gucken wieviele Datensaetze die Abfrage ermittelt kann...
if($req_mode == "old")
{
    $sql    = "SELECT COUNT(*) FROM ". TBL_DATES. "
                WHERE (  dat_org_shortname = '$g_organization'
                      OR (   dat_global   = 1
                         AND dat_org_shortname IN ($organizations) ))
                  AND dat_begin < '$act_date'
                  AND dat_end   < '$act_date'
                ORDER BY dat_begin DESC ";
}
else
{
    $sql    = "SELECT COUNT(*) FROM ". TBL_DATES. "
                WHERE (  dat_org_shortname = '$g_organization'
                      OR (   dat_global   = 1
                         AND dat_org_shortname IN ($organizations) ))
                  AND (  dat_begin >= '$act_date'
                      OR dat_end   >= '$act_date' )
                ORDER BY dat_begin ASC ";
}
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);
$row = mysql_fetch_array($result);
$num_dates = $row[0];

// Icon-Links und Navigation anzeigen

if($req_id == 0
&& ($g_current_user->editDates() || $g_preferences['enable_rss'] == true))
{
    // Neue Termine anlegen
    if($g_current_user->editDates())
    {
        echo "<p>
            <span class=\"iconLink\">
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline$req_headline\"><img
                class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Termin anlegen\"></a>
                <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline=$req_headline\">Anlegen</a>
            </span>
        </p>";
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}

if(mysql_num_rows($dates_result) == 0)
{
    // Keine Termine gefunden
    if($req_id > 0)
    {
        echo "<p>Der angeforderte Eintrag exisitiert nicht (mehr) in der Datenbank.</p>";
    }
    else
    {
        echo "<p>Es sind keine Eintr&auml;ge vorhanden.</p>";
    }
}
else
{
    // Termine auflisten
    while($row = mysql_fetch_object($dates_result))
    {
        echo "
        <div class=\"boxBody\" style=\"overflow: hidden;\">
            <div class=\"boxHead\">
                <div style=\"width: 85%; float: left;\">
                    <img src=\"$g_root_path/adm_program/images/date.png\" style=\"vertical-align: middle;\" alt=\"". strSpecialChars2Html($row->dat_headline). "\">
                    ". mysqldatetime("d.m.y", $row->dat_begin). "
                    &nbsp;". strSpecialChars2Html($row->dat_headline). "
                </div>";
                // Link zum iCal export
                echo "<div style=\"text-align: right;\">
                    <img src=\"$g_root_path/adm_program/images/database_out.png\" style=\"cursor: pointer\"
                        width=\"16\" height=\"16\" border=\"0\" alt=\"Exportieren (iCal)\" title=\"Exportieren (iCal)\"
                        onclick=\"self.location.href='$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=$row->dat_id&mode=4'\">";

                    // aendern & loeschen darf man nur eigene Termine, ausser Moderatoren
                    if ($g_current_user->editDates())
                    {
                        echo "&nbsp;<img src=\"$g_root_path/adm_program/images/edit.png\" style=\"cursor: pointer\"
                            width=\"16\" height=\"16\" border=\"0\" alt=\"Bearbeiten\" title=\"Bearbeiten\"
                            onclick=\"self.location.href='dates_new.php?dat_id=$row->dat_id&amp;headline=$req_headline'\">";

                            // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                            if($row->dat_org_shortname == $g_organization)
                            {
                                echo "
                                <img src=\"$g_root_path/adm_program/images/cross.png\" style=\"cursor: pointer\"
                                    width=\"16\" height=\"16\" border=\"0\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"
                                    onclick=\"self.location.href='$g_root_path/adm_program/modules/dates/dates_function.php?mode=5&dat_id=$row->dat_id'\">";
                            }
                    }
                echo "&nbsp;</div>
            </div>

            <div style=\"margin: 8px 4px 4px 4px;\">";
                if (mysqldatetime("h:i", $row->dat_begin) != "00:00")
                {
                    echo "Beginn:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>".
                    mysqldatetime("h:i", $row->dat_begin). "</b> Uhr&nbsp;&nbsp;&nbsp;&nbsp;";
                }

                if($row->dat_begin != $row->dat_end)
                {
                    if (mysqldatetime("h:i", $row->dat_begin) != "00:00")
                    {
                        echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    }

                    echo "Ende:&nbsp;";
                    if(mysqldatetime("d.m.y", $row->dat_begin) != mysqldatetime("d.m.y", $row->dat_end))
                    {
                        echo "<b>". mysqldatetime("d.m.y", $row->dat_end). "</b>";

                        if (mysqldatetime("h:i", $row->dat_end) != "00:00")
                        {
                            echo " um ";
                        }
                    }

                    if (mysqldatetime("h:i", $row->dat_end) != "00:00")
                    {
                        echo "<b>". mysqldatetime("h:i", $row->dat_end). "</b> Uhr";
                    }
                }

                if ($row->dat_location != "")
                {
                    echo "<br />Treffpunkt:&nbsp;<b>". strSpecialChars2Html($row->dat_location). "</b>";
                }
            echo "</div>
            <div style=\"margin: 8px 4px 4px 4px;\">";
                // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                if($g_preferences['enable_bbcode'] == 1)
                {
                    echo strSpecialChars2Html($bbcode->parse($row->dat_description));
                }
                else
                {
                    echo nl2br(strSpecialChars2Html($row->dat_description));
                }
            echo "</div>
            <div class=\"smallFontSize\" style=\"margin: 8px 4px 4px 4px;\">";
                $user_create = new User($g_adm_con, $row->dat_usr_id);
                echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
                " am ". mysqldatetime("d.m.y h:i", $row->dat_timestamp);

                if($row->dat_usr_id_change > 0)
                {
                    $user_change = new User($g_adm_con, $row->dat_usr_id_change);
                    echo "<br>Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                    " am ". mysqldatetime("d.m.y h:i", $row->dat_last_change);
                }
            echo "</div>
        </div>

        <br />";
    }  // Ende While-Schleife
}

if(mysql_num_rows($dates_result) > 2)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}
        
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>