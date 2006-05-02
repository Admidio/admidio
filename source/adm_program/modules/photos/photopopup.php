<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2006 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * Ordner : aus welchem Ordner stammt das Bild welches angezeigt werden soll
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
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
require("../../system/common.php");

//Uebernahme der uebergebenen variablen
$pho_id= $_GET['pho_id'];
$bild= $_GET['bild'];

//erfassen der Veranstaltung
$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE (pho_id ='$pho_id')";
$result = mysql_query($sql, $g_adm_con);
db_error($result);
$adm_photo = mysql_fetch_array($result);

//Aanzahl der Bilder
$bilder = $adm_photo["pho_quantity"];

//Naechstes und Letztes Bild
$last=$bild-1;
$next=$bild+1;

//Ordnerpfad zusammensetzen
$ordner = "../../../adm_my_files/photos/".$adm_photo["pho_begin"]."_".$adm_photo["pho_id"];

//Anfang HTML
echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org - Version: ". getVersion(). " -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
    <head>
        <title>$g_current_organization->longname - Fotogalerien</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";
        echo"
        <!--[if gte IE 5.5000]>
            <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";
    echo "
    </head>";

    //Ausgabe der Eine Tabelle Kopfzelle mit &Uuml;berschrift, Photographen und Datum
    //untere Zelle mit Buttons Bild und Fenster Schlie&szlig;en Button
    echo "
    <body>
        <div style=\"margin-top: 5px; margin-bottom: 5px;\" align=\"center\">
            <div class=\"formHead\" style=\"width: 95%\">".$adm_photo["pho_name"]."</div>
            <div class=\"formBody\" style=\"width: 95%; height: 520px;\">";
                echo"Datum: ".mysqldate("d.m.y", $adm_photo["pho_begin"]);
                if($adm_photo["pho_end"] != $adm_photo["pho_begin"])
                {
                    echo " bis ".mysqldate("d.m.y", $adm_photo["pho_end"]);
                }
                echo "<br>Fotos von: ".$adm_photo["pho_photographers"]."<br><br>";
                
                //Vor und zurueck buttons
                if($last>0)
                {
                    echo"
                    <a href=\"photopopup.php?bild=$last&pho_id=$pho_id\">
                        <img src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Vorheriges Bild\">
                    </a>
                    <a href=\"photopopup.php?bild=$last&pho_id=$pho_id\">Vorheriges Bild</a>&nbsp;&nbsp;&nbsp;&nbsp;";
                }
                if($next<=$bilder)
                {
                    echo"
                    <a href=\"photopopup.php?bild=$next&pho_id=$pho_id\">N&auml;chstes Bild</a>
                    <a href=\"photopopup.php?bild=$next&pho_id=$pho_id\">
                        <img src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"N&auml;chstes Bild\">
                    </a>";
                }
                echo"<br><br>";

                //Ermittlung der Original Bildgroesse
                $bildgroesse = getimagesize("$ordner/$bild.jpg");
                //Entscheidung ueber scallierung
                //Hochformat Bilder
                if ($bildgroesse[0]<=$bildgroesse[1])
                {
                    $side=y;
                    if ($bildgroesse[1]>380){
                        $scal=380;
                    }
                    else
                    {
                        $scal=$bildgroesse[1];
                    }
                }

                //Querformat Bilder
                if ($bildgroesse[0]>$bildgroesse[1])
                {
                    $side=x;
                    if ($bildgroesse[0]>500)
                    {
                        $scal=500;
                    }
                    else{
                        $scal=$bildgroesse[0];
                    }
                }
    
                //Ausgabe Bild
                echo"
                <div style=\"align: center\">
                    <img src=\"resize.php?bild=$ordner/$bild.jpg&amp;scal=$scal&amp;aufgabe=anzeigen&amp;side=$side\"  border=\"0\" alt=\"$ordner $bild\">
                </div>";
                
                //Fenster schliessen Button
                echo"
                <div style=\"align: center; margin-top: 10px;\">
                    <button name=\"close\" type=\"button\" value=\"close\" style=\"width: 150px;\" onClick=\"parent.window.close()\">
                        <img src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle; padding-bottom: 1px;\" width=\"16\" height=\"16\" border=\"0\" alt=\"Fenster schlie&szlig;en\">
                        &nbsp;Fenster schlie&szlig;en
                    </button>
                </div>
            </div>
        </div>";
    //Seitenende
    echo "
    </body>
</html>";
?>