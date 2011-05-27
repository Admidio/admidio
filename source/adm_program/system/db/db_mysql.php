<?php
/******************************************************************************
 * Datenbankschnittstelle zu einer MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. '/adm_program/system/db/db_common.php');
 
class DBMySql extends DBCommon
{
    // Verbindung zur Datenbank aufbauen    
    public function connect($sql_server, $sql_user, $sql_password, $sql_dbname, $new_connection = false)
    {
        $this->db_type   = 'mysql';
        $this->server    = $sql_server;
        $this->user      = $sql_user;
        $this->password  = $sql_password;
        $this->dbname    = $sql_dbname;
		$this->insert_id = 0;
        
        $this->connect_id = @mysql_connect($this->server, $this->user, $this->password, $new_connection);
        
        if($this->connect_id)
        {
            if (@mysql_select_db($this->dbname, $this->connect_id))
            {
                // MySql-Server-Version ermitteln und schauen, ob es UNICODE unterstuetzt
                $this->version = mysql_get_server_info($this->connect_id);

				// Verbindung zur DB in UTF8 aufbauen
				@mysql_query('SET NAMES \'utf8\'', $this->connect_id);
                
                // falls der Server die Joins begrenzt hat, kann dies mit diesem Statement aufgehoben werden
                @mysql_query('SET SQL_BIG_SELECTS=1', $this->connect_id);

                return $this->connect_id;
            }
        }
        return false;
    }

    // Bewegt den internen Ergebnis-Zeiger
    public function data_seek($result, $row_number)
    {
        return mysql_data_seek($result, $row_number);
    }   
    
    // Uebergibt Fehlernummer und Beschreibung an die uebergeordnete Fehlerbehandlung
    public function db_error($code = 0, $message = '')
    {
        if($code == 0)
        {
            if (!$this->connect_id)
            {
                parent::db_error(@mysql_errno(), @mysql_error());
            }
            else
            {
                parent::db_error(@mysql_errno($this->connect_id), @mysql_error($this->connect_id));
            }
        }
        else
        {
            parent::db_error($code, $message);
        }
    }

    // Escaped den mysql String
    public function escape_string($string)
    {
        return mysql_real_escape_string($string);
    }

    // Gibt den Speicher für den Result wieder frei
    public function fetch_assoc($result)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        return mysql_fetch_assoc($result);
    }

    // result : Ergebniskennung
    // result_type : MYSQL_ASSOC fuer assoziatives Array, MYSQL_NUM fuer numerisches
    //               oder MYSQL_BOTH fuer beides
    public function fetch_array($result = false, $result_type = MYSQL_BOTH)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_fetch_array($result, $result_type);
    }

    public function fetch_object($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_fetch_object($result);
    }

    // Liefert den Namen eines Feldes in einem Ergebnis
    public function field_name($result, $index)
    {
        return mysql_field_name($result, $index);
    }

    // Gibt den Speicher für den Result wieder frei
    public function free_result($result)
    {
        return mysql_free_result($result);
    }

    // Liefert die ID einer vorherigen INSERT-Operation
    public function insert_id()
    {
		$this->insert_id = mysql_insert_id($this->connect_id);
        return $this->insert_id;
    }
    
    // Liefert die Anzahl der Felder in einem Ergebnis
    public function num_fields($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_num_fields($result);
    }
    
    // Liefert die Anzahl der Datensaetze im Ergebnis
    public function num_rows($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_num_rows($result);
    }    
    
    public function query($sql)
    {
        global $g_debug;
		$this->insert_id = 0;
        
        // im Debug-Modus werden alle SQL-Statements mitgeloggt
        if($g_debug == 1)
        {
            error_log($sql);
        }

        $this->query_result = mysql_query($sql, $this->connect_id);

        if(!$this->query_result)
        {
            return $this->db_error();
        }

		// bei einem Insert-Statement noch die ID des eingefuegten DS ermitteln
		if(strpos(trim($sql), 'INSERT INTO') == 0)
		{
			$this->insert_id = mysql_insert_id($this->connect_id);
		}

        return $this->query_result;
    }

    public function select_db($database = '')
    {
        if(strlen($database) == 0)
        {
            $database = $this->dbname;
        }
        return mysql_select_db($database, $this->connect_id);
    }

    // Gibt die MYSQL Version der Datenbank zurück
    public function server_info()
    {
        return mysql_get_server_info();
    }

    // setzt die urspruengliche DB wieder auf aktiv
    // alternativ kann auch eine andere DB uebergeben werden
    public function setCurrentDB()
    {
        return $this->select_db($this->dbname);
    }  

	// This method delivers the columns and their properties of the passed variable as an array
	// The array has the following format:
	// array('Fieldname1' => array('serial' => '1', 'null' => '0', 'key' => '0', 'type' => 'integer'), 
	//       'Fieldname2' => ...)
    public function showColumns($table)
    {
		if(isset($this->db_structure[$table]) == false)
		{
			$columnProperties = array();

			$sql = 'SHOW COLUMNS FROM '.$table;
			$this->query($sql);
			
			while ($row = $this->fetch_array())
			{
				$columnProperties[$row['Field']]['serial'] = 0;
				$columnProperties[$row['Field']]['null']   = 0;
				$columnProperties[$row['Field']]['key']    = 0;
				
				if($row['Extra'] == 'auto_increment')
				{
					$columnProperties[$row['Field']]['serial'] = 1;
				}
				if($row['Null'] == 'YES')
				{
					$columnProperties[$row['Field']]['null']   = 1;
				}
				if($row['Key'] == 'PRI' || $row['Key'] == 'MUL')
				{
					$columnProperties[$row['Field']]['key'] = 1;
				}

				if(strpos($row['Type'], 'tinyint(1)') !== false)
				{
					$columnProperties[$row['Field']]['type'] = 'boolean';
				}
				elseif(strpos($row['Type'], 'smallint') !== false)
				{
					$columnProperties[$row['Field']]['type'] = 'smallint';
				}
				elseif(strpos($row['Type'], 'int') !== false)
				{
					$columnProperties[$row['Field']]['type'] = 'integer';
				}
				else
				{
					$columnProperties[$row['Field']]['type'] = $row['Type'];
				}
			}
			
			// safe array with table structure in class array
			$this->db_structure[$table] = $columnProperties;
		}
		
		return $this->db_structure[$table];
    }  
}
 
?>