<?php
/******************************************************************************
 * Klasse fuer den Zugriff auf die Datenbanktabelle adm_rooms
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu, um ein neues Raumobjekt in der Datenbanktabelle
 * adm_rooms zu erstellen. 
 *
 *****************************************************************************/ 
require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');
require_once(SERVER_PATH. '/adm_program/system/classes/ubb_parser.php');

class TableRooms extends TableAccess
{
	protected $bbCode;
    
    // Konstruktor
    public function __construct(&$db, $room = '')
    {
        parent::__construct($db, TBL_ROOMS, 'room', $room);
    }
    
    // liefert die Beschreibung je nach Type zurueck
    // type = 'PLAIN'  : reiner Text ohne Html oder BBCode
    // type = 'HTML'   : BB-Code in HTML umgewandelt
    // type = 'BBCODE' : Beschreibung mit BBCode-Tags
    public function getDescription($type = 'HTML')
    {
        global $g_preferences;
        $description = '';

        // wenn BBCode aktiviert ist, die Beschreibung noch parsen, ansonsten direkt ausgeben
        if($g_preferences['enable_bbcode'] == 1 && $type != 'BBCODE')
        {
            if(is_object($this->bbCode) == false)
            {
                $this->bbCode = new ubbParser();
            }

            $description = $this->bbCode->parse($this->getValue('room_description'));

            if($type == 'PLAIN')
            {
                $description = strStripTags($description);
            }
        }
        else
        {
            $description = nl2br($this->getValue('room_description'));
        }
        return $description;
    }

    // Raum mit der uebergebenen ID oder dem Raumnamen aus der Datenbank auslesen
    public function readData($room, $sql_where_condition = '', $sql_additional_tables = '')
    {
        global $g_current_organization;

        if(is_numeric($room))
        {
            $sql_where_condition .= ' room_id = '.$room;
        }
        else
        {
            $room = addslashes($room);
            $sql_where_condition .= ' room_name LIKE \''.$room.'\' ';
        }
        return parent::readData($room, $sql_where_condition, $sql_additional_tables);
    }
}
?>
