<?php
/******************************************************************************
 * Bilder werden hochgeladen und Bericht angezeigt
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * pho_id: id des Albums zu dem die Bilder hinzugefuegt werden sollen
 *
 *****************************************************************************/

require("../../system/photo_album_class.php");
require("../../system/common.php");
require("../../system/login_valid.php");
require("photo_function.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_photo_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show("photoverwaltunsrecht");
}

// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false)
{
    $g_message->show("invalid");
}

if (empty($_POST))
{
    $g_message->show("empty_photo_post", ini_get(post_max_size));
}

// Fotoalbums-Objekt erzeugen oder aus Session lesen
if(isset($_SESSION['photo_album']) && $_SESSION['photo_album']->getValue("pho_id") == $_GET["pho_id"])
{
    $photo_album =& $_SESSION['photo_album'];
    $photo_album->db =& $g_db;
}
else
{
    $photo_album = new PhotoAlbum($g_db, $_GET["pho_id"]);
    $_SESSION['photo_album'] =& $photo_album;
}

// pruefen, ob Album zur aktuellen Organisation gehoert
if($photo_album->getValue('pho_org_shortname') != $g_organization)
{
    $g_message->show("invalid");
}

//Ordnerpfad
$ordner = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id");

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
                    $g_message->show("dateiendungphotoup");
                }
            }

            //Die hochgeladene Datei ueberschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Groesse.
            if($_FILES["bilddatei"]["error"]["$x"]==1)
            {
                $g_message->show("photo_2big", ini_get("upload_max_filesize"));
            }
        }

    }

    //Kontrolle ob Bilder ausgewaehlt wurden
    if($counter==0)
    {
        $g_message->show("photodateiphotoup");
    }

    // Bilder wurden erfolgreich hochgeladen -> Upload-Seite aus der Navi-Klasse entfernen
    $_SESSION['navigation']->deleteLastUrl();
}//Kontrollmechanismen

//Photomodulspezifische CSS laden
$g_layout['header'] = "<link rel=\"stylesheet\" href=\"". THEME_PATH. "/css/photos.css\" type=\"text/css\" media=\"screen\" />";

// Html-Kopf ausgeben
$g_layout['title'] = "Fotos hochladen";
require(THEME_SERVER_PATH. "/overall_header.php");

/*****************************Verarbeitung******************************************/
if($_POST["upload"])
{
    //bei selbstaufruf der Datei Hinweise zu hochgeladenen Dateien und Kopieren der Datei in Ordner
    //Anlegen des Berichts
    echo"<h1 class=\"moduleHeadline\">Fotogalerien - Upload</h1>
    <div class=\"photoModuleContainer\">
        Bitte einen Moment Geduld ...<br /><br />
        Die Bilder wurden dem Album <strong>".$photo_album->getValue("pho_name")."</strong>
        erfolgreich hinzugefügt, wenn sie hier angezeigt werden.<br />";

        //Verarbeitungsschleife fuer die einzelnen Bilder
        $bildnr=$photo_album->getValue("pho_quantity");
        for($x=0; $x<=4; $x=$x+1)
        {
            $y=$x+1;
            if($_FILES["bilddatei"]["name"][$x]!=NULL && $ordner!=NULL)
            {
                //errechnen der neuen Bilderzahl
                $bildnr++;
                echo "<br />Bild $bildnr:<br />";

                //Bild in Tempordner verschieben, groeße aendern und speichern
                if(move_uploaded_file($_FILES["bilddatei"]["tmp_name"][$x], SERVER_PATH. "/adm_my_files/photos/temp".$y.".jpg"))
                {
                    $temp_bild=SERVER_PATH. "/adm_my_files/photos/temp".$y.".jpg";

                    //Bild skalliert speichern
                    image_save($temp_bild, $g_preferences['photo_save_scale'], $ordner."/".$bildnr.".jpg");
                    
                    //Nachsehen ob Thumnailordner existiert
                    if(!file_exists($ordner."/thumbnails"))
                    {
                        mkdir($ordner."/thumbnails", 0777);
                        chmod($ordner."/thumbnails", 0777);
                    }
                    
                    //Thumbnail speichern
                    image_save($temp_bild, $g_preferences['photo_thumbs_scale'], $ordner."/thumbnails/".$bildnr.".jpg");
                    //Loeschen des Bildes aus Arbeitsspeicher
                    
                    if(file_exists(SERVER_PATH. "/adm_my_files/photos/temp".$y.".jpg"))
                    {
                        unlink(SERVER_PATH. "/adm_my_files/photos/temp".$y.".jpg");
                    }           
                    
                
                }//Ende Bild speichern


                //Kontrolle
                if(file_exists($ordner."/".$bildnr.".jpg"))
                {
                    echo"<img src=\"photo_show.php?scal=300&amp;pic_nr=".$bildnr."&amp;pho_id=".$photo_album->getValue("pho_id")."&amp;pho_begin=".$photo_album->getValue("pho_begin")."\"
                            class=\"photoOutput\" /><br /><br />";

                    //Aendern der Datenbankeintaege
                    $photo_album->setValue("pho_quantity", $photo_album->getValue("pho_quantity")+1);
                    $photo_album->save();
                }
                else
                {
                    $bildnr--;
                    echo"Das Bild konnte nicht verarbeitet werden.";
                }
                unset($y);
            }//if($bilddatei!= "")
        }//for

        //Buttons
        echo"
        <hr />
        <ul class=\"iconTextLinkList\">
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id\"><img 
                    src=\"". THEME_PATH. "/icons/application_view_tile.png\" alt=\"Übersicht\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id\">Übersicht</a>
                </span>
            </li>
            <li>
                <span class=\"iconTextLink\">
                    <a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id\"><img 
                    src=\"". THEME_PATH. "/icons/photo.png\" alt=\"Weitere Bilder hochladen\" /></a>
                    <a href=\"$g_root_path/adm_program/modules/photos/photoupload.php?pho_id=$pho_id\">Weitere Bilder hochladen</a>
                </span>
            </li>
         </ul>
    </div><br /><br />";
}//if($upload)

//Seitenende
require(THEME_SERVER_PATH. "/overall_footer.php");

?>