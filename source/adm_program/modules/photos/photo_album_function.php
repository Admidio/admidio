<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 * pho_id: id des Albums das bearbeitet werden soll
 * job:    - new    (neue eingaben speichern)
 *         - change (Aenderungen ausfuehren)
 *         - delete (Loeschen eines Albums)
 *		   - set_rights
 *
 *****************************************************************************/

require_once("../../system/common.php");
require_once("../../system/login_valid.php");
require_once("../../system/classes/table_photos.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// erst pruefen, ob der User Fotoberarbeitungsrechte hat
if(!$g_current_user->editPhotoRight())
{
    $g_message->show("photoverwaltunsrecht");
}

// Uebergabevariablen pruefen

if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false && $_GET["pho_id"]!=NULL)
{
    $g_message->show("invalid");
}

if(isset($_GET["job"]) && $_GET["job"] != "new" && $_GET["job"] != "delete" && $_GET["job"] != "change")
{
    $g_message->show("invalid");
}

//Gepostete Variablen in Session speichern
$_SESSION['photo_album_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];

// Fotoalbumobjekt anlegen
$photo_album = new TablePhotos($g_db);

if($_GET["job"] != "new")
{
    $photo_album->readData($pho_id);
    
    // Pruefung, ob das Fotoalbum zur aktuellen Organisation gehoert
    if($photo_album->getValue("pho_org_shortname") != $g_organization)
    {
        $g_message->show("norights");
    }
}

//Speicherort mit dem Pfad aus der Datenbank
$ordner = SERVER_PATH. "/adm_my_files/photos/".$photo_album->getValue("pho_begin")."_".$photo_album->getValue("pho_id");

/********************Aenderungen oder Neueintraege kontrollieren***********************************/
if(isset($_POST["submit"]) && $_POST["submit"])
{
    //Gesendete Variablen Uebernehmen und kontollieren

    //Freigabe(muss zuerst gemacht werden da diese nicht gesetzt sein koennte)
    if(isset($_POST['pho_locked']) == false)
    {
        $_POST['pho_locked'] = 0;
    }

    //Album
    if(strlen($_POST['pho_name']) == 0)
    {
        $g_message->show("feld", "Album");
    }
    
    //Beginn
    if(strlen($_POST['pho_begin'] > 0))
    {
        if(dtCheckDate($_POST['pho_begin']))
        {
            $_POST['pho_begin'] = dtFormatDate($_POST['pho_begin'], "Y-m-d");
        }
        else
        {
            $g_message->show("date_invalid", "Beginn");
        }
    }
    else
    {
        $g_message->show("feld", "Beginn");
    }    
    
    //Ende
    if(strlen($_POST['pho_end']) > 0)
    {
        if(dtCheckDate($_POST['pho_end']))
        {
            $_POST['pho_end'] = dtFormatDate($_POST['pho_end'], "Y-m-d");
        }
        else
        {
            $g_message->show("date_invalid", "Ende");
        }
    }
    else
    {
        $_POST['pho_end'] = $_POST['pho_begin'];
    }

    //Anfang muss vor oder gleich Ende sein
    if(strlen($_POST['pho_end']) > 0 && $_POST['pho_end'] < $_POST['pho_begin'])
    {
        $g_message->show("startvorend");
    }

    //Photographen
    if(strlen($_POST["pho_photographers"]) == 0)
    {
        $_POST["pho_photographers"] = "unbekannt";
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "pho_") === 0)
        {
            $photo_album->setValue($key, $value);
        }
    }
    
    /********************neuen Datensatz anlegen***********************************/
    if ($_GET["job"]=="new")
    {
        // Album in Datenbank schreiben
        $photo_album->save();
        
        $error = $photo_album->createFolder();
        
        if($error['code'] < 0)
        {
            $photo_album->delete();
            
            // der entsprechende Ordner konnte nicht angelegt werden
            $g_message->addVariableContent($error['text'], 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
            $g_message->show("write_access");
        }
        
        $pho_id = $photo_album->getValue("pho_id");

        // Anlegen des Albums war erfolgreich -> album_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung des Ordners***********************************/
    //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
    elseif ($_GET["job"]=="change" && $ordner != SERVER_PATH. "/adm_my_files/photos/".$_POST['pho_begin']."_"."$pho_id")
    {
        $ordnerneu = $_POST['pho_begin']."_".$photo_album->getValue("pho_id");
        //testen ob Schreibrechte fuer adm_my_files bestehen
        if(is_writeable(SERVER_PATH. "/adm_my_files/photos") == false)
        {
            $g_message->addVariableContent("adm_my_files/photos", 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2, false);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
            $g_message->show("write_access");
        }
        //wenn Rechte OK, Ordner erstellen
        else
        {
            mkdir(SERVER_PATH. "/adm_my_files/photos/$ordnerneu",0777);
            chmod(SERVER_PATH. "/adm_my_files/photos/$ordnerneu", 0777);
            mkdir(SERVER_PATH. "/adm_my_files/photos/$ordnerneu/thumbnails",0777);
            chmod(SERVER_PATH. "/adm_my_files/photos/$ordnerneu/thumbnails", 0777);
        }

        //Fotos verschieben
        for($x=1; $x<=$photo_album->getValue("pho_quantity"); $x++)
        {
            chmod("$ordner/$x.jpg", 0777);
            copy("$ordner/$x.jpg", SERVER_PATH. "/adm_my_files/photos/$ordnerneu/$x.jpg");
            unlink("$ordner/$x.jpg");
        }
        
        //Thumbnails verschieben
        for($x=1; $x<=$photo_album->getValue("pho_quantity"); $x++)
        {
            chmod("$ordner/thumbnails/$x.jpg", 0777);
            copy("$ordner/thumbnails/$x.jpg", SERVER_PATH. "/adm_my_files/photos/$ordnerneu/thumbnails/$x.jpg");
            unlink("$ordner/thumbnails/$x.jpg");
        }

        //alten ordner loeschen
        chmod("$ordner/thumbnails", 0777);
        rmdir("$ordner/thumbnails");
        chmod("$ordner", 0777);
        rmdir("$ordner");
        
        // Aendern des Albums war erfolgreich -> album_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung der Datenbankeinträge***********************************/

    if($_GET["job"]=="change")
    {
        // geaenderte Daten in der Datenbank akutalisieren
        $photo_album->save();
    }

    //Photomodulspezifische CSS laden
    $g_layout['header'] = "<link rel=\"stylesheet\" href=\"". THEME_PATH. "/css/photos.css\" type=\"text/css\" media=\"screen\" />";
    
    // HTML-Kopf
    $g_layout['title'] = "Foto-Abum-Verwaltung";
    require(THEME_SERVER_PATH. "/overall_header.php");

    echo"
    <div class=\"formLayout\" id=\"photo_report_form\">
        <div class=\"formHead\">Bericht</div>
        <div class=\"formBody\"> 
            <p>Das Album wurde erfolgreich angelegt / ge&auml;ndert:</p>  
            <ul class=\"formFieldList\">
                <li><dl>
                    <dt>Album:</dt>
                    <dd>".$photo_album->getValue("pho_name")."</dd>
                </dl></li>

                <li><dl>
                    <dt>im Album:</dt>
                    <dd>";
                        if($photo_album->getValue("pho_pho_id_parent") > 0)
                        {
                            $photo_album_parent = new TablePhotos($g_db, $photo_album->getValue("pho_pho_id_parent"));
                            echo $photo_album_parent->getValue("pho_name");
                        }
                        else
                        {
                            echo "Fotogalerien(Hauptordner)";
                        }
                    echo"</dd>
                </dl></li>

                <li><dl>
                    <dt>Anfangsdatum:</dt>
                    <dd>".mysqldate("d.m.y", $photo_album->getValue("pho_begin"))."</dd>
                </dl></li>

                <li><dl>
                    <dt>Enddatum:</dt>
                    <dd>".mysqldate("d.m.y", $photo_album->getValue("pho_end"))."</dd>
                </dl></li>

                <li><dl>
                    <dt>Fotografen:</dt>
                    <dd>".$photo_album->getValue("pho_photographers")."</dd>
                </dl></li>

                <li><dl>
                    <dt>Gesperrt:</dt>
                    <dd>";
                        if($photo_album->getValue("pho_locked")==1)
                        {
                             echo "Ja";
                        }
                        else
                        {
                             echo "Nein";
                        }   
                    echo"</dd>
                </dl></li>

                <li><dl>
                    <dt>Aktuelle Bilderzahl:</dt>
                    <dd>";
                        if($photo_album->getValue("pho_quantity")!=NULL)
                        {
                            echo $photo_album->getValue("pho_quantity");
                        }
                        else
                        {
                            echo"0";
                        }
                    echo"</dd>
                </dl></li>
            <ul>
        </div>
    </div>
    <ul class=\"iconTextLinkList\">
        <li>
            <span class=\"iconTextLink\">
                <a href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\">Weiter&nbsp;</a>
                <a href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\"><img src=\"". THEME_PATH. "/icons/forward.png\" alt=\"Weiter\" /></a>
            </span>
        </li>
    </ul>";
}//submit


/***********************Album Loeschen*******************************************/

if(isset($_GET["job"]) && $_GET["job"]=="delete")
{
    if($photo_album->delete())
    {
        echo "done"; 
    }
    exit();
}

require(THEME_SERVER_PATH. "/overall_footer.php");
?>