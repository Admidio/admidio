<?php
/******************************************************************************
 * Show a list of all weblinks
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * start     : Angabe, ab welchem Datensatz Links angezeigt werden sollen
 * headline  : Ueberschrift, die ueber den Links steht
 *             (Default) Links
 * cat_id    : show only links of this category id, if id is not set than show all links
 * id        : Nur einen einzigen Link anzeigen lassen.
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_category.php');
require_once('../../system/classes/table_weblink.php');
unset($_SESSION['links_request']);

// Initialize and check the parameters
$getStart    = admFuncVariableIsValid($_GET, 'start', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('LNK_WEBLINKS'));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getLinkId   = admFuncVariableIsValid($_GET, 'id', 'numeric', 0);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require_once('../../system/login_valid.php');
}

// Navigation initialisieren - Modul faengt hier an.
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$gLayout['title']  = $getHeadline;
if($getCatId > 0)
{
    $category = new TableCategory($gDb, $getCatId);
    $gLayout['title'] .= ' - '.$category->getValue('cat_name');
}

$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', scrolling:false, onComplete:function(){$("#admButtonNo").focus();}});

            $("#admCategory").change(function () {
                var categoryId = document.getElementById("admCategory").value;
                self.location.href = "links.php?cat_id=" + categoryId + "&headline='.$getHeadline.'";
            });
        }); 
    //--></script>';

if($gPreferences['enable_rss'] == 1)
{
    $gLayout['header'] = $gLayout['header']. '<link rel="alternate" type="application/rss+xml" title="'.$gL10n->get('SYS_RSS_FEED_FOR_VAR', $gCurrentOrganization->getValue('org_longname'). ' - '.$getHeadline).'"
        href="'. $g_root_path. '/adm_program/modules/links/rss_links.php?headline='.$getHeadline.'" />';
};

require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $gLayout['title']. '</h1>
<div id="links_overview">';

// SQL-Statement zusammenbasteln

$sqlCondidtions = '';

if($getLinkId > 0)
{
    // falls eine id fuer einen bestimmten Link uebergeben worden ist...
    $sqlCondidtions .= ' AND lnk_id = '. $getLinkId;
}
else if($getCatId > 0)
{
    // alle Links zu einer Kategorie anzeigen
    $sqlCondidtions .= ' AND cat_id = '.$getCatId;
}

if($gValidLogin == false)
{
    // if user isn't logged in, then don't show hidden categories
    $sqlCondidtions .= ' AND cat_hidden = 0 ';
}

// Gucken wieviele Linkdatensaetze insgesamt fuer die Gruppierung vorliegen...
// Das wird naemlich noch fuer die Seitenanzeige benoetigt...
// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
$sql = 'SELECT COUNT(*) FROM '. TBL_LINKS. ', '. TBL_CATEGORIES .'
        WHERE lnk_cat_id = cat_id
        AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
        AND cat_type = \'LNK\'
        '.$sqlCondidtions;
$cat_result = $gDb->query($sql);
$row = $gDb->fetch_array($cat_result);
$numLinks = $row[0];

// Anzahl Ankuendigungen pro Seite
if($gPreferences['weblinks_per_page'] > 0)
{
    $weblinks_per_page = $gPreferences['weblinks_per_page'];
}
else
{
    $weblinks_per_page = $numLinks;
}

// Links entsprechend der Einschraenkung suchen
$sql = 'SELECT cat.*, lnk.*
          FROM '. TBL_CATEGORIES .' cat, '. TBL_LINKS. ' lnk
         WHERE lnk_cat_id = cat_id
           AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
           AND cat_type = \'LNK\'
           '.$sqlCondidtions.'
         ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC
         LIMIT '.$weblinks_per_page.' OFFSET '.$getStart;
$links_result = $gDb->query($sql);

// show icon links and navigation

if($getLinkId == 0)
{
    $topNavigation = '';

    if ($gCurrentUser->editWeblinksRight())
    {
        // show link to create new weblink
        $topNavigation .= '
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/links/links_new.php?headline='. $getHeadline. '">
                    <img src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('LNK_CREATE_LINK').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/links/links_new.php?headline='. $getHeadline. '">'.$gL10n->get('LNK_CREATE_LINK').'</a>
            </span>
        </li>';
    }

    // create select box with all categories that have links
    $calendarSelectBox = FormElements::generateCategorySelectBox('LNK', $getCatId, 'admCategory', $gL10n->get('SYS_ALL'), true);
            
    if(strlen($calendarSelectBox) > 0)
    {
        // show category select box with link to calendar preferences
       $topNavigation .= '<li>'.$gL10n->get('SYS_CATEGORY').':&nbsp;&nbsp;'.$calendarSelectBox;

            if($gCurrentUser->editWeblinksRight())
            {
                $topNavigation .= '<a  class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=LNK"><img
                    src="'. THEME_PATH. '/icons/options.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" title="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>';
            }
        $topNavigation .= '</li>';
    }            
    elseif($gCurrentUser->editWeblinksRight())
    {
        // show link to category preferences
        $topNavigation .= '
        <li><span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=LNK"><img
                src="'. THEME_PATH. '/icons/application_double.png" alt="'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories.php?type=LNK">'.$gL10n->get('SYS_MAINTAIN_CATEGORIES').'</a>
        </span></li>';
    }                
    
    if(strlen($topNavigation) > 0)
    {
        echo '<ul class="iconTextLinkList">'.$topNavigation.'</ul>';
    }

    // Navigation mit Vor- und Zurueck-Buttons
    $baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $getHeadline;
    echo admFuncGeneratePagination($baseUrl, $numLinks, $weblinks_per_page, $getStart, TRUE);
}

if ($gDb->num_rows($links_result) == 0)
{
    // Keine Links gefunden
    if ($getLinkId > 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $j = 0;         // Zaehlervariable fuer Anzahl von fetch_object
    $i = 0;         // Zaehlervariable fuer Anzahl der Links in einer Kategorie
    $previous_cat_id = -1;  // Vorherige Kategorie-ID.
    $new_category = true;   // Kommt jetzt eine neue Kategorie?

    $weblink = new TableWeblink($gDb);

    // Solange die vorherige Kategorie-ID sich nicht veraendert...
    // Sonst in die neue Kategorie springen
    while ($row = $gDb->fetch_array($links_result))
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
                <div class="formHead">'.$weblink->getValue('cat_name').'</div>
                <div class="formBody" style="overflow: hidden;">';
        }

        echo '<div id="lnk_'.$weblink->getValue('lnk_id').'">';
            if($i > 0)
            {
                echo '<hr />';
            }

		// Ausgabe des Links
		echo '
			<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $gPreferences['weblinks_target']. '"><img src="'. THEME_PATH. '/icons/weblinks.png"
				alt="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" title="'.$gL10n->get('LNK_GO_TO', $weblink->getValue('lnk_name')).'" /></a>
			<a href="'.$g_root_path.'/adm_program/modules/links/links_redirect.php?lnk_id='.$weblink->getValue('lnk_id').'" target="'. $gPreferences['weblinks_target']. '">'.$weblink->getValue('lnk_name').'</a>';
		// aendern & loeschen duerfen nur User mit den gesetzten Rechten
		if ($gCurrentUser->editWeblinksRight())
		{
			echo '
			<a class="iconLink" href="'.$g_root_path.'/adm_program/modules/links/links_new.php?lnk_id='.$weblink->getValue('lnk_id').'&amp;headline='. $getHeadline. '"><img
				src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>
            <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=lnk&amp;element_id=lnk_'.
                $weblink->getValue('lnk_id').'&amp;name='.urlencode($weblink->getValue('lnk_name')).'&amp;database_id='.$weblink->getValue('lnk_id').'"><img 
                src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
		}

		// Beschreibung ausgeben, falls vorhanden
		if(strlen($weblink->getValue('lnk_description')) > 0)
		{
			echo '<div style="margin-top: 10px;">'.$weblink->getValue('lnk_description').'</div>';
		}
		
		echo '<div class="smallFontSize" style="text-align: right">'.$gL10n->get('LNK_COUNTER'). ': '.$weblink->getValue('lnk_counter').'</div>
		</div>';

        $j++;
        $i++;

        // Jetzt wird die jtzige die vorherige Kategorie
        $previous_cat_id = $weblink->getValue('lnk_cat_id');

        $new_category = false;
    }  // Ende While-Schleife

    // Es wurde noch gar nichts geschrieben ODER ein einzelner Link ist versteckt
    if ($numLinks == 0)
    {
        echo '<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>';
    }

    echo '</div></div>';
} // Ende Wenn mehr als 0 Datensaetze

echo '</div>';

// Navigation mit Vor- und Zurueck-Buttons
$baseUrl = $g_root_path.'/adm_program/modules/links/links.php?headline='. $getHeadline;
echo admFuncGeneratePagination($baseUrl, $numLinks, $weblinks_per_page, $getStart, TRUE);

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>
