<?php
/******************************************************************************
 * Allgemeine Datenbankschnittstelle
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
class DB
{
    public $user;
    public $password;
    public $dbname;
    public $server;

    protected $layer;
    protected $version;    
    protected $connect_id;    
    protected $query_result;
    protected $sql;
    protected $transactions = 0;
    
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
    
    // Ausgabe der Datenbank-Fehlermeldung
    public function db_error($code = 0, $message = '')
    {
        global $g_root_path, $g_message, $g_preferences, $g_current_organization, $g_debug, $g_l10n;

        $backtrace = getBacktrace();

        // Rollback bei einer offenen Transaktion
        if($this->transactions > 0)
        {
            $this->endTransaction(true);
        }        

        if(headers_sent() == false && isset($g_preferences) && defined('THEME_SERVER_PATH'))
        {
            // Html-Kopf ausgeben
            $g_layout['title']  = $g_l10n->get('SYS_DATABASE_ERROR');
            require(THEME_SERVER_PATH. '/overall_header.php');       
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
        if($g_debug == 1)
        {
            error_log($code. ': '. $message);
        }
        
        if(headers_sent() == false && isset($g_preferences) && defined('THEME_SERVER_PATH'))
        {
            require(THEME_SERVER_PATH. '/overall_footer.php');       
        }
        
        exit();
    }
}
 
?>