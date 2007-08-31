<?php
/******************************************************************************
 * Ankuendigungen auflisten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * start     - Angabe, ab welchem Datensatz Ankuendigungen angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
 * id        - Nur eine einzige Annkuendigung anzeigen lassen.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/bbcode.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_start    = 0;
$req_headline = "Ank&uuml;ndigungen";
$req_id       = 0;

// Uebergabevariablen pruefen

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

unset($_SESSION['announcements_request']);
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = $req_headline;
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] = "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Ankuendigungen\"
    href=\"$g_root_path/adm_program/modules/announcements/rss_announcements.php\">";
};

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "<h1 class=\"moduleHeadline\">$req_headline</h1>";

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

// falls eine id fuer eine bestimmte Ankuendigung uebergeben worden ist...
if($req_id > 0)
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE ( ann_id = $req_id
                      AND ((ann_global   = 1 AND ann_org_shortname IN ($organizations))
                           OR ann_org_shortname = '". $g_current_organization->getValue("org_shortname"). "'))";
}
//...ansonsten alle fuer die Gruppierung passenden Ankuendigungen aus der DB holen.
else
{
    $sql    = "SELECT * FROM ". TBL_ANNOUNCEMENTS. "
                WHERE (  ann_org_shortname = '". $g_current_organization->getValue("org_shortname"). "'
                      OR (   ann_global   = 1
                         AND ann_org_shortname IN ($organizations) ))
                ORDER BY ann_timestamp DESC
                LIMIT $req_start, 10 ";
}

$announcements_result = $g_db->query($sql);

// Gucken wieviele Datensaetze die Abfrage ermittelt kann...
$sql    = "SELECT COUNT(*) FROM ". TBL_ANNOUNCEMENTS. "
            WHERE (  ann_org_shortname = '$g_organization'
                  OR (   ann_global   = 1
                     AND ann_org_shortname IN ($organizations) ))
            ORDER BY ann_timestamp ASC ";
$result = $g_db->query($sql);
$row    = $g_db->fetch_array($result);
$num_announcements = $row[0];

// Icon-Links und Navigation anzeigen

if($req_id == 0
&& ($g_current_user->editAnnouncements() || $g_preferences['enable_rss'] == true))
{
    // Neue Ankuendigung anlegen
    if($g_current_user->editAnnouncements())
    {
        echo "
        <ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/announcements/announcements_new.php?headline=$req_headline\"><img
                    src=\"$g_root_path/adm_program/images/add.png\" alt=\"Neu anlegen\"></a>
                    <a href=\"$g_root_path/adm_program/modules/announcements/announcements_new.php?headline=$req_headline\">Anlegen</a>
                </span>
            </li>
        </ul>";        
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/modules/announcements/announcements.php?headline=$req_headline";
    echo generatePagination($base_url, $num_announcements, 10, $req_start, TRUE);
}

if ($g_db->num_rows($announcements_result) == 0)
{
    // Keine Ankuendigungen gefunden
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
    // Ankuendigungen auflisten
    while($row = $g_db->fetch_object($announcements_result))
    {
        echo "
        <div class=\"boxBody\" style=\"overflow: hidden;\">
            <div class=\"boxHead\">
                <div style=\"width: 70%; float: left;\">
                    <img src=\"$g_root_path/adm_program/images/note.png\" style=\"vertical-align: top;\" alt=\"". strSpecialChars2Html($row->ann_headline). "\">&nbsp;".
                    strSpecialChars2Html($row->ann_headline). "
                </div>";

                // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                if($g_current_user->editAnnouncements())
                {
                    echo "<div style=\"text-align: right;\">
                        <span class=\"iconLink\">
                            <a href=\"$g_root_path/adm_program/modules/announcements/announcements_new.php?ann_id=$row->ann_id&amp;headline=$req_headline\"><img 
                            src=\"$g_root_path/adm_program/images/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\"></a>
                        </span>";

                        // Loeschen darf man nur Ankuendigungen der eigenen Gliedgemeinschaft
                        if($row->ann_org_shortname == $g_organization)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/announcements/announcements_function.php?mode=4&ann_id=$row->ann_id\"><img 
                                src=\"$g_root_path/adm_program/images/cross.png\" alt=\"L&ouml;schen\" title=\"L&ouml;schen\"></a>
                            </span>";
                        }
                        echo mysqldatetime("d.m.y", $row->ann_timestamp). "&nbsp;
                    </div>";
                }
                else
                {
                    echo "<div style=\"text-align: right;\">". mysqldatetime("d.m.y", $row->ann_timestamp). "&nbsp;</div>";
                }
            echo "</div>

            <div style=\"margin: 8px 4px 4px 4px;\">";
                // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
                if($g_preferences['enable_bbcode'] == 1)
                {
                    echo strSpecialChars2Html($bbcode->parse($row->ann_description));
                }
                else
                {
                    echo nl2br(strSpecialChars2Html($row->ann_description));
                }
            echo "</div>
            <div class=\"editInformation\">";
                $user_create = new User($g_db, $row->ann_usr_id);
                echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
                " am ". mysqldatetime("d.m.y h:i", $row->ann_timestamp);

                // Zuletzt geaendert nur anzeigen, wenn Änderung nach 15 Minuten oder durch anderen Nutzer gemacht wurde
                if($row->ann_usr_id_change > 0
                && (  strtotime($row->ann_last_change) > (strtotime($row->ann_timestamp) + 900)
                   || $row->ann_usr_id_change != $row->ann_usr_id ) )
                {
                    $user_change = new User($g_db, $row->ann_usr_id_change);
                    echo "<br>Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                    " am ". mysqldatetime("d.m.y h:i", $row->ann_last_change);
                }
            echo "</div>
        </div>

        <br />";
    }  // Ende While-Schleife
}

if($g_db->num_rows($announcements_result) > 2)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $base_url = "$g_root_path/adm_program/modules/announcements/announcements.php?headline=$req_headline";
    echo generatePagination($base_url, $num_announcements, 10, $req_start, TRUE);
}
        
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>