<?php
/******************************************************************************
 * Backup
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Roland Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based on backupDB Version 1.2.5a-200806190803 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('backup.functions.php');

// Some Defines

define('BACKTICKCHAR',             '`');
define('QUOTECHAR',                '\'');
define('LINE_TERMINATOR',          "\n");    // \n = UNIX; \r\n = Windows; \r = Mac
define('BUFFER_SIZE',              32768);   // in bytes
define('TABLES_PER_COL',           30);      // number of table names per column in partial table backup selection screen
define('STATS_INTERVAL',           500);     // number of records processed between each DHTML stats refresh
define('MYSQL_RECONNECT_INTERVAL', 100000);  // disconnect and reconnect to MySQL every <interval> rows, to prevent timeouts

//Some Config
$fullbackupfilename = 'db_backup.'.date('Y-m-d.Gis').'.sql.gz';
$backupabsolutepath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash

$_SESSION['navigation']->addUrl(CURRENT_URL);

// nur Webmaster duerfen ein Backup starten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

$g_layout['title'] = 'Datenbank Backup';

require(THEME_SERVER_PATH. '/overall_header.php');

echo '<h1 class="moduleHeadline">Datenbank Backup</h1>';


error_reporting(E_ALL);
@ini_set('display_errors', '1');


OutputInformation('', '
<ul class="iconTextLinkList" id="cancel_link">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup.php"><img
            src="'. THEME_PATH. '/icons/error.png" alt="Abbrechen" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup.php">Abbrechen</a>
        </span>
    </li>
</ul>');
flush();

$newfullfilename = $backupabsolutepath.$fullbackupfilename;


$starttime = getmicrotime();
	
// Open Zipfile for writeaccess
if ($zp = @gzopen($newfullfilename, 'wb6')) 
{

	$fileheaderline  = '-- Admidio v'.ADMIDIO_VERSION. BETA_VERSION_TEXT.' (http://www.admidio.org)'.LINE_TERMINATOR;
	$fileheaderline .= '-- Backup vom '.date('d.m.Y').' um '.date('G:i:s').LINE_TERMINATOR.LINE_TERMINATOR;
	$fileheaderline .= '-- Database: '.$g_adm_db.LINE_TERMINATOR.LINE_TERMINATOR;
	$fileheaderline .= '-- User: '.$g_current_user->getValue('FIRST_NAME'). ' '. $g_current_user->getValue('LAST_NAME').LINE_TERMINATOR.LINE_TERMINATOR;
	gzwrite($zp, $fileheaderline, strlen($fileheaderline));
	
	//Ignore Foreignkeys during Import
	gzwrite($zp, 'SET FOREIGN_KEY_CHECKS=0;'.LINE_TERMINATOR.LINE_TERMINATOR, strlen('SET FOREIGN_KEY_CHECKS=0;'.LINE_TERMINATOR.LINE_TERMINATOR));
	
	unset($SelectedTables);

	@set_time_limit(60);
	
	// Liste der Tabellen aus den Tabellen Defines ermitteln
    foreach (get_defined_constants() as $key=>$value)
    if (substr($key,0,strlen('TBL_'))=='TBL_')
	{
		$SelectedTables[] = $value;	
	}
	
	// MYSQL Version ermitteln um spaeter den Storage Engine Type richtig abzufragen
	$mysql_server_info = $g_db->server_info();
    // Ab Version 
	$TypeEngineKey = (version_compare($mysql_server_info, '4.1.2', '>=') ? 'Engine' : 'Type'); 

	OutputInformation('', null, 'Checking tables...<br /><br />');
	$TableErrors = array();
	$repairresult = '';
	$CanContinue = true;
	// Alle Tabellen Optimieren
	foreach ($SelectedTables as $selectedtablename) 
	{
		OutputInformation('statusinfo', 'Checking table <b>'.$selectedtablename.'</b>');
		@set_time_limit(60);
		$result = $g_db->query('CHECK TABLE '.BACKTICKCHAR.$selectedtablename.BACKTICKCHAR);
		while ($row = $g_db->fetch_array($result))
		{
			@set_time_limit(60);
			if ($row['Msg_text'] == 'OK') 
			{
				$g_db->query('OPTIMIZE TABLE '.BACKTICKCHAR.$selectedtablename.BACKTICKCHAR);
			} 
			else 
			{
				OutputInformation('statusinfo', 'Repairing table <b>'.$selectedtablename.'</b>');
				//Fehlerbehandlung muss hier noch rein.....
			}
		}
	}

	OutputInformation('statusinfo', '');

	OutputInformation('', '<div id="topprogress"><p>Gesamtfortschritt:</p></div>');
	$overallrows = 0;
	echo '<table class="tableList" cellspacing="0"><tr><th colspan="'.ceil(count($SelectedTables) / TABLES_PER_COL).'"><b>'.$g_adm_db.'</b></th></tr><tr><td style="white-space: nowrap; vertical-align: top;">';
	$tablecounter = 0;
	for ($t = 0; $t < count($SelectedTables); $t++) {
		if ($tablecounter++ >= TABLES_PER_COL) {
			echo '</td><td style="white-space: nowrap; vertical-align: top;">';
			$tablecounter = 1;
		}
		$SQLquery = 'SELECT COUNT(*) AS '.BACKTICKCHAR.'num'.BACKTICKCHAR.' FROM '.BACKTICKCHAR.$SelectedTables[$t].BACKTICKCHAR;
		$result = $g_db->query($SQLquery);
		$row = $g_db->fetch_array($result);
		$rows[$t] = $row['num'];
		$overallrows += $rows[$t];
		echo '<div id="rows_'.$SelectedTables[$t].'">'.$SelectedTables[$t].' ('.number_format($rows[$t]).' records)</div>';
	}
	echo '</td></tr></table><br />';
	

	$alltablesstructure = '';

	for ($t = 0; $t < count($SelectedTables); $t++) {
		@set_time_limit(60);
		OutputInformation('statusinfo', 'Creating structure for <b>'.$SelectedTables[$t].'</b>');

		$fieldnames     = array();
		$structurelines = array();
		$result = $g_db->query('SHOW FIELDS FROM '.BACKTICKCHAR.$SelectedTables[$t].BACKTICKCHAR);
		while ($row = $g_db->fetch_array($result)) {
			$structureline  = BACKTICKCHAR.$row['Field'].BACKTICKCHAR;
			$structureline .= ' '.$row['Type'];
			$structureline .= ' '.($row['Null'] ? '' : 'NOT ').'NULL';
			preg_match('/^[a-z]+/i', $row['Type'], $matches);
			$RowTypes[$SelectedTables[$t]][$row['Field']] = $matches[0];
			if (@$row['Default']) {
				if (preg_match('/^(tiny|medium|long)?(text|blob)/i', $row['Type'])) {
					// no default values
				} else {
					$structureline .= ' default \''.$row['Default'].'\'';
				}
			}
			$structureline .= ($row['Extra'] ? ' '.$row['Extra'] : '');
			$structurelines[] = $structureline;

			$fieldnames[] = $row['Field'];
		}
		$g_db->free_result($result);

		$tablekeys    = array();
		$uniquekeys   = array();
		$fulltextkeys = array();
		$result = $g_db->query('SHOW INDEX FROM '.BACKTICKCHAR.$SelectedTables[$t].BACKTICKCHAR);
		$INDICES = array();
		while ($row = $g_db->fetch_array($result)) {
			$INDICES[$row['Key_name']][$row['Seq_in_index']] = $row;
		}
		$g_db->free_result($result);
		foreach ($INDICES as $index_name => $columndata) {
			$structureline  = '';
			if ($index_name == 'PRIMARY') {
				$structureline .= 'PRIMARY ';
			} elseif ((@$columndata[1]['Index_type'] == 'FULLTEXT') || ($columndata[1]['Comment'] == 'FULLTEXT')) {
				$structureline .= 'FULLTEXT ';
			} elseif (!$columndata[1]['Non_unique']) {
				$structureline .= 'UNIQUE ';
			}
			$structureline .= 'KEY';
			if ($index_name != 'PRIMARY') {
				$structureline .= ' '.BACKTICKCHAR.$index_name.BACKTICKCHAR;
			}
			$structureline .= ' (';
			$firstkeyname = true;
			foreach ($columndata as $seq_in_index => $row) {
				if (!$firstkeyname) {
					$structureline .= ',';
				}
				$structureline .= BACKTICKCHAR.$row['Column_name'].BACKTICKCHAR;
				if ($row['Sub_part']) {
					$structureline .= '('.$row['Sub_part'].')';
				}
				$firstkeyname = false;
			}
			$structureline .= ')';
			$structurelines[] = $structureline;
		}

		$TableStatusResult = $g_db->query('SHOW TABLE STATUS LIKE "'.$g_db->escape_string($SelectedTables[$t]).'"');
		if (!($TableStatusRow = $g_db->fetch_array($TableStatusResult))) {
			die('failed to execute "SHOW TABLE STATUS" on '.$SelectedTables[$t]);
		}

		$tablestructure  = 'CREATE TABLE IF NOT EXISTS '.BACKTICKCHAR.$SelectedTables[$t].BACKTICKCHAR.' ('.LINE_TERMINATOR;
		$tablestructure .= '  '.implode(','.LINE_TERMINATOR.'  ', $structurelines).LINE_TERMINATOR;
		$tablestructure .= ') TYPE='.$TableStatusRow[$TypeEngineKey];
		if ($TableStatusRow['Auto_increment'] !== null) {
			$tablestructure .= ' AUTO_INCREMENT='.$TableStatusRow['Auto_increment'];
		}
		$tablestructure .= ';'.LINE_TERMINATOR.LINE_TERMINATOR;

		$alltablesstructure .= str_replace(' ,', ',', $tablestructure);

	} // end table structure backup
	
	
	gzwrite($zp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
	
	$datastarttime = getmicrotime();
	OutputInformation('statusinfo', '');

	//Tabelleninhalte aus der Datenbank extrahieren
	$processedrows    = 0;
	@set_time_limit(300);
	for ($t = 0; $t < count($SelectedTables); $t++) {
		$result = $g_db->query('SELECT * FROM '.$SelectedTables[$t]);
		$rows[$t] = $g_db->num_rows($result);
		if ($rows[$t] > 0) {
			$tabledatadumpline = '-- dumping data for '.$SelectedTables[$t].LINE_TERMINATOR;
			gzwrite($zp, $tabledatadumpline, strlen($tabledatadumpline));
		}
		unset($fieldnames);
		for ($i = 0; $i < $g_db->num_fields($result); $i++) {
			$fieldnames[] = $g_db->field_name($result, $i);
		}
		
		$currentrow       = 0;
		$thistableinserts = '';
		while ($row = $g_db->fetch_array($result))
		{
			unset($valuevalues);
			foreach ($fieldnames as $key => $val) 
			{
				if ($row[$key] === null) 
				{

					$valuevalues[] = 'NULL';

				} 
				else 
				{

					switch ($RowTypes[$SelectedTables[$t]][$val]) 
					{
						// binary data dump, two hex characters per byte
						case 'tinyblob':
						case 'blob':
						case 'mediumblob':
						case 'longblob':
							$data = $row[$key];
							$data_len = strlen($data);
							if ($data_len) 
							{
								$hexstring = '0x';
								for ($i = 0; $i < $data_len; $i++) 
								{
									$hexstring .= str_pad(dechex(ord($data{$i})), 2, '0', STR_PAD_LEFT);
								}
								$valuevalues[] = $hexstring;
							}
							break;

						// just the (numeric) value, not surrounded by quotes
						case 'tinyint':
						case 'smallint':
						case 'mediumint':
						case 'int':
						case 'bigint':
						case 'float':
						case 'double':
						case 'decimal':
						case 'year':
							$valuevalues[] = $g_db->escape_string($row[$key]);
							break;

						// value surrounded by quotes
						case 'varchar':
						case 'char':
						case 'tinytext':
						case 'text':
						case 'mediumtext':
						case 'longtext':
						case 'enum':
						case 'set':
						case 'date':
						case 'datetime':
						case 'time':
						case 'timestamp':
						default:
							$valuevalues[] = QUOTECHAR.$g_db->escape_string($row[$key]).QUOTECHAR;
							break;
					}

				}
			}
			
			// INSERT Befehl fuer diese Tabelle zusammenbauen
			if($currentrow == 0)
			{
				$thistableinserts .= 'INSERT INTO '.BACKTICKCHAR.$SelectedTables[$t].BACKTICKCHAR.' ('.BACKTICKCHAR.implode(BACKTICKCHAR.', '.BACKTICKCHAR, $fieldnames).BACKTICKCHAR.') VALUES '.LINE_TERMINATOR;
				$thistableinserts .= '('.implode(', ', $valuevalues).')';

			}
			else
			{
				$thistableinserts .= ','.LINE_TERMINATOR.'('.implode(', ', $valuevalues).')';

			}
			
			if (strlen($thistableinserts) >= BUFFER_SIZE) 
			{
				gzwrite($zp, $thistableinserts, strlen($thistableinserts));
				$thistableinserts = '';
			}
			if ((++$currentrow % STATS_INTERVAL) == 0) 
			{
				@set_time_limit(60);
				OutputInformation('rows_'.$SelectedTables[$t], '<b>'.$SelectedTables[$t].' ('.number_format($rows[$t]).' records, ['.number_format(($currentrow / $rows[$t])*100).'%])</b>');
				$elapsedtime = getmicrotime() - $datastarttime;
				$percentprocessed = ($processedrows + $currentrow) / $overallrows;
				$overallprogress = '<p>Gesamtfortschritt:</p><p>'.number_format($processedrows + $currentrow).' / '.number_format($overallrows).' ('.number_format($percentprocessed * 100, 1).'% fertig) ['.FormattedTimeRemaining($elapsedtime).' verstrichen';
				if (($percentprocessed > 0) && ($percentprocessed < 1)) 
				{
					$overallprogress .= ', '.FormattedTimeRemaining(abs($elapsedtime - ($elapsedtime / $percentprocessed))).' übrig';
				}
				$overallprogress .= ']</p>';
				OutputInformation('topprogress', $overallprogress);
			}

		}
		OutputInformation('rows_'.$SelectedTables[$t], $SelectedTables[$t].' ('.number_format($rows[$t]).' Datensätze, [100%])');
		$processedrows += $rows[$t];
		if($currentrow > 0)
		{
			$thistableinserts .= ';'.LINE_TERMINATOR.LINE_TERMINATOR;
		}
		gzwrite($zp, $thistableinserts, strlen($thistableinserts));
	}
	
	
	
	// Enable ForeignKey Checks again!
	gzwrite($zp, 'SET FOREIGN_KEY_CHECKS=1;'.LINE_TERMINATOR.LINE_TERMINATOR, strlen('SET FOREIGN_KEY_CHECKS=1;'.LINE_TERMINATOR.LINE_TERMINATOR));
	
	gzclose($zp);
	


} 
else 
{

	echo '<b>Warning:</b> failed to open '.$backupabsolutepath.$fullbackupfilename.' for writing!<br /><br />';
	if (is_dir($backupabsolutepath)) 
	{
		echo '<i>CHMOD 777</i> on the directory ('.htmlentities($backupabsolutepath).') should fix that.';
	} 
	else 
	{
		echo 'The specified directory does not exist: "'.htmlentities($backupabsolutepath).'"';
	}

}


echo '<p>Backup fertiggestellt in '.FormattedTimeRemaining(getmicrotime() - $starttime, 2).'.</p>

<p>Backupdatei: <a href="'.$g_root_path.'/adm_program/administration/backup/get_backup_file.php?filename='.basename($newfullfilename).'">'.basename($newfullfilename).'</a>
('.FileSizeNiceDisplay(filesize($newfullfilename), 2).')</p>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" title="Zurück zur Backupseite"/></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück zur Backupseite</a>
        </span>
    </li>
</ul>';

OutputInformation('cancel_link', '');
OutputInformation('topprogress', '');


require(THEME_SERVER_PATH. '/overall_footer.php');
?>
