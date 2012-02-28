<?php
/******************************************************************************
 * Create the backup
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * 
 * Based on backupDB Version 1.2.7-201104261502 
 * by James Heinrich <info@silisoftware.com>  
 * available at http://www.silisoftware.com
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('backup.functions.php');

// only webmaster are allowed to start backup
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set db in non-strict mode so that table names and field names get a single quote
// in ANSI mode they get double quote and than some dbs got errors during import
$gDb->query('SET SQL_MODE = \'\'');

// Some Defines
define('ADMIN_EMAIL', $gPreferences['email_administrator']); // eg: admin@example.com

define('BACKTICKCHAR',             '`');
define('QUOTECHAR',                '\'');
define('LINE_TERMINATOR',          "\n");    // \n = UNIX; \r\n = Windows; \r = Mac
define('BUFFER_SIZE',              32768);   // in bytes
define('TABLES_PER_COL',           30);      // number of table names per column in partial table backup selection screen
define('STATS_INTERVAL',           500);     // number of records processed between each DHTML stats refresh
define('MYSQL_RECONNECT_INTERVAL', 100000);  // disconnect and reconnect to MySQL every <interval> rows, to prevent timeouts
define('OUTPUT_COMPRESSION_TYPE',  'gzip');  // 'bzip2', 'gzip', 'none'; best at "bzip2" for mysqldump-based backups, "gzip" for PHP-based backups
define('OUTPUT_COMPRESSION_LEVEL', 6);       // bzip2/gzip compression level (1=fastest,9=best)

//Some Config
$DHTMLenabled       = true;  // set $DHTMLenabled = FALSE to prevent JavaScript errors in incompatible browsers
                             // set $DHTMLenabled = TRUE to get the nice DHTML display in recent browsers
$dbNameInCreate     = true;  // if true: "CREATE TABLE `database`.`table`", if false: "CREATE TABLE `table`"
$CreateIfNotExists  = true;  // if true: "CREATE TABLE IF NOT EXISTS `database`.`table`", if false: "CREATE TABLE `database`.`table`"
$ReplaceInto        = false; // if true: "REPLACE INTO ", if false: "INSERT INTO "
$HexBLOBs           = true;  // if true: blobs get data dumped as hex string; if false: blobs get data dumped as escaped binary string
$SuppressHTMLoutput = (@$_REQUEST['nohtml'] ? true : false); // disable all output for running as a cron job
$Disable_mysqldump  = false; // LEAVE THIS AS "false"! If true, avoid use of "mysqldump" program to export databases which is *MUCH* *MUCH* faster than doing it row-by-row in PHP. If mysqldump is not available, will automatically fall back to slower row-by-row method. Highly recommended to leave this at "false" (i.e. do use mysqldump)
$backuptimestamp    = '.'.date('Y-m-d.Gis'); // timestamp
$backupabsolutepath = SERVER_PATH. '/adm_my_files/backup/'; // make sure to include trailing slash
$fileextension = ((OUTPUT_COMPRESSION_TYPE == 'bzip2') ? '.bz2' : ((OUTPUT_COMPRESSION_TYPE == 'gzip') ? '.gz' : ''));
$fullbackupfilename = 'db_backup'.$backuptimestamp.'.sql'.$fileextension;
$partbackupfilename = 'db_backup_partial'.$backuptimestamp.'.sql'.$fileextension;
$strubackupfilename = 'db_backup_structure'.$backuptimestamp.'.sql'.$fileextension;
$tempbackupfilename = 'db_backup.temp.sql'.$fileextension;

$NeverBackupDBtypes  = array('HEAP');
$CloseWindowOnFinish = false;


$_SESSION['navigation']->addUrl(CURRENT_URL);

$gLayout['title'] = $gL10n->get('BAC_DATABASE_BACKUP');

require(SERVER_PATH. '/adm_program/system/overall_header.php');

echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>';


error_reporting(E_ALL);
@ini_set('display_errors', '1');


OutputInformation('', '
<ul class="iconTextLinkList" id="cancel_link">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup.php"><img
            src="'. THEME_PATH. '/icons/error.png" alt="'.$gL10n->get('SYS_ABORT').'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/backup/backup.php">'.$gL10n->get('SYS_ABORT').'</a>
        </span>
    </li>
</ul>');
flush();

$_REQUEST['StartBackup'] = 'complete';
$TypeEngineKey = 'Engine';
$newfullfilename = $backupabsolutepath.$fullbackupfilename;
unset($SelectedTables);
unset($tables);

// create a list with all tables out of the "table defines"
foreach (get_defined_constants() as $key => $value)
{
	if (substr($key,0,strlen('TBL_'))=='TBL_')
	{
		$tables[] = $value;	
	}
}
$SelectedTables[$g_adm_db] = $tables;

$starttime = getmicrotime();

		switch (OUTPUT_COMPRESSION_TYPE) {
			case 'gzip':
			case 'none':
				// great
				break;
			case 'bzip2':
				if (!function_exists('bzopen')) {
					die('ERROR: PHP-bzip2 support does not appear to be installed, please change OUTPUT_COMPRESSION_TYPE to one of "gzip" or "none"');
				}
				break;
			default:
				die('ERROR: OUTPUT_COMPRESSION_TYPE ('.htmlentities(OUTPUT_COMPRESSION_TYPE).') must be one of "bzip2", "gzip", "none"');
				break;
		}
		if (((OUTPUT_COMPRESSION_TYPE == 'gzip')  && ($zp = @gzopen($backupabsolutepath.$tempbackupfilename, 'wb'.OUTPUT_COMPRESSION_LEVEL))) ||
			((OUTPUT_COMPRESSION_TYPE == 'bzip2') && ($bp = @bzopen($backupabsolutepath.$tempbackupfilename, 'w'))) ||
			((OUTPUT_COMPRESSION_TYPE == 'none')  && ($fp = @fopen($backupabsolutepath.$tempbackupfilename, 'wb')))) {

			$fileheaderline  = '-- Admidio v'.ADMIDIO_VERSION. BETA_VERSION_TEXT.' (http://www.admidio.org)'.LINE_TERMINATOR;
			$fileheaderline .= '-- '.$gL10n->get('BAC_BACKUP_FROM', date('d.m.Y'), date('G:i:s')).LINE_TERMINATOR.LINE_TERMINATOR;
			$fileheaderline .= '-- '.$gL10n->get('SYS_DATABASE').': '.$g_adm_db.LINE_TERMINATOR.LINE_TERMINATOR;
			$fileheaderline .= '-- '.$gL10n->get('SYS_USER').': '.$gCurrentUser->getValue('FIRST_NAME'). ' '. $gCurrentUser->getValue('LAST_NAME').LINE_TERMINATOR.LINE_TERMINATOR;
			$fileheaderline .= 'SET FOREIGN_KEY_CHECKS=0;'.LINE_TERMINATOR.LINE_TERMINATOR;
			if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
				bzwrite($bp, $fileheaderline, strlen($fileheaderline));
			} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
				gzwrite($zp, $fileheaderline, strlen($fileheaderline));
			} else {
				fwrite($fp, $fileheaderline, strlen($fileheaderline));
			}
			
			// Begin original backupDB

			OutputInformation('', null, 'Checking tables...<br><br>');
			$TableErrors = array();
			foreach ($SelectedTables as $dbname => $selectedtablesarray) {
				mysql_select_db($dbname);
				$repairresult = '';
				$CanContinue = true;
				foreach ($selectedtablesarray as $selectedtablename) {
					OutputInformation('statusinfo', 'Checking table <b>'.htmlentities($dbname.'.'.$selectedtablename).'</b>');
					@set_time_limit(60);
					$SQLquery = 'CHECK TABLE '.BACKTICKCHAR.$selectedtablename.BACKTICKCHAR;
					$result = $gDb->query($SQLquery);
					while ($row = $gDb->fetch_assoc($result)) {
						@set_time_limit(60);
						if ($row['Msg_text'] == 'OK') {

							$SQLquery = 'OPTIMIZE TABLE '.BACKTICKCHAR.$selectedtablename.BACKTICKCHAR;
							$gDb->query($SQLquery);

						} else {

							OutputInformation('statusinfo', 'Repairing table <b>'.htmlentities($selectedtablename).'</b>');
							$SQLquery  = 'REPAIR TABLE '.BACKTICKCHAR.$gDb->escape_string($selectedtablename).BACKTICKCHAR.' EXTENDED';
							$fixresult = $gDb->query($SQLquery);
							$repairresult .= $SQLquery.($gDb->db_error() ? LINE_TERMINATOR.$gDb->db_error() : '').LINE_TERMINATOR;
							$ThisCanContinue = false;
							while (is_resource($fixresult) && ($fixrow = $gDb->fetch_assoc($fixresult))) {
								$thisMessage = $fixrow['Msg_type'].': '.$fixrow['Msg_text'];
								$repairresult .= $thisMessage.LINE_TERMINATOR;
								switch ($thisMessage) {
									case 'status: OK':
									case 'error: The handler for the table doesn\'t support repair':
										$ThisCanContinue = true;
										break;
								}
							}
							if (!$ThisCanContinue) {
								$CanContinue = false;
							}

							$repairresult .= LINE_TERMINATOR.str_repeat('-', 60).LINE_TERMINATOR.LINE_TERMINATOR;

						}
					}
				}

				if (!empty($repairresult)) {
					mail(ADMIN_EMAIL, 'backupDB: MySQL Table Error Report', $repairresult);
					echo '<pre>'.$repairresult.'</pre>';
					if (!$CanContinue) {
						if ($SuppressHTMLoutput) {
							ob_end_clean();
							echo 'errors';
						}
						exit;
					}
				}
			}
			OutputInformation('statusinfo', '');

			OutputInformation('', '<br><span id="topprogress" style="font-weight: bold;">Overall Progress:</span><br>');
			$overallrows = 0;
			foreach ($SelectedTables as $dbname => $value) {
				$gDb->select_db($dbname);
				echo '<table class="tableList" cellspacing="0"><tr><th colspan="'.ceil(count($SelectedTables[$dbname]) / TABLES_PER_COL).'"><b>'.htmlentities($dbname).'</b></th></tr><tr><td nowrap valign="top">';
				$tablecounter = 0;
				for ($t = 0; $t < count($SelectedTables[$dbname]); $t++) {
					if ($tablecounter++ >= TABLES_PER_COL) {
						echo '</td><td nowrap valign="top">';
						$tablecounter = 1;
					}
					$SQLquery  = 'SELECT COUNT(*) AS '.BACKTICKCHAR.'num'.BACKTICKCHAR;
					$SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escape_string($SelectedTables[$dbname][$t]).BACKTICKCHAR;
					$result = $gDb->query($SQLquery);
					$row = $gDb->fetch_assoc($result);
					$rows[$t] = $row['num'];
					$overallrows += $rows[$t];
					echo '<span id="rows_'.$dbname.'_'.$SelectedTables[$dbname][$t].'">'.htmlentities($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records)</span><br>';
				}
				echo '</td></tr></table><br>';
			}

			$alltablesstructure = '';
			foreach ($SelectedTables as $dbname => $value) {
				$gDb->select_db($dbname);
				for ($t = 0; $t < count($SelectedTables[$dbname]); $t++) {
					@set_time_limit(60);
					OutputInformation('statusinfo', 'Creating structure for <b>'.htmlentities($dbname.'.'.$SelectedTables[$dbname][$t]).'</b>');

					$fieldnames = array();

					$SQLquery  = 'SHOW CREATE TABLE '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
					$result_showcreatetable = $gDb->query($SQLquery);
					if ($gDb->num_rows($result_showcreatetable) == 1) {
						$row = $gDb->fetch_assoc($result_showcreatetable);
						$tablestructure = $row['Create Table'];

						$SQLquery  = 'SHOW FULL FIELDS';
						$SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
						$result_showfields = $gDb->query($SQLquery);
						while ($row = $gDb->fetch_assoc($result_showfields)) {
							if (preg_match('#^[a-z]+#i', $row['Type'], $matches)) {
								$RowTypes[$dbname][$SelectedTables[$dbname][$t]][$row['Field']] = $matches[0];
							}
							$fieldnames[] = $row['Field'];
						}
						$gDb->free_result($result_showfields);
					} else {
						$structurelines = array();
						$SQLquery  = 'SHOW FULL FIELDS';
						$SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escape_string($SelectedTables[$dbname][$t]).BACKTICKCHAR;
						$result_showfields = $gDb->query($SQLquery);
						while ($row = $gDb->fetch_assoc($result_showfields)) {
							$structureline  = BACKTICKCHAR.$row['Field'].BACKTICKCHAR;
							$structureline .= ' '.$row['Type'];
							if (isset($row['Collation']) && !is_null($row['Collation']) && !empty($row['Collation'])) {
								$structureline .= ' COLLATE '.$row['Collation'];
							}
							switch (strtoupper($row['Null'])) {
								case '1':
								case 'YES':
									$field_is_null = true;
									break;
								case '':
								case '0':
								case 'NO':
								default:
									$field_is_null = false;
									break;
							}
							if (!preg_match('#^(tiny|medium|long)?(text|blob)#i', $row['Type'])) {
								if ($field_is_null && is_null($row['Default'])) {
									$structureline .= ' DEFAULT NULL';
								} elseif ($field_is_null) {
									$structureline .= ' NULL';
								} else {
									$structureline .= ' NOT NULL';
								}
							}
							preg_match('#^[a-z]+#i', $row['Type'], $matches);
							$RowTypes[$dbname][$SelectedTables[$dbname][$t]][$row['Field']] = $matches[0];
							if (isset($row['Default']) && !is_null($row['Default'])) {
								if (preg_match('#^(tiny|medium|long)?(text|blob)#i', $row['Type'])) {
									// no default values
								} elseif ((strtolower($row['Type']) == 'timestamp') && (strtoupper($row['Default']) == 'CURRENT_TIMESTAMP')) {
									$structureline .= ' DEFAULT '.$row['Default'];
								} else {
									$structureline .= ' DEFAULT \''.$row['Default'].'\'';
								}
							}
							$structureline .= ($row['Extra'] ? ' '.strtoupper($row['Extra']) : '');
							$structurelines[] = $structureline;

							$fieldnames[] = $row['Field'];
						}
						$gDb->free_result($result_showfields);

						$tablekeys    = array();
						$uniquekeys   = array();
						$fulltextkeys = array();

						$SQLquery  = 'SHOW INDEX';
						$SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
						$result_showindex = $gDb->query($SQLquery);
						$INDICES = array();
						while ($row = $gDb->fetch_assoc($result_showindex)) {
							$INDICES[$row['Key_name']][$row['Seq_in_index']] = $row;
						}
						$gDb->free_result($result_showindex);
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

						$SQLquery  = 'SHOW TABLE STATUS LIKE "'.$gDb->escape_string($SelectedTables[$dbname][$t]).'"';
						$result_tablestatus = $gDb->query($SQLquery);
						if (!($TableStatusRow = $gDb->fetch_assoc($result_tablestatus))) {
							die('failed to execute "'.$SQLquery.'" on '.$dbname.'.'.$tablename);
						}
						$gDb->free_result($result_tablestatus);

						$tablestructure  = 'CREATE TABLE '.($CreateIfNotExists ? 'IF NOT EXISTS ' : '').($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' ('.LINE_TERMINATOR;
						$tablestructure .= '  '.implode(','.LINE_TERMINATOR.'  ', $structurelines).LINE_TERMINATOR;
						$tablestructure .= ') '.strtoupper($TypeEngineKey).'='.$TableStatusRow[$TypeEngineKey];
						if (isset($TableStatusRow['Collation']) && !is_null($TableStatusRow['Collation']) && !empty($TableStatusRow['Collation'])) {
							$tablestructure .= ' COLLATE='.$TableStatusRow['Collation'];
						}
						if ($TableStatusRow['Auto_increment'] !== null) {
							$tablestructure .= ' AUTO_INCREMENT='.$TableStatusRow['Auto_increment'];
						}
					}
					$tablestructure .= ';'.LINE_TERMINATOR.LINE_TERMINATOR;
					$gDb->free_result($result_showcreatetable);

					$alltablesstructure .= str_replace(' ,', ',', $tablestructure);

				} // end table structure backup
			}
			if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
				bzwrite($bp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
			} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
				gzwrite($zp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
			} else {
				fwrite($fp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
			}
			
			$datastarttime = getmicrotime();
			OutputInformation('statusinfo', '');
			
			if ($_REQUEST['StartBackup'] != 'structure') {
				$processedrows    = 0;
				foreach ($SelectedTables as $dbname => $value) {
					@set_time_limit(60);
					$gDb->select_db($dbname);
					for ($t = 0; $t < count($SelectedTables[$dbname]); $t++) {
						$SQLquery  = 'SELECT *';
						$SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escape_string($SelectedTables[$dbname][$t]).BACKTICKCHAR;
						$result = $gDb->query($SQLquery);
						$rows[$t] = $gDb->num_rows($result);
						if ($rows[$t] > 0) {
							$tabledatadumpline = '# dumping data for '.$dbname.'.'.$SelectedTables[$dbname][$t].LINE_TERMINATOR;
							if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
								bzwrite($bp, $tabledatadumpline, strlen($tabledatadumpline));
							} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
								gzwrite($zp, $tabledatadumpline, strlen($tabledatadumpline));
							} else {
								fwrite($fp, $tabledatadumpline, strlen($tabledatadumpline));
							}
						}
						unset($fieldnames);
						for ($i = 0; $i < $gDb->num_fields($result); $i++) {
							$fieldnames[] = $gDb->field_name($result, $i);
						}
						if ($_REQUEST['StartBackup'] == 'complete') {
							$insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' ('.BACKTICKCHAR.implode(BACKTICKCHAR.', '.BACKTICKCHAR, $fieldnames).BACKTICKCHAR.') VALUES (';
						} else {
							$insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' VALUES (';
						}
						$currentrow       = 0;
						$thistableinserts = '';
						while ($row = $gDb->fetch_array($result)) {
							unset($valuevalues);
							foreach ($fieldnames as $key => $val) {
								if ($row[$key] === null) {

									$valuevalues[] = 'NULL';

								} else {

									switch ($RowTypes[$dbname][$SelectedTables[$dbname][$t]][$val]) {
										// binary data dump, two hex characters per byte
										case 'tinyblob':
										case 'blob':
										case 'mediumblob':
										case 'longblob':
											$data = $row[$key];
											$data_len = strlen($data);
											if ($HexBLOBs && $data_len) {
												$hexstring = '0x';
												for ($i = 0; $i < $data_len; $i++) {
													$hexstring .= str_pad(dechex(ord($data{$i})), 2, '0', STR_PAD_LEFT);
												}
												$valuevalues[] = $hexstring;
											} else {
												$valuevalues[] = QUOTECHAR.$gDb->escape_string($data).QUOTECHAR;
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
											$valuevalues[] = $gDb->escape_string($row[$key]);
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
											$valuevalues[] = QUOTECHAR.$gDb->escape_string($row[$key]).QUOTECHAR;
											break;
									}

								}
							}
							$thistableinserts .= $insertstatement.implode(', ', $valuevalues).');'.LINE_TERMINATOR;

							if (strlen($thistableinserts) >= BUFFER_SIZE) {
								if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
									bzwrite($bp, $thistableinserts, strlen($thistableinserts));
								} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
									gzwrite($zp, $thistableinserts, strlen($thistableinserts));
								} else {
									fwrite($fp, $thistableinserts, strlen($thistableinserts));
								}
								$thistableinserts = '';
							}
							if ((++$currentrow % STATS_INTERVAL) == 0) {
								@set_time_limit(60);
								if ($DHTMLenabled) {
									OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], '<b>'.htmlentities($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, ['.number_format(($currentrow / $rows[$t])*100).'%])</b>');
									$elapsedtime = getmicrotime() - $datastarttime;
									$percentprocessed = ($processedrows + $currentrow) / $overallrows;
									$overallprogress = 'Overall Progress: '.number_format($processedrows + $currentrow).' / '.number_format($overallrows).' ('.number_format($percentprocessed * 100, 1).'% done) ['.FormattedTimeRemaining($elapsedtime).' elapsed';
									if (($percentprocessed > 0) && ($percentprocessed < 1)) {
										$overallprogress .= ', '.FormattedTimeRemaining(abs($elapsedtime - ($elapsedtime / $percentprocessed))).' remaining';
									}
									$overallprogress .= ']';
									OutputInformation('topprogress', $overallprogress);
								}
							}
							if (($currentrow % MYSQL_RECONNECT_INTERVAL) == 0) {
								$gDb->close();
								if (!@$gDb->connect(DB_HOST, DB_USER, DB_PASS)) {
									mail(ADMIN_EMAIL, 'backupDB: FAILURE! Failed to connect to MySQL database (line '.__LINE__.')', 'Failed to reconnect to SQL database (row #'.$currentrow.') on line '.__LINE__.' in file '.@$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].LINE_TERMINATOR.$gDb->db_error());
									die('There was a problem connecting to the database:<br>'.LINE_TERMINATOR.$gDb->db_error());
								}
								$gDb->select_db($dbname);
							}
						}
						$gDb->free_result($result);
						if ($DHTMLenabled) {
							OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], htmlentities($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, [100%])');
							$processedrows += $rows[$t];
						}
						if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
							bzwrite($bp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
						} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
							gzwrite($zp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
						} else {
							fwrite($fp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
						}
					}
				}
			}
			
			$activateForeignKeys = 'SET FOREIGN_KEY_CHECKS=1;'.LINE_TERMINATOR.LINE_TERMINATOR;
			
			if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
				bzwrite($bp, $activateForeignKeys, strlen($activateForeignKeys));
			} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
				gzwrite($zp, $activateForeignKeys, strlen($activateForeignKeys));
			} else {
				fwrite($fp, $activateForeignKeys, strlen($activateForeignKeys));
			}
			
			if (OUTPUT_COMPRESSION_TYPE == 'bzip2') {
				bzclose($bp);
			} elseif (OUTPUT_COMPRESSION_TYPE == 'gzip') {
				gzclose($zp);
			} else {
				fclose($fp);
			}

			if (file_exists($newfullfilename)) {
				unlink($newfullfilename); // Windows won't allow overwriting via rename
			}
			rename($backupabsolutepath.$tempbackupfilename, $newfullfilename);
			
		} else {

			echo '<b>Warning:</b> failed to open '.$backupabsolutepath.$tempbackupfilename.' for writing!<br><br>';
			if (is_dir($backupabsolutepath)) {
				echo '<i>CHMOD 777</i> on the directory ('.htmlentities($backupabsolutepath).') should fix that.';
			} else {
				echo 'The specified directory does not exist: "'.htmlentities($backupabsolutepath).'"';
			}

		}

		// End original backupDB


echo '<p>'.$gL10n->get('BAC_BACKUP_COMPLETED', FormattedTimeRemaining(getmicrotime() - $starttime, 2)).'.</p>

<p>'.$gL10n->get('BAC_BACKUP_FILE').': <a href="'.$g_root_path.'/adm_program/administration/backup/backup_file_function.php?job=get_file&amp;filename='.basename($newfullfilename).'">'.basename($newfullfilename).'</a>
('.FileSizeNiceDisplay(filesize($newfullfilename), 2).')</p>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('BAC_BACK_TO_BACKUP_PAGE').'" title="'.$gL10n->get('BAC_BACK_TO_BACKUP_PAGE').'"/></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('BAC_BACK_TO_BACKUP_PAGE').'</a>
        </span>
    </li>
</ul>';

OutputInformation('cancel_link', '');
OutputInformation('topprogress', '');


require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>
