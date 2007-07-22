<?php
/******************************************************************************
 * Photogalerien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 *
 * Uebergaben:
 * pho_id: id der Veranstaltung die bearbeitet werden soll
 * job:    - makenew (neue eingaben speichern)
 *         - makechange (Aenderungen ausfuehren)
 *         - delete (Loeschen einer Veranstaltung)
 *         - delete_request
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
 * Foundation, Inc., 79 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
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

if(isset($_GET["job"]) && $_GET["job"] != "makenew" && $_GET["job"] != "do_delete"
    && $_GET["job"] != "makechange" && $_GET["job"] != "delete_request")
{
    $g_message->show("invalid");
}

//Gepostete Variablen in Session speichern
$_SESSION['photo_event_request'] = $_REQUEST;

//Uebernahme Variablen
$pho_id  = $_GET['pho_id'];

// Fotoeventobjekt anlegen
$photo_event = new PhotoEvent($g_adm_con);

if($_GET["job"] != "makenew")
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

    //Veranstaltung
    if(strlen($_POST['pho_name']) == 0)
    {
        $g_message->show("feld", "Veranstaltung");
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
    if ($_GET["job"]=="makenew")
    {
        //Wenn keine Schreibrechte Loeschen der Daten aus der Datenbank
        if(is_writeable(SERVER_PATH. "/adm_my_files/photos") == false)
        {
            $g_message->addVariableContent("adm_my_files/photos", 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2);
            $g_message->setForwardUrl("$g_root_path/adm_program/modules/photos/photos.php");
            $g_message->show("write_access");
        }

        // Veranstaltung in Datenbank schreiben
        $photo_event->save();
        $pho_id = $photo_event->getValue("pho_id");
        
        //Verzeichnis erstellen    
        $ordnerneu = $_POST['pho_begin']."_".$pho_id;

        //wenn Rechte OK, Ordner erstellen
        $ordnererstellt = mkdir(SERVER_PATH. "/adm_my_files/photos/$ordnerneu",0777);
        chmod(SERVER_PATH. "/adm_my_files/photos/$ordnerneu", 0777);

        // Anlegen der Veranstaltung war erfolgreich -> event_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung des Ordners***********************************/
    //Bearbeiten Anfangsdatum und Ordner ge&auml;ndert
    elseif ($_GET["job"]=="makechange" && $ordner != SERVER_PATH. "/adm_my_files/photos/".$_POST['pho_begin']."_"."$pho_id")
    {
        $ordnerneu = "$beginn"."_".$photo_event->getValue("pho_id");
        //testen ob Schreibrechte fuer adm_my_files bestehen
        if(is_writeable(SERVER_PATH. "/adm_my_files/photos") == false)
        {
            $g_message->addVariableContent("adm_my_files/photos", 1);
            $g_message->addVariableContent($g_preferences['email_administrator'], 2);
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
        
        // Aendern der Veranstaltung war erfolgreich -> event_new aus der Historie entfernen
        $_SESSION['navigation']->deleteLastUrl();
    }//if

    /********************Aenderung der Datenbankeinträge***********************************/

    if($_GET["job"]=="makechange")
    {
        // geaenderte Daten in der Datenbank akutalisieren
        $photo_event->save();
    }

    //Photomodulspezifische CSS laden
	$g_layout['header'] = "<link rel=\"stylesheet\" href=\"$g_root_path/adm_program/layout/photos.css\" type=\"text/css\" media=\"screen\" />";
    
    // HTML-Kopf
    $g_layout['title'] = "Veranstaltungsverwaltung";
    require(SERVER_PATH. "/adm_program/layout/overall_header.php");

    echo"<h1>Bericht</h1>";
    echo"
    <div class=\"photo_list_container\">
		<div class=\"form_row\">Die Veranstaltung wurde erfolgreich angelegt / ge&auml;ndert:</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Veranstaltung:</div>
			<div class=\"form_row_field\">".$photo_event->getValue("pho_name")."</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">in Ordner:</div>
			<div class=\"form_row_field\">";
 				if($photo_event->getValue("pho_pho_id_parent") > 0)
                {
                    $photo_event_parent = new PhotoEvent($g_adm_con, $photo_event->getValue("pho_pho_id_parent"));
                    echo $photo_event_parent->getValue("pho_name");
                }
                else
                {
                    echo "Fotogalerien(Hauptordner)";
                }
        	echo"</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Anfangsdatum:</div>
			<div class=\"form_row_field\">".mysqldate("d.m.y", $photo_event->getValue("pho_begin"))."</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Enddatum:</div>
			<div class=\"form_row_field\">".mysqldate("d.m.y", $photo_event->getValue("pho_end"))."</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Fotografen:</div>
			<div class=\"form_row_field\">".$photo_event->getValue("pho_photographers")."</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Gesperrt:</div>
			<div class=\"form_row_field\">";
	        	if($photo_event->getValue("pho_locked")==1)
	            {
	                 echo "Ja";
	            }
	            else
	            {
	                 echo "Nein";
	            }  	
        	echo"</div>
		</div>
        
		<div class=\"form_row\">
			<div class=\"form_row_text\">Aktuelle Bilderzahl:</div>
			<div class=\"form_row_field\">";
        		if($photo_event->getValue("pho_quantity")!=NULL)
        		{
					echo $photo_event->getValue("pho_quantity");
        		}
        		else
        		{
					echo"0";
        		}
	        echo"</div>
		</div>
        <div class=\"form_row\"><hr  /></div>
        <div class=\"form_row\">
			<button name=\"weiter\" type=\"button\" value=\"weiter\" onclick=\"self.location.href='$g_root_path/adm_program/modules/photos/photos.php?pho_id=$pho_id'\">Weiter&nbsp;
	            <img src=\"$g_root_path/adm_program/images/forward.png\" alt=\"Weiter\">
	        </button>
		</div>
	</div><br><br>";
}//submit


/***********************Veranstaltung Loeschen*******************************************/

//Nachfrage ob geloescht werden soll
if(isset($_GET["job"]) && $_GET["job"]=="delete_request")
{
    $g_message->setForwardYesNo("$g_root_path/adm_program/modules/photos/photo_event_function.php?job=do_delete&pho_id=$pho_id");
    $g_message->show("delete_veranst", utf8_encode($photo_event->getValue("pho_name")));
}

// Nun Veranstaltung loeschen
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