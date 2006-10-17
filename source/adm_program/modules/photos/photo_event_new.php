<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 * pho_id: id der Veranstaltung die bearbeitet werden soll
 * aufgabe: - new (neues Formular)
 *          - change (Formular fuer Aenderunmgen)
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 79 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");

// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false && $_GET["pho_id"]!=NULL)
{
    $g_message->show("invalid");
}

if(isset($_GET["aufgabe"]) && $_GET["aufgabe"] != "new" && $_GET["aufgabe"] != "change")
{
    $g_message->show("invalid");
}


if(isset($_SESSION['photo_event_request']))
{
    $form_values = $_SESSION['photo_event_request'];
    unset($_SESSION['photo_event_request']);
}
else
{
    $form_values['veranstaltung']   = "";
    $form_values['parent']          = "";
    $form_values['beginn']          = "";
    $form_values['ende']            = "";
    $form_values['photographen']    = "";
    $form_values['locked']          = 0;


    //Erfassen der Veranstaltung bei Aenderungsaufruf
    $sql = "SELECT *
              FROM ". TBL_PHOTOS. "
             WHERE pho_id = {0} ";
    $sql = prepareSQL($sql, array($_GET['pho_id']));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $adm_photo = mysql_fetch_array($result);

    if ($_GET['aufgabe'] == "change")
    {
        $form_values['veranstaltung']   = $adm_photo["pho_name"];
        $form_values['parent']          = $adm_photo["pho_pho_id_parent"];
        $form_values['beginn']          = mysqldate("d.m.y", $adm_photo["pho_begin"]);
        $form_values['ende']            = mysqldate("d.m.y", $adm_photo["pho_end"]);
        $form_values['photographen']    = $adm_photo["pho_photographers"];
        $form_values['locked']          = $adm_photo["pho_locked"];

    }
}

//Aktueller Timestamp
$act_datetime= date("Y.m.d G:i:s", time());

//erfassen der Veranstaltungsliste
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE pho_org_shortname ='$g_organization'
        ORDER BY pho_begin DESC ";
$result_list = mysql_query($sql, $g_adm_con);
db_error($result_list);

//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid  && ($aufgabe=="change" && !editPhoto($adm_photo["pho_org_shortname"])) || !editPhoto())
{
    $g_message->show("photoverwaltunsrecht");
}

//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid && $aufgabe=="change" && editPhoto($adm_photo['pho_org_shortname']))
{
    //Speicherort
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

}

/******************************HTML-Teil******************************************/
echo"
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
    <head>
        <title>$g_current_organization->longname - Veranstaltungsverwaltung</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

        <!--[if lt IE 7]>
            <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";

        require("../../../adm_config/header.php");
    echo "
    </head>";

require("../../../adm_config/body_top.php");
echo "<div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";


    /****************************Formular***********************************************/
    //Kopfzeile
    echo"
    <div class=\"formHead\">";
        if($_GET["aufgabe"]=="new")
        {
            echo "Neue Veranstaltung anlegen";
        }
        if($_GET["aufgabe"]=="change")
        {
                echo "Veranstaltung bearbeiten";
        }
    echo"</div>";

    //Body
    echo"
    <div class=\"formBody\" align=\"center\">
        <form method=\"POST\" action=\"photo_event_function.php?pho_id=". $_GET["pho_id"];
            if($_GET["aufgabe"]=="new")
            {
                echo "&aufgabe=makenew\">";
            }
            if($_GET["aufgabe"]=="change")
            {
                echo "&aufgabe=makechange\">";
            }

            //Veranstaltung
            echo"
            <div>
                <div style=\"text-align: right; width: 170px; float: left;\">Veranstaltung:</div>
                <div style=\"text-align: left; margin-left: 180px;\">
                    <input type=\"text\" id=\"veranstaltung\" name=\"veranstaltung\" style=\"width: 300px;\" maxlength=\"50\" tabindex=\"1\" value=\"".$form_values['veranstaltung']."\">
                </div>
            </div>";

            //Parent
            //Suchen nach Kindern Funktion mit selbstaufruf
            function subfolder($parent_id, $vorschub, $pho_id, $adm_photo, $option)
            {
                global $g_adm_con;
                $vorschub=$vorschub."&nbsp;&nbsp;&nbsp;&nbsp;";

                //Erfassen der auszugebenden Veranstaltung
                $sql = "SELECT *
                        FROM ". TBL_PHOTOS. "
                        WHERE (pho_pho_id_parent ='$parent_id')";
                $result_child = mysql_query($sql, $g_adm_con);
                db_error($result_child, 1);

                while($adm_photo_child=mysql_fetch_array($result_child)){
                    if($adm_photo_child["pho_id"]!=NULL)
                    {
                        if($adm_photo_child["pho_id"]!=$adm_photo["pho_pho_id_parent"] && $adm_photo_child["pho_id"]!=$pho_id && $option!=false)
                        {
                            echo"<option value=\"".$adm_photo_child["pho_id"]."\">".$vorschub."&#151;".$adm_photo_child["pho_name"]
                            ."&nbsp(".mysqldate("y", $adm_photo_child["pho_begin"]).")</option>";
                        }
                        if($adm_photo_child["pho_id"]==$form_values['parent'] && $option!=false)
                        {
                            echo"<option value=\"".$adm_photo_child["pho_id"]."\" selected=\"selected\">".$vorschub."&#151;".$adm_photo_child["pho_name"]
                            ."&nbsp(".mysqldate("y", $adm_photo_child["pho_begin"]).")</option>";
                        }

                        //Versnstaltung selbst darf nicht ausgewaehlt werden
                        if($adm_photo_child["pho_id"]!=$adm_photo["pho_pho_id_parent"] && $adm_photo_child["pho_id"]==$pho_id && $_GET["aufgabe"]=="change" || $option==false )
                        {
                            echo"<option value=\"".$adm_photo_child["pho_id"]."\" disabled=\"disabled\">".$vorschub."&#151;".$adm_photo_child["pho_name"]
                            ."&nbsp(".mysqldate("y", $adm_photo_child["pho_begin"]).")</option>";
                            $option=false;
                        }

                        $parent_id = $adm_photo_child["pho_id"];
                        subfolder($parent_id, $vorschub, $pho_id, $adm_photo, $option);
                    }//if
                }//while
            }//function

            echo"
            <div style=\"margin-top: 6px;\">
               <div style=\"text-align: right; width: 170px; float: left;\">in Ordner:</div>
               <div style=\"text-align: left; margin-left: 180px;\">
                    <select size=\"1\" name=\"parent\" tabindex=\"2\">
                        <option value=\"0\">Fotogalerien(Hauptordner)</option>";

                        while($adm_photo_list = mysql_fetch_array($result_list)){
                            if($adm_photo_list["pho_pho_id_parent"]==NULL)
                            {
                                //Wenn die Elternveranstaltung von pho_id dann selectet
                                if($adm_photo_list["pho_id"]==$form_values['parent'] || ($_GET["aufgabe"]=="new" && $pho_id==$adm_photo_list["pho_id"]))
                                {
                                    echo"<option value=\"".$adm_photo_list["pho_id"]."\" selected=\"selected\">".$adm_photo_list["pho_name"]
                                    ."&nbsp;(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";
                                    $option=true;
                                }
                                //Normal
                                if($pho_id!=$adm_photo_list["pho_id"] && $adm_photo_list["pho_id"]!=$adm_photo["pho_pho_id_parent"])
                                {
                                    echo"<option value=\"".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]
                                    ."&nbsp;(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";
                                    $option=true;
                                }
                                //Versnstaltung selbst darf nicht ausgewaehlt werden
                                if($pho_id==$adm_photo_list["pho_id"] && $_GET["aufgabe"]=="change")
                                {
                                    echo"<option value=\"".$adm_photo_list["pho_id"]."\" disabled=\"disabled\">".$adm_photo_list["pho_name"]
                                    ."&nbsp;(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";
                                    $option=false;
                                }
                                $parent_id = $adm_photo_list["pho_id"];
                                //Auftruf der Funktion
                                subfolder($parent_id, $vorschub, $pho_id, $adm_photo, $option);
                            }//if
                        }//while
                        echo"
                    </select>
                </div>
            </div>";

            //Beginn
            echo"
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 170px; float: left;\">Beginn:</div>
                <div style=\"text-align: left; margin-left: 180px;\">
                    <input type=\"text\" name=\"beginn\" size=\"10\" tabindex=\"3\" maxlength=\"10\" value=\"".$form_values['beginn']."\">
                </div>
            </div>";

            //Ende
            echo"
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 170px; float: left;\">Ende:</div>
                <div style=\"text-align: left; margin-left: 180px;\">
                    <input type=\"text\" name=\"ende\" size=\"10\" tabindex=\"4\" maxlength=\"10\" value=\"".$form_values['ende']."\">
                </div>
            </div>";

            //Photographen
            echo"
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 170px; float: left;\">Fotografen:</div>
                <div style=\"text-align: left; margin-left: 180px;\">
                    <input type=\"text\" name=\"photographen\" style=\"width: 300px;\" tabindex=\"5\" maxlength=\"100\" value=\"".$form_values['photographen']."\">
                </div>
            </div>";

            //Freigabe
            echo"
            <div style=\"margin-top: 6px;\">
                <div style=\"text-align: right; width: 170px; float: left;\">Sperren:</div>
                <div style=\"text-align: left; margin-left: 180px;\">";
                    if($form_values['locked']==1)
                    {
                        echo "<input type=\"checkbox\" name=\"locked\" id=\"locked\" tabindex=\"6\" checked value=\"1\">";
                    }
                    if($form_values['locked']==0)
                    {
                        echo "<input type=\"checkbox\" name=\"locked\" id=\"locked\" tabindex=\"6\" value=\"1\">";
                    }
                 echo"</div>
            </div>";

            //Submit- und Zurueckbutton
            echo"
            <div style=\"margin-top: 6px;\">
                <hr width=\"85%\" />
                Hilfe: <img src=\"$g_root_path/adm_program/images/help.png\" style=\"cursor: pointer; vertical-align: top;\" vspace=\"1\" width=\"16\" height=\"16\" border=\"0\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=veranst_help','Message','width=500, height=400, left=310,top=200,scrollbars=no')\">
                <hr width=\"85%\" />
                <div style=\"margin-top: 6px;\">
                    <button name=\"zurueck\" type=\"button\" tabindex=\"7\" value=\"zurueck\" onclick=\"history.back()\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                        &nbsp;Zur&uuml;ck
                    </button>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    <button name=\"submit\" type=\"submit\" tabindex=\"8\" value=\"speichern\">
                        <img src=\"$g_root_path/adm_program/images/disk.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                        &nbsp;Speichern
                    </button>
               </div>
            </div>
        </form>
    </div>

    <script type=\"text/javascript\">
        <!--
            document.getElementById('veranstaltung').focus();
        -->
    </script>";


    /***********************************Ende********************************************/
        echo"</div>";

        require("../../../adm_config/body_bottom.php");
        echo "</body>
    </html>";
//Moderation
?>