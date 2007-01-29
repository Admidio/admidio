<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 *
 * Bild: welches Bild soll angezeigt werden
 * pho_id: Id der Veranstaltung aus der das Bild stammt
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
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

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}


// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false)
{
    $g_message->show("invalid");
}

if(isset($_GET["bild"]) && is_numeric($_GET["bild"]) == false)
{
    $g_message->show("invalid");
}

//Uebernahme der uebergebenen variablen
$pho_id= $_GET['pho_id'];
$bild= $_GET['bild'];

//erfassen der Veranstaltung falls noch nicht in Session gespeichert
if(!isset($_SESSION['photo_event']) || $_SESSION['photo_event']['pho_id']!= $pho_id)
{
    $sql="  SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_id ={0}";
    $sql    = prepareSQL($sql, array($pho_id));
    $result = mysql_query($sql, $g_adm_con);
    db_error($result);
    $adm_photo = mysql_fetch_array($result);

    //Variablen in Session schreiben
    $_SESSION['photo_event']['pho_id']= $adm_photo['pho_id'];
    $_SESSION['photo_event']['pho_org_schortname']= $adm_photo['pho_org_schortname'];
    $_SESSION['photo_event']['pho_quantity']= $adm_photo['pho_quantity'];
    $_SESSION['photo_event']['pho_name']= $adm_photo['pho_name'];
    $_SESSION['photo_event']['pho_begin']= $adm_photo['pho_begin'];
    $_SESSION['photo_event']['pho_end']= $adm_photo['pho_end'];
    $_SESSION['photo_event']['pho_photographers']= $adm_photo['pho_photographers'];
    $_SESSION['photo_event']['pho_usr_id']= $adm_photo['pho_usr_id'];
    $_SESSION['photo_event']['pho_timestamp']= $adm_photo['pho_timestamp'];
    $_SESSION['photo_event']['pho_locked']= $adm_photo['pho_locked'];
    $_SESSION['photo_event']['pho_pho_id_parent']= $adm_photo['pho_pho_id_parent'];
    $_SESSION['photo_event']['pho_last_change']= $adm_photo['pho_last_change'];
    $_SESSION['photo_event']['pho_usr_id_change']= $adm_photo['pho_usr_id_change'];

}


//Aanzahl der Bilder
$bilder = $_SESSION['photo_event']['pho_quantity'];

//Naechstes und Letztes Bild
$last=$bild-1;
$next=$bild+1;

//Ordnerpfad zusammensetzen
$ordner = "../../../adm_my_files/photos/".$_SESSION['photo_event']['pho_begin']."_".$_SESSION['photo_event']['pho_id'];

//Anfang HTML
echo "
<!-- (c) 2004 - 2005 The Admidio Team - http://www.admidio.org -->\n
<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
    <head>
        <title>$g_current_organization->longname - Fotogalerien</title>
        <link rel=\"stylesheet\" type=\"text/css\" href=\"$g_root_path/adm_config/main.css\">";
        echo"
        <!--[if lt IE 7]>
            <script type=\"text/javascript\" src=\"$g_root_path/adm_program/system/correct_png.js\"></script>
        <![endif]-->";
    echo "
    </head>";

    //Ausgabe der Eine Tabelle Kopfzelle mit &Uuml;berschrift, Photographen und Datum
    //untere Zelle mit Buttons Bild und Fenster Schlie&szlig;en Button
    $body_height = $g_preferences['photo_show_height']+ 130;
    $body_with = $g_preferences['photo_show_width']+20;

    echo "
    <body>
        <div style=\"margin-top: 5px; margin-bottom: 5px;\" align=\"center\">
            <div class=\"formHead\" style=\"width:".$body_with."px\">".$_SESSION['photo_event']['pho_name']."</div>
            <div class=\"formBody\" style=\"width:".$body_with."px; height: ".$body_height."px;\">";
                echo"Datum: ".mysqldate("d.m.y", $_SESSION['photo_event']['pho_begin']);
                if($_SESSION['photo_event']['pho_end'] != $_SESSION['photo_event']['pho_begin'])
                {
                    echo " bis ".mysqldate("d.m.y", $_SESSION['photo_event']['pho_end']);
                }
                echo "<br>Fotos von: ".$_SESSION['photo_event']['pho_photographers']."<br><br>";

                //Vor und zurueck buttons
                if($last>0)
                {
                    echo"<span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"photopopup.php?bild=$last&pho_id=$pho_id\">
                            <img class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Vorheriges Bild\">
                        </a>
                        <a class=\"iconLink\" href=\"photopopup.php?bild=$last&pho_id=$pho_id\">Vorheriges Bild</a>
                    </span>
                    &nbsp;&nbsp;&nbsp;&nbsp;";
                }
                if($next<=$bilder)
                {
                    echo"<span class=\"iconLink\">
                        <a class=\"iconLink\" href=\"photopopup.php?bild=$next&pho_id=$pho_id\">N&auml;chstes Bild</a>
                        <a class=\"iconLink\" href=\"photopopup.php?bild=$next&pho_id=$pho_id\">
                            <img class=\"iconLink\" src=\"$g_root_path/adm_program/images/forward.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"N&auml;chstes Bild\">
                        </a>
                    </span>";
                }
                echo"<br><br>";

                //Ermittlung der Original Bildgroesse
                $bildgroesse = getimagesize("$ordner/$bild.jpg");
                //Entscheidung ueber scallierung
                //Hochformat Bilder
                if ($bildgroesse[0]<=$bildgroesse[1])
                {
                    $side="y";
                    if ($bildgroesse[1]>$g_preferences['photo_show_height']){
                        $scal=$g_preferences['photo_show_height'];
                    }
                    else
                    {
                        $scal=$bildgroesse[1];
                    }
                }

                //Querformat Bilder
                if ($bildgroesse[0]>$bildgroesse[1])
                {
                    $side="x";
                    if ($bildgroesse[0]>$g_preferences['photo_show_width'])
                    {
                        $scal=$g_preferences['photo_show_width'];
                    }
                    else{
                        $scal=$bildgroesse[0];
                    }
                }

                //Ausgabe Bild
                echo"
                <div style=\"align: center\">
                    <img src=\"photo_show.php?bild=$ordner/$bild.jpg&amp;scal=$scal&amp;side=$side\"  border=\"0\" alt=\"$ordner $bild\">
                </div>";

                //Fenster schliessen Button
                echo"<p>
                    <span class=\"iconLink\">
                        <a href=\"javascript:parent.window.close()\"><img
                        class=\"iconLink\" src=\"$g_root_path/adm_program/images/door_in.png\" style=\"vertical-align: middle;\" border=\"0\" alt=\"Login\"></a>
                        <a class=\"iconLink\" href=\"javascript:parent.window.close()\">Fenster schlie&szlig;en</a>
                    </span>
                </p>
            </div>
        </div>";
    //Seitenende
    echo "
    </body>
</html>";
?>