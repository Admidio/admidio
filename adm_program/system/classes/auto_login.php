<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_auto_login
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Autologinobjekt zu erstellen.
 * Das Autologin kann ueber diese Klasse in der Datenbank verwaltet werden.
 *
 *****************************************************************************/

require_once(SERVER_PATH. "/adm_program/system/classes/table_access.php");

class AutoLogin extends TableAccess
{
    // Konstruktor
    function AutoLogin(&$db, $session = "")
    {
        $this->db            =& $db;
        $this->table_name     = TBL_AUTO_LOGIN;
        $this->column_praefix = "atl";
        
        if(strlen($session) > 0)
        {
            $this->getAutoLogin($session);
        }
        else
        {
            $this->clear();
        }
    }

    // AutoLogin mit der uebergebenen Session-ID aus der Datenbank auslesen
    function getAutoLogin($session)
    {
        $this->readData($session);
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function _save()
    {
        if($this->new_record)
        {
            // Insert
            global $g_current_organization;
            $this->setValue("atl_org_id", $g_current_organization->getValue("org_id"));
            $this->setValue("atl_last_login", date("Y-m-d H:i:s", time()));
            $this->setValue("atl_ip_address", $_SERVER['REMOTE_ADDR']);
            
            // Tabelle aufraeumen, wenn ein neuer Datensatz geschrieben wird
            $this->tableCleanup();
        }
        else
        {
            // Update
            $this->setValue("atl_last_login", date("Y-m-d H:i:s", time()));
            $this->setValue("atl_ip_address", $_SERVER['REMOTE_ADDR']);
        }
    }  
    
    // diese Funktion loescht Datensaetze aus der AutoLogin-Tabelle die nicht mehr gebraucht werden
    function tableCleanup()
    {
        // Zeitpunkt bestimmen, ab dem die Auto-Logins geloescht werden, mind. 1 Jahr alt
        $date_session_delete = time() - 60*60*24*365;
            
        $sql    = "DELETE FROM ". TBL_AUTO_LOGIN. " 
                    WHERE atl_last_login < '". date("Y.m.d H:i:s", $date_session_delete). "'";
        $this->db->query($sql);
    }    
}
?>