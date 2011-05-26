<?php
/******************************************************************************
 * Datenbankschnittstelle zu einer Postgre-Datenbank
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. '/adm_program/system/db/db_common.php');
 
class DBPostgre extends DBCommon
{
    // Verbindung zur Datenbank aufbauen    
    public function connect($sql_server, $sql_user, $sql_password, $sql_dbname, $new_connection = false)
    {
        $this->layer      = 'postgre';
        $this->server     = $sql_server;
        $this->user       = $sql_user;
        $this->password   = $sql_password;
        $this->dbname     = $sql_dbname;
		$this->insert_id  = 0;		
		$connectionString = 'host='.$this->server.' port=5432 dbname='.$this->dbname.' user='.$this->user.' password='.$this->password;
		
		if($new_connection == true)
		{
			$this->connect_id = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
		}
		else
		{
			$this->connect_id = @pg_connect($connectionString);
		}
        
        if($this->connect_id)
        {
            // Postgre-Server-Version ermitteln
			$versionArray = pg_version($this->connect_id);
			$this->version = $versionArray['client'];

            return $this->connect_id;
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
    public function fetch_array($result = false, $result_type = PGSQL_BOTH)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return pg_fetch_array($result, null, $result_type);
    }

    public function fetch_object($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return pg_fetch_object($result);
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
		// insert_id wurde in query() entsprechend gesetzt
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
        
        return pg_num_rows($result);
    }    
    
    public function query($sql)
    {
        global $g_debug;

		if(strpos(trim(strtolower($sql)), 'create table') !== false)
		{
			// bei einem Create-Table-Statement ggf. vorhandene Tabellenoptionen von MySQL abgeschnitten werden
			$sql = substr(strtolower($sql), 0, strrpos($sql, ')') + 1);

			// Postgre kennt unsigned nicht
			$sql = str_replace('unsigned', '', $sql);

			// Boolean macht Probleme, da Postgre es als String behandelt
			$sql = str_replace('boolean', 'smallint', $sql);

			// Blobs sind in Postgre bytea Datentypen
			$sql = str_replace('blob', 'bytea', $sql);
			
			// Auto_Increment muss durch Serial ersetzt werden
			$posAutoIncrement = strpos($sql, 'auto_increment');
			if($posAutoIncrement > 0)
			{
				$posInteger = strrpos(substr($sql, 0, $posAutoIncrement), 'integer');
				$sql = substr($sql, 0, $posInteger).' serial '.substr($sql, $posAutoIncrement + 14);
			}
		}
		
		$sql = str_replace('"', '\'', $sql);
        
        // im Debug-Modus werden alle SQL-Statements mitgeloggt
        if($g_debug == 1)
        {
            error_log($sql);
        }
		
        $this->query_result = pg_query($this->connect_id, $sql);

        if(!$this->query_result)
        {
            return $this->db_error();
        }

		// bei einem Insert-Statement noch die ID des eingefuegten DS ermitteln
		if(strpos(trim(strtolower($sql)), 'insert into') !== false)
		{
			// bei einem Insert-Statement noch die ID des eingefuegten DS ermitteln
			$insert_query = pg_query('SELECT lastval()');
			$insert_row = pg_fetch_row($insert_query);
			$this->insert_id = $insert_row[0];
		}

        return $this->query_result;
    }

	// Postgre baut eine Verbdingung immer direkt zu einer einzelnen Datenbank auf
	// aus diesem Grund hat select_db keine Auswirkung
    public function select_db($database = '')
    {
        if(strlen($database) == 0)
        {
            $database = $this->dbname;
        }
        return $this->connect_id;
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

			$sql = 'SELECT column_name, column_default, is_nullable, data_type
					  FROM information_schema.columns WHERE table_name = \''.$table.'\'';
			$this->query($sql);
			
			while ($row = $this->fetch_array())
			{
				$columnProperties[$row['column_name']]['serial'] = 0;
				$columnProperties[$row['column_name']]['null']   = 0;
				$columnProperties[$row['column_name']]['key']    = 0;
				
				if(strpos($row['column_default'], 'nextval') !== false)
				{
					$columnProperties[$row['column_name']]['serial'] = 1;
				}
				if($row['is_nullable'] == 'YES')
				{
					$columnProperties[$row['column_name']]['null']   = 1;
				}
				/*if($row['Key'] == 'PRI' || $row['Key'] == 'MUL')
				{
					$columnProperties[$row['column_name']]['key'] = 1;
				}*/

				if(strpos($row['data_type'], 'timestamp') !== false)
				{
					$columnProperties[$row['column_name']]['type'] = 'timestamp';
				}
				elseif(strpos($row['data_type'], 'time') !== false)
				{
					$columnProperties[$row['column_name']]['type'] = 'time';
				}
				else
				{
					$columnProperties[$row['column_name']]['type'] = $row['data_type'];
				}
			}
			
			// safe array with table structure in class array
			$this->db_structure[$table] = $columnProperties;
		}
		
		return $this->db_structure[$table];
    }  
}
 
?>