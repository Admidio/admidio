<?php
/******************************************************************************
 * Rollen Datum verändern
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id     - Datum der übergebenen User-Id aendern
 * rol_id     - Rollen ID
 * mode   : 0 - (Default) Anzeige des Passwordaenderungsformulars
 *          1 - Passwortaenderung wird verarbeitet
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/classes/table_members.php");

// nur Webmaster duerfen fremde Passwoerter aendern
if(!$g_current_user->assignRoles())
{
    $g_message->show("norights", "", "", false);
}

// Uebergabevariablen pruefen

if(isset($_GET["usr_id"]) && is_numeric($_GET["usr_id"]) == false)
{
    $g_message->show("invalid", "", "", false);
}

if(isset($_GET["rol_id"]) && is_numeric($_GET["rol_id"]) == false)
{
    $g_message->show("invalid", "", "", false);
}

//Einlesen der Mitgliedsdaten
 $mem = NEW TableMembers($g_db);
 $mem->readData(array('rol_id' => $_GET['rol_id'], 'usr_id' => $_GET['usr_id']));


if(isset($_GET["mode"]) && is_numeric($_GET["mode"]) && $_GET["mode"] == 1)
{
    /***********************************************************************/
    /* Formular verarbeiten */
    /***********************************************************************/
   //Check das Beginn Datum
   if(dtCheckDate($_GET['rol_begin']))
   {
      // Datum formatiert zurueckschreiben
      $date_arr = explode(".", $_GET['rol_begin']);
      $date_from_timestamp = mktime(0,0,0,$date_arr[1],$date_arr[0],$date_arr[2]);
      $date_begin = date("Y-m-d H:i:s", $date_from_timestamp);
    }
    else
    {
        $g_message->show("date_invalid", "Beginn", "Hinweis", false);
    }
    //Falls gesetzt wird das Enddatum gecheckt
    if($_GET['rol_end'] != '') {
       if(dtCheckDate($_GET['rol_end']))
      {
         // Datum formatiert zurueckschreiben
         $date_arr = explode(".", $_GET['rol_end']);
         $date_from_timestamp = mktime(0,0,0,$date_arr[1],$date_arr[0],$date_arr[2]);
         $date_end = date("Y-m-d H:i:s", $date_from_timestamp);
       }
       else
       {
           $g_message->show("date_invalid", "Ende", "Hinweis", false);
       }
       if ($date_end < $date_begin) {
          $g_message->show("date_invalid", "Anfang/Ende", "Hinweis", false);
       }
    }
    else {
      $date_end = "9999-12-31";
    }

   $mem->setValue('mem_begin',$date_begin);
   $mem->setValue('mem_end',$date_end);
   $mem->save();
   $adress=$g_root_path."/adm_program/modules/profile/profile.php?user_id=".$_GET['usr_id'];
   $g_message->setForwardUrl($adress, 2000);
   $g_message->show("save");

}
else
{
    /***********************************************************************/
    /* Datum anzeigen */
    /***********************************************************************/
    // Variablen
    $rol_from = $mem->getValue('mem_begin', $g_preferences['system_date']);
    $rol_to = NULL;
    if ($mem->getValue('mem_end') != NULL) {
        $rol_to = $mem->getValue('mem_end', $g_preferences['system_date']);
    }

    // Html-Kopf ausgeben
    $g_layout['title']    = "Datum bearbeiten";
    $g_layout['includes'] = false;
    require(THEME_SERVER_PATH. "/overall_header.php");

    // Html des Modules ausgeben
    echo '
    <form action="'. $g_root_path. '/adm_program/modules/profile/roles_date.php?usr_id='. $_GET['usr_id']. '&mode=1&rol_id='.$_GET['rol_id'].'" method="post">
    <div class="formLayout" id="password_form" style="width: 300px">
        <div class="formHead">'. $g_layout['title']. '</div>
        <div class="formBody">
            <ul class="formFieldList">
                <li>
                    <dl>
                        <dt><label for="rol_begin">Beginn:</label></dt>
                        <dd><input type="edit" id="rol_begin" name="rol_begin" size="10" maxlength="20" value="'.$rol_from.'"/></dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for="rol_end">Ende:</label></dt>
                        <dd><input type="edit" id="rol_end" name="rol_end" size="10" maxlength="20" value="'.$rol_to.'"/></dd>
                    </dl>
                </li>
            </ul>
            <hr />
            <div class="formSubmit">
                <button name="close" type="button" value="Schließen" onclick="window.close()"><img src="'. THEME_PATH. '/icons/door_in.png" alt="Schließen" />&nbsp;Schließen</button>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <button name="save" type="submit" value="Speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
            </div>
        </div>
    </form>';

    require(THEME_SERVER_PATH. "/overall_footer.php");
}

?>