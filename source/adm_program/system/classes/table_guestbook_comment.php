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
 * getText($type = 'HTML') - liefert den Text je nach Type zurueck
 *          type = 'PLAIN'  : reiner Text ohne Html oder BBCode
 *          type = 'HTML'   : BB-Code in HTML umgewandelt
 *          type = 'BBCODE' : Text mit BBCode-Tags
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
    function readData($gbc_id, $sql_where_condition = '', $sql_additional_tables = '')
    {
        $sql_additional_tables .= TBL_GUESTBOOK;
        $sql_where_condition   .= '    gbc_gbo_id = gbo_id 
                                   AND gbc_id     = '.$gbc_id;
        parent::readData($gbc_id, $sql_where_condition, $sql_additional_tables);
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

    // liefert den Text je nach Type zurueck
    // type = 'PLAIN'  : reiner Text ohne Html oder BBCode
    // type = 'HTML'   : BB-Code in HTML umgewandelt
    // type = 'BBCODE' : Text mit BBCode-Tags
    function getText($type = 'HTML')
    {
        global $g_preferences;
        $description = '';

        // wenn BBCode aktiviert ist, den Text noch parsen, ansonsten direkt ausgeben
        if($g_preferences['enable_bbcode'] == 1 && $type != 'BBCODE')
        {
            if(is_object($this->bbCode) == false)
            {
                $this->bbCode = new ubbParser();
            }

            $description = $this->bbCode->parse($this->getValue('gbc_text'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('gbc_text'));
        }
        return $description;
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