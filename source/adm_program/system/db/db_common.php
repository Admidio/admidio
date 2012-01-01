<?php
/******************************************************************************
 * Common database interface
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
class DBCommon
{
    public $dbType;
    public $user;
    public $password;
    public $dbName;
    public $server;

    protected $name;		// Name of database system like "MySQL"
    protected $version;    
    protected $minVersion;    
    protected $connectId;
    protected $queryResult;
    protected $sql;
    protected $transactions = 0;
	protected $dbStructure;	// array with arrays of every table with their structure
    
    // Ausgabe der Datenbank-Fehlermeldung
    public function db_error($code = 0, $message = '')
    {
        global $g_root_path, $gMessage, $gPreferences, $gCurrentOrganization, $gDebug, $gL10n;

        $backtrace = $this->getBacktrace();

        // Rollback bei einer offenen Transaktion
        if($this->transactions > 0)
        {
            $this->endTransaction(true);
        }        

        if(headers_sent() == false && isset($gPreferences) && defined('THEME_SERVER_PATH'))
        {
            // Html-Kopf ausgeben
            $gLayout['title']  = $gL10n->get('SYS_DATABASE_ERROR');
            require(SERVER_PATH. '/adm_program/system/overall_header.php');       
        }
        
        // Ausgabe des Fehlers an Browser
        $error_string = '<div style="font-family: monospace;">
                         <p><b>S Q L - E R R O R</b></p>
                         <p><b>CODE:</b> '.$code.'</p>
                         '.$message.'<br /><br />
                         <b>B A C K T R A C E</b><br />
                         '.$backtrace.'
                         </div>';
        echo $error_string;
        
        // ggf. Ausgabe des Fehlers in Log-Datei
        if($gDebug == 1)
        {
            error_log($code. ': '. $message);
        }
        
        if(headers_sent() == false && isset($gPreferences) && defined('THEME_SERVER_PATH'))
        {
            require(SERVER_PATH. '/adm_program/system/overall_footer.php');       
        }
        
        exit();
    }

    public function endTransaction($rollback = false)
    {
        if($rollback)
        {
            $result = $this->query('ROLLBACK');
        }
        else
        {
            // If there was a previously opened transaction we do not commit yet... 
            // but count back the number of inner transactions
            if ($this->transactions > 1)
            {
                $this->transactions--;
                return true;
            }

            $result = $this->query('COMMIT');

            if (!$result)
            {
                $this->db_error();
            }
        }
        $this->transactions = 0;
        return $result;
    }
	
	// Teile dieser Funktion sind von get_backtrace aus phpBB3
	// Return a nicely formatted backtrace (parts from the php manual by diz at ysagoon dot com)
	protected function getBacktrace()
	{
		$output = '<div style="font-family: monospace;">';
		$backtrace = debug_backtrace();
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
	
	// returns the minimum required version of the database
	public function getName()
	{
		if(strlen($this->name) == 0)
		{
			$xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/db/databases.xml', 0, true);
			$node = $xmlDatabases->xpath("/databases/database[@id='".$this->dbType."']/name");
			$this->name = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
		}
		return $this->name;		
	}
	
	// returns the minimum required version of the database
	public function getMinVersion()
	{
		if(strlen($this->minVersion) == 0)
		{
			$xmlDatabases = new SimpleXMLElement(SERVER_PATH.'/adm_program/system/db/databases.xml', 0, true);
			$node = $xmlDatabases->xpath("/databases/database[@id='".$this->dbType."']/minversion");
			$this->minversion = (string)$node[0]; // explicit typcasting because of problem with simplexml and sessions
		}
		return $this->minVersion;		
	}

	// returns the version of the database
	public function getVersion()
	{
		if(strlen($this->version) == 0)
		{
			$this->version = $this->server_info();
		}
		return $this->version;
	}
	
    // Modus der Transaktoin setzen (Inspiriert von phpBB)
    public function startTransaction()
    {
        // If we are within a transaction we will not open another one, 
        // but enclose the current one to not loose data (prevening auto commit)
        if ($this->transactions > 0)
        {
            $this->transactions++;
            return true;
        }

        $result = $this->query('START TRANSACTION');

        if (!$result)
        {
            $this->db_error();
        }

        $this->transactions = 1;
        return $result;
    }
}
 
?>