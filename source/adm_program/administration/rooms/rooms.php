<?php

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_rooms.php');
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>'; 


unset($_SESSION['rooms_request']);
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

$req_headline = 'Raum';
$req_id       = 0;
require(THEME_SERVER_PATH. '/overall_header.php');
 // Html des Modules ausgeben
echo '<h1 class="moduleHeadline">Raumverwaltung</h1>
<span class="iconTextLink">
    <a href="'.$g_root_path.'/adm_program/administration/rooms/rooms_new.php?headline='.$req_headline.'"><img src="'. THEME_PATH. '/icons/add.png" alt="Rolle anlegen" /></a>
    <a href="'.$g_root_path.'/adm_program/administration/rooms/rooms_new.php?headline='.$req_headline.'">Raum anlegen</a>
</span>
<br/>';

$sql = 'SELECT * FROM adm_rooms ORDER BY room_id';
$rooms_result = $g_db->query($sql);

if($g_db->num_rows($rooms_result) == 0)
{
    // Keine Räume gefunden
    if($req_id > 0)
    {
        echo '<p>Der angeforderte Eintrag existiert nicht (mehr) in der Datenbank.</p>';
    }
    else
    {
        echo '<p>Es sind keine Eintr&auml;ge vorhanden.</p>';
    }
}
else
{
    $room = new TableRooms($g_db);
    //Räume auflisten
    while($row=$g_db->fetch_array($rooms_result))
    {
        // GB-Objekt initialisieren und neuen DS uebergeben
        $room->clear();
        $room->setArray($row);
        
        echo '<br/>
        <div class="boxLayout" id="room_'.$room->getValue('room_id').'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'.$g_root_path.'/adm_themes/classic/icons/home.png" alt="'. $room->getValue('room_name'). '" />'
                    
                     . $room->getValue('room_name').'
                </div>
                <div class="boxHeadRight">';
                    if ($g_current_user->editDates())
                    {
                        //Bearbeiten
                        echo '
                        <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/rooms/rooms_new.php?room_id='. $room->getValue('room_id'). '&amp;headline='.$req_headline.'"><img src="'. THEME_PATH. '/icons/edit.png" alt="Bearbeiten" title="Bearbeiten" /></a>';
                            
                        //Löschen
                        echo '
                        <a class="iconLink" href="javascript:deleteObject(\'room\', \'room_'.$room->getValue('room_id').'\','.$room->getValue('room_id').',\''.$room->getValue('room_name').'\')"><img src="'. THEME_PATH. '/icons/delete.png" alt="L&ouml;schen" title="L&ouml;schen" /></a>';
                    }
          echo '</div>
            </div>
            <div class="boxBody">
                <div class="date_info_block">
                    <table style="float:left; width: 200px;">
                        <tr>
                            <td>Kapazit&auml;t:</td>
                            <td><strong>'.$room->getValue('room_capacity').'</strong></td>
                        </tr>';
                        if($room->getValue('room_overhang')!=null)
                        {
                            echo '<tr>
                                    <td>&Uuml;berhang:</td>
                                    <td><strong>'.$room->getValue('room_overhang').'</strong></td>
                                  </tr>';
                        }
              echo '</table>';
                    if($room->getValue('room_description')!=null)
                    {
                       echo '<div class="date_description" style="clear: left;"><br/>'
                            .$room->getDescription('HTML').'</div>';
                    }
                    $sql = 'SELECT room_usr_id_create, room_timestamp_create, usr_login_name FROM '.TBL_ROOMS.', '.TBL_USERS.' WHERE room_id="'.$room->getValue('room_id').'" AND usr_id = room_usr_id_create';
                    $result = $g_db->query($sql);
                    $row = $g_db->fetch_array($result);
            echo ' <div class="editInformation"><br/>
                            Angelegt von '.$row['usr_login_name'].' am '.mysqldatetime('d.m.y h:i',$row['room_timestamp_create']).'
                   </div>
                </div>
            </div>
        </div>';
    }
}



 
require(THEME_SERVER_PATH. '/overall_footer.php');
?>