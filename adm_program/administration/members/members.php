<?php
/******************************************************************************
 * Verwaltung der aller Mitglieder in der Datenbank
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * members - 1 : (Default) Nur Mitglieder der Gliedgemeinschaft anzeigen
 *           0 : Mitglieder, Ehemalige, Mitglieder anderer Gliedgemeinschaften
 * letter      : alle User deren Nachnamen mit dem Buchstaben beginnt, werden angezeigt
 * start       : Angabe, ab welchem Datensatz Mitglieder angezeigt werden sollen
 * search      : Inhalt des Suchfeldes, damit dieser beim Blaettern weiter genutzt werden kann
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
$_SESSION['navigation']->addUrl($g_current_url);


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
                ORDER BY last_name, first_name ";
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
                ORDER BY last_name, first_name ";
}
error_log($sql);
$result_mgl = mysql_query($sql, $g_adm_con);
db_error($result_mgl,__FILE__,__LINE__);

$num_members = mysql_num_rows($result_mgl);

if($num_members < $req_start)
{
    $g_message->show("invalid");
}

// User zaehlen, die mind. einer Rolle zugeordnet sind
$sql    = "SELECT COUNT(*) as count
             FROM ". TBL_USERS. "
            WHERE usr_valid = 1 ";
$result = mysql_query($sql, $g_adm_con);
db_error($result,__FILE__,__LINE__);

$row = mysql_fetch_array($result);
$count_mem_rol = $row['count'];

// Html-Kopf ausgeben
$g_layout['title']  = "Benutzerverwaltung";
$g_layout['header'] = '
    <link rel="stylesheet" type="text/css" href="autosuggest.css">
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.Ajax.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.DOM.js"></script>
    <script type="text/javascript" src="../../libs/bsn.autosuggest/bsn.AutoSuggest.js"></script>
    
    <style type="text/css">
        /* Safari braucht im Body position: relative damit das SuggestFeld unter und nicht auf der Suchbox liegt*/
        body {
            position: relative;
        }
    </style>';

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<h1 class=\"moduleHeadline\">Benutzerverwaltung</h1>

<p>
    <span class=\"iconLink\">
        <a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\"><img
        class=\"iconLink\" src=\"$g_root_path/adm_program/images/add.png\" alt=\"Benutzer anlegen\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/profile/profile_new.php?new_user=1\">Benutzer anlegen</a>
    </span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class=\"iconLink\">
        <a href=\"$g_root_path/adm_program/administration/members/import.php\"><img
        class=\"iconLink\" src=\"$g_root_path/adm_program/images/database_in.png\" alt=\"Benutzer importieren\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/import.php\">Benutzer importieren</a>
    </span>
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class=\"iconLink\">
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields.php\"><img
        class=\"iconLink\" src=\"$g_root_path/adm_program/images/application_form.png\" alt=\"Organisationsspezifische Profilfelder pflegen\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/fields.php\">Profilfelder pflegen</a>
    </span>
</p>";

if($count_mem_rol != mysql_num_rows($result_mgl) || $req_members == false)
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
    echo "<p>
    <span class=\"iconLink\">
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=$link_members&letter=$req_letter&queryForm=$req_queryForm\"><img
        class=\"iconLink\" src=\"$g_root_path/adm_program/images/$link_icon\" alt=\"$link_text\"></a>
        <a class=\"iconLink\" href=\"$g_root_path/adm_program/administration/members/members.php?members=$link_members&letter=$req_letter&queryForm=$req_queryForm\">$link_text</a>
    </span>
    </p>";
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
<div style=\"width: 300px;\">
    <form action=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members\" method=\"post\">
        <input type=\"text\" value=\"$req_queryForm\" name=\"queryForm\" id=\"queryForm\" autocomplete=\"off\" style=\"width: 200px;\"  />
        <input type=\"submit\" value=\"Suchen\" />
    </form>
</div>

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

echo "<p>";

    // Leiste mit allen Buchstaben des Alphabets anzeigen

    if (strlen($req_letter) == 0 && !$req_queryForm)
    {
        echo "<b>Alle</b>&nbsp;&nbsp;&nbsp;";
    }
    else
    {
        echo "<a href=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members\">Alle</a>&nbsp;&nbsp;&nbsp;";
    }

    // Alle Anfangsbuchstaben der Nachnamen ermitteln, die bisher in der DB gespeichert sind
    if($req_members == 1)
    {
        $sql    = "SELECT DISTINCT UPPER(SUBSTRING(usd_value, 1, 1)) 
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
                    ORDER BY usd_value ";
    }
    else
    {
        $sql    = "SELECT DISTINCT UPPER(SUBSTRING(usd_value, 1, 1))  
                     FROM ". TBL_USERS. ", ". TBL_USER_FIELDS. ", ". TBL_USER_DATA. "
                    WHERE usr_valid  = 1 
                      AND usf_name   = 'Nachname'
                      AND usd_usf_id = usf_id
                      AND usd_usr_id = usr_id
                    ORDER BY usd_value ";
    }
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);

    $letter_row = mysql_fetch_array($result);
    $letter_menu = "A";

    // kleine Vorschleife die alle Sonderzeichen (Zahlen) vor dem A durchgeht 
    // (diese werden nicht im Buchstabenmenue angezeigt)
    while(ord($letter_row[0]) < ord("A"))
    {
        $letter_row = mysql_fetch_array($result);
    }

    // Nun alle Buchstaben mit evtl. vorhandenen Links im Buchstabenmenue anzeigen
    for($i = 0; $i < 26;$i++)
    {
        // pruefen, ob es Mitglieder zum Buchstaben gibt, unter Beruecksichtigung deutscher Sonderzeichen
        if( $letter_menu == $letter_row[0]
        || ($letter_menu == "A" && utf8_encode($letter_row[0]) == "Ä")
        || ($letter_menu == "O" && utf8_encode($letter_row[0]) == "Ö")
        || ($letter_menu == "U" && utf8_encode($letter_row[0]) == "Ü") )
        {
            $letter_found = true;
        }
        else
        {
            $letter_found = false;
        }

        if($letter_menu == substr($req_letter, 0, 1))
        {
            echo "<b>$letter_menu</b>";
        }
        elseif($letter_found == true)
        {
            echo "<a href=\"$g_root_path/adm_program/administration/members/members.php?members=$req_members&letter=$letter_menu\">$letter_menu</a>";
        }
        else
        {
            echo $letter_menu;
        }

        echo "&nbsp;&nbsp;";

        // naechsten Buchstaben anwaehlen
        if($letter_found == true)
        {
            $letter_row = mysql_fetch_array($result);
        }
        $letter_menu = strNextLetter($letter_menu);
    }
echo "</p>";

if($num_members > 0)
{
    echo "<table class=\"tableList\" cellpadding=\"2\" cellspacing=\"0\">
        <tr>
            <th class=\"tableHeader\" align=\"right\">Nr.</th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" 
				src=\"$g_root_path/adm_program/images/user.png\" alt=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" 
				title=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" border=\"0\"></th>
            <th class=\"tableHeader\" align=\"left\">&nbsp;Name</th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" 
				src=\"$g_root_path/adm_program/images/email.png\" alt=\"E-Mail\" title=\"E-Mail\"></th>
            <th class=\"tableHeader\" align=\"center\"><img style=\"cursor: help;\" 
				src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\"></th>
            <th class=\"tableHeader\" align=\"left\">&nbsp;Benutzer</th>
            <th class=\"tableHeader\" align=\"center\">&nbsp;Aktualisiert am</th>
            <th class=\"tableHeader\" align=\"center\">Bearbeiten</th>
        </tr>";
        $i = 0;

        // jetzt erst einmal zu dem ersten relevanten Datensatz springen
        if(!mysql_data_seek($result_mgl, $req_start))
        {
            $g_message->show("invalid");
        }

        for($i = 0; $i < $members_per_page && $i + $req_start < $num_members; $i++)
        {
            if($row = mysql_fetch_array($result_mgl))
            {
                echo "
                <tr class=\"listMouseOut\" onmouseover=\"this.className='listMouseOver'\" onmouseout=\"this.className='listMouseOut'\">
                    <td align=\"right\">". ($req_start + $i + 1). "&nbsp;</td>
                    <td align=\"center\">";
                        if($row['member'] > 0)
                        {
                            echo "<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "\"><img
                                src=\"$g_root_path/adm_program/images/user.png\" alt=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\"
                                title=\"Mitglied bei ". $g_current_organization->getValue("org_longname"). "\" border=\"0\"></a>";
                        }
                        else
                        {
                            echo "&nbsp;";
                        }
                    echo "</td>
                    <td align=\"left\">&nbsp;<a href=\"$g_root_path/adm_program/modules/profile/profile.php?user_id=". $row['usr_id']. "\">". $row['last_name']. ",&nbsp;". $row['first_name']. "</a></td>
                    <td align=\"center\">";
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
                            echo "<a href=\"$mail_link\"><img src=\"$g_root_path/adm_program/images/email.png\"
                                alt=\"E-Mail an ". $row['email']. " schreiben\" title=\"E-Mail an ". $row['email']. " schreiben\" border=\"0\"></a>";
                        }
                    echo "</td>
                    <td align=\"center\">";
                        if(strlen($row['homepage']) > 0)
                        {
                            echo "<a href=\"". $row['homepage']. "\" target=\"_blank\"><img
                                src=\"$g_root_path/adm_program/images/globe.png\" alt=\"Homepage\" title=\"Homepage\" border=\"0\"></a>";
                        }
                    echo "</td>
                    <td align=\"left\">&nbsp;". $row['usr_login_name']. "</td>
                    <td align=\"center\">&nbsp;". mysqldatetime("d.m.y h:i" , $row['usr_last_change']). "</td>
                    <td align=\"center\">";
                        // pruefen, ob der User noch in anderen Organisationen aktiv ist
                        $sql    = "SELECT *
                                     FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
                                    WHERE rol_valid   = 1
                                      AND rol_cat_id  = cat_id
                                      AND cat_org_id <> ". $g_current_organization->getValue("org_id"). "
                                      AND mem_rol_id  = rol_id
                                      AND mem_valid   = 1
                                      AND mem_usr_id  = ". $row['usr_id'];
                        $result      = mysql_query($sql, $g_adm_con);
                        db_error($result,__FILE__,__LINE__);
                        $b_other_orga = false;

                        if(mysql_num_rows($result) > 0)
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
                            echo "<a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=". $row['usr_id']. "&mode=5\"><img
                                src=\"$g_root_path/adm_program/images/key.png\" border=\"0\" alt=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"
                                title=\"E-Mail mit Benutzernamen und neuem Passwort zuschicken\"></a>&nbsp;";
                        }
                        else
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">&nbsp;";
                        }

                        // Link um User zu editieren
                        // es duerfen keine Nicht-Mitglieder editiert werden, die Mitglied in einer anderen Orga sind
                        if($row['member'] > 0 || $b_other_orga == false)
                        {
                            echo "<a href=\"$g_root_path/adm_program/modules/profile/profile_new.php?user_id=". $row['usr_id']. "\"><img
                                src=\"$g_root_path/adm_program/images/edit.png\" border=\"0\" alt=\"Benutzerdaten bearbeiten\" title=\"Benutzerdaten bearbeiten\"></a>&nbsp;";
                        }
                        else
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">";
                        }


                        // wenn der User nicht mehr Mitglied der aktuellen Orga, aber noch Mitglied einer anderen Orga ist,
                        // dann darf er nicht aus der DB geloescht werden
                        if(($b_other_orga == false || $row['member'] > 0)
                        && $row['usr_id'] != $g_current_user->getValue("usr_id"))
                        {
                            echo "<a href=\"$g_root_path/adm_program/administration/members/members_function.php?user_id=". $row['usr_id']. "&mode=6\"><img
                                src=\"$g_root_path/adm_program/images/cross.png\" border=\"0\" alt=\"Benutzer entfernen\" title=\"Benutzer entfernen\"></a>";
                        }
                        else
                        {
                            echo "<img src=\"$g_root_path/adm_program/images/dummy.gif\" border=\"0\" alt=\"dummy\" style=\"width: 16px; height: 16px;\">";
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
        
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>