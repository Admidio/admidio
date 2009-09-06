<?php
/******************************************************************************
 *
 * Diese Klasse dient dazu, um ein neues Raumobjekt in der Datenbanktabelle
 * adm_rooms zu erstellen. 
 *
       
*****************************************************************************/ 
require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableRooms extends TableAccess
{
    var $room_choice = array(
                0 => '---'
    ); //beihnaltet alle in der DB gespeicherten Räume
    // Konstruktor
    function TableRooms(&$db, $room = '')
    {
        $this->db            =& $db;
        $this->table_name     = TBL_ROOMS;
        $this->column_praefix = 'room';

        if(strlen($room) > 0)
        {
            $this->readData($room);
        }
        else
        {
            $this->clear();
        }
        
        //room_choice befüllen
        $sql = 'SELECT room_id, room_name FROM '.TBL_ROOMS.'';
        $result = $this->db->query($sql);
        while($row = $this->db->fetch_array($result))
        {
            $this->room_choice[$row['room_id']] = $row['room_name'];
        }
    }
    
    // Raum mit der uebergebenen ID oder dem Raumnamen aus der Datenbank auslesen
    function readData($room, $sql_where_condition = '', $sql_additional_tables = '')
    {
        global $g_current_organization;

        if(is_numeric($room))
        {
            $sql_where_condition .= ' room_id = '.$room;
        }
        else
        {
            $room = addslashes($room);
            $sql_where_condition .= ' room_name LIKE "'.$room.'" ';
        }

      //  $sql_additional_tables .= TBL_CATEGORIES;
      //  $sql_where_condition   .= ' AND rol_cat_id = cat_id
      //                              AND cat_org_id = '. $g_current_organization->getValue('org_id');
        parent::readData($room, $sql_where_condition, $sql_additional_tables);
    }
    
    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    function save()
    {
        global $g_current_user, $g_current_session;
        $fields_changed = $this->columnsValueChanged;


        if($this->new_record)
        {
            $this->setValue('room_timestamp_create', DATETIME_NOW);
            $this->setValue('room_usr_id_create', $g_current_user->getValue('usr_id'));
        }
        else
        {
            // Daten nicht aktualisieren, wenn derselbe User dies innerhalb von 15 Minuten gemacht hat
            if(time() > (strtotime($this->getValue('room_timestamp_create')) + 900)
            || $g_current_user->getValue('usr_id') != $this->getValue('room_usr_id_create') )
            {
                $this->setValue('room_timestamp_change', DATETIME_NOW);
                $this->setValue('room_usr_id_change', $g_current_user->getValue('usr_id'));
            }
        }

        parent::save();

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if($fields_changed && is_object($g_current_session))
        {
            // einlesen aller Userobjekte der angemeldeten User anstossen, da evtl.
            // eine Rechteaenderung vorgenommen wurde
            $g_current_session->renewUserObject();
        }
    }
    
    function getRoomsArray()
    {
        return $this->room_choice;
    }
    
    
}
?>
