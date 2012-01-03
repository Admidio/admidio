<?php
/******************************************************************************
 * Database interface to PostgreSQL database
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. '/adm_program/system/db/db_common.php');
 
class DBPostgreSQL extends DBCommon
{
    // create database connection
    public function connect($sql_server, $sql_user, $sql_password, $sql_dbName, $new_connection = false)
    {
        $this->dbType    = 'postgresql';
        $this->server     = $sql_server;
        $this->user       = $sql_user;
        $this->password   = $sql_password;
        $this->dbName     = $sql_dbName;	
		$connectionString = 'host='.$this->server.' port=5432 dbname='.$this->dbName.' user='.$this->user.' password='.$this->password;
		
		if($new_connection == true)
		{
			$this->connectId = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
		}
		else
		{
			$this->connectId = @pg_connect($connectionString);
		}
        
        if($this->connectId)
        {
			// create connection to database in UTF8
			@pg_query('SET client_encoding = \'utf-8\'', $this->connectId);
			//@pg_query('SET bytea_output = \'escape\'', $this->connectId);

            return $this->connectId;
        }
        return false;
    }

    // Bewegt den internen Ergebnis-Zeiger
    public function data_seek($result, $rowNumber)
    {
        return pg_result_seek($result, $rowNumber);
    }   
    
    // Uebergibt Fehlernummer und Beschreibung an die uebergeordnete Fehlerbehandlung
    public function db_error($code = 0, $message = '')
    {
        if($code == 0)
        {
            if (!$this->connectId)
            {
                parent::db_error(0, @pg_last_error());
            }
            else
            {
                parent::db_error(0, @pg_result_error($this->connectId));
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
        return pg_escape_string($string);
    }

    // Gibt den Speicher für den Result wieder frei
    public function fetch_assoc($result)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        return pg_fetch_assoc($result);
    }

    // result : Ergebniskennung
    // resultType : MYSQL_ASSOC fuer assoziatives Array, MYSQL_NUM fuer numerisches
    //               oder MYSQL_BOTH fuer beides
    public function fetch_array($result = false, $resultType = PGSQL_BOTH)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        return pg_fetch_array($result, null, $resultType);
    }

    public function fetch_object($result = false)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        return pg_fetch_object($result);
    }

    // Liefert den Namen eines Feldes in einem Ergebnis
    public function field_name($result, $index)
    {
        return pg_field_name($result, $index);
    }

    // Gibt den Speicher für den Result wieder frei
    public function free_result($result)
    {
        return pg_free_result($result);
    }

    // Liefert die ID einer vorherigen INSERT-Operation
    public function insert_id()
    {
		$insert_query = pg_query('SELECT lastval()');
		$insert_row = pg_fetch_row($insert_query);
		return $insert_row[0];
    }
    
    // Liefert die Anzahl der Felder in einem Ergebnis
    public function num_fields($result = false)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        return pg_num_fields($result);
    }
    
    // Liefert die Anzahl der Datensaetze im Ergebnis
    public function num_rows($result = false)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        return pg_num_rows($result);
    }    
    
	// send sql to database server 
	// sql        : sql statement that should be executed
	// throwError : show error of sql statement and stop current script
    public function query($sql, $throwError = true)
    {
        global $gDebug;

		if(strpos(trim(strtolower($sql)), 'create table') !== false)
		{
			// bei einem Create-Table-Statement ggf. vorhandene Tabellenoptionen von MySQL abgeschnitten werden
			$sql = substr(strtolower($sql), 0, strrpos($sql, ')') + 1);

			// PostgreSQL kennt unsigned nicht
			$sql = str_replace('unsigned', '', $sql);

			// Boolean macht Probleme, da PostgreSQL es als String behandelt
			$sql = str_replace('boolean', 'smallint', $sql);

			// Blobs sind in PostgreSQL bytea Datentypen
			$sql = str_replace('blob', 'bytea', $sql);
			
			// Auto_Increment muss durch Serial ersetzt werden
			$posAutoIncrement = strpos($sql, 'auto_increment');
			if($posAutoIncrement > 0)
			{
				$posInteger = strrpos(substr($sql, 0, $posAutoIncrement), 'integer');
				$sql = substr($sql, 0, $posInteger).' serial '.substr($sql, $posAutoIncrement + 14);
			}
		}
		
        // if debug mode then log all sql statements
        if($gDebug == 1)
        {
            error_log($sql);
        }
		
        $this->queryResult = pg_query($this->connectId, $sql);

        if($this->queryResult == false && $throwError == true)
        {
            return $this->db_error();
        }

        return $this->queryResult;
    }

	// PostgreSQL baut eine Verbdingung immer direkt zu einer einzelnen Datenbank auf
	// aus diesem Grund hat select_db keine Auswirkung
    public function select_db($database = '')
    {
        if(strlen($database) == 0)
        {
            $database = $this->dbName;
        }
        return $this->connectId;
    }

    // returns the PostgreSQL version of the database
    public function server_info()
    {
		$version_query = pg_query('SELECT VERSION()');
		$row = pg_fetch_row($version_query);

		// the string (PostgreSQL 9.0.4, compiled by Visual C++ build 1500, 64-bit) must be separated
		$version_array  = explode(',', $row[0]);
		$version_array2 = explode(' ', $version_array[0]);
		return $version_array2[1];
    }

    // setzt die urspruengliche DB wieder auf aktiv
    public function setCurrentDB()
    {
        return $this->select_db($this->dbName);
    }
	
	// this method sets db specific properties of admidio
	// this settings are necessary because the database won't work with the default settings of admidio
	public function setDBSpecificAdmidioProperties($version = '')
	{
	    if(version_compare('2.3.0', $version) == 0 || strlen($version) == 0)
		{
			// soundex is not a default function in PostgreSQL
			$sql = 'UPDATE '.TBL_PREFERENCES.' SET prf_value = \'0\'
					 WHERE prf_name LIKE \'system_search_similar\'';
			$this->query($sql, $this->connectId);
		}
	}

	// This method delivers the columns and their properties of the passed variable as an array
	// The array has the following format:
	// array('Fieldname1' => array('serial' => '1', 'null' => '0', 'key' => '0', 'type' => 'integer'), 
	//       'Fieldname2' => ...)
    public function showColumns($table)
    {
		if(isset($this->dbStructure[$table]) == false)
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
			$this->dbStructure[$table] = $columnProperties;
		}
		
		return $this->dbStructure[$table];
    }
}
 
?>