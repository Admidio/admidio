<?php
/**
 ***********************************************************************************************
 * Create the backup
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**********************************************************************************
 * Based on backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>
 * available at http://www.silisoftware.com/scripts/index.php?scriptname=backupDB
 *********************************************************************************/

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/backup.functions.php');
require(__DIR__ . '/../../system/login_valid.php');

// only administrators are allowed to create backups
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// module not available for other databases except MySQL
if (DB_ENGINE !== Database::PDO_ENGINE_MYSQL) {
    $gMessage->show($gL10n->get('SYS_BACKUP_ONLY_MYSQL'));
    // => EXIT
}

// set db in non-strict mode so that table names and field names get a single quote
// in ANSI mode they get double quote and than some dbs got errors during import
$gDb->queryPrepared('SET SQL_MODE = \'\'');

// Some Defines
define('ADMIN_EMAIL', $gSettingsManager->getString('email_administrator')); // eg: admin@example.com

define('BACKTICKCHAR', '`');
define('QUOTECHAR', '\'');
define('LINE_TERMINATOR', "\n");    // \n = UNIX; \r\n = Windows; \r = Mac
define('BUFFER_SIZE', 32768);   // in bytes
define('TABLES_PER_COL', 30);      // number of table names per column in partial table backup selection screen
define('STATS_INTERVAL', 500);     // number of records processed between each DHTML stats refresh
define('MYSQL_RECONNECT_INTERVAL', 100000);  // disconnect and reconnect to MySQL every <interval> rows, to prevent timeouts
define('OUTPUT_COMPRESSION_TYPE', 'gzip');  // 'bzip2', 'gzip', 'none'; best at "bzip2" for mysqldump-based backups, "gzip" for PHP-based backups
define('OUTPUT_COMPRESSION_LEVEL', 6);       // bzip2/gzip compression level (1=fastest,9=best)

// Some Config
$DHTMLenabled       = true;  // set $DHTMLenabled = FALSE to prevent JavaScript errors in incompatible browsers
                             // set $DHTMLenabled = TRUE to get the nice DHTML display in recent browsers
$dbNameInCreate     = true;  // if true: "CREATE TABLE `database`.`table`", if false: "CREATE TABLE `table`"
$CreateIfNotExists  = true;  // if true: "CREATE TABLE IF NOT EXISTS `database`.`table`", if false: "CREATE TABLE `database`.`table`"
$ReplaceInto        = false; // if true: "REPLACE INTO ", if false: "INSERT INTO "
$HexBLOBs           = true;  // if true: blobs get data dumped as hex string; if false: blobs get data dumped as escaped binary string
$SuppressHTMLoutput = (@$_REQUEST['nohtml'] ? true : false); // disable all output for running as a cron job
$Disable_mysqldump  = false; // LEAVE THIS AS "false"! If true, avoid use of "mysqldump" program to export databases which is *MUCH* *MUCH* faster than doing it row-by-row in PHP. If mysqldump is not available, will automatically fall back to slower row-by-row method. Highly recommended to leave this at "false" (i.e. do use mysqldump)
$backuptimestamp    = '.'.date('Y-m-d.His'); // timestamp
$backupabsolutepath = ADMIDIO_PATH . FOLDER_DATA . '/backup/'; // make sure to include trailing slash
$fileextension = ((OUTPUT_COMPRESSION_TYPE === 'bzip2') ? '.bz2' : ((OUTPUT_COMPRESSION_TYPE === 'gzip') ? '.gz' : ''));
$fullbackupfilename = 'db_backup'.$backuptimestamp.'.sql'.$fileextension;
$partbackupfilename = 'db_backup_partial'.$backuptimestamp.'.sql'.$fileextension;
$strubackupfilename = 'db_backup_structure'.$backuptimestamp.'.sql'.$fileextension;
$tempbackupfilename = 'db_backup.temp.sql'.$fileextension;

$NeverBackupDBtypes  = array('HEAP');
$CloseWindowOnFinish = false;

error_reporting(E_ALL);
@ini_set('display_errors', '1');

$_REQUEST['StartBackup'] = 'complete';
$TypeEngineKey = 'Engine';
$newfullfilename = $backupabsolutepath.$fullbackupfilename;
unset($SelectedTables, $tables);

// create a list with all tables with configured table prefix
$sql = 'SELECT table_name
          FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name LIKE ?
           AND table_type = "BASE TABLE"';
$statement = $gDb->queryPrepared($sql, array(DB_NAME, TABLE_PREFIX . '_%'));
$tables = array();
while ($tableName = $statement->fetchColumn()) {
    $tables[] = $tableName;
}

$sql = 'SELECT table_name
          FROM information_schema.tables
         WHERE table_schema = ?
           AND table_name LIKE ?
           AND table_type = "VIEW"';
$statement = $gDb->queryPrepared($sql, array(DB_NAME, TABLE_PREFIX . '_%'));
$views = $statement->fetchAll(PDO::FETCH_COLUMN, 0);


$SelectedTables[DB_NAME] = $tables;
$SelectedViews[DB_NAME] = $views;

$starttime = getmicrotime();

// Start original backupDB
switch (OUTPUT_COMPRESSION_TYPE) {
    case 'gzip': // fallthrough
    case 'none':
        // great
        break;
    case 'bzip2':
        if (!function_exists('bzopen')) {
            exit('ERROR: PHP-bzip2 support does not appear to be installed, please change OUTPUT_COMPRESSION_TYPE to one of "gzip" or "none"');
        }
        break;
    default:
        exit('ERROR: OUTPUT_COMPRESSION_TYPE ('.SecurityUtils::encodeHTML(OUTPUT_COMPRESSION_TYPE).') must be one of "bzip2", "gzip", "none"');
}
function open_backup_file($path)
{
    if (OUTPUT_COMPRESSION_TYPE === 'bzip2') {
        return @bzopen($path, 'w');
    } elseif (OUTPUT_COMPRESSION_TYPE === 'gzip') {
        return @gzopen($path, 'wb'.OUTPUT_COMPRESSION_LEVEL);
    } else {
        return @fopen($path, 'wb');
    }
}
function write_backup_code($fp, $str)
{
    if (OUTPUT_COMPRESSION_TYPE === 'bzip2') {
        bzwrite($fp, $str, strlen($str));
    } elseif (OUTPUT_COMPRESSION_TYPE === 'gzip') {
        gzwrite($fp, $str, strlen($str));
    } else {
        fwrite($fp, $str, strlen($str));
    }
}
function close_backup_file($fp)
{
    if (OUTPUT_COMPRESSION_TYPE === 'bzip2') {
        bzclose($fp);
    } elseif (OUTPUT_COMPRESSION_TYPE === 'gzip') {
        gzclose($fp);
    } else {
        fclose($fp);
    }
}


if ($fp = open_backup_file($backupabsolutepath.$tempbackupfilename)) {
    $fileheaderline  = '-- Admidio v'.ADMIDIO_VERSION_TEXT.' (https://www.admidio.org)'.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('SYS_BACKUP_FROM', array(date('d.m.Y'), date('G:i:s'))).LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('SYS_DATABASE').': '.DB_NAME.LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('SYS_USER').': '.$gCurrentUser->getValue('FIRST_NAME', 'database'). ' '. $gCurrentUser->getValue('LAST_NAME', 'database').LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= 'SET FOREIGN_KEY_CHECKS=0;'.LINE_TERMINATOR.LINE_TERMINATOR;

    write_backup_code($fp, $fileheaderline);

    // Begin original backupDB (removed table optimize and repair part because some user database had problems with this)

    OutputInformation('', '<br /><span id="topprogress" style="font-weight: bold;">Overall Progress:</span><br />');
    $overallrows = 0;
    foreach ($SelectedTables as $dbname => $value) {
        echo '<table class="tableList" cellspacing="0"><tr><th colspan="'.ceil(count($SelectedTables[$dbname]) / TABLES_PER_COL).'"><strong>'.SecurityUtils::encodeHTML($dbname).'</strong></th></tr><tr><td nowrap valign="top">';
        $tablecounter = 0;
        for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t) {
            if ($tablecounter++ >= TABLES_PER_COL) {
                echo '</td><td nowrap valign="top">';
                $tablecounter = 1;
            }
            $SQLquery  = 'SELECT COUNT(*) AS '.BACKTICKCHAR.'num'.BACKTICKCHAR;
            $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
            $countTablesStatement = $gDb->query($SQLquery);
            $row = $countTablesStatement->fetch();
            $rows[$t] = $row['num'];
            $overallrows += $rows[$t];
            echo '<span id="rows_'.$dbname.'_'.$SelectedTables[$dbname][$t].'">'.SecurityUtils::encodeHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records)</span><br />';
        }
        foreach ($SelectedViews[$dbname] as $view) {
            if ($tablecounter++ >= TABLES_PER_COL) {
                echo '</td><td nowrap valign="top">';
                $tablecounter = 1;
            }
            echo '<span id="view_'.$dbname.'_'.$view.'">'.SecurityUtils::encodeHTML($view).' (View)</span><br />';
        }
        echo '</td></tr></table><br />';
    }

    $alltablesstructure = '';
    foreach ($SelectedTables as $dbname => $value) {
        for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t) {
            PhpIniUtils::startNewExecutionTimeLimit(60);
            OutputInformation('statusinfo', 'Creating structure for <strong>'.SecurityUtils::encodeHTML($dbname.'.'.$SelectedTables[$dbname][$t]).'</strong>');

            $fieldnames = array();

            $SQLquery  = 'SHOW CREATE TABLE '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
            $showcreatetableStatement = $gDb->query($SQLquery);
            if ($showcreatetableStatement->rowCount() === 1) {
                $row = $showcreatetableStatement->fetch();
                $tablestructure = $row['Create Table'];

                $SQLquery  = 'SHOW FULL FIELDS';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
                $showfieldsStatement = $gDb->query($SQLquery);
                while ($row = $showfieldsStatement->fetch()) {
                    if (preg_match('#^[a-z]+#i', $row['Type'], $matches)) {
                        $RowTypes[$dbname][$SelectedTables[$dbname][$t]][$row['Field']] = $matches[0];
                    }
                    $fieldnames[] = $row['Field'];
                }
            } else {
                $structurelines = array();
                $SQLquery  = 'SHOW FULL FIELDS';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
                $showfieldsStatement = $gDb->query($SQLquery);
                while ($row = $showfieldsStatement->fetch()) {
                    $structureline  = BACKTICKCHAR.$row['Field'].BACKTICKCHAR;
                    $structureline .= ' '.$row['Type'];
                    if (isset($row['Collation']) && !is_null($row['Collation']) && !empty($row['Collation'])) {
                        $structureline .= ' COLLATE '.$row['Collation'];
                    }
                    switch (strtoupper($row['Null'])) {
                        case '1': // fallthrough
                        case 'YES':
                            $field_is_null = true;
                            break;
                        case '': // fallthrough
                        case '0': // fallthrough
                        case 'NO': // fallthrough
                        default:
                            $field_is_null = false;
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
                        } elseif ((strtolower($row['Type']) === 'timestamp') && (strtoupper($row['Default']) === 'CURRENT_TIMESTAMP')) {
                            $structureline .= ' DEFAULT '.$row['Default'];
                        } else {
                            $structureline .= ' DEFAULT \''.$row['Default'].'\'';
                        }
                    }
                    $structureline .= ($row['Extra'] ? ' '.strtoupper($row['Extra']) : '');
                    $structurelines[] = $structureline;

                    $fieldnames[] = $row['Field'];
                }

                $tablekeys    = array();
                $uniquekeys   = array();
                $fulltextkeys = array();

                $SQLquery  = 'SHOW INDEX';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
                $showindexStatement = $gDb->query($SQLquery);
                $INDICES = array();
                while ($row = $showindexStatement->fetch()) {
                    $INDICES[$row['Key_name']][$row['Seq_in_index']] = $row;
                }
                foreach ($INDICES as $index_name => $columndata) {
                    $structureline  = '';
                    if ($index_name === 'PRIMARY') {
                        $structureline .= 'PRIMARY ';
                    } elseif ((@$columndata[1]['Index_type'] === 'FULLTEXT') || ($columndata[1]['Comment'] === 'FULLTEXT')) {
                        $structureline .= 'FULLTEXT ';
                    } elseif (!$columndata[1]['Non_unique']) {
                        $structureline .= 'UNIQUE ';
                    }
                    $structureline .= 'KEY';
                    if ($index_name !== 'PRIMARY') {
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

                $SQLquery  = 'SHOW TABLE STATUS = '.$gDb->escapeString($SelectedTables[$dbname][$t]);
                $tablestatusStatement = $gDb->query($SQLquery);
                if (!($TableStatusRow = $tablestatusStatement->fetch())) {
                    exit('failed to execute "'.$SQLquery.'" on '.$dbname.'.'.$tablename);
                }

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

            $alltablesstructure .= str_replace(' ,', ',', $tablestructure);
        } // end table structure backup
    }
    write_backup_code($fp, $alltablesstructure);


    $datastarttime = getmicrotime();
    OutputInformation('statusinfo', '');

    if ($_REQUEST['StartBackup'] !== 'structure') {
        $processedrows = 0;
        foreach ($SelectedTables as $dbname => $value) {
            PhpIniUtils::startNewExecutionTimeLimit(60);
            for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t) {
                $SQLquery  = 'SELECT *';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
                $statement = $gDb->query($SQLquery);
                $rows[$t] = $statement->rowCount();
                if ($rows[$t] > 0) {
                    $tabledatadumpline = '# dumping data for '.$dbname.'.'.$SelectedTables[$dbname][$t].LINE_TERMINATOR;
                    write_backup_code($fp, $tabledatadumpline);
                }
                unset($fieldnames);
                $fieldnames = $gDb->getTableColumns($SelectedTables[$dbname][$t]);

                if ($_REQUEST['StartBackup'] === 'complete') {
                    $insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' ('.BACKTICKCHAR.implode(BACKTICKCHAR.', '.BACKTICKCHAR, $fieldnames).BACKTICKCHAR.') VALUES (';
                } else {
                    $insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' VALUES (';
                }
                $currentrow       = 0;
                $thistableinserts = '';
                while ($row = $statement->fetch(\PDO::FETCH_NUM)) {
                    unset($valuevalues);
                    foreach ($fieldnames as $key => $val) {
                        if ($row[$key] === null) {
                            $valuevalues[] = 'NULL';
                        } else {
                            switch ($RowTypes[$dbname][$SelectedTables[$dbname][$t]][$val]) {
                                // binary data dump, two hex characters per byte
                                case 'tinyblob': // fallthrough
                                case 'blob': // fallthrough
                                case 'mediumblob': // fallthrough
                                case 'longblob':
                                    $data = $row[$key];
                                    $data_len = strlen($data);
                                    if ($HexBLOBs && $data_len) {
                                        $hexstring = '0x';
                                        for ($i = 0; $i < $data_len; ++$i) {
                                            $hexstring .= str_pad(dechex(ord($data[$i])), 2, '0', STR_PAD_LEFT);
                                        }
                                        $valuevalues[] = $hexstring;
                                    } else {
                                        $valuevalues[] = $gDb->escapeString($data);
                                    }
                                    break;

                                // just the (numeric) value, not surrounded by quotes
                                case 'tinyint': // fallthrough
                                case 'smallint': // fallthrough
                                case 'mediumint': // fallthrough
                                case 'int': // fallthrough
                                case 'bigint': // fallthrough
                                case 'float': // fallthrough
                                case 'double': // fallthrough
                                case 'decimal': // fallthrough
                                case 'year':
                                    $valuevalues[] = $row[$key];
                                    break;

                                // value surrounded by quotes
                                case 'varchar': // fallthrough
                                case 'char': // fallthrough
                                case 'tinytext': // fallthrough
                                case 'text': // fallthrough
                                case 'mediumtext': // fallthrough
                                case 'longtext': // fallthrough
                                case 'enum': // fallthrough
                                case 'set': // fallthrough
                                case 'date': // fallthrough
                                case 'datetime': // fallthrough
                                case 'time': // fallthrough
                                case 'timestamp': // fallthrough
                                default:
                                    $valuevalues[] = $gDb->escapeString($row[$key]);
                            }
                        }
                    }
                    $thistableinserts .= $insertstatement.implode(', ', $valuevalues).');'.LINE_TERMINATOR;

                    if (strlen($thistableinserts) >= BUFFER_SIZE) {
                        write_backup_code($fp, $thistableinserts);
                        $thistableinserts = '';
                    }
                    if ((++$currentrow % STATS_INTERVAL) == 0) {
                        PhpIniUtils::startNewExecutionTimeLimit(60);
                        if ($DHTMLenabled) {
                            OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], '<strong>'.SecurityUtils::encodeHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, ['.number_format(($currentrow / $rows[$t])*100).'%])</strong>');
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
                        // reconnect to database
                        try {
                            $gDb = Database::createDatabaseInstance();
                        } catch (AdmException $e) {
                            OutputInformation('topprogress', $e->getText());
                            exit();
                        }
                    }
                }
                if ($DHTMLenabled) {
                    OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], SecurityUtils::encodeHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, [100%])');
                    $processedrows += $rows[$t];
                }
                write_backup_code($fp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR);
            }
        }
    }


    // Since views can depend on each other in any order, we need to
    // create temporary tables with the (potentially) required columns first,
    // which will later be dropped again and replaced by the actual view.
    // 1) Drop view from database
    // 2) create surrogate table to have columns available in case another view depends on it
    // After all surrogate tables are created:
    // 3) remove surrogate table
    // 4) Create actual view (CREATE VIEW command only, no data ex-/import required)
    $allviewsstructure = '';
    foreach ($SelectedViews as $dbname => $value) {
        foreach ($value as $view) {
            PhpIniUtils::startNewExecutionTimeLimit(60);
            OutputInformation('view_'.$dbname.'_'.$view, SecurityUtils::encodeHTML($view).' (Surrogate Table structure for view)');
            $viewstructure  = '-- ---------------------------------------------'.LINE_TERMINATOR;
            $viewstructure .= "--   Surrogate table for view `$view` => will be replaced below by the view definition. ".LINE_TERMINATOR;
            $viewstructure .= '--   This ensures that views can depend on each other.'.LINE_TERMINATOR;
            $viewstructure .= '--   '.LINE_TERMINATOR;

            $fieldnames = array();
            $SQLquery  = 'SHOW FULL FIELDS';
            $SQLquery .= ' FROM '.BACKTICKCHAR.$view.BACKTICKCHAR;
            $showfieldsStatement = $gDb->query($SQLquery);
            $structurelines = array();
            while ($row = $showfieldsStatement->fetch()) {
                $structurelines[] = BACKTICKCHAR.$row['Field'].BACKTICKCHAR.' '.$row['Type'];
            }
            $viewstructure .= 'DROP VIEW IF EXISTS '.($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$view.BACKTICKCHAR.';'.LINE_TERMINATOR;
            $viewstructure .= 'CREATE TABLE IF NOT EXISTS '.($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$view.BACKTICKCHAR.' ('.LINE_TERMINATOR;
            $viewstructure .= '  '.implode(','.LINE_TERMINATOR.'  ', $structurelines).LINE_TERMINATOR;
            $viewstructure .= ');'.LINE_TERMINATOR.LINE_TERMINATOR;

            $allviewsstructure .= $viewstructure;
        }
    }
    foreach ($SelectedViews as $dbname => $value) {
        foreach ($value as $view) {
            OutputInformation('view_'.$dbname.'_'.$view, SecurityUtils::encodeHTML($view).' (View definition)');
            $viewstructure  = '-- ---------------------------------------------'.LINE_TERMINATOR;
            $viewstructure .= "--   Definition of View `$view`".LINE_TERMINATOR;
            $viewstructure .= '--   '.LINE_TERMINATOR;

            $viewstructure .= 'DROP TABLE IF EXISTS '.($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$view.BACKTICKCHAR.';'.LINE_TERMINATOR;
            $viewstructure .= 'DROP VIEW IF EXISTS '.($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$view.BACKTICKCHAR.';'.LINE_TERMINATOR;

            $SQLquery  = 'SHOW CREATE VIEW '.BACKTICKCHAR.$view.BACKTICKCHAR;
            $showcreateviewStatement = $gDb->query($SQLquery);
            if ($showcreateviewStatement->rowCount() === 1) {
                $row = $showcreateviewStatement->fetch();
                $viewstructure .= $row['Create View'];
//                 echo '<span id="view_'.$dbname.'_'.$view.'">'.SecurityUtils::encodeHTML($view).' (View definition)</span><br />';
                OutputInformation('view_'.$dbname.'_'.$view, SecurityUtils::encodeHTML($view).' (View definition)');
            } else {
                // TODO: When do we get more than one row for the view definition?
            }
            $viewstructure .= ';'.LINE_TERMINATOR.LINE_TERMINATOR;
            $allviewsstructure .= $viewstructure;
        }
    }
    write_backup_code($fp, $allviewsstructure);


    $activateForeignKeys = 'SET FOREIGN_KEY_CHECKS=1;'.LINE_TERMINATOR.LINE_TERMINATOR;
    write_backup_code($fp, $activateForeignKeys);

    close_backup_file($fp);

    if (is_file($newfullfilename)) {
        unlink($newfullfilename); // Windows won't allow overwriting via rename
    }
    rename($backupabsolutepath.$tempbackupfilename, $newfullfilename);
} else {
    echo '<strong>Warning:</strong> failed to open '.$backupabsolutepath.$tempbackupfilename.' for writing!<br /><br />';
    if (is_dir($backupabsolutepath)) {
        echo '<em>CHMOD 777</em> on the directory ('.SecurityUtils::encodeHTML($backupabsolutepath).') should fix that.';
    } else {
        echo 'The specified directory does not exist: "'.SecurityUtils::encodeHTML($backupabsolutepath).'"';
    }
}
// End original backupDB

echo '<div class="alert alert-success form-alert"><i class="fas fa-check"></i><strong>'.
    $gL10n->get('SYS_BACKUP_COMPLETED', array(FormattedTimeRemaining(getmicrotime() - $starttime, 2))).'.</strong><br /><br />

'.$gL10n->get('SYS_BACKUP_FILE').'
<a class="admidio-icon-link" href="'.SecurityUtils::encodeUrl(ADMIDIO_URL.FOLDER_MODULES.'/backup/backup_file_function.php', array('job' => 'get_file', 'filename' => basename($newfullfilename))).'">
    <i class="fas fa-file-archive"></i>'.basename($newfullfilename).'</a>
('.FileSizeNiceDisplay(filesize($newfullfilename), 2).')</div>';

OutputInformation('cancel_link', '');
OutputInformation('topprogress', '');
