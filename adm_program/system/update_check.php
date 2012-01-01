<?php
/******************************************************************************
 * Admidio Update Prüfung
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * mode			: 1 - (Default) Nur Verfügbarkeit des Updates prüfen
 *				  2 - Updateregbnis anzeigen
 *
 *****************************************************************************/

require_once('common.php');
require_once('login_valid.php');

// Initialize and check the parameters
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1, false, null, true);

// Funktion zur Ermittlung der Update-Version
function GetUpdateVersion($update_info, $search)
{
	// Variablen festlegen
	$i = 0;
	$pointer = '';
	$update_version = '';
	$current_version_start = strpos($update_info, $search);
	$adding = strlen($search)-1;

	// Version auslesen
	while($pointer != "\n")
	{
		$i++;
		$update_version = $update_version. $pointer;
		$pointer = substr($update_info, $current_version_start+$adding+$i, 1);
	}
	
	return trim($update_version, "\n\r");
}

// Funktion zur Überprüfung eines Updates
function CheckVersion($current_version, $check_stable_version, $check_beta_version, $beta_release, $beta_flag)
{
	// Updatezustand (0 = Kein Update, 1 = Neue stabile Version, 2 = Neue Beta-Version, 3 = Neue stabile + Beta Version)
	$update = 0;
	
	// Zunächst auf stabile Version prüfen
	$status = version_compare($check_stable_version, $current_version);
	if($status == 1)
	{
		$update = 1;
	}
	
	// Jetzt auf Beta Version prüfen
	$status = version_compare($check_beta_version, $current_version);
	if($status == 1 || ($status == 0 && version_compare($beta_release, $beta_flag) == 1))
	{
		if($update == 1)
		{
			$update = 3;
		}
		else
		{
			$update = 2;
		}
	}
	
	return $update;
}

// Erreichbarkeit der Updateinformation prüfen und bei Verbindung
// verfügbare Admidio Versionen vom Server einlesen (Textfile)
// Zunächst die Methode selektieren (CURL bevorzugt)
$available = 0;
if(@file_get_contents('http://www.admidio.org/update.txt') == false)
{
	$available = 0;
}
else
{
	$available = 1;
}

if($available == 0)
{
	// Admidio Versionen nicht auslesbar
	$stable_version = 'n/a';
	$beta_version = 'n/a';
	$beta_release = '';
	
	$version_update = 99;
}
else if($available == 1)
{
	$update_info = file_get_contents('http://www.admidio.org/update.txt');
	
	// Admidio Versionen vom Server übergeben
	$stable_version = GetUpdateVersion($update_info, 'Version=');
	$beta_version   = GetUpdateVersion($update_info, 'Beta-Version=');
	$beta_release   = GetUpdateVersion($update_info, 'Beta-Release=');
	
	// Keine Stabile Version verfügbar (eigentlich unmöglich)
	if($stable_version == '')
	{
		$stable_version = 'n/a';
	}
	
	// Keine Beatversion verfügbar
	if($beta_version == '')
	{
		$beta_version = 'n/a';
		$beta_release = '';
	}
	
	// Auf Update prüfen
	$version_update = CheckVersion(ADMIDIO_VERSION, $stable_version, $beta_version, $beta_release, BETA_VERSION);
}


 // Nur im Anzeigemodus geht es weiter, ansonsten kann der aktuelle Updatestand 
// in der Variable $version_update abgefragt werden.
// $version_update (0 = Kein Update, 1 = Neue stabile Version, 2 = Neue Beta-Version, 3 = Neue stabile + Beta Version, 99 = Keine Verbindung)

if($getMode == 2)
{
	/***********************************************************************/
	/* Updateergebnis anzeigen */
	/***********************************************************************/

	if($version_update == 1)
	{
		$versionstext = '<b>'.$gL10n->get('UPD_NEW').'</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="'.$gL10n->get('UPD_ADMIDIO').'" title="'.$gL10n->get('UPD_ADMIDIO').'" /></a>';
	}
	else if($version_update == 2)
	{
		$versionstext = '<b>'.$gL10n->get('UPD_NEW_BETA').'</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="'.$gL10n->get('UPD_ADMIDIO').'" title="'.$gL10n->get('UPD_ADMIDIO').'" /></a>';
	}
	else if($version_update == 3)
	{
		$versionstext = '<b>'.$gL10n->get('UPD_NEW_BOTH').'</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="'.$gL10n->get('UPD_ADMIDIO').'" title="'.$gL10n->get('UPD_ADMIDIO').'" /></a>';
	}	
	else if($version_update == 99)
	{
		$admidio_link = '<a href="http://www.admidio.org/index.php?page=download"" target="_blank">Admidio</a>';
		$versionstext = $gL10n->get('UPD_CONNECTION_ERROR', $admidio_link);
	}	
	else
	{
		if(BETA_VERSION > 0) {$versionstext_beta = 'Beta ';}
		else {$versionstext_beta = ' ';}
		$versionstext = '<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/ok.png" alt="Ok" /> '.$gL10n->get('UPD_NO_NEW', $versionstext_beta);
	}

	// Html-Kopf ausgeben
	$gLayout['title']    = $gL10n->get('UPD_TITLE');
	$gLayout['includes'] = false;
	require(SERVER_PATH. '/adm_program/system/overall_header.php');

	// Html des Modules ausgeben
	echo '
	<div class="formLayout" id="update_form" style="width: 300px">
		<div class="formHead">'. $gLayout['title']. '</div>
		<div class="formBody">
			<ul class="formFieldList">
				<li>
					<dl>
						<dt><label for="stable_admidio">'.$gL10n->get('UPD_STABLE_VERSION').':</label></dt>
						<dd style="margin-left: 55%;"><b>' .$stable_version. '</b></dd>
					</dl>
				</li>
				<li>
					<dl>
						<dt><label for="beta_admidio">'.$gL10n->get('UPD_BETA_VERSION').':</label></dt>
						<dd style="margin-left: 55%;"><b>'. $beta_version;
                        if($version_update != 99 && $beta_version != 'n/a')
                        {
                            echo '&nbsp;Beta&nbsp;';
                        }
                        echo $beta_release. '</b></dd>
					</dl>
				</li>	
				<li><hr /></li>
				<li>
					<dl>
						<dt><label for="current_admidio">'.$gL10n->get('UPD_CURRENT_VERSION').':</label></dt>
						<dd style="margin-left: 55%;"><b>'. ADMIDIO_VERSION. BETA_VERSION_TEXT. '</b></dd>
					</dl>
				</li>
			</ul>

			<div style="margin-top: 20px;">' .$versionstext. '</div>
		</div>';
	  
	require(SERVER_PATH. '/adm_program/system/overall_footer.php');
}


?>