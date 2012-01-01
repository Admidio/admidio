<?php
/******************************************************************************
 * Class manages access to database table adm_rooms
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu, um ein neues Raumobjekt in der Datenbanktabelle
 * adm_rooms zu erstellen. 
 *
 *****************************************************************************/ 

 require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableRooms extends TableAccess
{
	// constructor
    public function __construct(&$db, $room_id = 0)
    {
        parent::__construct($db, TBL_ROOMS, 'room', $room_id);
    }

    public function getValue($field_name, $format = '')
    {
        if($field_name == 'room_description')
        {
			if(isset($this->dbColumns['room_description']) == false)
			{
				$value = '';
			}
			elseif($format == 'plain')
			{
				$value = html_entity_decode(strStripTags($this->dbColumns['room_description']));
			}
			else
			{
				$value = $this->dbColumns['room_description'];
			}
        }
        else
        {
            $value = parent::getValue($field_name, $format);
        }
 
        return $value;
    }
 
    // validates the value and adapts it if necessary
    public function setValue($field_name, $field_value, $check_value = true)
    {
        if($field_name == 'room_description')
        {
            return parent::setValue($field_name, $field_value, false);
        }
        return parent::setValue($field_name, $field_value);
    }
}
?>
