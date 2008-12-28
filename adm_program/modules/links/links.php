<?php
/******************************************************************************
 * Links auflisten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * start     - Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * category  - Angabe der Kategorie damit auch nur eine angezeigt werden kann.
 * headline  - Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * id        - Nur einen einzigen Link anzeigen lassen.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/classes/ubb_parser.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
}
//Kontrolle ob nur Kategorien angezeigt werden
if(isset($_GET['category']) == false)
{
    $_GET['category'] = "";
}

// Uebergabevariablen pruefen

if (array_key_exists("start", $_GET))
{
    if (is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["start"] = 0;
}

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Weblinks";
}

if ($g_preferences['enable_bbcode'] == 1)
{
    // Klasse fuer BBCode initialisieren
    $bbcode = new ubbParser();
}

// Navigation initialisieren - Modul faengt hier an.
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['links_request']);

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];
$g_layout['header'] = $g_js_vars. '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/jquery/jquery.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] = $g_layout['header']. "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Links\"
        href=\"$g_root_path/adm_program/modules/links/rss_links.php\" />";
};

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $_GET["headline"]. '</h1>
<div id="links_overview">';

// SQL-Statement zusammenbasteln

$condition = "";
$hidden    = "";

if ($_GET['id'] > 0)
{
	// falls eine id fuer einen bestimmten Link uebergeben worden ist...
	$condition = " AND lnk_id = ". $_GET['id'];
}
else if (strlen($_GET['category']) > 0) 
{
	// alle Links zu einer Kategorie anzeigen
	$condition = " AND cat_name   = '". $_GET['category']. "' ";
} 

if ($g_valid_login == false)
{
	// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
	$hidden = " AND cat_hidden = 0 ";
}

// Gucken wieviele Linkdatensaetze insgesamt fuer die Gruppierung vorliegen...
// Das wird naemlich noch fuer die Seitenanzeige benoetigt...
// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
$sql = "SELECT COUNT(*) FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
		WHERE lnk_cat_id = cat_id
		AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
		AND cat_type = 'LNK'
			$condition
		    $hidden
		ORDER BY cat_sequence, lnk_name DESC";
$result = $g_db->query($sql);
$row = $g_db->fetch_array($result);
$numLinks = $row[0];

// Anzahl Ankuendigungen pro Seite
if($g_preferences['weblinks_per_page'] > 0)
{
    $weblinks_per_page = $g_preferences['weblinks_per_page'];
}
else
{
    $weblinks_per_page = $numLinks;
}

// Links entsprechend der Einschraenkung suchen
$sql1 = "SELECT * FROM ". TBL_LINKS. ", ". TBL_CATEGORIES ."
  		  WHERE lnk_cat_id = cat_id
		    AND cat_org_id = ". $g_current_organization->getValue("org_id"). "
		    AND cat_type = 'LNK'
		        $condition
  		        $hidden
		  ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC
		  LIMIT ". $_GET['start']. ", ". $weblinks_per_page;
$links_result = $g_db->query($sql1);

// Icon-Links und Navigation anzeigen

if ($_GET['id'] == 0 && ($g_current_user->editWeblinksRight() || $g_preferences['enable_rss'] == true))
{
    // Neuen Link anlegen
    if ($g_current_user->editWeblinksRight())
    {
        echo "
        <ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/links/links_new.php?headline=". $_GET["headline"]. "\"><img
                    src=\"". THEME_PATH. "/icons/add.png\" alt=\"Neu anlegen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/links/links_new.php?headline=". $_GET["headline"]. "\">Neu anlegen</a>
                </span>
            </li>
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=LNK\"><img
                    src=\"". THEME_PATH. "/icons/application_double.png\" alt=\"Kategorien pflegen\" /></a>
                    <a href=\"$g_root_path/adm_program/administration/roles/categories.php?type=LNK\">Kategorien pflegen</a>
                </span>
            </li>
        </ul>";
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
    echo generatePagination($baseUrl, $numLinks, $weblinks_per_page, $_GET["start"], TRUE);
}

if ($g_db->num_rows($links_result) == 0)
{
    // Keine Links gefunden
    if ($_GET['id'] > 0)
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

    // Zaehlervariable fuer Anzahl von fetch_object
    $j = 0;
    // Zaehlervariable fuer Anzahl der Links in einer Kategorie
    $i = 0;
    // Vorherige Kategorie-ID.
    $previous_cat_id = -1;
    // Kommt jetzt eine neue Kategorie?
    $new_category = true;

    // Solange die vorherige Kategorie-ID sich nicht veraendert...
    // Sonst in die neue Kategorie springen
    while ($row = $g_db->fetch_object($links_result))
    {

        if ($row->lnk_cat_id != $previous_cat_id)
        {
			$i = 0;
			$new_category = true;
			if ($j>0)
			{
				echo "</div></div><br />";
			}
			echo "<div class=\"formLayout\">
				<div class=\"formHead\">".$row->cat_name."</div>
				<div class=\"formBody\" style=\"overflow: hidden;\">";
        }
        
        echo "<div id=\"lnk_".$row->lnk_id."\">";
    		if($i > 0)
    		{
    			echo "<hr />";
    		}
			
			if($g_preferences['enable_weblinks_redirect'] == 1)
			{
				echo "
	    		<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/links_redirect.php?lnk_id=$row->lnk_id\" target=\"_blank\"><img src=\"". THEME_PATH. "/icons/weblinks.png\"
	    			alt=\"Gehe zu $row->lnk_name\" title=\"Gehe zu $row->lnk_name\" /></a>
	    		<a href=\"$g_root_path/adm_program/modules/links/links_redirect.php?lnk_id=$row->lnk_id\" target=\"_blank\">$row->lnk_name</a>

	    		<div style=\"margin-top: 10px;\">";
			}
			else
			{			
	    		echo "
	    		<a class=\"iconLink\" href=\"$row->lnk_url\" target=\"_blank\"><img src=\"". THEME_PATH. "/icons/weblinks.png\"
	    			alt=\"Gehe zu $row->lnk_name\" title=\"Gehe zu $row->lnk_name\" /></a>
	    		<a href=\"$row->lnk_url\" target=\"_blank\">$row->lnk_name</a>

	    		<div style=\"margin-top: 10px;\">";
			}

    			// wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
    			if ($g_preferences['enable_bbcode'] == 1)
    			{
    				echo $bbcode->parse($row->lnk_description);
    			}
    			else
    			{
    				echo nl2br($row->lnk_description);
    			}
    		echo "</div>";

    		if($g_current_user->editWeblinksRight())
    		{
    			echo "
    			<div class=\"editInformation\">";
    				// aendern & loeschen duerfen nur User mit den gesetzten Rechten
    				if ($g_current_user->editWeblinksRight())
    				{
    					echo "
    					<a class=\"iconLink\" href=\"$g_root_path/adm_program/modules/links/links_new.php?lnk_id=$row->lnk_id&amp;headline=". $_GET['headline']. "\"><img
    						src=\"". THEME_PATH. "/icons/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>
    					<a class=\"iconLink\" href=\"javascript:deleteObject('lnk', 'lnk_".$row->lnk_id."',".$row->lnk_id.",'".$row->lnk_name."')\"><img
    						src=\"". THEME_PATH. "/icons/delete.png\" alt=\"Löschen\" title=\"Löschen\" /></a>";
    				}
    				$user_create = new User($g_db, $row->lnk_usr_id_create);
    				echo "Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname").
    				" am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp_create);

    				if($row->lnk_usr_id_change > 0)
    				{
    					$user_change = new User($g_db, $row->lnk_usr_id_change);
    					echo "<br />Zuletzt bearbeitet von ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname").
    					" am ". mysqldatetime("d.m.y h:i", $row->lnk_timestamp_change);
    				}
    			echo "</div>";
    		}
        echo "</div>";

		$j++;
        $i++;

        // Jetzt wird die jtzige die vorherige Kategorie
        $previous_cat_id = $row->lnk_cat_id;

        $new_category = false;
    }  // Ende While-Schleife

    // Es wurde noch gar nichts geschrieben ODER ein einzelner Link ist versteckt
    if ($numLinks == 0)
    {
        echo "<p>Es sind keine Einträge vorhanden.</p>";
    }

    echo "</div></div>";
} // Ende Wenn mehr als 0 Datensaetze

echo '</div>';

// Navigation mit Vor- und Zurueck-Buttons
$baseUrl = "$g_root_path/adm_program/modules/links/links.php?headline=". $_GET["headline"];
echo generatePagination($baseUrl, $numLinks, $weblinks_per_page, $_GET["start"], TRUE);

require(THEME_SERVER_PATH. "/overall_footer.php");

?>
