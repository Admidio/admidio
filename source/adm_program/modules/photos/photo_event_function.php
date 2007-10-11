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
 * pho_id: id des Albums das bearbeitet werden soll
 * job:    - new    (neue eingaben speichern)
 *         - change (Aenderungen ausfuehren)
 *         - delete (Loeschen eines Albums)
 *         - delete_request
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/photo_event_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_photo_module'] != 1)
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

if(isset($_GET["job"]) && $_GET["job"] != "new" && $_GET["job"] != "do_delete"
    && $_GET["job"] != "change" && $_GET["job"] != "delete_request")
{
    $g_message->show("invalid");
}

//Gepostete Variablen in Session speichern
$_SESSION['photo_event_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];

// Fotoeventobjekt anlegen
$photo_event = new PhotoEvent($g_db);

if($_GET["job"] != "new")
{
    $photo_event->getPhotoEvent($pho_id);
    
    // Pruefung, ob die Fotoveranstaltung zur aktuellen Organisation gehoert
    if($photo_event->getValue("pho_org_shortname") != $g_organization)
    {
        $g_message->show("norights");
    }
}

//Speicherort mit dem Pfad aus der Datenbank
$ordner = SERVER_PATH. "/adm_my_files/photos/".$photo_event->getValue("pho_begin")."_".$photo_event->getValue("pho_id");

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
        $_POST["pho_photographers"] = "leider unbekannt";
    }

    // POST Variablen in das Role-Objekt schreiben
    foreach($_POST as $key => $value)
    {
        if(strpos($key, "pho_") === 0)
        {
            $photo_event->setValue($key, $value);
        }
    }
    
    /********************neuen Datensatz anlegen***********************************/
    if ($_GET["job"]=="new")
    {
        // Album in Datenbank schreiben
        $photo_event->save();
        
        $error = $photo_event->createFolder();
        
        if($error['code'] < 0)
        {
            $photo_event->delete();
            
            // der entsprechende Ordner konnte nicht angelegt werden
            $g_message->addVariableContent($error['text'], 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2 ,false);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
            $g_message->show("write_access");
        }
        
        $pho_id = $photo_event->getValue("pho_id");

        // Anlegen des Albums war erfolgreich -> event_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung des Ordners***********************************/
    //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
    elseif ($_GET["job"]=="change" && $ordner != SERVER_PATH. "/adm_my_files/photos/".$_POST['pho_begin']."_"."$pho_id")
    {
        $ordnerneu = "$beginn"."_".$photo_event->getValue("pho_id");
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
        }

        //Dateien verschieben
        for($x=1; $x<=$photo_event->getValue("pho_quantity"); $x++)
        {
            chmod("$ordner/$x.jpg", 0777);
            copy("$ordner/$x.jpg", SERVER_PATH. "/adm_my_files/photos/$ordnerneu/$x.jpg");
            unlink("$ordner/$x.jpg");
        }

        //alten ordner loeschen
        chmod("$ordner", 0777);
        rmdir("$ordner");
        
        // Aendern des Albums war erfolgreich -> event_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung der Datenbankeinträge***********************************/

    if($_GET["job"]=="change")
    {
        // geaenderte Daten in der Datenbank akutalisieren
        $photo_event->save();
    }

    //Photomodulspezifische CSS laden
    $g_layout['header'] = "<link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/photos.css\" type=\"text/css\" media=\"screen\" />";
    
    // HTML-Kopf
    $g_layout['title'] = "Foto-Abum-Verwaltung";
    require(SERVER_PATH. "/adm_program/layout/overall_header.php");

    echo"
    <div class=\"formLayout\" id=\"photo_report_form\">
        <div class=\"formHead\">Bericht</div>
        <div class=\"formBody\"> 
            <p>Das Album wurde erfolgreich angelegt / ge&auml;ndert:</p>  
            <ul class=\"formFieldList\">
                <li><dl>
                    <dt>Album:</dt>
                    <dd>".$photo_event->getValue("pho_name")."</dd>
                </dl></li>

                <li><dl>
                    <dt>im Album:</dt>
                    <dd>";
                        if($photo_event->getValue("pho_pho_id_parent") > 0)
                        {
                            $photo_event_parent = new PhotoEvent($g_db, $photo_event->getValue("pho_pho_id_parent"));
                            echo $photo_event_parent->getValue("pho_name");
                        }
                        else
                        {
                            echo "Fotogalerien(Hauptordner)";
                        }
                    echo"</dd>
                </dl></li>

                <li><dl>
                    <dt>Anfangsdatum:</dt>
                    <dd>".mysqldate("d.m.y", $photo_event->getValue("pho_begin"))."</dd>
                </dl></li>

                <li><dl>
                    <dt>Enddatum:</dt>
                    <dd>".mysqldate("d.m.y", $photo_event->getValue("pho_end"))."</dd>
                </dl></li>

                <li><dl>
                    <dt>Fotografen:</dt>
                    <dd>".$photo_event->getValue("pho_photographers")."</dd>
                </dl></li>

                <li><dl>
                    <dt>Gesperrt:</dt>
                    <dd>";
                        if($photo_event->getValue("pho_locked")==1)
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
                        if($photo_event->getValue("pho_quantity")!=NULL)
                        {
                            echo $photo_event->getValue("pho_quantity");
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
                <a href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\"><img src=\"$g_root_path/adm_program/images/forward.png\" alt=\"Weiter\" /></a>
            </span>
        </li>
    </ul>";
}//submit


/***********************Album Loeschen*******************************************/

//Nachfrage ob geloescht werden soll
if(isset($_GET["job"]) && $_GET["job"]=="delete_request")
{
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/photos/photo_event_function.php?job=do_delete&pho_id=$pho_id");
    $g_message->show("delete_veranst", $photo_event->getValue("pho_name"));
}

// Nun Album loeschen
if(isset($_GET["job"]) && $_GET["job"]=="do_delete")
{
    $return_code = $photo_event->delete();
    
    $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php?pho_id=". $photo_event->getValue("pho_pho_id_parent"));
    if($return_code)
    {
        $g_message->show("event_deleted");
    }
    else
    {
        $g_message->show("event_deleted_error");
    }
}

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");
?>