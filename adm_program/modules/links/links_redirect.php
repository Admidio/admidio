<?php
/******************************************************************************
 * Redirect für Links
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * lnk_id - ID des Links, auf den weitergeleitet werden soll
 * headline  - Ueberschrift, die ueber den Links steht
  *
 *****************************************************************************/

require('../../system/common.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['weblinks_redirect_seconds'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}
elseif($g_preferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// Uebergabevariablen pruefen
if (array_key_exists('lnk_id', $_GET))
{
    if (is_numeric($_GET['lnk_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = 'Weblinks';
}

// SQL-Statement zusammenbasteln
$hidden    = '';

if ($g_valid_login == false)
{
	// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
	$hidden = ' AND cat_hidden = 0 ';
}

// Link aus Datenbank auslesen
$sql = 'SELECT * FROM '. TBL_LINKS. ', '. TBL_CATEGORIES .'
  		  WHERE lnk_cat_id = cat_id
		    AND cat_org_id = '. $g_current_organization->getValue('org_id'). '
		    AND cat_type = "LNK"
			AND lnk_id = '. $_GET["lnk_id"]. '
  		        '.$hidden.'
		  ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC';

$result = $g_db->query($sql);

while($row = $g_db->fetch_array($result))
{
	$url = $row['lnk_url'];
	$url_name = $row['lnk_name'];
}
// Wenn kein Link gefunden wurde Fehler ausgeben
if ($url == '')
{
	$g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

$g_layout['header'] = '<meta http-equiv="refresh" content="'. $g_preferences["weblinks_redirect_seconds"].'; url='.$url.'">';

//Counter zählt die sekunden bis zur Weiterleitung runter
$g_layout['header'] =$g_layout['header'].'<script type="text/javascript">
    function countDown(init)
    {
        if (init || --document.getElementById( "counter" ).firstChild.nodeValue > 0 )
        {
        	window.setTimeout( "countDown()" , 1000 );
        }
    };
    countDown(true);
</script>'; 

// Html-Kopf ausgeben
$g_layout['title'] = $_GET['headline'];

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'. $_GET['headline']. '</h1>
<div id="links_overview">
	<div class="formLayout">
			<div class="formHead">Redirect</div>
			<div class="formBody" style="overflow: hidden;">Du verlässt jetzt das Webangebot von <i>'. $g_current_organization->getValue('org_longname'). '</i> und		
			 wirst in <span id="counter">'.$g_preferences["weblinks_redirect_seconds"].'</span> Sekunden automatisch zu <b>'.$url_name.'</b> ('.$url.') weitergeleitet.<br><br>
			 Sollte die automatische Weiterleitung nicht funktionieren, klicke bitte <a href="'.$url.'" target="_self">hier</a>!</div>
	</div>
</div>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>