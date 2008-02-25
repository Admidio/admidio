<?php
/******************************************************************************
 * Verwaltung der aller Mitglieder in der Datenbank
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * members - 1 : (Default) Nur Mitglieder der Gliedgemeinschaft anzeigen
 *           0 : Mitglieder, Ehemalige, Mitglieder anderer Gliedgemeinschaften
 * letter      : alle User deren Nachnamen mit dem Buchstaben beginnt, werden angezeigt
 * start       : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen
 * search      : Inhalt des Suchfeldes, damit dieser beim Blaettern weiter genutzt werden kann
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// nur berechtigte User duerfen die Mitgliederverwaltung aufrufen
if (!$g_current_user->editUser())
{
    $g_message->show("norights");
}

// lokale Variablen initialisieren
$restrict = "";
$listname = "";
$i = 0;
$members_per_page = 20; // Anzahl der Mitglieder, die auf einer Seite angezeigt werden

// lokale Variablen der Uebergabevariablen initialisieren
$req_members   = 1;
$req_letter    = "";
$req_start     = 0;
$req_search    = null;
$req_queryForm = null;

// Uebergabevariablen pruefen

if (isset($_GET['members']) && is_numeric($_GET['members']))
{
    $req_members = $_GET['members'];
}

if (isset($_GET['letter']))
{
    
    if(strlen($_GET['letter']) > 1)
    {
        $g_message->show("invalid");
    }
    $req_letter = $_GET['letter'];
}

if(isset($_GET['start']))
{
    if(is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['search']))
{
    $req_search = strStripTags($_GET['search']);
}

// members aus dem $_REQUEST Array holen, da es sowohl durch GET als auch durch POST uebergeben werden kann
if (isset($_REQUEST['queryForm']) && strlen($_REQUEST['queryForm']) > 0)
{
    $req_queryForm = strStripTags($_REQUEST['queryForm']);
}

// Die zum Caching in der Session zwischengespeicherten Namen werden beim
// neu laden der Seite immer abgeraeumt...
unset ($_SESSION['QuerySuggestions']);

// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);


// Bedingungen fuer das SQL-Statement je nach Modus setzen
if ($req_queryForm)
{
    // Bedingung fuer die Suchanfrage
    $search_string = str_replace(',', '', $req_queryForm). '%';
    $search_condition = " AND (  last_name.usd_value  LIKE '$search_string'
                              OR first_name.usd_value LIKE '$search_string' ) ";    
}
else
{
    $search_condition = " AND last_name.usd_value LIKE '$req_letter%' ";
}

// alle Mitglieder zur Auswahl selektieren
// unbestaetigte User werden dabei nicht angezeigt
if($req_members)
{
    $sql    = "SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, 
                      email.usd_value as email, homepage.usd_value as homepage,
                      usr_login_name, usr_last_change, 1 member
                 FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_USERS. "
                RIGHT JOIN ". TBL_USER_DATA. " as last_name
                   ON last_name.usd_usr_id = usr_id
                  AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                      $search_condition
                 LEFT JOIN ". TBL_USER_DATA. " as first_name
                   ON first_name.usd_usr_id = usr_id
                  AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
                 LEFT JOIN ". TBL_USER_DATA. " as email
                   ON email.usd_usr_id = usr_id
                  AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                 LEFT JOIN ". TBL_USER_DATA. " as homepage
                   ON homepage.usd_usr_id = usr_id
                  AND homepage.usd_usf_id = ". $g_current_user->getProperty("Homepage", "usf_id"). "
                WHERE usr_valid = 1
                  AND mem_usr_id = usr_id
                  AND mem_rol_id = rol_id
                  AND mem_valid  = 1
                  AND rol_valid  = 1
                  AND rol_cat_id = cat_id
                  AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                ORDER BY last_name.usd_value ";
}
else
{
    // alle DB-User auslesen und Anzahl der zugeordneten Orga-Rollen ermitteln
    $sql    = "SELECT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, 
                      email.usd_value as email, homepage.usd_value as homepage,
                      usr_login_name, usr_last_change, count(cat_id) member
                 FROM ". TBL_USERS. "
                RIGHT JOIN ". TBL_USER_DATA. " as last_name
                   ON last_name.usd_usr_id = usr_id
                  AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                      $search_condition
                 LEFT JOIN ". TBL_MEMBERS. "
                   ON mem_usr_id = usr_id
                  AND mem_valid  = 1
                 LEFT JOIN ". TBL_ROLES. "
                   ON mem_rol_id = rol_id
                  AND rol_valid  = 1
                 LEFT JOIN ". TBL_CATEGORIES. "
                   ON rol_cat_id = cat_id
                  AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                 LEFT JOIN ". TBL_USER_DATA. " as first_name
                   ON first_name.usd_usr_id = usr_id
                  AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
                 LEFT JOIN ". TBL_USER_DATA. " as email
                   ON email.usd_usr_id = usr_id
                  AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                 LEFT JOIN ". TBL_USER_DATA. " as homepage
                   ON homepage.usd_usr_id = usr_id
                  AND homepage.usd_usf_id = ". $g_current_user->getProperty("Homepage", "usf_id"). "
                WHERE usr_valid = 1
                GROUP BY usr_id
                ORDER BY last_name.usd_value ";
}
$result_mgl  = $g_db->query($sql);
$num_members = $g_db->num_rows($result_mgl);

if($num_members < $req_start)
{
    $g_message->show("invalid");
}

// User zaehlen, die mind. einer Rolle zugeordnet sind
$sql    = "SELECT COUNT(*) as count
             FROM ". TBL_USERS. "
            WHERE usr_valid = 1 ";
$result = $g_db->query($sql);
$row    = $g_db->fetch_array($result);
$count_mem_rol = $row['count'];

// Html-Kopf ausgeben
$g_layout['title']  = "Benutzerverwaltung";
$g_layout['header'] = '
    <link rel="stylesheet" type="text/css" href="'. THEME_PATH. '/css/autosuggest.css" />
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.Ajax.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.DOM.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.AutoSuggest.js"></script>';

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Benutzerverwaltung</h1>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\"><img
            src=\"". THEME_PATH. "/icons/add.png\" alt=\"Benutzer anlegen\" /></a>
            <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\">Benutzer anlegen</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/members/import.php\"><img
            src=\"". THEME_PATH. "/icons/database_in.png\" alt=\"Benutzer importieren\" /></a>
            <a href=\"$g_root_path/adm_program/administration/members/import.php\">Benutzer importieren</a>
        </span>
    </li>
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/administration/members/fields.php\"><img
            src=\"". THEME_PATH. "/icons/application_form.png\" alt=\"Organisationsspezifische Profilfelder pflegen\" /></a>
            <a href=\"$g_root_path/adm_program/administration/members/fields.php\">Profilfelder pflegen</a>
        </span>
    </li>
</ul>";

if($count_mem_rol != $g_db->num_rows($result_mgl) || $req_members == false)
{
    // Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
    if($req_members == 1)
    {
        $link_text = "Alle Benutzer anzeigen";
        $link_icon = "group.png";
        $link_members = 0;
    }
    else
    {
        $link_text = "Nur Mitglieder anzeigen";
        $link_icon = "user.png";
        $link_members = 1;
    }
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/administration/members/members.php?members=$link_members&amp;letter=$req_letter&amp;queryForm=$req_queryForm\"><img
                src=\"". THEME_PATH. "/icons/$link_icon\" alt=\"$link_text\" /></a>
                <a href=\"$g_root_path/adm_program/administration/members/members.php?members=$link_members&amp;letter=$req_letter&amp;queryForm=$req_queryForm\">$link_text</a>
            </span>
        </li>
    </ul>";
}

if($req_members)
{
    echo "<p>Alle aktiven Mitglieder ";
}
else
{
    echo "<p>Alle Mitglieder und Ehemalige ";
}

if(strlen($req_letter) > 0)
{
    echo " mit Nachnamen $req_letter*";
}
echo " werden angezeigt</p>";

//Hier gibt es jetzt noch die Suchbox...
echo "
<form action=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members\" method=\"post\">
    <div id=\"search_members\">
        <input type=\"text\" value=\"$req_queryForm\" name=\"queryForm\" id=\"queryForm\" style=\"width: 200px;\"  />
        <input type=\"submit\" value=\"Suchen\" />    
    </div>
</form>

<script type=\"text/javascript\">
    var options = {
                script:\"$g_root_path/adm_program/administration/members/query_suggestions.php?members=$req_members&\",
                varname:\"query\",
                minchars:1,
                timeout:5000
    };
    var as = new AutoSuggest('queryForm', options);
</script>
";

echo "<div class=\"pageNavigation\">";

    // Leiste mit allen Buchstaben des Alphabets anzeigen

    if (strlen($req_letter) == 0 && !$req_queryForm)
    {
        echo "<span class=\"selected\">Alle</span>&nbsp;&nbsp;&nbsp;";
    }
    else
    {
        echo "<a href=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members\">Alle</a>&nbsp;&nbsp;&nbsp;";
    }

    // Alle Anfangsbuchstaben der Nachnamen ermitteln, die bisher in der DB gespeichert sind
    if($req_members == 1)
    {
        $sql    = "SELECT UPPER(SUBSTRING(usd_value, 1, 1)) as letter, COUNT(1) as count
                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", 
                          ". TBL_USERS. ", ". TBL_USER_FIELDS. ", ". TBL_USER_DATA. "
                    WHERE rol_valid  = 1
                      AND rol_cat_id = cat_id
                      AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
                      AND mem_rol_id = rol_id
                      AND mem_usr_id = usr_id
                      AND mem_valid  = 1
                      AND usr_valid  = 1 
                      AND usf_name   = 'Nachname'
                      AND usd_usf_id = usf_id
                      AND usd_usr_id = usr_id
                    GROUP BY UPPER(SUBSTRING(usd_value, 1, 1))
                    ORDER BY usd_value ";
    }
    else
    {
        $sql    = "SELECT UPPER(SUBSTRING(usd_value, 1, 1)) as letter, COUNT(1) as count 
                     FROM ". TBL_USERS. ", ". TBL_USER_FIELDS. ", ". TBL_USER_DATA. "
                    WHERE usr_valid  = 1 
                      AND usf_name   = 'Nachname'
                      AND usd_usf_id = usf_id
                      AND usd_usr_id = usr_id
                    GROUP BY UPPER(SUBSTRING(usd_value, 1, 1))
                    ORDER BY usd_value ";
    }
    $result      = $g_db->query($sql);
    $letter_row  = $g_db->fetch_array($result);
    $letter_menu = "A";

    // kleine Vorschleife die alle Sonderzeichen (Zahlen) vor dem A durchgeht 
    // (diese werden nicht im Buchstabenmenue angezeigt)
    while(ord($letter_row['letter']) < ord("A"))
    {
        $letter_row = $g_db->fetch_array($result);
    }

    // Nun alle Buchstaben mit evtl. vorhandenen Links im Buchstabenmenue anzeigen
    for($i = 0; $i < 26;$i++)
    {
        // pruefen, ob es Mitglieder zum Buchstaben gibt, unter Beruecksichtigung deutscher Sonderzeichen
        if( $letter_menu == $letter_row['letter']
        || ($letter_menu == "A" && $letter_row[0] == "Ä")
        || ($letter_menu == "O" && $letter_row[0] == "Ö")
        || ($letter_menu == "U" && $letter_row[0] == "Ü") )
        {
            $letter_found = true;
        }
        else
        {
            $letter_found = false;
        }

        if($letter_menu == substr($req_letter, 0, 1))
        {
            echo "<span class=\"selected\">$letter_menu</span>";
        }
        elseif($letter_found == true)
        {
            echo "<a href=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members&amp;letter=$letter_menu\" title=\"". $letter_row['count']. " Benutzer gefunden\">$letter_menu</a>";
        }
        else
        {
            echo $letter_menu;
        }

        echo "&nbsp;&nbsp;";

        // naechsten Buchstaben anwaehlen
        if($letter_found == true)
        {
            $letter_row = $g_db->fetch_array($result);
        }
        $letter_menu = strNextLetter($letter_menu);
    }
echo "</div>";

if($num_members > 0)
{
    echo "<table class=\"tableList\" cellspacing=\"0\">
        <tr>
            <th>Nr.</th>
            <th><img class=\"iconInformation\" 
                src=\"". THEME_PATH. "/icons/user.png\" alt=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" 
                title=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" /></th>
            <th>Name</th>
            <th><img class=\"iconInformation\" 
                src=\"". THEME_PATH. "/icons/email.png\" alt=\"E-Mail\" title=\"E-Mail\" /></th>
            <th><img class=\"iconInformation\" 
                src=\"". THEME_PATH. "/icons/globe.png\" alt=\"Homepage\" title=\"Homepage\" /></th>
            <th>Benutzer</th>
            <th>Aktualisiert am</th>
            <th style=\"text-align: center;\">Funktionen</th>
        </tr>";
        $i = 0;

        // jetzt erst einmal zu dem ersten relevanten Datensatz springen
        if(!$g_db->data_seek($result_mgl, $req_start))
        {
            $g_message->show("invalid");
        }

        for($i = 0; $i < $members_per_page && $i + $req_start < $num_members; $i++)
        {
            if($row = $g_db->fetch_array($result_mgl))
            {
                echo "
                <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                    <td>". ($req_start + $i + 1). "</td>
                    <td>";
                        if($row['member'] > 0)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "\"><img
                                src=\"". THEME_PATH. "/icons/user.png\" alt=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\"
                                title=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" /></a>
                            </span>";
                        }
                        else
                        {
                            echo "&nbsp;";
                        }
                    echo "</td>
                    <td><a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "\">". $row['last_name']. ",&nbsp;". $row['first_name']. "</a></td>
                    <td>";
                        if(strlen($row['email']) > 0)
                        {
                            if($g_preferences['enable_mail_module'] != 1)
                            {
                                $mail_link = "mailto:". $row['email'];
                            }
                            else
                            {
                                $mail_link = "$g_root_path/adm_program/modules/mail/mail.php?usr_id=". $row['usr_id'];
                            }
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$mail_link\"><img src=\"". THEME_PATH. "/icons/email.png\"
                                alt=\"E-Mail an ". $row['email']. " schreiben\" title=\"E-Mail an ". $row['email']. " schreiben\" /></a>
                            </span>";
                        }
                    echo "</td>
                    <td>";
                        if(strlen($row['homepage']) > 0)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"". $row['homepage']. "\" target=\"_blank\"><img
                                src=\"". THEME_PATH. "/icons/globe.png\" alt=\"Homepage\" title=\"Homepage\" /></a>
                            </span>";
                        }
                    echo "</td>
                    <td>". $row['usr_login_name']. "</td>
                    <td>". mysqldatetime("d.m.y h:i" , $row['usr_last_change']). "</td>
                    <td style=\"text-align: center;\">";
                        // pruefen, ob der User noch in anderen Organisationen aktiv ist
                        $sql    = "SELECT *
                                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
                                    WHERE rol_valid   = 1
                                      AND rol_cat_id  = cat_id
                                      AND cat_org_id <> ". $g_current_organization->getValue("org_id"). "
                                      AND mem_rol_id  = rol_id
                                      AND mem_valid   = 1
                                      AND mem_usr_id  = ". $row['usr_id'];
                        $result = $g_db->query($sql);
                        $b_other_orga = false;

                        if($g_db->num_rows($result) > 0)
                        {
                            $b_other_orga = true;
                        }
                        
                        // Link um E-Mail mit neuem Passwort zu zuschicken
                        // nur ausfuehren, wenn E-Mails vom Server unterstuetzt werden
                        if($row['member'] > 0
                        && $g_current_user->isWebmaster()
                        && strlen($row['usr_login_name']) > 0
                        && strlen($row['email']) > 0
                        && $g_preferences['enable_system_mails'] == 1)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=". $row['usr_id']. "&amp;mode=5\"><img
                                src=\"". THEME_PATH. "/icons/key.png\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"
                                title=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\" /></a>
                            </span>";
                        }
                        else
                        {
                            echo "
                            <span class=\"iconLink\">
                                <img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" />
                            </span>";
                        }

                        // Link um User zu editieren
                        // es duerfen keine Nicht-Mitglieder editiert werden, die Mitglied in einer anderen Orga sind
                        if($row['member'] > 0 || $b_other_orga == false)
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $row['usr_id']. "\"><img
                                src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Benutzerdaten bearbeiten\" title=\"Benutzerdaten bearbeiten\" /></a>
                            </span>";
                        }
                        else
                        {
                            echo "
                            <span class=\"iconLink\">
                                <img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" />
                            </span>";
                        }

                        // wenn der User nicht mehr Mitglied der aktuellen Orga, aber noch Mitglied einer anderen Orga ist,
                        // dann darf er nicht aus der DB geloescht werden
                        if(($b_other_orga == false || $row['member'] > 0)
                        && $row['usr_id'] != $g_current_user->getValue("usr_id"))
                        {
                            echo "
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=". $row['usr_id']. "&amp;mode=6\"><img
                                src=\"". THEME_PATH. "/icons/cross.png\" alt=\"Benutzer entfernen\" title=\"Benutzer entfernen\" /></a>
                            </span>";
                        }
                        else
                        {
                            echo "
                            <span class=\"iconLink\">
                                <img src=\"". THEME_PATH. "/icons/dummy.png\" alt=\"dummy\" />
                            </span>";
                        }
                    echo "</td>
                </tr>";
            }
        }
    echo "</table>";

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/administration/members/members.php?letter=$req_letter&members=$req_members&queryForm=$req_queryForm";
    echo generatePagination($base_url, $num_members, $members_per_page, $req_start, TRUE);

}
else
{
    echo "<p>Es wurde keine Daten gefunden !</p><br />";
}
        
require(THEME_SERVER_PATH. "/overall_footer.php");

?>