<?php
/******************************************************************************
 * Links auflisten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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

require_once('../../system/common.php');
require_once('../../system/classes/table_weblink.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
elseif($g_preferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}
//Kontrolle ob nur Kategorien angezeigt werden
if(isset($_GET['category']) == false)
{
    $_GET['category'] = '';
}

// Uebergabevariablen pruefen

if (array_key_exists('start', $_GET))
{
    if (is_numeric($_GET['start']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['start'] = 0;
}

if (array_key_exists('id', $_GET))
{
    if (is_numeric($_GET['id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['id'] = 0;
}

if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = $g_l10n->get('LNK_WEBLINKS');
}

// Navigation initialisieren - Modul faengt hier an.
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

unset($_SESSION['links_request']);

// Html-Kopf ausgeben
$g_layout['title']  = $_GET['headline'];
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] = $g_layout['header']. '<link type="application/rss+xml" rel="alternate" title="'. $g_current_organization->getValue('org_longname'). ' - '.$g_layout['title'].'"
        href="'. $g_root_path. '/adm_program/modules/links/rss_links.php" />';
};

require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>
<div id="links_overview">';

// SQL-Statement zusammenbasteln

$condition = '';
$hidden    = '';

if ($_GET['id'] > 0)
{
    // falls eine id fuer einen bestimmten Link uebergeben worden ist...
    $condition = ' AND lnk_id = '. $_GET['id'];
}
else if (strlen($_GET['category']) > 0)
{
    // alle Links zu einer Kategorie anzeigen
    $condition = ' AND cat_name   = "'. $_GET['category']. '"';
}

if ($g_valid_login == false)
{
    // Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
    $hidden = ' AND cat_hidden = 0 ';
}

// Gucken wieviele Linkdatensaetze insgesamt fuer die Gruppierung vorliegen...
// Das wird naemlich noch fuer die Seitenanzeige benoetigt...
// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
$sql = 'SELECT COUNT(*) FROM '. TBL_LINKS. ', '. TBL_CATEGORIES .'
        WHERE lnk_cat_id = cat_id
        AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
        AND cat_type = "LNK"
        '.$condition.'
        '.$hidden.'
        ORDER BY cat_sequence, lnk_name DESC';
$cat_result = $g_db->query($sql);
$row = $g_db->fetch_array($cat_result);
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
$sql = 'SELECT cat.*, lnk.*
          FROM '. TBL_CATEGORIES .' cat, '. TBL_LINKS. ' lnk
         WHERE lnk_cat_id = cat_id
           AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
           AND cat_type = "LNK"
           '.$condition.'
           '.$hidden.'
         ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC
         LIMIT '. $_GET["start"]. ', '. $weblinks_per_page;
$links_result = $g_db->query($sql);

// Icon-Links und Navigation anzeigen

if ($_GET['id'] == 0 && ($g_current_user->editWeblinksRight() || $g_preferences['enable_rss'] == true))
{
    if ($g_current_user->editWeblinksRight())
    {
        // Neuen Link anlegen
        echo '
        <ul class="iconTextLinkList">
            <li>
                <span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/modules/links/links_new.php?headline='. $_GET['headline']. '">
                        <img src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('LNK_CREATE_LINK').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/modules/links/links_new.php?headline='. $_GET['headline']. '">'.$g_l10n->get('LNK_CREATE_LINK').'</a>
                </span>
            </li>';
       //Kategorie pflegen
        echo'
            <li>
                <span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=LNK">
                        <img src="'. THEME_PATH. '/icons/application_double.png" alt="'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=LNK">'.$g_l10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
                </span>
            </li>
        </ul>';
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $_GET['headline'];
    echo generatePagination($baseUrl, $numLinks, $weblinks_per_page, $_GET['start'], TRUE);
}

if ($g_db->num_rows($links_result) == 0)
{
    // Keine Links gefunden
    if ($_GET['id'] > 0)
    {
        echo '<p>'.$g_l10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$g_l10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $j = 0;         // Zaehlervariable fuer Anzahl von fetch_object
    $i = 0;         // Zaehlervariable fuer Anzahl der Links in einer Kategorie
    $previous_cat_id = -1;  // Vorherige Kategorie-ID.
    $new_category = true;   // Kommt jetzt eine neue Kategorie?

    $weblink = new TableWeblink($g_db);

    // Solange die vorherige Kategorie-ID sich nicht veraendert...
    // Sonst in die neue Kategorie springen
    while ($row = $g_db->fetch_array($links_result))
    {
        // Link-Objekt initialisieren und neuen DS uebergeben
        $weblink->clear();
        $weblink->setArray($row);

        if ($weblink->getValue('lnk_cat_id') != $previous_cat_id)
        {
            $i = 0;
            $new_category = true;
            if ($j>0)
            {
                echo '</div></div><br />';
            }
            echo '<div class="formLayout">
                <div class="formHead">'.$row['cat_name'].'</div>
                <div class="formBody" style="overflow: hidden;">';
        }

        echo '<div id="lnk_'.$weblink->getValue('lnk_id').'">';
            if($i > 0)
            {
                echo '<hr />';
            }

		// Ausgabe des Links
		echo '
			<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $g_preferences['weblinks_target']. '"><img src="'. THEME_PATH. '/icons/weblinks.png"
				alt="'.$g_l10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" title="'.$g_l10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" /></a>
			<a href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $g_preferences['weblinks_target']. '">'.$weblink->getValue('lnk_name').'</a>';
		// aendern & loeschen duerfen nur User mit den gesetzten Rechten
		if ($g_current_user->editWeblinksRight())
		{
			echo '
			<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/links/links_new.php?lnk_id='.$weblink->getValue('lnk_id').'&amp;headline='. $_GET['headline']. '"><img
				src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>
			<a class="iconLink" href="javascript:deleteObject(\'lnk\', \'lnk_'.$weblink->getValue('lnk_id').'\', \''.$weblink->getValue('lnk_id').'\',\''.$weblink->getValue('lnk_name').'\')">
			   <img	src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
		}

		// Beschreibung ausgeben, falls vorhanden
		if(strlen($weblink->getValue('lnk_description'))>0)
		{
			echo '<div style="margin-top: 10px;">'.$weblink->getDescription('HTML').'</div>';
		}
        echo '</div>';
		
		echo '<div class="smallFontSize" style="text-align: right">'.$g_l10n->get('LNK_COUNTER'). ': '.$weblink->getValue('lnk_counter').'</div>';

        $j++;
        $i++;

        // Jetzt wird die jtzige die vorherige Kategorie
        $previous_cat_id = $weblink->getValue('lnk_cat_id');

        $new_category = false;
    }  // Ende While-Schleife

    // Es wurde noch gar nichts geschrieben ODER ein einzelner Link ist versteckt
    if ($numLinks == 0)
    {
        echo '<p>'.$g_l10n->get('SYS_NO_ENTRIES').'</p>';
    }

    echo '</div></div>';
} // Ende Wenn mehr als 0 Datensaetze

echo '</div>';

// Navigation mit Vor- und Zurueck-Buttons
$baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $_GET['headline'];
echo generatePagination($baseUrl, $numLinks, $weblinks_per_page, $_GET['start'], TRUE);

require(THEME_SERVER_PATH. '/overall_footer.php');

?>
