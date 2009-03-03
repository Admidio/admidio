<?php
/******************************************************************************
 * Admidio Update Prüfung
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Matthias Roberg
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * show			: 1 - (Default) Nur Verfügbarkeit des Updates prüfen
 *				  2 - Updateregbnis anzeigen
 *
 *****************************************************************************/

require_once('common.php');
require_once('login_valid.php');

// Funktion zur Erreichbarkeitsprüfung der Updatedatei
function domainAvailable($strDomain)
{
	$rCurlHandle = curl_init($strDomain);

    curl_setopt($rCurlHandle, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($rCurlHandle, CURLOPT_HEADER, TRUE);
    curl_setopt($rCurlHandle, CURLOPT_NOBODY, TRUE);
    curl_setopt($rCurlHandle, CURLOPT_RETURNTRANSFER, TRUE);

    $strResponse = curl_exec($rCurlHandle);

    curl_close ($rCurlHandle);

    if (!$strResponse )
    {
      return FALSE;
    }
    return TRUE;
}

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

// Uebergabevariablen pruefen
if(isset($_GET['show']))
{
	if(is_numeric($_GET['show']) == false || $_GET['show'] > 2)
	{
		$g_message->show('invalid', '', '', false);
	}
	else
	{
		$show = $_GET['show'];		
	}
}
else
{
	$show = 1;
}

// Erreichbarkeit der Updateinformation prüfen und bei Verbindung
// verfügbare Admidio Versionen vom Server einlesen (Textfile)
if(domainAvailable('http://www.admidio.org/update.txt'))
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
else
{
	// Admidio Versionen vom Server übergeben
	$stable_version = 'n/a';
	$beta_version = 'n/a';
	$beta_release = '';
	
	$version_update = 99;
}

 // Nur im Anzeigemodus geht es weiter, ansonsten kann der aktuelle Updatestand 
// in der Variable $version_update abgefragt werden.
// $version_update (0 = Kein Update, 1 = Neue stabile Version, 2 = Neue Beta-Version, 3 = Neue stabile + Beta Version, 99 = Keine Verbindung)

if($show == 2)
{
	/***********************************************************************/
	/* Updateergebnis anzeigen */
	/***********************************************************************/

	if($version_update == 1)
	{
		$versionstext = '<b>Eine neue Version ist verfügbar!</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="Zur Admidio Updateseite" title="Zur Admidio Updateseite" /></a>';
	}
	else if($version_update == 2)
	{
		$versionstext = '<b>Eine neue Beta Version ist verfügbar!</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="Zur Admidio Updateseite" title="Zur Admidio Updateseite" /></a>';
	}
	else if($version_update == 3)
	{
		$versionstext = '<b>Eine neue Version und eine neue Beta ist verfügbar!</b>&nbsp;
						<a href="http://www.admidio.org/index.php?page=download"" target="_blank">
						<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/update_link.png" alt="Zur Admidio Updateseite" title="Zur Admidio Updateseite" /></a>';
	}	
	else if($version_update == 99)
	{
		$versionstext = 'Es konnte keine Verbindung zum Admidio Updateserver hergestellt werden! Bitte prüfe Deine Internetverbindung oder versuche es zu einem späteren Zeitpunkt nocheinmal.';
	}	
	else
	{
		$versionstext = '<img style="vertical-align: middle;" src="'. THEME_PATH. '/icons/ok.png" alt="Ok" /> Du benutzt eine aktuelle Admidio-Version!';
	}

	// Html-Kopf ausgeben
	$g_layout['title']    = 'Update Prüfung';
	$g_layout['includes'] = false;
	require(THEME_SERVER_PATH. '/overall_header.php');

	// Html des Modules ausgeben
	echo '
	<div class="formLayout" id="update_form" style="width: 300px">
		<div class="formHead">'. $g_layout['title']. '</div>
		<div class="formBody">
			<ul class="formFieldList">
				<li>
					<dl>
						<dt><label for="stable_admidio">Aktuelle stabile Version:</label></dt>
						<dd style="margin-left: 55%;"><b>' .$stable_version. '</b></dd>
					</dl>
				</li>
				<li>
					<dl>
						<dt><label for="beta_admidio">Aktuelle Beta Version:</label></dt>
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
						<dt><label for="current_admidio">Installierte Version:</label></dt>
						<dd style="margin-left: 55%;"><b>'. ADMIDIO_VERSION. BETA_VERSION_TEXT. '</b></dd>
					</dl>
				</li>
			</ul>

			<div style="margin-top: 20px;">' .$versionstext. '</div>
		</div>';
	  
	require(THEME_SERVER_PATH. '/overall_footer.php');
}


?>