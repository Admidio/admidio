<?php
/**
 ***********************************************************************************************
 * Create the backup
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**********************************************************************************
 * Based on backupDB Version 1.2.7-201104261502
 * by James Heinrich <info@silisoftware.com>
 * available at http://www.silisoftware.com/scripts/index.php?scriptname=backupDB
 *********************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('backup.functions.php');

// only administrators are allowed to create backups
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    // => EXIT
}

// module not available for other databases except MySQL
if($gDbType !== 'mysql')
{
    $gMessage->show($gL10n->get('BAC_ONLY_MYSQL'));
    // => EXIT
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
$sql = 'SHOW TABLES LIKE \''.$g_tbl_praefix.'\_%\'';
$statement = $gDb->query($sql);
$tables = array();
while($table = $statement->fetch())
{
    $tables[] = $table[0];
}

$SelectedTables[$g_adm_db] = $tables;

$starttime = getmicrotime();

// Start original backupDB
switch (OUTPUT_COMPRESSION_TYPE)
{
    case 'gzip':
    case 'none':
        // great
        break;
    case 'bzip2':
        if (!function_exists('bzopen'))
        {
            exit('ERROR: PHP-bzip2 support does not appear to be installed, please change OUTPUT_COMPRESSION_TYPE to one of "gzip" or "none"');
        }
        break;
    default:
        exit('ERROR: OUTPUT_COMPRESSION_TYPE ('.noHTML(OUTPUT_COMPRESSION_TYPE).') must be one of "bzip2", "gzip", "none"');
        break;
}
if ((OUTPUT_COMPRESSION_TYPE === 'gzip'  && ($zp = @gzopen($backupabsolutepath.$tempbackupfilename, 'wb'.OUTPUT_COMPRESSION_LEVEL))) ||
    (OUTPUT_COMPRESSION_TYPE === 'bzip2' && ($bp = @bzopen($backupabsolutepath.$tempbackupfilename, 'w'))) ||
    (OUTPUT_COMPRESSION_TYPE === 'none'  && ($fp = @fopen($backupabsolutepath.$tempbackupfilename, 'wb'))))
{

    $fileheaderline  = '-- Admidio v'.ADMIDIO_VERSION_TEXT.' (https://www.admidio.org)'.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('BAC_BACKUP_FROM', date('d.m.Y'), date('G:i:s')).LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('SYS_DATABASE').': '.$g_adm_db.LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= '-- '.$gL10n->get('SYS_USER').': '.$gCurrentUser->getValue('FIRST_NAME', 'database'). ' '. $gCurrentUser->getValue('LAST_NAME', 'database').LINE_TERMINATOR.LINE_TERMINATOR;
    $fileheaderline .= 'SET FOREIGN_KEY_CHECKS=0;'.LINE_TERMINATOR.LINE_TERMINATOR;
    if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
    {
        bzwrite($bp, $fileheaderline, strlen($fileheaderline));
    }
    elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
    {
        gzwrite($zp, $fileheaderline, strlen($fileheaderline));
    }
    else
    {
        fwrite($fp, $fileheaderline, strlen($fileheaderline));
    }

    // Begin original backupDB (removed table optimize and repair part because some user database had problems with this)

    OutputInformation('', '<br /><span id="topprogress" style="font-weight: bold;">Overall Progress:</span><br />');
    $overallrows = 0;
    foreach ($SelectedTables as $dbname => $value)
    {
        echo '<table class="tableList" cellspacing="0"><tr><th colspan="'.ceil(count($SelectedTables[$dbname]) / TABLES_PER_COL).'"><strong>'.noHTML($dbname).'</strong></th></tr><tr><td nowrap valign="top">';
        $tablecounter = 0;
        for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t)
        {
            if ($tablecounter++ >= TABLES_PER_COL)
            {
                echo '</td><td nowrap valign="top">';
                $tablecounter = 1;
            }
            $SQLquery  = 'SELECT COUNT(*) AS '.BACKTICKCHAR.'num'.BACKTICKCHAR;
            $SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escapeString($SelectedTables[$dbname][$t]).BACKTICKCHAR;
            $countTablesStatement = $gDb->query($SQLquery);
            $row = $countTablesStatement->fetch();
            $rows[$t] = $row['num'];
            $overallrows += $rows[$t];
            echo '<span id="rows_'.$dbname.'_'.$SelectedTables[$dbname][$t].'">'.noHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records)</span><br />';
        }
        echo '</td></tr></table><br />';
    }

    $alltablesstructure = '';
    foreach ($SelectedTables as $dbname => $value)
    {
        for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t)
        {
            @set_time_limit(60);
            OutputInformation('statusinfo', 'Creating structure for <strong>'.noHTML($dbname.'.'.$SelectedTables[$dbname][$t]).'</strong>');

            $fieldnames = array();

            $SQLquery  = 'SHOW CREATE TABLE '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
            $showcreatetableStatement = $gDb->query($SQLquery);
            if ($showcreatetableStatement->rowCount() === 1)
            {
                $row = $showcreatetableStatement->fetch();
                $tablestructure = $row['Create Table'];

                $SQLquery  = 'SHOW FULL FIELDS';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR;
                $showfieldsStatement = $gDb->query($SQLquery);
                while ($row = $showfieldsStatement->fetch())
                {
                    if (preg_match('#^[a-z]+#i', $row['Type'], $matches))
                    {
                        $RowTypes[$dbname][$SelectedTables[$dbname][$t]][$row['Field']] = $matches[0];
                    }
                    $fieldnames[] = $row['Field'];
                }
            }
            else
            {
                $structurelines = array();
                $SQLquery  = 'SHOW FULL FIELDS';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escapeString($SelectedTables[$dbname][$t]).BACKTICKCHAR;
                $showfieldsStatement = $gDb->query($SQLquery);
                while ($row = $showfieldsStatement->fetch())
                {
                    $structureline  = BACKTICKCHAR.$row['Field'].BACKTICKCHAR;
                    $structureline .= ' '.$row['Type'];
                    if (isset($row['Collation']) && !is_null($row['Collation']) && !empty($row['Collation']))
                    {
                        $structureline .= ' COLLATE '.$row['Collation'];
                    }
                    switch (strtoupper($row['Null']))
                    {
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
                    if (!preg_match('#^(tiny|medium|long)?(text|blob)#i', $row['Type']))
                    {
                        if ($field_is_null && is_null($row['Default']))
                        {
                            $structureline .= ' DEFAULT NULL';
                        }
                        elseif ($field_is_null)
                        {
                            $structureline .= ' NULL';
                        }
                        else
                        {
                            $structureline .= ' NOT NULL';
                        }
                    }
                    preg_match('#^[a-z]+#i', $row['Type'], $matches);
                    $RowTypes[$dbname][$SelectedTables[$dbname][$t]][$row['Field']] = $matches[0];
                    if (isset($row['Default']) && !is_null($row['Default']))
                    {
                        if (preg_match('#^(tiny|medium|long)?(text|blob)#i', $row['Type']))
                        {
                            // no default values
                        }
                        elseif ((strtolower($row['Type']) === 'timestamp') && (strtoupper($row['Default']) === 'CURRENT_TIMESTAMP'))
                        {
                            $structureline .= ' DEFAULT '.$row['Default'];
                        }
                        else
                        {
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
                while ($row = $showindexStatement->fetch())
                {
                    $INDICES[$row['Key_name']][$row['Seq_in_index']] = $row;
                }
                foreach ($INDICES as $index_name => $columndata)
                {
                    $structureline  = '';
                    if ($index_name === 'PRIMARY')
                    {
                        $structureline .= 'PRIMARY ';
                    }
                    elseif ((@$columndata[1]['Index_type'] === 'FULLTEXT') || ($columndata[1]['Comment'] === 'FULLTEXT'))
                    {
                        $structureline .= 'FULLTEXT ';
                    }
                    elseif (!$columndata[1]['Non_unique'])
                    {
                        $structureline .= 'UNIQUE ';
                    }
                    $structureline .= 'KEY';
                    if ($index_name !== 'PRIMARY')
                    {
                        $structureline .= ' '.BACKTICKCHAR.$index_name.BACKTICKCHAR;
                    }
                    $structureline .= ' (';
                    $firstkeyname = true;
                    foreach ($columndata as $seq_in_index => $row)
                    {
                        if (!$firstkeyname)
                        {
                            $structureline .= ',';
                        }
                        $structureline .= BACKTICKCHAR.$row['Column_name'].BACKTICKCHAR;
                        if ($row['Sub_part'])
                        {
                            $structureline .= '('.$row['Sub_part'].')';
                        }
                        $firstkeyname = false;
                    }
                    $structureline .= ')';
                    $structurelines[] = $structureline;
                }

                $SQLquery  = 'SHOW TABLE STATUS LIKE "'.$gDb->escapeString($SelectedTables[$dbname][$t]).'"';
                $tablestatusStatement = $gDb->query($SQLquery);
                if (!($TableStatusRow = $tablestatusStatement->fetch()))
                {
                    exit('failed to execute "'.$SQLquery.'" on '.$dbname.'.'.$tablename);
                }

                $tablestructure  = 'CREATE TABLE '.($CreateIfNotExists ? 'IF NOT EXISTS ' : '').($dbNameInCreate ? BACKTICKCHAR.$dbname.BACKTICKCHAR.'.' : '').BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' ('.LINE_TERMINATOR;
                $tablestructure .= '  '.implode(','.LINE_TERMINATOR.'  ', $structurelines).LINE_TERMINATOR;
                $tablestructure .= ') '.strtoupper($TypeEngineKey).'='.$TableStatusRow[$TypeEngineKey];
                if (isset($TableStatusRow['Collation']) && !is_null($TableStatusRow['Collation']) && !empty($TableStatusRow['Collation']))
                {
                    $tablestructure .= ' COLLATE='.$TableStatusRow['Collation'];
                }
                if ($TableStatusRow['Auto_increment'] !== null)
                {
                    $tablestructure .= ' AUTO_INCREMENT='.$TableStatusRow['Auto_increment'];
                }
            }
            $tablestructure .= ';'.LINE_TERMINATOR.LINE_TERMINATOR;

            $alltablesstructure .= str_replace(' ,', ',', $tablestructure);

        } // end table structure backup
    }
    if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
    {
        bzwrite($bp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
    }
    elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
    {
        gzwrite($zp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
    }
    else
    {
        fwrite($fp, $alltablesstructure.LINE_TERMINATOR, strlen($alltablesstructure) + strlen(LINE_TERMINATOR));
    }

    $datastarttime = getmicrotime();
    OutputInformation('statusinfo', '');

    if ($_REQUEST['StartBackup'] !== 'structure')
    {
        $processedrows = 0;
        foreach ($SelectedTables as $dbname => $value)
        {
            @set_time_limit(60);
            for ($t = 0, $tMax = count($SelectedTables[$dbname]); $t < $tMax; ++$t)
            {
                $SQLquery  = 'SELECT *';
                $SQLquery .= ' FROM '.BACKTICKCHAR.$gDb->escapeString($SelectedTables[$dbname][$t]).BACKTICKCHAR;
                $statement = $gDb->query($SQLquery);
                $rows[$t] = $statement->rowCount();
                if ($rows[$t] > 0)
                {
                    $tabledatadumpline = '# dumping data for '.$dbname.'.'.$SelectedTables[$dbname][$t].LINE_TERMINATOR;
                    if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
                    {
                        bzwrite($bp, $tabledatadumpline, strlen($tabledatadumpline));
                    }
                    elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
                    {
                        gzwrite($zp, $tabledatadumpline, strlen($tabledatadumpline));
                    }
                    else
                    {
                        fwrite($fp, $tabledatadumpline, strlen($tabledatadumpline));
                    }
                }
                unset($fieldnames);
                $fieldnames = $gDb->getTableColumns($gDb->escapeString($SelectedTables[$dbname][$t]));

                if ($_REQUEST['StartBackup'] === 'complete')
                {
                    $insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' ('.BACKTICKCHAR.implode(BACKTICKCHAR.', '.BACKTICKCHAR, $fieldnames).BACKTICKCHAR.') VALUES (';
                }
                else
                {
                    $insertstatement = ($ReplaceInto ? 'REPLACE' : 'INSERT').' INTO '.BACKTICKCHAR.$SelectedTables[$dbname][$t].BACKTICKCHAR.' VALUES (';
                }
                $currentrow       = 0;
                $thistableinserts = '';
                while ($row = $statement->fetch())
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
                            switch ($RowTypes[$dbname][$SelectedTables[$dbname][$t]][$val])
                            {
                                // binary data dump, two hex characters per byte
                                case 'tinyblob':
                                case 'blob':
                                case 'mediumblob':
                                case 'longblob':
                                    $data = $row[$key];
                                    $data_len = strlen($data);
                                    if ($HexBLOBs && $data_len)
                                    {
                                        $hexstring = '0x';
                                        for ($i = 0; $i < $data_len; ++$i)
                                        {
                                            $hexstring .= str_pad(dechex(ord($data{$i})), 2, '0', STR_PAD_LEFT);
                                        }
                                        $valuevalues[] = $hexstring;
                                    }
                                    else
                                    {
                                        $valuevalues[] = QUOTECHAR.$gDb->escapeString($data).QUOTECHAR;
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
                                    $valuevalues[] = $gDb->escapeString($row[$key]);
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
                                    $valuevalues[] = QUOTECHAR.$gDb->escapeString($row[$key]).QUOTECHAR;
                                    break;
                            }

                        }
                    }
                    $thistableinserts .= $insertstatement.implode(', ', $valuevalues).');'.LINE_TERMINATOR;

                    if (strlen($thistableinserts) >= BUFFER_SIZE)
                    {
                        if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
                        {
                            bzwrite($bp, $thistableinserts, strlen($thistableinserts));
                        }
                        elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
                        {
                            gzwrite($zp, $thistableinserts, strlen($thistableinserts));
                        }
                        else
                        {
                            fwrite($fp, $thistableinserts, strlen($thistableinserts));
                        }
                        $thistableinserts = '';
                    }
                    if ((++$currentrow % STATS_INTERVAL) == 0)
                    {
                        @set_time_limit(60);
                        if ($DHTMLenabled)
                        {
                            OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], '<strong>'.noHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, ['.number_format(($currentrow / $rows[$t])*100).'%])</strong>');
                            $elapsedtime = getmicrotime() - $datastarttime;
                            $percentprocessed = ($processedrows + $currentrow) / $overallrows;
                            $overallprogress = 'Overall Progress: '.number_format($processedrows + $currentrow).' / '.number_format($overallrows).' ('.number_format($percentprocessed * 100, 1).'% done) ['.FormattedTimeRemaining($elapsedtime).' elapsed';
                            if (($percentprocessed > 0) && ($percentprocessed < 1))
                            {
                                $overallprogress .= ', '.FormattedTimeRemaining(abs($elapsedtime - ($elapsedtime / $percentprocessed))).' remaining';
                            }
                            $overallprogress .= ']';
                            OutputInformation('topprogress', $overallprogress);
                        }
                    }
                    if (($currentrow % MYSQL_RECONNECT_INTERVAL) == 0)
                    {
                        // reconnect to database
                        try
                        {
                            $gDb = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
                        }
                        catch(AdmException $e)
                        {
                            OutputInformation('topprogress', $e->getText());
                            exit();
                        }
                    }
                }
                if ($DHTMLenabled)
                {
                    OutputInformation('rows_'.$dbname.'_'.$SelectedTables[$dbname][$t], noHTML($SelectedTables[$dbname][$t]).' ('.number_format($rows[$t]).' records, [100%])');
                    $processedrows += $rows[$t];
                }
                if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
                {
                    bzwrite($bp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
                }
                elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
                {
                    gzwrite($zp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
                }
                else
                {
                    fwrite($fp, $thistableinserts.LINE_TERMINATOR.LINE_TERMINATOR, strlen($thistableinserts) + strlen(LINE_TERMINATOR) + strlen(LINE_TERMINATOR));
                }
            }
        }
    }

    $activateForeignKeys = 'SET FOREIGN_KEY_CHECKS=1;'.LINE_TERMINATOR.LINE_TERMINATOR;

    if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
    {
        bzwrite($bp, $activateForeignKeys, strlen($activateForeignKeys));
    }
    elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
    {
        gzwrite($zp, $activateForeignKeys, strlen($activateForeignKeys));
    }
    else
    {
        fwrite($fp, $activateForeignKeys, strlen($activateForeignKeys));
    }

    if (OUTPUT_COMPRESSION_TYPE === 'bzip2')
    {
        bzclose($bp);
    }
    elseif (OUTPUT_COMPRESSION_TYPE === 'gzip')
    {
        gzclose($zp);
    }
    else
    {
        fclose($fp);
    }

    if (is_file($newfullfilename))
    {
        unlink($newfullfilename); // Windows won't allow overwriting via rename
    }
    rename($backupabsolutepath.$tempbackupfilename, $newfullfilename);
}
else
{
    echo '<strong>Warning:</strong> failed to open '.$backupabsolutepath.$tempbackupfilename.' for writing!<br /><br />';
    if (is_dir($backupabsolutepath))
    {
        echo '<em>CHMOD 777</em> on the directory ('.noHTML($backupabsolutepath).') should fix that.';
    }
    else
    {
        echo 'The specified directory does not exist: "'.noHTML($backupabsolutepath).'"';
    }
}
// End original backupDB

echo '<div class="alert alert-success form-alert"><span class="glyphicon glyphicon-ok"></span><strong>'.
    $gL10n->get('BAC_BACKUP_COMPLETED', FormattedTimeRemaining(getmicrotime() - $starttime, 2)).'.</strong><br /><br />

'.$gL10n->get('BAC_BACKUP_FILE').' <a href="'.ADMIDIO_URL.FOLDER_MODULES.'/backup/backup_file_function.php?job=get_file&amp;filename='.basename($newfullfilename).'">'.basename($newfullfilename).'</a>
('.FileSizeNiceDisplay(filesize($newfullfilename), 2).')</div>';

OutputInformation('cancel_link', '');
OutputInformation('topprogress', '');
