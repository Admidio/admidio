<?php
/******************************************************************************
 * Database interface to PostgreSQL database
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
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
    
    /** Read the last error from the database and call the parent method to display
     *  this error to the user.
     *  @param $code    Optional you could set a code that should be displayed to the user.
     *                  Per default the database error code will be read and displayed.
     *  @param $message Optional you could set a message that should be displayed to the user.
     *                  Per default the database error message will be read and displayed.
     *  @return Will exit the script and returns a html output with the error informations.
     */
    public function db_error($code = 0, $message = '')
    {
        if($code == 0)
        {
            parent::db_error(1, @pg_last_error());
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

    /** Fetch a result row as an associative array, a numeric array, or both.
     *  @param $result     The result resource that is being evaluated. This result comes from a call to query().
     *  @param $resultType Set the result type. Can contain @b ASSOC for an associative array, 
     *                     @b NUM for a numeric array or @b BOTH (Default).
     *  @return Returns an array that corresponds to the fetched row and moves the internal data pointer ahead. 
     */
    public function fetch_array($result = false, $resultType = 'BOTH')
    {
        $typeArray = array('BOTH' => PGSQL_BOTH, 'ASSOC' => PGSQL_ASSOC, 'NUM' => PGSQL_NUM);

        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        return pg_fetch_array($result, null, $typeArray[$resultType]);
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
    
    /** Returns the number of rows of the last executed statement.
     *  Therefore a valid result must exists or set as parameter.
     *  If no valid result exists the method will return 0.
     *  @param $result Optional a valid result of a executed sql statement. If no result is set
     *                 then the method will look for a result within the database object.
     *  @return Return the number of rows of the result of the sql statement.
     */
    public function num_rows($result = false)
    {
        if($result === false)
        {
            $result = $this->queryResult;
        }
        
        // no result then return 0
        if($result === false)
        {
            return 0;
        }
        else
        {
            return pg_num_rows($result);
        }
    }    
    
    /** Send a sql statement to the database that will be executed. If debug mode is set
     *  then this statement will be written to the error log. If it's a @b SELECT statement
     *  then also the number of rows will be logged. If an error occured the script will
     *  be terminated and the error with a backtrace will be send to the browser.
     *  @param $sql A string with the sql statement that should be executed in database.
     *  @param $throwError Default will be @b true and if an error the script will be terminated and
     *                     occured the error with a backtrace will be send to the browser. If set to 
     *                     @b false no error will be shown and the script will be continued.
     *  @return For @b SELECT statements a result resource will be returned and otherwise @b true. 
     *          If an error occured then @b false will be returned of the script was not terminated.
     */
    public function query($sql, $throwError = true)
    {
        global $gDebug;

		if(strpos(strtolower($sql), 'create table') !== false
		|| strpos(strtolower($sql), 'alter table') !== false)
		{
			if(strpos(strtolower($sql), 'create table') !== false)
			{
				// bei einem Create-Table-Statement ggf. vorhandene Tabellenoptionen von MySQL abgeschnitten werden
				$sql = substr(strtolower($sql), 0, strrpos($sql, ')') + 1);
			}

			// PostgreSQL doesn't know unsigned
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
		
        $this->queryResult = @pg_query($this->connectId, $sql);

        if($this->queryResult == false)
        {
            if($throwError == true)
            {
                return $this->db_error();
            }
        }
        elseif($gDebug == 1 && strpos(strtoupper($sql), 'SELECT') === 0)
        {
            // if debug modus then show number of selected rows
            error_log('Found rows: '.$this->num_rows());
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