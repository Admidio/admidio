<?php
/******************************************************************************
 * Klasse fuer Datenbanktabelle adm_guestbook
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Gaestebucheintragsobjekt zu erstellen. 
 * Eine Gaestebucheintrag kann ueber diese Klasse in der Datenbank verwaltet werden
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

class TableGuestbook extends TableAccess
{
    protected $bbCode;

    // Konstruktor
    public function __construct(&$db, $gbo_id = 0)
    {
        parent::__construct($db, TBL_GUESTBOOK, 'gbo', $gbo_id);
    }
    
    // die Methode loescht den Gaestebucheintrag mit allen zugehoerigen Kommentaren
    public function delete()
    {
        //erst einmal alle vorhanden Kommentare zu diesem Gaestebucheintrag loeschen...
        $sql = 'DELETE FROM '. TBL_GUESTBOOK_COMMENTS. ' WHERE gbc_gbo_id = '. $this->getValue('gbo_id');
        $result = $this->db->query($sql);
        
        return parent::delete();
    }

    // liefert den Text je nach Type zurueck
    // type = 'PLAIN'  : reiner Text ohne Html oder BBCode
    // type = 'HTML'   : BB-Code in HTML umgewandelt
    // type = 'BBCODE' : Text mit BBCode-Tags
    public function getText($type = 'HTML')
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

            $description = $this->bbCode->parse($this->getValue('gbo_text'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('gbo_text'));
        }
        return $description;
    }
    
    // die Methode moderiert den Gaestebucheintrag 
    function moderate()
    {
        //Eintrag freischalten...
        $this->setValue('gbo_locked', '0');
        $this->save();
    }  

    // Methode, die Defaultdaten fur Insert und Update vorbelegt
    public function save()
    {
        global $g_current_organization;
        
        if($this->new_record)
        {
            $this->setValue('gbo_org_id', $g_current_organization->getValue('org_id'));
            $this->setValue('gbo_ip_address', $_SERVER['REMOTE_ADDR']);
        }

        parent::save();
    }

    // prueft die Gueltigkeit der uebergebenen Werte und nimmt ggf. Anpassungen vor
    public function setValue($field_name, $field_value)
    {
        if(strlen($field_value) > 0)
        {
            if($field_name == 'gbo_homepage')
            {
                // Die Webadresse wird jetzt, falls sie nicht mit http:// oder https:// beginnt, entsprechend aufbereitet
                if (substr($field_value, 0, 7) != 'http://' && substr($field_value, 0, 8) != 'https://' )
                {
                    $field_value = 'http://'. $field_value;
                }
            }
            elseif($field_name == 'gbo_email')
            {
                $field_value = admStrToLower($field_value);
                if (!strValidCharacters($field_value, 'email'))
                {
                    // falls die Email ein ungueltiges Format aufweist wird sie nicht gesetzt
                    return false;
                }
            }
        }
        parent::setValue($field_name, $field_value);
    } 
}
?>