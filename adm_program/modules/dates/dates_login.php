<?php
require_once('../../system/common.php');
require_once('../../system/classes/date.php');
require_once('../../system/classes/table_members.php');
require(THEME_SERVER_PATH. '/overall_header.php');
$req_headline = 'Anmeldung';

// Html-Kopf ausgeben
if(strlen($req_calendar) > 0)
{
    $g_layout['title'] = $req_headline. ' - '. $req_calendar;
}
else
{
    $g_layout['title'] = $req_headline;
}
$req_dat_id = 0;
if(isset($_GET['dat_id']))
{
    $req_dat_id = $_GET['dat_id'];
}


$sql = 'SELECT * FROM '.TBL_DATES.' WHERE dat_id ="'.$_GET['dat_id'].'"';
$result = $g_db->query($sql);
$date = $g_db->fetch_array($result); 
$user_id = $_SESSION['g_current_user']->getValue('usr_id');
$member = new TableMembers($g_db);

// Html des Modules ausgeben
echo ' 
<script type="text/javascript"><!--
    function showCalendar()
    {
        var calendar = "";
        if (document.getElementById("calendar").selectedIndex != 0)
        {
            var calendar = document.getElementById("calendar").value;
        } 
       // self.location.href = "dates.php?mode='.$req_mode.'&headline='.$req_headline.'&calendar=" + calendar;
    }
//--></script>
<h1 class="moduleHeadline">'. $g_layout['title']. '</h1>';

if($_GET['login']!=0)
{
    
    $timestamp = time();
    $current = date('Y-m-d H:i:s', $timestamp);
    $sql = 'SELECT * FROM '.TBL_MEMBERS.' WHERE mem_rol_id="'.$date['dat_rol_id'].'" AND mem_usr_id="'.$user_id.'"';
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);
    $member->startMembership($date['dat_rol_id'],$user_id);
    echo 'Sie haben sich erfolgreich f&uuml;r den Termin "'.$date['dat_headline'].'" am '.$date['dat_begin'].' angemeldet.';
    
}
else
{
    $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_rol_id="'.$date['dat_rol_id'].'" AND mem_usr_id="'.$user_id.'"';
    $g_db->query($sql);
    echo 'Sie haben sich erfolgreich aus dem Termin "'.$date['dat_headline'].'" am '.$date['dat_begin'].' ausgetragen.';
}

echo '      
 <ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" title="Zurück"/></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zur&uuml;ck</a>
        </span>
    </li>
</ul>';
require(THEME_SERVER_PATH. '/overall_footer.php');
?>
