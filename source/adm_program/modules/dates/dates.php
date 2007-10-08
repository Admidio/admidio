<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
 *****************************************************************************/

require("../../system/common.php");
require("../../system/bbcode.php");
require("../../system/date_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_dates_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
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
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = $req_headline;

if($g_preferences['enable_rss'] == 1 && $g_preferences['enable_dates_module'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Termine\"
        href=\"$g_root_path/adm_program/modules/dates/rss_dates.php\" />";
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
    $organizations = "'". $g_current_organization->getValue("org_shortname"). "'";
}

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...
if($req_id > 0)
{
    $sql = "SELECT * FROM ". TBL_DATES. "
             WHERE ( dat_id = $req_id
                   AND ((dat_global   = 1 AND dat_org_shortname IN ($organizations))
                        OR dat_org_shortname = '". $g_current_organization->getValue("org_shortname"). "'))";
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
    //fuer alter Termine...
    if($req_mode == "old")
    {
        $sql    = "SELECT * FROM ". TBL_DATES. "
                    WHERE (  dat_org_shortname = '". $g_current_organization->getValue("org_shortname"). "'
                       OR (   dat_global   = 1
                          AND dat_org_shortname IN ($organizations) ))
                      AND dat_begin < '$act_date'
                      AND dat_end   < '$act_date'
                    ORDER BY dat_begin DESC
                    LIMIT $req_start, 10 ";
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
                    LIMIT $req_start, 10 ";
    }
}
$dates_result = $g_db->query($sql);

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
$result = $g_db->query($sql);
$row    = $g_db->fetch_array($result);
$num_dates = $row[0];

// Icon-Links und Navigation anzeigen

if($req_id == 0
&& ($g_current_user->editDates() || $g_preferences['enable_rss'] == true))
{
    // Neue Termine anlegen
    if($g_current_user->editDates())
    {
        echo "
        <ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline$req_headline\"><img
                    src=\"$g_root_path/adm_program/images/add.png\" alt=\"Termin anlegen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline=$req_headline\">Anlegen</a>
                </span>
            </li>
        </ul>";    
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}

if($g_db->num_rows($dates_result) == 0)
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
    $date = new Date($g_db);

    // Termine auflisten
    while($row = $g_db->fetch_object($dates_result))
    {
        $date->clear();
        $date->setArray($row);
        echo "
        <div class=\"boxLayout\">
            <div class=\"boxHead\">
                <div class=\"boxHeadLeft\">
                    <img src=\"$g_root_path/adm_program/images/date.png\" class=\"icon16\" alt=\"". $date->getValue("dat_headline"). "\" />
                    ". mysqldatetime("d.m.y", $date->getValue("dat_begin")). "
                </div>
                <div class=\"boxHeadCenter\">". $date->getValue("dat_headline"). "</div>
                <div class=\"boxHeadRight\">
                    <span class=\"iconLink\">
                        <a href=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=". $date->getValue("dat_id"). "&amp;mode=4\"><img 
                        src=\"$g_root_path/adm_program/images/database_out.png\" alt=\"Exportieren (iCal)\" title=\"Exportieren (iCal)\" /></a>
                    </span>";
                    
                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($g_current_user->editDates())
                    {
                        if($date->editRight() == true)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/dates/dates_new.php?dat_id=". $date->getValue("dat_id"). "&amp;headline=$req_headline\"><img 
                                src=\"$g_root_path/adm_program/images/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>
                            </span>";
                        }

                        // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                        if($date->getValue("dat_org_shortname") == $g_organization)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/dates/dates_function.php?mode=5&amp;dat_id=". $date->getValue("dat_id"). "\"><img 
                                src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Löschen\" title=\"Löschen\" /></a>
                            </span>";
                        }
                    }
                echo"</div>
            </div>

            <div class=\"boxBody\">
                <div style=\"margin-bottom: 10px;\">";
                    if (mysqldatetime("h:i", $date->getValue("dat_begin")) != "00:00")
                    {
                        echo "Beginn:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>".
                        mysqldatetime("h:i", $date->getValue("dat_begin")). "</strong> Uhr&nbsp;&nbsp;&nbsp;&nbsp;";
                    }

                    if($date->getValue("dat_begin") != $date->getValue("dat_end"))
                    {
                        if (mysqldatetime("h:i", $date->getValue("dat_begin")) != "00:00")
                        {
                            echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                        }

                        echo "Ende:&nbsp;";
                        if(mysqldatetime("d.m.y", $date->getValue("dat_begin")) != mysqldatetime("d.m.y", $date->getValue("dat_end")))
                        {
                            echo "<strong>". mysqldatetime("d.m.y", $date->getValue("dat_end")). "</strong>";

                            if (mysqldatetime("h:i", $date->getValue("dat_end")) != "00:00")
                            {
                                echo " um ";
                            }
                        }

                        if (mysqldatetime("h:i", $date->getValue("dat_end")) != "00:00")
                        {
                            echo "<strong>". mysqldatetime("h:i", $date->getValue("dat_end")). "</strong> Uhr";
                        }
                    }

                    if ($date->getValue("dat_location") != "")
                    {
                        if (mysqldatetime("h:i", $date->getValue("dat_begin")) != "00:00"
                        && $date->getValue("dat_begin") != $date->getValue("dat_end"))
                        {
                            echo "<br />";
                        }
                        echo "Treffpunkt:&nbsp;<strong>". $date->getValue("dat_location"). "</strong>";
                    }
                echo "</div>
                <div>";
                    // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                    if($g_preferences['enable_bbcode'] == 1)
                    {
                        echo $bbcode->parse($date->getValue("dat_description"));
                    }
                    else
                    {
                        echo nl2br($date->getValue("dat_description"));
                    }
                echo "</div>
                <div class=\"editInformation\">";
                    $user_create = new User($g_db, $date->getValue("dat_usr_id"));
                    echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
                    " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_timestamp"));

                    // Zuletzt geaendert nur anzeigen, wenn Aenderung nach 15 Minuten oder durch anderen Nutzer gemacht wurde
                    if($date->getValue("dat_usr_id_change") > 0
                    && (  strtotime($date->getValue("dat_last_change")) > (strtotime($date->getValue("dat_timestamp")) + 900)
                       || $date->getValue("dat_usr_id_change") != $date->getValue("dat_usr_id") ) )
                    {
                        $user_change = new User($g_db, $date->getValue("dat_usr_id_change"));
                        echo "<br />Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                        " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_last_change"));
                    }
                echo "</div>
            </div>
        </div>";
    }  // Ende While-Schleife
}

if($g_db->num_rows($dates_result) > 2)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}
        
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>