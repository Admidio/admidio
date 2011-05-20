<?php
/******************************************************************************
 * Redirect für Links
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * lnk_id - ID des Links, auf den weitergeleitet werden soll
 * headline  - Ueberschrift, die ueber den Links steht
  *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/classes/table_weblink.php');

if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
if($g_preferences['enable_weblinks_module'] == 2)
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

// Wenn Link gültig ist, Counter um eine Position erhöhen
$link = new TableWeblink($g_db, $_GET['lnk_id']);
$link->setValue('lnk_counter',$link->getValue('lnk_counter') + 1);
$link->save();

// MR: Neue Prüfung für direkte Weiterleitung oder mit Anzeige
if ($g_preferences['weblinks_redirect_seconds'] > 0)
{
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
	
	$redirect_seconds = '<span id="counter">'.$g_preferences["weblinks_redirect_seconds"].'</span>';

	// Html-Kopf ausgeben
	$g_layout['title'] = $_GET['headline'];

	require(SERVER_PATH. '/adm_program/system/overall_header.php');

	// Html des Modules ausgeben
	echo '<h1 class="moduleHeadline">'. $_GET['headline']. '</h1>
	<div id="links_overview">
	<div class="formLayout">
			<div class="formHead">'.$g_l10n->get('LNK_REDIRECT').'</div>
			<div class="formBody" style="overflow: hidden;">'.$g_l10n->get('LNK_REDIRECT_DESC', $g_current_organization->getValue('org_longname'), 
                '<span id="counter">'.$g_preferences['weblinks_redirect_seconds'].'</span>', '<b>'.$url_name.'</b> ('.$url.')', 
                '<a href="'.$url.'" target="_self">hier</a>').'</div>
			</div>
	</div>';

	require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}
else
{
	header('Location:'.$url);
}

?>