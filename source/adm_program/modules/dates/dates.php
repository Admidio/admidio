<?php
/******************************************************************************
 * Termine auflisten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * mode: actual         - (Default) Alle aktuellen und zukuenftige Termine
 *                         anzeigen
 *       old            - Alle bereits erledigten
 * start                - Angabe, ab welchem Datensatz Termine angezeigt werden
 *                        sollen
 * headline             - Ueberschrift, die ueber den Terminen steht
 *                      (Default) Termine
 * category             - Angabe der Kategorie damit auch nur eine angezeigt
 *                        werden kann.
 * id                   - Nur einen einzigen Termin anzeigen lassen.
 * date                 - Alle Termine zu einem Datum werden aufgelistet
 *                        Uebergabeformat: YYYYMMDD
 * calendar-selection   - 1: Es wird die Box angezeigt
 *                        0: Es wird keine Box angezeigt
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/classes/ubb_parser.php");
require("../../system/classes/table_date.php");

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
$sql_datum    = "";

// Uebergabevariablen pruefen

//Kontrolle ob nur ein Kalender angezeigt werden soll
if(isset($_GET['category']) == false)
{
    $_GET['category'] = Null;
}
$category = $_GET['category'];


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

if(array_key_exists("date", $_GET))
{
    if(is_numeric($_GET["date"]) == false)
    {
        $g_message->show("invalid");
    }
    else
    {
        $sql_datum = substr($_GET["date"],0,4). "-". substr($_GET["date"],4,2). "-". substr($_GET["date"],6,2);
    }
}

// Prüfen ob Box angezeigt werden soll und setzten des Flags
if ($g_preferences['dates_show_calendar_select'] == 1) {
   $dates_show_calendar_select = 1;
} else {
   $dates_show_calendar_select = 0;
}
if(isset($_GET['calendar-selection']) != false)
{
    if($_GET['category-selection'] == 1)
    {
        $dates_show_calendar_select = 1;
    }
    else if ($_GET['calendar-selection'] == 0)
    {
        $dates_show_calendar_select = 0;
    }
}

if($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode
    $bbcode = new ubbParser();
}

unset($_SESSION['dates_request']);
$act_date = date("Y-m-d", time());
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title'] = $req_headline;
$g_layout['header'] = $g_js_vars. '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/script.aculo.us/prototype.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects"></script>';

if($g_preferences['enable_rss'] == 1 && $g_preferences['enable_dates_module'] == 1)
{
    $g_layout['header'] .=  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Termine\"
        href=\"$g_root_path/adm_program/modules/dates/rss_dates.php\" />";
};

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo" <script type=\"text/javascript\"><!--
        function showCategory()
        {
          if (document.getElementById('category').selectedIndex != 0) {
            var category = document.getElementById('category').value;
            self.location.href = 'dates.php?category='+category;
          } else { self.location.href = 'dates.php';}
        }
    //--></script>";
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
$organizations = "";
$hidden = "";
$conditions = "";
$arr_ref_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $key => $value)
{
	$organizations = $organizations. $key. ", ";
}
$organizations = $organizations. $g_current_organization->getValue("org_id");

if ($g_valid_login == false)
{
	// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
	$hidden = " AND cat_hidden = 0 ";
}

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...(Aber nur, wenn der User die Berechtigung hat
if($req_id > 0)
{
    $conditions = " AND dat_id = $req_id ".$hidden;
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
   if (strlen($_GET['category']) != NULL)
   {
	  // alle Termine zu einer Kategorie anzeigen
	  $conditions = " AND cat_name   = '". $_GET['category']. "' ";
   }
    // Termine an einem Tag suchen
    if(strlen($sql_datum) > 0)
    {
        $conditions = $conditions."   AND DATE_FORMAT(dat_begin, '%Y-%m-%d')       <= '$sql_datum'
                          AND DATE_FORMAT(dat_end, '%Y-%m-%d %H:%i:%s') > '$sql_datum 00:00:00'
                          $hidden
                        ORDER BY dat_begin ASC ";
    }
    //fuer alte Termine...
    elseif($req_mode == "old")
    {
        $conditions = $conditions."   AND DATE_FORMAT(dat_begin, '%Y-%m-%d') < '$act_date'
                          AND DATE_FORMAT(dat_end, '%Y-%m-%d')   < '$act_date'
                          $hidden
                        ORDER BY dat_begin DESC ";
    }
    //... ansonsten fuer kommende Termine
    else
    {
        $conditions = $conditions."   AND (  DATE_FORMAT(dat_begin, '%Y-%m-%d')       >= '$act_date'
                              OR DATE_FORMAT(dat_end, '%Y-%m-%d %H:%i:%s') > '$act_date 00:00:00' )
                           $hidden
                        ORDER BY dat_begin ASC ";
    }

}

$sql = "SELECT * FROM ". TBL_DATES. ", ". TBL_CATEGORIES. "
         WHERE dat_cat_id = cat_id
           AND (  cat_org_id = ". $g_current_organization->getValue("org_id"). "
               OR (   dat_global   = 1
                  AND cat_org_id IN ($organizations) ))
               $conditions
         LIMIT $req_start, 10";
$dates_result = $g_db->query($sql);

if($req_id == 0)
{
    // Gucken wieviele Datensaetze die Abfrage ermittelt kann...
    $sql = "SELECT COUNT(1) as count
              FROM ". TBL_DATES. ", ". TBL_CATEGORIES. "
             WHERE dat_cat_id = cat_id
               AND (  cat_org_id = ". $g_current_organization->getValue("org_id"). "
                   OR (   dat_global   = 1
                      AND cat_org_id IN ($organizations) ))
                   $conditions ";
    $result = $g_db->query($sql);
    $row    = $g_db->fetch_array($result);
    $num_dates = $row['count'];
}
else
{
    $num_dates = 1;
}

// Neue Termine anlegen
if($g_current_user->editDates())
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline$req_headline\"><img
                src=\"". THEME_PATH. "/icons/add.png\" alt=\"Termin anlegen\" title=\"Termin anlegen\"/></a>
                <a href=\"$g_root_path/adm_program/modules/dates/dates_new.php?headline=$req_headline\">Anlegen</a>
            </span>
        </li>
        <li>
            <span class=\"iconTextLink\">
               <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=DAT&amp;title=Kalender\"><img
                  src=\"". THEME_PATH. "/icons/application_double.png\" alt=\"Kalender pflegen\" title=\"Kalender pflegen\"/></a>
               <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=DAT&amp;title=Kalender\">Kalender pflegen</a>
            </span>
        </li>
    </ul>";
}

if($num_dates > 10)
{
    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}

//Abfrage ob die Box angezeigt werden soll, falls nicht nur ein Termin gewählt wurde
if(($dates_show_calendar_select == 1) && ($req_id == 0))
   {
   // Combobox mit allen Kategorien anzeigen
   $sql = "SELECT DISTINCT cat_name
            FROM ". TBL_CATEGORIES. "
            WHERE cat_org_id = ". $g_current_organization->getValue("org_id"). "
            AND cat_type   = 'DAT'";
   if($g_valid_login == false)
   {
     $sql .= " AND cat_hidden = 0 ";
   }
   $sql .= " ORDER BY cat_sequence ASC ";
   $result = $g_db->query($sql);

   if($g_db->num_rows($result) > 0)
   {
       echo '<p>Kalender wählen:&nbsp;&nbsp;
      <select size="1" id="category" onchange="showCategory()">
         <option value="Alle" ';
         if(strlen($_GET['category']) == 0)
         {
             echo ' selected="selected" ';
         }
         echo '>Alle</option>';

         while($row = $g_db->fetch_object($result))
         {
             echo '<option value="'. urlencode($row->cat_name). '"';
             if($_GET['category'] == $row->cat_name)
             {
                 echo ' selected="selected" ';
             }
             echo '>'.$row->cat_name.'</option>';
         }
     echo '</select></p>';
      }
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
        echo "<p>Es sind keine Einträge vorhanden.</p>";
    }
}
else
{
    $date = new TableDate($g_db);
    // Termine auflisten
    while($row = $g_db->fetch_array($dates_result))
    {
        $date->clear();
        $date->setArray($row);
        echo "
        <div class=\"boxLayout\" id=\"dat_".$date->getValue("dat_id")."\">
            <div class=\"boxHead\">
                <div class=\"boxHeadLeft\">
                    <img src=\"". THEME_PATH. "/icons/dates.png\" alt=\"". $date->getValue("dat_headline"). "\" />
                    ". mysqldatetime("d.m.y", $date->getValue("dat_begin"));
                    if(mysqldatetime("d.m.y", $date->getValue("dat_begin")) != mysqldatetime("d.m.y", $date->getValue("dat_end")))
                    {
                        echo ' - '. mysqldatetime("d.m.y", $date->getValue("dat_end"));
                    }
                    echo ' ' . $date->getValue("dat_headline"). "
                </div>
                <div class=\"boxHeadRight\">
                    <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/dates/dates_function.php?dat_id=". $date->getValue("dat_id"). "&amp;mode=4\"><img
                        src=\"". THEME_PATH. "/icons/database_out.png\" alt=\"Exportieren (iCal)\" title=\"Exportieren (iCal)\" /></a>";

                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if ($g_current_user->editDates())
                    {
                        if($date->editRight() == true)
                        {
                            echo "
                            <a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/dates/dates_new.php?dat_id=". $date->getValue("dat_id"). "&amp;headline=$req_headline\"><img
                                src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>";
                        }

                        // Loeschen darf man nur Termine der eigenen Gliedgemeinschaft
                        if($date->getValue("cat_org_id") == $g_current_organization->getValue("org_id"))
                        {
                            echo '
                            <a class="iconLink" href="javascript:deleteObject(\'dat\', \'dat_'.$date->getValue("dat_id").'\','.$date->getValue("dat_id").',\''.$date->getValue("dat_headline").'\')"><img
                                src="'. THEME_PATH. '/icons/delete.png" alt="Löschen" title="Löschen" /></a>';
                        }
                    }
                echo"</div>
            </div>

            <div class=\"boxBody\">";
                // Uhrzeit und Ort anzeigen, falls vorhanden
                if ($date->getValue("dat_all_day") == 0 || strlen($date->getValue("dat_location")) > 0)
                {
                    echo '<div class="date_info_block">';
                        $margin_left_location = "0";
                        if ($date->getValue("dat_all_day") == 0)
                        {
                            echo '<div style="float: left;">Beginn:<br />Ende:</div>
                            <div style="float: left;">
                                <strong>'. mysqldatetime("h:i", $date->getValue("dat_begin")). '</strong> Uhr<br />
                                <strong>'. mysqldatetime("h:i", $date->getValue("dat_end")). '</strong> Uhr
                            </div>';
                            $margin_left_location = "40";
                        }

                        if ($date->getValue("dat_location") != "")
                        {
                            echo '<div style="float: left; padding-left: '. $margin_left_location. 'px;">Kalender:&nbsp;<br />Treffpunkt:</div>
                            <strong>'. $date->getValue("cat_name"). '</strong><br/><div>';
                                // Karte- und Routenlink anzeigen, sobald 2 Woerter vorhanden sind,
                                // die jeweils laenger als 3 Zeichen sind
                                $map_info_count = 0;
                                foreach(split("[,; ]", $date->getValue("dat_location")) as $key => $value)
                                {
                                    if(strlen($value) > 3)
                                    {
                                        $map_info_count++;
                                    }
                                }

                                if($g_preferences['dates_show_map_link']
                                && $map_info_count > 1)
                                {
                                    echo '<a href="http://maps.google.com/?q='. urlencode($date->getValue("dat_location")). '" target="_blank" title="Auf Karte zeigen"/><strong>'.$date->getValue("dat_location").'</strong></a>';

                                    // bei gueltigem Login und genuegend Adressdaten auch noch Route anbieten
                                    if($g_valid_login && strlen($g_current_user->getValue("Adresse")) > 0
                                    && (  strlen($g_current_user->getValue("PLZ"))  > 0 || strlen($g_current_user->getValue("Ort"))  > 0 ))
                                    {
                                        $route_url = 'http://maps.google.com/?f=d&amp;saddr='. urlencode($g_current_user->getValue("Adresse"));
                                        if(strlen($g_current_user->getValue("PLZ"))  > 0)
                                        {
                                            $route_url .= ",%20". urlencode($g_current_user->getValue("PLZ"));
                                        }
                                        if(strlen($g_current_user->getValue("Ort"))  > 0)
                                        {
                                            $route_url .= ",%20". urlencode($g_current_user->getValue("Ort"));
                                        }
                                        if(strlen($g_current_user->getValue("Land"))  > 0)
                                        {
                                            $route_url .= ",%20". urlencode($g_current_user->getValue("Land"));
                                        }

                                        $route_url .= '&amp;daddr='. urlencode($date->getValue("dat_location"));
                                        echo '<spam class="iconTextLink">&nbsp;&nbsp;<a href="'. $route_url. '" target="_blank"><img
                                        src="'. THEME_PATH. '/icons/map.png" alt="Route anzeigen" title="Route anzeigen"/></a></spam>';
                                    }
                                } else {
                                    echo '<strong>'. $date->getValue("dat_location"). '</strong>';
                                }
                            echo '</div>';
                           //echo '<div style="float: left;"><strong>'. $date->getValue("cat_name"). '&nbsp;</strong></div>';
                        } else {
                           echo '<div style="float: left; padding-left: '. $margin_left_location. 'px;">Kalender:</div>
                            <div style="float: left;"><strong>'. $date->getValue("cat_name"). '&nbsp;</strong></div>';
                        }
                    echo '</div>';
                } else {
                  echo '<div class="date_info_block">';
                  $margin_left_location = "0";
                  echo '<div style="float: left;">Kalender:</div>
                        <div style="float: left;">
                           <strong>'.$date->getValue("cat_name"). '</strong>
                        </div>';
                  echo '</div>';
                }
                echo '<div class="date_description" style="clear: left;">';
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
                    $user_create = new User($g_db, $date->getValue("dat_usr_id_create"));
                    echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
                    " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_timestamp_create"));

                    if($date->getValue("dat_usr_id_change") > 0)
                    {
                        $user_change = new User($g_db, $date->getValue("dat_usr_id_change"));
                        echo "<br />Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
                        " am ". mysqldatetime("d.m.y h:i", $date->getValue("dat_timestamp_change"));
                    }
                echo "</div>
            </div>
        </div>";
    }  // Ende While-Schleife
}

if($num_dates > 10)
{
    // Navigation mit Vor- und Zurueck-Buttons
    // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
    $base_url = "$g_root_path/adm_program/modules/dates/dates.php?mode=$req_mode&headline=$req_headline";
    echo generatePagination($base_url, $num_dates, 10, $req_start, TRUE);
}

require(THEME_SERVER_PATH. "/overall_footer.php");

?>