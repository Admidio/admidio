<?php
/******************************************************************************
 * Datenbankschnittstelle zu einer MySql-Datenbank
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once(SERVER_PATH. "/adm_program/system/db/db.php");
 
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
        $this->utf8     = false;
        
        $this->connect_id = @mysql_connect($this->server, $this->user, $this->password, $new_connection);
        
        if($this->connect_id)
        {
            if (@mysql_select_db($this->dbname, $this->connect_id))
            {
                // MySql-Server-Version ermitteln und schauen, ob es UNICODE unterstuetzt
                $this->version = mysql_get_server_info($this->connect_id);

                if (version_compare($this->version, '4.1.3', '>='))
                {
                    $this->utf8 = true;
                    @mysql_query("SET NAMES 'utf8'", $this->connect_id);
                }

                return $this->connect_id;
            }
        }
        return false;
    }
    
    // setzt die urspruengliche DB wieder auf aktiv
    // alternativ kann auch eine andere DB uebergeben werden
    function setCurrentDB($database = "")
    {
        if(strlen($database) == 0)
        {
            $database = $this->dbname;
        }
        return mysql_select_db($database, $this->connect_id);
    }
    
    function query($sql, $encode = true)
    {
        global $g_debug;
        
        // im Debug-Modus werden alle SQL-Statements mitgeloggt
        if($g_debug == 1)
        {
            error_log($sql);
        }
        
        if($this->utf8 == false && $encode == true)
        {
            $sql = utf8_decode($sql);
        }
        
        $this->query_result = mysql_query($sql, $this->connect_id);

        if(!$this->query_result)
        {
            return $this->db_error();
        }

        return $this->query_result;
    }

    // result : Ergebniskennung
    // result_type : MYSQL_ASSOC fuer assoziatives Array, MYSQL_NUM fuer numerisches
    //               oder MYSQL_BOTH fuer beides
    function fetch_array($result = false, $result_type = MYSQL_BOTH)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        $values = mysql_fetch_array($result, $result_type);
        
        // Daten nun noch nach UTF8 konvertieren
        if(is_array($values) && $this->utf8 == false)
        {
            foreach($values as $key => $value)
            {
                // Blob-Felder duerfen nicht encodiert werden !!!
                if($key != "ses_blob" || $key != "usr_photo")
                {
                    $values[$key] = utf8_encode($value);
                }
            }
        }
        return $values;
    }

    function fetch_object($result = false)
    {
        if($result === false)
        {
            $result = $this->query_result;
        }
        
        $object = mysql_fetch_object($result);
        
        if($this->utf8 == false)
        {
            $values = get_object_vars($object);

            if(is_array($values))
            {
                foreach($values as $key => $value)
                {
                    // Blob-Felder duerfen nicht encodiert werden !!!
                    if($key != "ses_blob" || $key != "usr_photo")
                    {
                        $object->{$key} = utf8_encode($value);
                    }
                }
            }
        }
        return $object;
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
    
    // Gibt die MYSQL Version der Datenbank zurück
    function server_info()
    {
        return mysql_get_server_info();
    }

    // Escaped den mysql String
    function escape_string($string)
    {
        return mysql_real_escape_string($string);
    }

    // Gibt den Speicher für den Result wieder frei
    function free_result($result)
    {
        return mysql_free_result($result);
    }
    
    // Uebergibt Fehlernummer und Beschreibung an die uebergeordnete Fehlerbehandlung
    function db_error($code = 0, $message = '')
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
}
 
?>