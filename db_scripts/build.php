<?php

// Demo-DB neu erstellen

include("../config.php");
include("mysql.php");

define('SERVER_PATH', substr(__FILE__, 0, strpos(__FILE__, "db_scripts")-1));

// Teile dieser Funktion sind von get_backtrace aus phpBB3
// Return a nicely formatted backtrace (parts from the php manual by diz at ysagoon dot com)

function getBacktrace()
{
    //global $phpbb_root_path;

    $output = '<div style="font-family: monospace;">';
    $backtrace = debug_backtrace();
    //$path = phpbb_realpath($phpbb_root_path);
    $path = SERVER_PATH;

    foreach ($backtrace as $number => $trace)
    {
        // We skip the first one, because it only shows this file/function
        if ($number == 0)
        {
            continue;
        }

        // Strip the current directory from path
        if (empty($trace['file']))
        {
            $trace['file'] = '';
        }
        else
        {
            $trace['file'] = str_replace(array($path, '\\'), array('', '/'), $trace['file']);
            $trace['file'] = substr($trace['file'], 1);
        }
        $args = array();

        // If include/require/include_once is not called, do not show arguments - they may contain sensible information
        if (!in_array($trace['function'], array('include', 'require', 'include_once')))
        {
            unset($trace['args']);
        }
        else
        {
            // Path...
            if (!empty($trace['args'][0]))
            {
                $argument = htmlentities($trace['args'][0]);
                $argument = str_replace(array($path, '\\'), array('', '/'), $argument);
                $argument = substr($argument, 1);
                $args[] = "'{$argument}'";
            }
        }

        $trace['class'] = (!isset($trace['class'])) ? '' : $trace['class'];
        $trace['type'] = (!isset($trace['type'])) ? '' : $trace['type'];

        $output .= '<br />';
        $output .= '<b>FILE:</b> ' . htmlentities($trace['file']) . '<br />';
        $output .= '<b>LINE:</b> ' . ((!empty($trace['line'])) ? $trace['line'] : '') . '<br />';

        $output .= '<b>CALL:</b> ' . htmlentities($trace['class'] . $trace['type'] . $trace['function']) . '(' . ((sizeof($args)) ? implode(', ', $args) : '') . ')<br />';
    }
    $output .= '</div>';
    return $output;
}


// setzt die Ausfuehrungszeit des Scripts auf 2 Min., da hier teilweise sehr viel gemacht wird
// allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
@set_time_limit(240); 

echo "Beginne mit der Installation ...<br />";

 // Verbindung zu Datenbank herstellen
$db = new MySqlDB();
$connection = $db->connect($g_adm_srv, $g_adm_usr, $g_adm_pw, $g_adm_db);

$filename = "db.sql";
$file     = fopen($filename, "r")
			or showPage("Die Datei <strong>db.sql</strong> konnte nicht im Verzeichnis <strong>adm_install/db_scripts</strong> gefunden werden.", "installation.php?mode=5", "back.png", "Zurück");
$content  = fread($file, filesize($filename));
$sql_arr  = explode(";", $content);
fclose($file);

echo "Datei db.sql einlesen ...<br />";

foreach($sql_arr as $sql)
{
	if(strlen(trim($sql)) > 0)
	{
		// Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
		$sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
		$db->query($sql);
	}
}


$filename = "data.sql";
$file     = fopen($filename, "r")
			or showPage("Die Datei <strong>db.sql</strong> konnte nicht im Verzeichnis <strong>adm_install/db_scripts</strong> gefunden werden.", "installation.php?mode=5", "back.png", "Zurück");
$content  = fread($file, filesize($filename));
$sql_arr  = explode(";", $content);
fclose($file);

echo "Datei data.sql einlesen ...<br />";

foreach($sql_arr as $sql)
{
	if(strlen(trim($sql)) > 0)
	{
		// Praefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
		$sql = str_replace("%PRAEFIX%", $g_tbl_praefix, $sql);
		$db->query($sql);
	}
}


echo "Installation erfolgreich !<br />";

?>