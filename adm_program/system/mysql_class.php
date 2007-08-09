<?php
/******************************************************************************
 * Datenbankschnittstelle zu einer MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. "/adm_program/system/db_class.php");
 
class MySqlDB extends DB
{
    // Verbindung zur Datenbank aufbauen    
    function connect($sql_server, $sql_user, $sql_password, $sql_dbname, $new_connection = false)
    {
        $this->layer    = "mysql";
        $this->server   = $sql_server;
        $this->user     = $sql_user;
        $this->password = $sql_password;
        $this->dbname   = $sql_dbname;
        
        $this->connect_id = @mysql_connect($this->server, $this->user, $this->password, $new_connection);
        
        if($this->connect_id)
        {
            if (@mysql_select_db($this->dbname, $this->connect_id))
            {
                // MySql-Server-Version ermitteln und schauen, ob es UNICODE unterstuetzt
                $this->mysql_version = mysql_get_server_info($this->connect_id);
/*
                if (version_compare($this->mysql_version, '4.1.3', '>='))
                {
                    @mysql_query("SET NAMES 'utf8'", $this->connect_id);
                }
*/
                return $this->connect_id;
            }
        }
        return $this->db_error();
    }
    
    function select_db($database)
    {
        return mysql_select_db($database, $this->connect_id);
    }
    
    function query($sql)
    {
        global $g_debug;
        
        // im Debug-Modus werden alle SQL-Statements mitgeloggt
        if($g_debug)
        {
            error_log($sql);
        }
        
        $this->query_result = mysql_query($sql, $this->connect_id);

        if(!$this->query_result)
        {
            return $this->db_error();
        }

        return $this->query_result;
    }

    // result : Ergebniskennung
    // result_type : ASSOC fuer assoziatives Array, MYSQL_NUM fuer numerisches 
    //               oder MYSQL_BOTH fuer beides
    function fetch_array($result = false, $result_type = MYSQL_BOTH)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_fetch_array($result, $result_type);
    }

    function fetch_object($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_fetch_object($result);
    }

    // Liefert die ID einer vorherigen INSERT-Operation
    function insert_id()
    {
        return mysql_insert_id($this->connect_id);
    }
    
    // Liefert die Anzahl der Datensaetze im Ergebnis
    function num_rows($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_num_rows($result);
    }    
    
    // Liefert die Anzahl der Felder in einem Ergebnis
    function num_fields($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        return mysql_num_fields($result);
    }
    
    // Liefert den Namen eines Feldes in einem Ergebnis
    function field_name($result, $index)
    {
        return mysql_field_name($result, $index);
    }

    // Bewegt den internen Ergebnis-Zeiger
    function data_seek($result, $row_number)
    {
        return mysql_data_seek($result, $row_number);
    }
    
    // Modus einer Transaktion setzen
    // diese Funktion wird aus transaction() aufgerufen
    function _transaction($status = 'begin')
    {
        switch ($status)
        {
            case 'begin':
                return mysql_query('BEGIN', $this->connect_id);
            break;

            case 'commit':
                return mysql_query('COMMIT', $this->connect_id);
            break;

            case 'rollback':
                return mysql_query('ROLLBACK', $this->connect_id);
            break;
        }

        return true;
    }    
    
    // gibt ein Array mit Fehlernummer und Beschreibung zurueck    
    // diese Funktion wird aus db_error() aufgerufen
    function _db_error()
    {
        if (!$this->connect_id)
        {
            return array(
                'message'   => @mysql_error(),
                'code'      => @mysql_errno()
            );
        }

        return array(
            'message'   => @mysql_error($this->connect_id),
            'code'      => @mysql_errno($this->connect_id)
        );
    }   
}
 
?>