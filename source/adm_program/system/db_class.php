<?php
/******************************************************************************
 * Allgemeine Datenbankschnittstelle
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
class DB
{
    var $layer;
    var $user;
    var $password;
    var $dbname;
    var $server;
    
    var $connect_id;    
    var $query_result;
    var $sql;
    var $transaction = false;
    
    // Modus der Transaktoin setzen (Inspiriert von phpBB)
    function sql_transaction($status = 'begin')
    {
        switch ($status)
        {
            case 'begin':
                // If we are within a transaction we will not open another one, 
                // but enclose the current one to not loose data (prevening auto commit)
                if ($this->transaction)
                {
                    $this->transactions++;
                    return true;
                }

                $result = $this->_transaction('begin');

                if (!$result)
                {
                    $this->db_error();
                }

                $this->transaction = true;
            break;

            case 'commit':
                // If there was a previously opened transaction we do not commit yet... 
                // but count back the number of inner transactions
                if ($this->transaction && $this->transactions)
                {
                    $this->transactions--;
                    return true;
                }

                $result = $this->_transaction('commit');

                if (!$result)
                {
                    $this->db_error();
                }

                $this->transaction = false;
                $this->transactions = 0;
            break;

            case 'rollback':
                $result = $this->_transaction('rollback');
                $this->transaction = false;
                $this->transactions = 0;
            break;

            default:
                $result = $this->_transaction($status);
            break;
        }

        return $result;
    }    
    
    function db_error()
    {
        global $g_root_path, $g_message, $g_preferences, $g_current_organization;

        if(headers_sent() == false && isset($g_preferences))
        {
            // Html-Kopf ausgeben
            $g_layout['title']  = "Datenbank-Fehler";
            require(SERVER_PATH. "/adm_program/layout/overall_header.php");       
        }

        $error = $this->_db_error();
        $backtrace = getBacktrace();
                    
        $error_string = "<div style=\"font-family: monospace;\">
                         <p><b>S Q L - E R R O R</b></p>
                         <p><b>CODE:</b> ". $error['code']. "</p>
                         ". $error['message']. "<br><br>
                         <b>B A C K T R A C E</b><br>
                         $backtrace
                         </div>";
        echo $error_string;
        
        if(headers_sent() == false && isset($g_preferences))
        {
            require(SERVER_PATH. "/adm_program/layout/overall_footer.php");       
        }
        
        exit();
    }    
}
 
?>