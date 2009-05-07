<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_guestbook_comments
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Gaestebuchkommentarobjekt zu erstellen. 
 * Eine Gaestebuchkommentar kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Neben den Methoden der Elternklasse TableAccess, stehen noch zusaetzlich
 * folgende Methoden zur Verfuegung:
 *
 * getDescriptionWithBBCode() 
 *                   - liefert die Beschreibung mit dem originalen BBCode zurueck
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableGuestbookComment extends TableAccess
{
    var $bbCode;

    // Konstruktor
    function TableGuestbookComment(&$db, $gbc_id = 0)
    {
        $this->db            =& $db;
        $this->table_name     = TBL_GUESTBOOK_COMMENTS;
        $this->column_praefix = 'gbc';
        
        if($gbc_id > 0)
        {
            $this->readData($gbc_id);
        }
        else
        {
            $this->clear();
        }
    }

    // Termin mit der uebergebenen ID aus der Datenbank auslesen
    function readData($gbc_id)
    {
        $tables    = TBL_GUESTBOOK;
        $condition = '       gbc_gbo_id = gbo_id 
                         AND gbc_id     = '.$gbc_id;
        parent::readData($gbc_id, $condition, $tables);
    }
    
    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    function setValue($field_name, $field_value)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == 'gbc_email')
            {
                if (!isValidEmailAddress($field_value))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie einfach auf null gesetzt
                    $field_value = '';
                }
            }
        }
        parent::setValue($field_name, $field_value);
    }

    // liefert die Beschreibung mit dem originalen BBCode zurueck
    // das einfache getValue liefert den geparsten BBCode in HTML zurueck
    function getDescriptionWithBBCode()
    {
        return parent::getValue('gbc_text');
    }

    function getValue($field_name)
    {
        global $g_preferences;
    
        // innerhalb dieser Methode kein getValue nutzen, da sonst eine Endlosschleife erzeugt wird !!!
        $value = $this->dbColumns[$field_name];

        if($field_name == 'gbc_text')
        {
            // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
            if($g_preferences['enable_bbcode'] == 1)
            {
                if(is_object($this->bbCode) == false)
                {
                    $this->bbCode = new ubbParser();
                }            
                return $this->bbCode->parse(parent::getValue($field_name, $value));
            }
            else
            {
                return nl2br(parent::getValue($field_name, $value));
            }        
        }
        return parent::getValue($field_name, $value);
    }

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    function save()
    {
        global $g_current_organization, $g_current_user;
        
        if($this->new_record)
        {
            $this->setValue('gbc_timestamp', DATETIME_NOW);
            $this->setValue('gbc_usr_id', $g_current_user->getValue('usr_id'));
            $this->setValue('gbc_org_id', $g_current_organization->getValue('org_id'));
            $this->setValue('gbc_ip_address', $_SERVER['REMOTE_ADDR']);
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('gbc_timestamp')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('gbc_usr_id') )
            {
                $this->setValue('gbc_timestamp_change', DATETIME_NOW);
                $this->setValue('gbc_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }
        parent::save();
    }   
}
?>