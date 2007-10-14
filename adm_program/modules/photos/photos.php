<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id:      id des Albums dessen Bilder angezeigt werden sollen
 * thumb_seite: welch Seite der Thumbnails ist die aktuelle
 * start:       mit welchem Element beginnt die Albumliste
 * locked:      das Album soll freigegebn/gesperrt werden
 *
 *****************************************************************************/

require_once("../../system/photo_event_class.php");
require_once("../../system/common.php");
require_once("photo_function.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

//ID Pruefen
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]))
{
    $pho_id = $_GET["pho_id"];
}
else 
{
    $pho_id = NULL;
}

unset($_SESSION['photo_event_request']);

//Wurde keine Album uebergeben kann das Navigationsstack zurueckgesetzt werden
if ($pho_id == NULL)
{
    $_SESSION['navigation']->clear();
}

//URL auf Navigationstack ablegen
$_SESSION['navigation']->addUrl(CURRENT_URL);

//aktuelle event_element
if(array_key_exists("start", $_GET))
{
    if(is_numeric($_GET["start"]) == false)
    {
        $g_message->show("invalid");
    }
    $event_element = $_GET['start'];
}
else
{
    $event_element = 0;
}

if(array_key_exists("thumb_seite", $_GET))
{
    if(is_numeric($_GET["thumb_seite"]) == false)
    {
        $g_message->show("invalid");
    }
    $thumb_seite = $_GET['thumb_seite'];
}
else
{
    $thumb_seite = 1;
}

if(isset($_GET["locked"]))
{
    $locked = $_GET["locked"];
}
else
{
    $locked=NULL;
}

if(!is_numeric($locked) && $locked!=NULL)
{
    $g_message->show("invalid");
}

// Fotoveranstaltungs-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_event']) && $_SESSION['photo_event']->getValue("pho_id") == $pho_id)
{
    $photo_event =& $_SESSION['photo_event'];
    $photo_event->db =& $g_db;
}
else
{
    // einlesen des Albums falls noch nicht in Session gespeichert
    $photo_event = new PhotoEvent($g_db);
    if($pho_id > 0)
    {
        $photo_event->getPhotoEvent($pho_id);
    }

    $_SESSION['photo_event'] =& $photo_event;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($pho_id > 0 && $photo_event->getValue("pho_org_shortname") != $g_organization)
{
    $g_message->show("invalid");
}   

/*********************LOCKED************************************/
//Falls gefordert und Foto-edit-rechte, aendern der Freigabe
if($locked=="1" || $locked=="0")
{
    // erst pruefen, ob der User Fotoberarbeitungsrechte hat
    if(!$g_current_user->editPhotoRight())
    {
        $g_message->show("photoverwaltunsrecht");
    }
    
    $photo_event->setValue("pho_locked", $locked);
    $photo_event->save();

    //Zurueck zur Elternveranstaltung    
    $pho_id = $photo_event->getValue("pho_pho_id_parent");
    $photo_event->getPhotoEvent($pho_id);
}

/*********************HTML_TEIL*******************************/

// Html-Kopf ausgeben
$g_layout['title'] = "Fotogalerien";
if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] =  "<link type=\"application/rss+xml\" rel=\"alternate\" title=\"". $g_current_organization->getValue("org_longname"). " - Fotos\"
            href=\"$g_root_path/adm_program/modules/photos/rss_photos.php\" />";
};

//Lightbox-Mode
if($g_preferences['photo_show_mode']==1)
{
    $g_layout['header'] = $g_layout['header']."
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/prototype.js\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/script.aculo.us/scriptaculous.js?load=effects\"></script>
        <script type=\"text/javascript\" src=\"".$g_root_path."/adm_program/libs/lightbox/lightbox.js\"></script>
        <link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/lightbox.css\" type=\"text/css\" media=\"screen\" />";
}

//Photomodulspezifische CSS laden
$g_layout['header'] = $g_layout['header']."<link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/photos.css\" type=\"text/css\" media=\"screen\" />";

if($g_preferences['photo_show_mode']==1)
{
    $g_layout['onload'] = "initLightbox()";
}

require(SERVER_PATH. "/adm_program/layout/overall_header.php");

//Ueberschift
echo"<h1 class=\"moduleHeadline\">";
if($pho_id > 0)
{
    echo $photo_event->getValue("pho_name");
}
else
{
    echo "Fotogalerien";
}
echo "</h1>";

//solange nach Unterveranstaltungen suchen bis es keine mehr gibt
$navilink = "";
$pho_parent_id = $photo_event->getValue("pho_pho_id_parent");
$photo_event_parent = new PhotoEvent($g_db);

while ($pho_parent_id > 0)
{
    // Einlesen des Eltern Albums
    $photo_event_parent->getPhotoEvent($pho_parent_id);
    
    //Link zusammensetzen
    $navilink = "&nbsp;&gt;&nbsp;<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$photo_event_parent->getValue("pho_id")."\">".
        $photo_event_parent->getValue("pho_name")."</a>".$navilink;

    //Elternveranst
    $pho_parent_id = $photo_event_parent->getValue("pho_pho_id_parent");
}

if($pho_id > 0)
{
    //Ausgabe des Linkpfads
    echo "<div class=\"navigationPath\">
            <a href=\"$g_root_path/adm_program/modules/photos/photos.php\"><img src=\"$g_root_path/adm_program/images/application_view_tile.png\" alt=\"Fotogalerien\" /></a>
            <a href=\"$g_root_path/adm_program/modules/photos/photos.php\">Fotogalerien</a>$navilink
        </div>";
}

//bei Seitenaufruf mit Moderationsrechten
if($g_current_user->editPhotoRight())
{
    echo"<ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/photos/photo_event_new.php?job=new&amp;pho_id=$pho_id\"><img
                        src=\"$g_root_path/adm_program/images/add.png\" alt=\"Album anlegen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/photos/photo_event_new.php?job=new&amp;pho_id=$pho_id\">Album anlegen</a>
                </span>
            </li>";
        if($pho_id > 0)
        {
            echo "<li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id\"><img
                         src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Bilder hochladen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id\">Bilder hochladen</a>
                </span>
            </li>";
        }
    echo "</ul>";
}

//Anlegender Tabelle
echo "<div class=\"photoModuleContainer\">";
    /*************************THUMBNAILS**********************************/
    //Nur wenn uebergebenes Album Bilder enthaelt
    if($photo_event->getValue("pho_quantity") > 0)
    {        
        //Aanzahl der Bilder
        $bilder = $photo_event->getValue("pho_quantity");
        //Ordnerpfad
        $ordner_foto = "/adm_my_files/photos/".$photo_event->getValue("pho_begin")."_".$photo_event->getValue("pho_id");
        $ordner      = SERVER_PATH. $ordner_foto;
        $ordner_url  = $g_root_path. $ordner_foto;

        //Nachsehen ob Thumnailordner existiert und wenn nicht SafeMode ggf. anlegen
        if(!file_exists($ordner."/thumbnails"))
        {
            mkdir($ordner."/thumbnails", 0777);
        }
      
        //Thumbnails pro Seite
        $thumbs_per_side = $g_preferences['photo_thumbs_row']*$g_preferences['photo_thumbs_column'];

        //Differenz
        $difference = $g_preferences['photo_thumbs_row']-$g_preferences['photo_thumbs_column'];

        //Popupfenstergr???üe
        $popup_height = $g_preferences['photo_show_height']+210;
        $popup_width  = $g_preferences['photo_show_width']+70;

        //Ausrechnen der Seitenzahl
        if (settype($bilder, "int") || settype($thumb_seiten, "int"))
        {
            $thumb_seiten = round($bilder / $thumbs_per_side);
        }

        if ($thumb_seiten * $thumbs_per_side < $bilder)
        {
            $thumb_seiten++;
        }

        //Datum des Albums
        echo"<div id=\"photoEventInformation\">
            Datum: ".mysqldate("d.m.y", $photo_event->getValue("pho_begin"));
            if($photo_event->getValue("pho_end") != $photo_event->getValue("pho_begin"))
            {
                echo " bis ".mysqldate("d.m.y", $photo_event->getValue("pho_end"));
            }
        echo"</div>";

        //Container mit Navigation
        echo" <div class=\"pageNavigation\">";
            //Seitennavigation
            echo"Seite:&nbsp;";
    
            //Vorherige thumb_seite
            $vorseite=$thumb_seite-1;
            if($vorseite>=1)
            {
                echo"
                <a href=\"$g_root_path/adm_program/modules/photos/photos.php?thumb_seite=$vorseite&amp;pho_id=$pho_id\">
                    <img src=\"$g_root_path/adm_program/images/back.png\" alt=\"Vorherige\" />
                </a>
                <a href=\"$g_root_path/adm_program/modules/photos/photos.php?thumb_seite=$vorseite&amp;pho_id=$pho_id\">Vorherige</a>&nbsp;&nbsp;";
            }
    
            //Seitenzahlen
            for($s=1; $s<=$thumb_seiten; $s++)
            {
                if($s==$thumb_seite)
                {
                    echo $thumb_seite."&nbsp;";
                }
                if($s!=$thumb_seite){
                    echo"<a href='$g_root_path/adm_program/modules/photos/photos.php?thumb_seite=$s&pho_id=$pho_id'>$s</a>&nbsp;";
                }
            }
    
            //naechste thumb_seite
            $nachseite=$thumb_seite+1;
            if($nachseite<=$thumb_seiten){
                echo"
                <a href=\"$g_root_path/adm_program/modules/photos/photos.php?thumb_seite=$nachseite&amp;pho_id=$pho_id\">N&auml;chste</a>
                <a href=\"$g_root_path/adm_program/modules/photos/photos.php?thumb_seite=$nachseite&amp;pho_id=$pho_id\">
                    <img src=\"$g_root_path/adm_program/images/forward.png\" alt=\"N&auml;chste\" />
                </a>";
            }
        echo"</div>";
            
        //Thumbnailtabelle
        echo"
        <table id=\"photoThumbnailTable\">";
            for($zeile=1;$zeile<=$g_preferences['photo_thumbs_row'];$zeile++)//durchlaufen der Tabellenzeilen
            {
                echo "<tr class=\"photoThumbnailTableRow\">";
                for($spalte=1;$spalte<=$g_preferences['photo_thumbs_column'];$spalte++)//durchlaufen der Tabellenzeilen
                {
                    echo "<td class=\"photoThumbnailTableColumn\">";
                    $bild = ($thumb_seite*$thumbs_per_side)-$thumbs_per_side+($zeile*$g_preferences['photo_thumbs_column'])-$g_preferences['photo_thumbs_row']+$spalte+$difference;//Errechnug welches Bild ausgegeben wird
                    if ($bild <= $bilder)
                    {
                        
                        //Wenn Thumbnail existiert laengere Seite ermitteln
                        $thumb_length=1;
                        if(file_exists($ordner."/thumbnails/".$bild.".jpg"))
                        {
                            //Ermittlung der Original Bildgroesse
                            $bildgroesse = getimagesize($ordner."/thumbnails/".$bild.".jpg");
                            
                            $thumb_length = $bildgroesse[1];
                            if($bildgroesse[0]>$bildgroesse[1])
                            {
                                $thumb_length = $bildgroesse[0];
                            }
                        }
                        
                        //Nachsehen ob Bild als Thumbnail in entsprechender Groesse hinterlegt ist
                        //Wenn nicht und nicht SafeMode anlegen
                        if(!file_exists($ordner."/thumbnails/".$bild.".jpg") || $thumb_length !=$g_preferences['photo_thumbs_scale'])
                        {
                            image_save($ordner."/".$bild.".jpg", $g_preferences['photo_thumbs_scale'], $ordner."/thumbnails/".$bild.".jpg");
                        }
                         
                        
                        
                            //Popup-Mode
                            if($g_preferences['photo_show_mode']==0)
                            {
                                echo "<div>
                                    <img onclick=\"window.open('$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=$bild&pho_id=$pho_id','msg', 'height=".$popup_height.", width=".$popup_width.",left=162,top=5')\" 
                                     src=\"".$ordner_url."/thumbnails/".$bild.".jpg\" class=\"photoThumbnail\" alt=\"$bild\" />
                                </div>";
                            }

                            //Lightbox-Mode
                            elseif($g_preferences['photo_show_mode']==1)
                            {
                                echo "<div>
                                    <a href=\"".$ordner_url."/".$bild.".jpg\" rel=\"lightbox[roadtrip]\" title=\"".$photo_event->getValue("pho_name")."\"><img src=\"".$ordner_url."/thumbnails/".$bild.".jpg\" class=\"photoThumbnail\" alt=\"$bild\" /></a>
                                </div>";
                            }

                            //Gleichesfenster-Mode
                            elseif($g_preferences['photo_show_mode']==2)
                            {
                                echo "<div>
                                    <img onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photo_presenter.php?bild=$bild&pho_id=$pho_id'\" src=\"".$ordner_url."/thumbnails/".$bild.".jpg\" class=\"photoThumbnail\" alt=\"$bild\" />
                                </div>";
                            }   
                            
                            //Buttons fuer moderatoren
                            if($g_current_user->editPhotoRight())
                            {
                                echo"
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photo_function.php?pho_id=$pho_id&amp;bild=$bild&amp;thumb_seite=$thumb_seite&amp;job=rotate&amp;direction=left\"><img 
                                    src=\"$g_root_path/adm_program/images/arrow_turn_left.png\" alt=\"nach links drehen\" title=\"nach links drehen\" /></a>
                                </span>
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photo_function.php?pho_id=$pho_id&amp;bild=$bild&amp;thumb_seite=$thumb_seite&amp;job=delete_request\"><img 
                                    src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Foto l&ouml;schen\" title=\"Foto l&ouml;schen\" /></a>
                                </span>
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photo_function.php?pho_id=$pho_id&amp;bild=$bild&amp;thumb_seite=$thumb_seite&amp;job=rotate&amp;direction=right\"><img 
                                    src=\"$g_root_path/adm_program/images/arrow_turn_right.png\" alt=\"nach rechts drehen\" title=\"nach rechts drehen\" /></a>
                                </span>";
                            }
                            if($g_valid_login == true && $g_preferences['enable_ecard_module'] == 1)
                            {
                                echo"
                                <span class=\"iconLink\">
                                    <a href=\"".$g_root_path."/adm_program/modules/ecards/ecard_form.php?photo=".$bild."&amp;pho_id=".$pho_id."\"><img 
                                    src=\"$g_root_path/adm_program/images/email.png\" alt=\"Als Grußkarte versenden\" title=\"Als Grußkarte versenden\" /></a>
                                </span>";
                            }
                        
                    }//if
                    echo"</td>";
                }//for
                echo "
                </tr>";//Zeilenende
            }//for
        echo "</table>";

        //Anleger und Veraendererinfos
        echo"
        <div class=\"editInformation\">";
            if($photo_event->getValue("pho_usr_id") > 0)
            {
                $user_create = new User($g_db, $photo_event->getValue("pho_usr_id"));
                echo"Angelegt von ". $user_create->getValue("Vorname"). " ". $user_create->getValue("Nachname")
                ." am ". mysqldatetime("d.m.y h:i", $photo_event->getValue("pho_timestamp"));
            }
            
            // Zuletzt geaendert nur anzeigen, wenn ?Ñnderung nach 1 Stunde oder durch anderen Nutzer gemacht wurde
            if($photo_event->getValue("pho_usr_id_change") > 0
            && $photo_event->getValue("pho_last_change") > 0
            && (  strtotime($photo_event->getValue("pho_last_change")) > (strtotime($photo_event->getValue("pho_timestamp")) + 3600)
               || $photo_event->getValue("pho_usr_id_change") != $photo_event->getValue("pho_usr_id") ) )
            {
                $user_change = new User($g_db, $photo_event->getValue("pho_usr_id_change"));
                echo"<br />
                Letztes Update durch ". $user_change->getValue("Vorname"). " ". $user_change->getValue("Nachname")
                ." am ". mysqldatetime("d.m.y h:i", $photo_event->getValue("pho_last_change"));
            }
        echo "</div>";
    }
    /************************Albumliste*************************************/

    //erfassen der Alben die in der Albentabelle ausgegeben werden sollen
    $sql="      SELECT *
                FROM ". TBL_PHOTOS. "
                WHERE pho_org_shortname ='$g_organization' ";
    if($pho_id==NULL)
    {
        $sql=$sql." AND (pho_pho_id_parent IS NULL) ";
    }
    if($pho_id > 0)
    {
        $sql=$sql." AND pho_pho_id_parent = $pho_id ";
    }
    if (!$g_current_user->editPhotoRight())
    {
        $sql=$sql." AND pho_locked = 0 ";
    }

    $sql = $sql." ORDER BY pho_begin DESC ";
    $result_list = $g_db->query($sql);

    //Gesamtzahl der auszugebenden Alben
    $events = $g_db->num_rows($result_list);

    // falls zum aktuellen Album Bilder und Unterveranstaltungen existieren,
    // dann einen Trennstrich zeichnen
    if($photo_event->getValue("pho_quantity") > 0 && $events > 0)
    {
        echo"<hr />";
    }

    $ignored=0; //Summe aller zu ignorierender Elemente
    $ignore=0; //Summe der zu ignorierenden Elemente auf dieser Seite
    for($x=0; $x<$events; $x++)
    {
        $adm_photo_list = $g_db->fetch_array($result_list);
        //Hauptordner
        $ordner = SERVER_PATH. "/adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];
        
        if((!file_exists($ordner) || $adm_photo_list["pho_locked"]==1) && (!$g_current_user->editPhotoRight()))
        {
            $ignored++;
            if($x>=$event_element+$ignored-$ignore)
                $ignore++;
        }
    }

    //Dateizeiger auf erstes auszugebendes Element setzen
    if($events > 0 && $events != $ignored)
    {
        $g_db->data_seek($result_list, $event_element+$ignored-$ignore);
    }

    //Funktion mit selbstaufruf zum erfassen der Bilder in Unterveranstaltungen
    function bildersumme($pho_id_parent)
    {
        global $g_db;
        global $bildersumme;
        
        $sql = "    SELECT *
                    FROM ". TBL_PHOTOS. "
                    WHERE pho_pho_id_parent = $pho_id_parent
                    AND pho_locked = 0";
        $result_child = $g_db->query($sql);
        
        while($adm_photo_child = $g_db->fetch_array($result_child))
        {
            $bildersumme=$bildersumme+$adm_photo_child["pho_quantity"];
            bildersumme($adm_photo_child["pho_id"]);
        };
    }//function

    //Funktion mit selbstaufruf zum auswaehlen eines Beispielbildes aus einem moeglichst hohen Ordner
    function beispielbild($pho_id_parent)
    {
        global $g_db;
        global $bsp_pho_id;
        global $bsp_pic_nr;
        global $bsp_pic_begin;
        
        $sql = "    SELECT *
                    FROM ". TBL_PHOTOS. "
                    WHERE pho_pho_id_parent = $pho_id_parent
                    AND pho_locked   = 0";
        $result_child = $g_db->query($sql);
        
        while($adm_photo_child = $g_db->fetch_array($result_child))
        {
            if($adm_photo_child["pho_quantity"]!=0)
            {
                $bsp_pic_nr = mt_rand(1, $adm_photo_child["pho_quantity"]);
                $bsp_pho_id = $adm_photo_child["pho_id"];
                $bsp_pic_begin = $adm_photo_child["pho_begin"];
            }
            else 
            {
                beispielbild($adm_photo_child["pho_id"]);
            }
        };
    }//function

    // Navigation mit Vor- und Zurueck-Buttons
    $base_url = "$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$pho_id;
    echo "<div class=\"pageNavigation\">".generatePagination($base_url, $events-$ignored, 10, $event_element, TRUE)."</div>";
    
    $counter = 0;

    for($x=$event_element+$ignored-$ignore; $x<=$event_element+$ignored+9 && $x<$events; $x++)
    {
        $adm_photo_list = $g_db->fetch_array($result_list);
        //Hauptordner
        $ordner = SERVER_PATH. "/adm_my_files/photos/".$adm_photo_list["pho_begin"]."_".$adm_photo_list["pho_id"];

        //wenn ja Zeile ausgeben
        if(file_exists($ordner) && ($adm_photo_list["pho_locked"]==0) || $g_current_user->editPhotoRight())
        {
            if($counter == 0)
            {
                echo '<table id="photo_event_table">';
            }

            //Summe der Bilder erfassen und zufaelliges Beispeilbild auswaehlen
            $bildersumme=$adm_photo_list["pho_quantity"];
            //Funktion zum Bildersummieren aufrufen
            bildersumme($adm_photo_list["pho_id"]);

            //Bild aus Album als Vorschau auswaehlen
            $bsp_pho_id=0;
            $bsp_pic_nr=0;
            $bsp_pic_begin=0;

            //sehen ob die Hauptveranstaltung Bilder enthaelt, nur wenn nicht in unterveranst suchen
            if($adm_photo_list["pho_quantity"]>0)
            {
                $bsp_pic_nr=mt_rand(1, $adm_photo_list["pho_quantity"]);
                $bsp_pho_id=$adm_photo_list["pho_id"];
                $bsp_pic_begin=$adm_photo_list["pho_begin"];
            }
            //Sonst Funktionsaufruf zur Bildauswahl
            else 
            {
                beispielbild($adm_photo_list["pho_id"]);
            }

            //Pfad des Beispielbildes
            $bsp_pic_path = SERVER_PATH. "/adm_my_files/photos/".$bsp_pic_begin."_".$bsp_pho_id."/".$bsp_pic_nr.".jpg";

            //Wenn kein Bild gefunden wurde
            if($bsp_pho_id==0)
            {
               $bsp_pic_path = SERVER_PATH. "/adm_program/images/nopix.jpg";
            }

            //Ausgabe
            echo"
            <tr class=\"photoEventTableRow\">
                <td class=\"photoEventTablePicColumn\">";
                    if(file_exists($ordner))
                    {
                        //beispielbild nur anzeigen wenn x-seite unter 3+ y-seite ist
                        $bildgroesse = getimagesize($bsp_pic_path);
                        if($bildgroesse[0]<$bildgroesse[1]*3)
                        {
                            echo"
                                <a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."\">
                                <img  class=\"photoPreviewPic\" src=\"$g_root_path/adm_program/modules/photos/photo_show.php?pho_id=".$bsp_pho_id."&amp;pic_nr=".$bsp_pic_nr."&amp;pho_begin=".$bsp_pic_begin."&amp;scal=".$g_preferences['photo_preview_scale']."&amp;side=y\" alt=\"Zufallsbild\" /></a>
                            ";
                        }
                    }
                echo"</td>
                <td class=\"photoEventTableTextColumn\">";
                    if((!file_exists($ordner) && $g_current_user->editPhotoRight()) || ($adm_photo_list["pho_locked"]==1 && file_exists($ordner)))
                    {                   
                        echo"<ul class=\"iconLinkRow\">";
                        //Warnung fuer Leute mit Fotorechten: Ordner existiert nicht
                        if(!file_exists($ordner) && $g_current_user->editPhotoRight())
                        {
                            echo"<li><img src=\"$g_root_path/adm_program/images/warning16.png\" class=\"iconLink\" alt=\"Warnhinweis\" title=\"Warnhinweis\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=folder_not_found','Message','width=400, height=400, left=310,top=200,scrollbars=no')\" /></li>";
                        }

                        //Hinweis fur Leute mit Photorechten: Album ist gesperrt
                        if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
                        {
                            echo"<li><img src=\"$g_root_path/adm_program/images/lock.png\" class=\"iconLink\" alt=\"Album ist gesperrt\" title=\"Album ist gesperrt\"
                            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=not_approved','Message','width=400, height=300, left=310,top=200,scrollbars=no')\" /></li>";
                        }
                        echo"</ul>";
                    }

                    //Album angaben
                    if(file_exists($ordner))
                    {
                        echo"<a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=".$adm_photo_list["pho_id"]."\">".$adm_photo_list["pho_name"]."</a><br />";
                    }
                    else
                    {
                        echo $adm_photo_list["pho_name"];
                    }

                    echo"
                        Bilder: ".$bildersumme." <br />
                        Datum: ".mysqldate("d.m.y", $adm_photo_list["pho_begin"]);
                        if($adm_photo_list["pho_end"] != $adm_photo_list["pho_begin"])
                        {
                            echo " bis ".mysqldate("d.m.y", $adm_photo_list["pho_end"]);
                        }
                        echo "<br />Fotos von: ".$adm_photo_list["pho_photographers"]."<br/>";

                        //bei Moderationrecheten
                        if ($g_current_user->editPhotoRight())
                        {
                            $this_pho_id = $adm_photo_list["pho_id"];
                            if(file_exists($ordner))
                            {
                                echo"
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$this_pho_id\"><img 
                                    src=\"$g_root_path/adm_program/images/photo.png\" alt=\"Bilder hochladen\" title=\"Bilder hochladen\" /></a>
                                </span>

                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photo_event_new.php?pho_id=$this_pho_id&amp;job=change\"><img 
                                    src=\"$g_root_path/adm_program/images/edit.png\" alt=\"Bearbeiten\" title=\"Bearbeiten\" /></a>
                                </span>";
                            }

                            echo"
                            <span class=\"iconLink\">
                                <a href=\"$g_root_path/adm_program/modules/photos/photo_event_function.php?job=delete_request&amp;pho_id=$this_pho_id\"><img 
                                src=\"$g_root_path/adm_program/images/cross.png\" alt=\"Album L&ouml;schen\" title=\"Album L&ouml;schen\" /></a>
                            </span>";

                            if($adm_photo_list["pho_locked"]==1 && file_exists($ordner))
                            {
                                echo"
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=$this_pho_id&amp;locked=0\"><img 
                                    src=\"$g_root_path/adm_program/images/key.png\"  alt=\"Freigeben\" title=\"Freigeben\" /></a>
                                </span>";
                            }
                            elseif($adm_photo_list["pho_locked"]==0 && file_exists($ordner))
                            {
                                echo"
                                <span class=\"iconLink\">
                                    <a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=$this_pho_id&amp;locked=1\"><img 
                                    src=\"$g_root_path/adm_program/images/key.png\" alt=\"Sperren\" title=\"Sperren\" /></a>
                                </span>";
                            }
                        }
                    echo"
                </td>
            </tr>";
            $counter++;
        }//Ende wenn Ordner existiert
    };//for

    if($counter > 0)
    {
        //Tabellenende
        echo "</table>";
    }
        
    /****************************Leeres Album****************/
    //Falls das Album weder Bilder noch Unterordner enthaelt
    if(($photo_event->getValue("pho_quantity")=="0" || strlen($photo_event->getValue("pho_quantity")) == 0) && $events<1)  // alle vorhandenen Albumen werden ignoriert
    {
        echo"Dieses Album enth&auml;lt leider noch keine Bilder.";
    }
    
    if($g_db->num_rows($result_list) > 2)
    {
        // Navigation mit Vor- und Zurueck-Buttons
        // erst anzeigen, wenn mehr als 2 Eintraege (letzte Navigationsseite) vorhanden sind
        echo generatePagination($base_url, $events-$ignored, 10, $event_element, TRUE);
    }
echo "</div>";

/************************Buttons********************************/
//Uebersicht
if($photo_event->getValue("pho_id") > 0)
{
    echo "
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href=\"$g_root_path/adm_program/system/back.php\"><img 
                src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zurück\" /></a>
                <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
            </span>
        </li>
    </ul>";
}

/***************************Seitenende***************************/

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>