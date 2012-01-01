<?php
/******************************************************************************
 * Class manages access to database table adm_members
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Memberobjekt zu erstellen. 
 * Eine Mitgliedschaft kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * startMembership($rol_id, $usr_id, $leader = "")
 *                      - Methode setzt alle notwendigen Daten um eine 
 *                        Mitgliedschaft zu beginnen bzw. zu aktualisieren
 * stopMembership($rol_id, $usr_id)
 *                      - Methode setzt alle notwendigen Daten um eine 
 *                        Mitgliedschaft zu beginnen
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableMembers extends TableAccess
{
    // Konstruktor
    public function __construct(&$db)
    {
        parent::__construct($db, TBL_MEMBERS, 'mem');
    }
    

    // liest den Datensatz mit den ids rol_id und usr_id ein
    // die Methode gibt true zurueck, wenn ein DS gefunden wurde, andernfalls false
    // ids : Array mit den Schl�sseln rol_id und usr_id  Bsp.: array('rol_id'=>xy, 'usr_id'=>yx)
    // sql_where_condition : optional eine individuelle WHERE-Bedinugung fuer das SQL-Statement
    // sql_additioinal_tables : wird nicht verwendet (ben�tigt wegen Vererbung)
    public function readData($ids, $sql_where_condition = '', $sql_additional_tables = '')
    {
        $returnCode = false;

        if(is_array($ids) && is_numeric($ids['rol_id']) && is_numeric($ids['usr_id']))
        {
            if(strlen($sql_where_condition) > 0)
            {
                $sql_where_condition .= ' AND ';
            }
            $sql_where_condition .= '    mem_rol_id = '.$ids['rol_id'].'
                                     AND mem_usr_id = '.$ids['usr_id'];
            $returnCode = parent::readData(0, $sql_where_condition);

            $this->setValue('mem_rol_id', $ids['rol_id']);
            $this->setValue('mem_usr_id', $ids['usr_id']);
        }
        return $returnCode;
    }       

    // Speichert die Mitgliedschaft und aktualisiert das
    public function save($updateFingerPrint = true)
    {
        global $gCurrentSession;
        $fields_changed = $this->columnsValueChanged;
        
        parent::save($updateFingerPrint);
        
        if($fields_changed && is_object($gCurrentSession))
        {
            // einlesen des entsprechenden Userobjekts, da Aenderungen 
            // bei den Rollen vorgenommen wurden 
            $gCurrentSession->renewUserObject($this->getValue('mem_usr_id'));
        }
    } 
    
    // Methode setzt alle notwendigen Daten um eine Mitgliedschaft zu beginnen bzw. zu aktualisieren
    public function startMembership($rol_id, $usr_id, $leader = '')
    {
        if($this->getValue('mem_rol_id') != $rol_id
        || $this->getValue('mem_usr_id') != $usr_id)
        {
            $this->readData(array('rol_id' => $rol_id, 'usr_id' => $usr_id));
        }

        // Beginn nicht ueberschreiben, wenn schon existiert
        if(strcmp($this->getValue('mem_begin', 'Y-m-d'), DATE_NOW) > 0
        || $this->new_record)
        {
            $this->setValue('mem_begin', DATE_NOW);
        }

        // Leiter sollte nicht ueberschrieben werden, wenn nicht uebergeben wird
        if(strlen($leader) == 0)
        {
            if($this->new_record == true)
            {
                $this->setValue('mem_leader', 0);
            }
        }
        else
        {
            $this->setValue('mem_leader', $leader);
        }

        $this->setValue('mem_end', '9999-12-31');
        
        if($this->columnsValueChanged)
        {
            $this->save();
            return true;
        }
        return false;
    }

    // Methode setzt alle notwendigen Daten um eine Mitgliedschaft zu beenden
    public function stopMembership($rol_id, $usr_id)
    {
        if($this->getValue('mem_rol_id') != $rol_id
        || $this->getValue('mem_usr_id') != $usr_id)
        {
            $this->readData(array('rol_id' => $rol_id, 'usr_id' => $usr_id));
        }
        if($this->new_record == false)
        {
            // einen Tag abziehen, damit User direkt aus der Rolle entfernt werden
            $newEndDate = date('Y-m-d', time() - (24 * 60 * 60));

            // only stop membership if there is an actual membership
			// the actual date must be after the beginning 
			// and the actual date must be before the end date
            if(strcmp(date('Y-m-d', time()), $this->getValue('mem_begin', 'Y-m-d')) >= 0
            && strcmp($this->getValue('mem_end', 'Y-m-d'), $newEndDate) >= 0)
            {
                $this->setValue('mem_end', $newEndDate);
            
                // stop leader
                if($this->getValue('mem_leader')==1)
                {
                	$this->setValue('mem_leader', 0);
                }
                
                $this->save();
                return true;
            }
        }
        return false;
    }
}
?>