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
 * aufgabe: - makenew (neue eingaben speichern)
 *          - makechange (Aenderungen ausfuehren)
 *          - delete (Loeschen einer Veranstaltung)
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

if(isset($_GET["aufgabe"]) && $_GET["aufgabe"] != "makenew" && $_GET["aufgabe"] != "do_delete" 
    && $_GET["aufgabe"] != "makechange" && $_GET["aufgabe"] != "delete_request")
{
    $g_message->show("invalid");
}

$_SESSION['photo_event_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];
$aufgabe = $_GET['aufgabe'];

//Aktueller Timestamp
$act_datetime= date("Y.m.d G:i:s", time());

//Erfassen der Veranstaltung bei Aenderungsaufruf
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE (pho_id ='$pho_id')";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$adm_photo = mysql_fetch_array($result);

//erfassen der Veranstaltungsliste
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE pho_org_shortname ='$g_organization'
        ORDER BY pho_begin DESC ";
$result_list = mysql_query($sql, $g_adm_con);
db_error($result_list);

//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid && (!editPhoto($adm_photo["pho_org_shortname"]) && $aufgabe="change") || !editPhoto())
{
    $g_message->show("photoverwaltunsrecht");
}

//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid && editPhoto($adm_photo["$g_organization"]))
{
    //Speicherort
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    /********************Aenderungen oder Neueintraege kontrollieren***********************************/
    if($_POST["submit"])
    {
    //Gesendete Variablen Uebernehmen und kontollieren
        //Veranstaltung
        $veranstaltung = $_POST["veranstaltung"];
        if($veranstaltung=="")
        {
            $g_message->show("veranstaltung");
        }
        
        //Parent-Ordner
        $parent_id = $_POST["parent"];
        
        //Beginn
        $beginn =  $_POST["beginn"];
        if($beginn=="" || !dtCheckDate($beginn))
        {
           $g_message->show("startdatum");
        }
        if(dtCheckDate($beginn)){
            $beginn = dtFormatDate($beginn, "Y-m-d");
        }
       
        //Ende
        $ende =  $_POST["ende"];
        if($ende==""){
             $ende=$beginn;
        }
        else
        {
            if(!dtCheckDate($ende))
            {
                $g_message->show("enddatum");
            }
            if(dtCheckDate($ende))
            {
                $ende = dtFormatDate($ende, "Y-m-d");
            }   
        }
        
        //Anfang muss vor oder gleich Ende sein
        if($ende<$beginn)
        {
            $g_message->show("startvorend");
        }
        
        //Photographen
        $photographen =  $_POST["photographen"];
        if($photographen=="")
        {
            $photographen="leider unbekannt";
        }

        //Freigabe
        $locked=$_POST["locked"];
        if($locked==NULL)
        {
            $locked=0;
        }
        
        /********************neuen Datensatz anlegen***********************************/
        if ($aufgabe=="makenew")
        {
            $sql="  INSERT INTO ". TBL_PHOTOS. "(pho_quantity, pho_name, pho_begin, pho_end, pho_photographers,
                                                 pho_timestamp, pho_last_change, pho_org_shortname, pho_usr_id, pho_locked)
                    VALUES(0, 'neu', '0000-00-00', '0000-00-00', 'leider unbekannt', '$act_datetime', '$act_datetime', '$g_organization',
                            '$g_current_user->id', '$locked')";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            //erfragen der id
            $pho_id=mysql_insert_id($g_adm_con);
        }

        //Verzeichnis erstellen
        if ($aufgabe=="makenew")
        {
            $ordnerneu = "$beginn"."_"."$pho_id";
            //Wenn keine Schreibrechte Loeschen der Daten aus der Datenbank
            if (decoct(fileperms("../../../adm_my_files/photos"))!=40777)
            {
                $sql =" DELETE
                        FROM ". TBL_PHOTOS. "
                        WHERE (pho_id ='$pho_id')";
                $result = mysql_query($sql, $g_adm_con);
                db_error($result);

                $g_message->addVariableContent("adm_my_files/photos", 1);
                $g_message->addVariableContent($g_preferences['email_administrator'], 2);
                $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
                $g_message->show("write_access");
            }
            //wenn Rechte OK, Ordner erstellen
            else
            {
                $ordnererstellt = mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
                chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
            }
        }//if

        /********************Aenderung des Ordners***********************************/
        //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
        if ($aufgabe=="makechange" && $ordner!="../../../adm_my_files/photos/"."$beginn"."_"."$pho_id")
        {
            $ordnerneu = "$beginn"."_".$adm_photo["pho_id"];
            //testen ob Schreibrechte fuer adm_my_files bestehen
            if (decoct(fileperms("../../../adm_my_files/photos"))!=40777)
            {
                $g_message->addVariableContent("adm_my_files/photos", 1);
                $g_message->addVariableContent($g_preferences['email_administrator'], 2);
                $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
                $g_message->show("write_access");                
            }
            //wenn Rechte OK, Ordner erstellen
            else
            {
                mkdir("../../../adm_my_files/photos/$ordnerneu",0777);
                chmod("../../../adm_my_files/photos/$ordnerneu", 0777);
            }

            //Dateien verschieben
            for($x=1; $x<=$adm_photo["pho_quantity"]; $x++)
            {
                chmod("$ordner/$x.jpg", 0777);
                copy("$ordner/$x.jpg", "../../../adm_my_files/photos/$ordnerneu/$x.jpg");
                unlink("$ordner/$x.jpg");
            }

            //alten ordner loeschen
            chmod("$ordner", 0777);
            rmdir("$ordner");
        }//if

        /********************Aenderung der Datenbankeinträge***********************************/
        //Aendern  der Daten in der Datenbank
        $sql= " UPDATE ". TBL_PHOTOS. "
                SET     pho_name = '$veranstaltung',";
        if($parent_id!="0"){
            $sql=$sql." pho_pho_id_parent = '$parent_id',";
        }
        if($parent_id=="0"){
                $sql=$sql."pho_pho_id_parent = NULL,";
        }        
        $sql=$sql."     pho_begin ='$beginn',
                        pho_end ='$ende',
                        pho_photographers ='$photographen',
                        pho_last_change ='$act_datetime',
                        pho_usr_id_change = '$g_current_user->id',
                        pho_locked = '$locked'
                WHERE   pho_id = '$pho_id'";

        //SQL Befehl ausfuehren
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);

        /********************Daten aus Datenbank neu laden***********************************/

        //erfassen der Veranstaltung
        $sql="  SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_id ='$pho_id'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $neudaten = mysql_fetch_array($result);

        //Speicherort
        $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

        //Erfassen der Eltern Veranstaltung
        if($neudaten["pho_pho_id_parent"]!=NULL){
            $pho_parent_id=$neudaten["pho_pho_id_parent"];
            $sql="  SELECT *
                    FROM ". TBL_PHOTOS. "
                    WHERE pho_id ='$pho_parent_id'";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $neudaten_parent = mysql_fetch_array($result);
        }
        
        //Erfassen des Anlegers der Ubergebenen Veranstaltung
        if($neudaten["pho_usr_id"]!=NULL)
        {
            $sql  = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$neudaten["pho_usr_id"];
            $result_u1 = mysql_query($sql, $g_adm_con);
            db_error($result_u1);
            $user1 = mysql_fetch_object($result_u1);
        }

        //Erfassen des Veraenderers der Ubergebenen Veranstaltung
        if($pho_id!=NULL)
        {
            $sql  = "SELECT * FROM ". TBL_USERS. " WHERE usr_id =".$neudaten["pho_usr_id_change"];
            $result_u2 = mysql_query($sql, $g_adm_con);
            db_error($result_u2);
            $user2 = mysql_fetch_object($result_u2);
        }
    }// if submit

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

    /*******************************Bericht*********************************************/
    if($_POST["submit"])
    {
        echo"<div style=\"width: 430px\" align=\"center\" class=\"formHead\">Bericht</div>";
        echo"
        <div style=\"width: 430px\" align=\"center\" class=\"formBody\">
            <table cellspacing=3 cellpadding=0 border=\"0\">
                <tr><td colspan=\"2\" align=\"center\">Die Veranstaltung wurde erfolgreich angelegt / ge&auml;ndert:<br>&nbsp;</td></tr>
                <tr><td align=\"right\">Veranstaltung:</td><td align=\"left\">".$neudaten["pho_name"]."</td></tr>
                <tr><td align=\"right\" width=\"50%\">in Ordner:</td><td align=\"left\">";
                    if($pho_parent_id!=NULL)
                    {
                        echo $neudaten_parent["pho_name"];
                    }
                    if($pho_parent_id==NULL){
                        echo "Fotogalerien(Hauptordner)";
                    }
                echo"
                </td></tr>
                <tr><td align=\"right\">Anfangsdatum:</td><td align=\"left\">".mysqldate("d.m.y", $neudaten["pho_begin"])."</td></tr>
                <tr><td align=\"right\">Enddatum:</td><td align=\"left\">".mysqldate("d.m.y", $neudaten["pho_end"])."</td></tr>
                <tr><td align=\"right\">Fotografen:</td><td align=\"left\">".$neudaten["pho_photographers"]."</td></tr>
                <tr><td align=\"right\">Gesperrt:</td><td align=\"left\">";
                if($neudaten["pho_locked"]==1){
                     echo"Ja";
                }
                if($neudaten["pho_locked"]==0){
                     echo"Nein";
                }
                echo"
                </td></tr>
                <tr><td align=\"right\" width=\"50%\">Aktuelle Bilderzahl:</td><td align=\"left\">".$neudaten["pho_quantity"]."</td></tr>
            </table>
            <hr width=\"85%\" />
            <button name=\"weiter\" type=\"button\" value=\"weiter\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\">Weiter&nbsp;
                <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\">
            </button>
        </div><br><br>";
    }//submit

 
/***********************Veranstaltung Loeschen*******************************************/

    //Nachfrage ob geloescht werden soll
    if($_GET["job"]=="delete_request")
    {
        $g_message->setForwardYesNo("$g_root_path/adm_program/modules/photos/photo_event_function.php?job=do_delete&pho_id=$pho_id");
        $g_message->show("delete_veranst", utf8_encode($adm_photo["pho_name"]));
    }
    
    
    if($_GET["job"]=="do_delete")
    {
        //Erfasse der zu loeschenden Veranstaltung bzw. Unterveranstaltungen
        //Erfassen der Veranstaltung bei Aenderungsaufruf und schreiben in array
        $delete_ids = array(0=>$pho_id);
        $counter=1;
        //rekursive Funktion
        function event_delete ($delete_id)
        {
            global $g_adm_con;
            global $delete_ids;
            global $counter;
            $sql="  SELECT *
                    FROM ". TBL_PHOTOS. "
                    WHERE (pho_pho_id_parent ='$delete_id')";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            
            while($adm_photo_delete_collect  = mysql_fetch_array($result))
            {
                $delete_ids["$counter"]=$adm_photo_delete_collect["pho_id"];
                $counter++;
                event_delete($adm_photo_delete_collect["pho_id"]);
            }
        }

        //Funktion starten
        event_delete($pho_id);

        //Bericht
        echo"<div style=\"width: 500px\" align=\"center\" class=\"formHead\">Bericht</div>";
        echo"<div style=\"width: 500px\" align=\"center\" class=\"formBody\">";
        
        //Alle veranstaltungen aufrufen und sie selbst und ihre Bilder loeschen
        for($x=0; $x<$counter; $x++)
        {
            $pho_id_delete=$delete_ids[$x];
            $sql="  SELECT *
                    FROM ". TBL_PHOTOS. "
                    WHERE (pho_id ='$pho_id_delete')";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result);
            $adm_photo_delete = mysql_fetch_array($result);
            
            //Ordnerpfad zusammensetzen
            $ordner = "../../../adm_my_files/photos/".$adm_photo_delete["pho_begin"]."_".$adm_photo_delete["pho_id"];
            
            //wenn Ordner existiert
            if(file_exists($ordner))
            {
                chmod("$ordner", 0777);
                //Loeschen der Bilder
                for($y=1; $y<=$adm_photo_delete["pho_quantity"]; $y++)
                {
                    if(file_exists("$ordner/$y.jpg"))
                        {
                            chmod("$ordner/$y.jpg", 0777);
                                if(unlink("$ordner/$y.jpg"))
                                {
                                    echo"Datei &bdquo;".$adm_photo_delete["pho_begin"]."_".$adm_photo_delete["pho_id"]."/$y.jpg&rdquo; wurde erfolgreich gel&ouml;scht.<br>";
                                }
                        }
                }
            }
            
            //Loeschen der Daten aus der Datenbank
            $sql =" DELETE
                    FROM ". TBL_PHOTOS. "
                    WHERE (pho_id ='".$adm_photo_delete["pho_id"]."')";
            $result_delet = mysql_query($sql, $g_adm_con);
            db_error($result_delet);
            if($result_delet)
            {
                echo"Der Datensatz zu &bdquo;".$adm_photo_delete["pho_name"]."&rdquo; wurde aus der Datenbank gel&ouml;scht.";
            }
            
            //Loeschen der Ordners
            if(file_exists($ordner))
                {
                    if(rmdir("$ordner"))
                    {
                        echo"<br>Die Veranstaltung wurde erfolgreich gel&ouml;scht.<br>";
                    }
            }
        }//for

    //Zurueckbutton
    echo"
    <hr width=\"85%\" />
    <button name=\"weiter\" type=\"button\" value=\"weiter\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php'\">Weiter&nbsp;
        <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Weiter\">
    </button>
    </div>";
    }//Ende Veranstaltung loeschen
    
    /***********************************Ende********************************************/
        echo"</div>";
            
        require("../../../adm_config/body_bottom.php");
        echo "</body>
    </html>";
};//Moderation
?>