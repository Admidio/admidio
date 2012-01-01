<?php
/******************************************************************************
 * Redirect für Links
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * lnk_id   : ID des Links, auf den weitergeleitet werden soll
 * headline : Ueberschrift, die ueber den Links steht
  *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/classes/table_weblink.php');

// Initialize and check the parameters
$getLinkId   = admFuncVariableIsValid($_GET, 'lnk_id', 'numeric', null, true);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('LNK_WEBLINKS'));

if ($gPreferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
if($gPreferences['enable_weblinks_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// Lokale Variablen initialisieren
$url = '';
$urlName = '';
$sqlCondition = '';

// SQL-Statement zusammenbasteln
if ($gValidLogin == false)
{
	// Wenn User nicht eingeloggt ist, Kategorien, die hidden sind, aussortieren
	$sqlCondition = ' AND cat_hidden = 0 ';
}

// Link aus Datenbank auslesen
$sql = 'SELECT * FROM '. TBL_LINKS. ', '. TBL_CATEGORIES .'
  		 WHERE lnk_cat_id = cat_id
		   AND cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
		   AND cat_type = \'LNK\'
		   AND lnk_id = '.$getLinkId.'
  		       '.$sqlCondition.'
		 ORDER BY cat_sequence, lnk_name, lnk_timestamp_create DESC';
$result = $gDb->query($sql);

while($row = $gDb->fetch_array($result))
{
	$url = $row['lnk_url'];
	$urlName = $row['lnk_name'];
}
// Wenn kein Link gefunden wurde Fehler ausgeben
if(strlen($url) == 0)
{
	$gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Wenn Link gültig ist, Counter um eine Position erhöhen
$link = new TableWeblink($gDb, $getLinkId);
$link->setValue('lnk_counter',$link->getValue('lnk_counter') + 1);
$link->save();

// MR: Neue Prüfung für direkte Weiterleitung oder mit Anzeige
if ($gPreferences['weblinks_redirect_seconds'] > 0)
{
	$gLayout['header'] = '<meta http-equiv="refresh" content="'. $gPreferences['weblinks_redirect_seconds'].'; url='.$url.'">';

	//Counter zählt die sekunden bis zur Weiterleitung runter
	$gLayout['header'] = $gLayout['header'].'<script type="text/javascript">
		function countDown(init)
		{
			if (init || --document.getElementById( "counter" ).firstChild.nodeValue > 0 )
			{
				window.setTimeout( "countDown()" , 1000 );
			}
		};
		countDown(true);
	</script>'; 
	
	$redirect_seconds = '<span id="counter">'.$gPreferences["weblinks_redirect_seconds"].'</span>';

	// Html-Kopf ausgeben
	$gLayout['title'] = $getHeadline;

	require(SERVER_PATH. '/adm_program/system/overall_header.php');

	// Html des Modules ausgeben
	echo '<h1 class="moduleHeadline">'. $getHeadline. '</h1>
	<div id="links_overview">
	<div class="formLayout">
			<div class="formHead">'.$gL10n->get('LNK_REDIRECT').'</div>
			<div class="formBody" style="overflow: hidden;">'.$gL10n->get('LNK_REDIRECT_DESC', $gCurrentOrganization->getValue('org_longname'), 
                '<span id="counter">'.$gPreferences['weblinks_redirect_seconds'].'</span>', '<b>'.$urlName.'</b> ('.$url.')', 
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