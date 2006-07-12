<?php
/******************************************************************************
 * Bilder werden hochgeladen und Bericht angezeigt
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
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

//bei Seitenaufruf ohne Moderationsrechte
if(!$g_session_valid || $g_session_valid & !editPhoto())
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=photoverwaltunsrecht";
    header($location);
    exit();
}

if (empty($_POST))
{
    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=empty_photo_post";
    header($location);
    exit();
}


//bei Seitenaufruf mit Moderationsrechten
if($g_session_valid & editPhoto())
{
    //Uebernahme Variablen
    $pho_id= $_GET['pho_id'];

    //erfassen der Veranstaltung
    $sql = "    SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE (pho_id ='$pho_id')";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $adm_photo = mysql_fetch_array($result);
    
    //Ordnerpfad
    $ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

    //Erfassen der Eltern Veranstaltung
    if($adm_photo["pho_pho_id_parent"]!=NULL)
    {
        $pho_parent_id=$adm_photo["pho_pho_id_parent"];
        $sql="  SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_id ='$pho_parent_id'";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result);
        $adm_photo_parent = mysql_fetch_array($result);
    }

    //Kontrollmechanismen bei Upload
    if($_POST["upload"])
    {
        //zaehlen wieviele Bilder hochgeladen werden sollen und ob alle Uploads Fehlerfrei sind 
        $counter=0;
        for($x=0; $x<=4; $x++)
        {
            //Datei wurde hochgeladen
            if(isset($_FILES["bilddatei"]["name"]["$x"]))
            {
                //Es liegt kein Fehler vor, die Datei wurde erfolgreich hochgeladen.
                if($_FILES["bilddatei"]["error"]["$x"]==0)
                {
                    $counter++;
                    
                    //Dateiendungskontrolle
                    $bildinfo=getimagesize($_FILES["bilddatei"]["tmp_name"][$x]);
                    if ($_FILES["bilddatei"]["name"][$x]!=NULL && $bildinfo['mime']!="image/jpeg") 
                    {
                        $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=dateiendungphotoup";
                        header($location);
                        exit();
                    }
                }
                
                //Die hochgeladene Datei ueberschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Groesse.
                if($_FILES["bilddatei"]["error"]["$x"]==1)
                {
                    $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=photo_2big";
                    header($location);
                    exit();
                }
            }
            
        }

        //Kontrolle ob Bilder ausgewaehlt wurden
        if($counter==0)
        {
            $location = "Location: $g_root_path/adm_program/system/err_msg.php?err_code=photodateiphotoup";
            header($location);
            exit();
        }
   
   }//Kontrollmechanismen

//Beginn HTML
   echo "
   <!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
   <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
   <html>
        <head>
            <title>$g_current_organization->longname - Fotos hochladen</title>
            <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">

            <!--[if lt IE 7]>
                <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
            <![endif]-->";

            require("../../../adm_config/header.php");
        echo "
        </head>";

        require("../../../adm_config/body_top.php");
        echo "
        <div style=\"margin-top: 10px; margin-bottom: 10px;\" align=\"center\">";
          
/*****************************Verarbeitung******************************************/          
           if($_POST["upload"])
           {
                //bei selbstaufruf der Datei Hinweise zu hochgeladenen Dateien und Kopieren der Datei in Ordner
                //Anlegen des Berichts
                echo"
                <div style=\"width: 670px\" align=\"center\" class=\"formHead\">Bericht</div>
                <div style=\"width: 670px\" align=\"center\" class=\"formBody\">Bitte einen Moment Geduld. Die Bilder wurden der Veranstaltung <br> - ".$adm_photo["pho_name"]." - <br>erfolgreich hinzugef&uuml;gt, wenn sie hier angezeigt werden.<br>";
  
                     //Verarbeitungsschleife fuer die einzelnen Bilder
                        $bildnr=$adm_photo["pho_quantity"];
                        for($x=0; $x<=4; $x=$x+1)
                        {
                            $y=$x+1;
                            if($_FILES["bilddatei"]["name"][$x]!=NULL && $ordner!=NULL)
                            {
                                //errechnen der neuen Bilderzahl
                                $bildnr++;
                                echo "<br>Bild $bildnr:<br>";
                                
                                //Groessnanpassung Bild und Bericht
                                if(move_uploaded_file($_FILES["bilddatei"]["tmp_name"][$x], "../../../adm_my_files/photos/temp$y.jpg"))
                                {
                                    echo"<img src=\"resize.php?scal=640&ziel=$ordner/$bildnr&aufgabe=speichern&nr=$y\"><br><br>";
                                }
                                else
                                {
                                    echo"Das Bild konnte nicht verarbeitet werden.";
                                }
                                unset($y);
                            }//if($bilddatei!= "")
                        }//for
                   
                    //Aendern der Datenbankeintaege
                    $sql=" UPDATE ". TBL_PHOTOS. "
                           SET   pho_quantity = '$bildnr',
                                 pho_last_change ='$act_datetime',
                                 pho_usr_id_change = $g_current_user->id
                           WHERE pho_id = '$pho_id'";
                    $result = mysql_query($sql, $g_adm_con);
                    db_error($result);

                    //Buttons 
                    echo"
                    <hr width=\"85%\" />
                    <div style=\"margin-top: 6px;\">
                        <button name=\"uebersicht\" type=\"button\" value=\"zurueck\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\">
                            <img src=\"$g_root_path/adm_program/images/table.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Zur&uuml;ck\">
                            &nbsp;&Uuml;bersicht
                        </button>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <button name=\"moreupload\" type=\"button\" value=\"moreupload\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id'\">
                            <img src=\"$g_root_path/adm_program/images/photo.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Speichern\">
                            &nbsp;Weitere Bilder hochladen
                        </button>                    
                     </div>
                </div><br><br>";
            }//if($upload)
        //Seitenende
        echo"
        </div>";
        require("../../../adm_config/body_bottom.php");
    echo"
    </body>
</html>";
}//if Moderator
?>