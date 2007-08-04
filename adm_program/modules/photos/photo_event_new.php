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
 * job:    - new (neues Formular)
 *         - change (Formular fuer Aenderunmgen)
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
//Veranstaltungsuebergabe Numerisch und != Null?
if(isset($_GET["pho_id"]) && is_numeric($_GET["pho_id"]) == false && $_GET["pho_id"]!=NULL)
{
    $g_message->show("invalid");
}

// Aufgabe gesetzt, welche Aufgabe
if(isset($_GET["job"]) && $_GET["job"] != "new" && $_GET["job"] != "change")
{
    $g_message->show("invalid");
}

//Variablen initialisieren
$pho_id = $_GET["pho_id"];
$_SESSION['navigation']->addUrl($g_current_url);

// Fotoeventobjekt anlegen
$photo_event = new PhotoEvent($g_db);

// nur Daten holen, wenn Veranstaltung editiert werden soll
if ($_GET["job"] == "change")
{
    $photo_event->getPhotoEvent($pho_id);
    
    // Pruefung, ob die Fotoveranstaltung zur aktuellen Organisation gehoert
    if($photo_event->getValue("pho_org_shortname") != $g_organization)
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['photo_event_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['photo_event_request'] as $key => $value)
    {
        if(strpos($key, "pho_") == 0)
        {
            $photo_event->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['photo_event_request']);
}
else
{
    // Datum formatieren
    $photo_event->setValue("pho_begin", mysqldate('d.m.y', $photo_event->getValue("pho_begin")));
    $photo_event->setValue("pho_end", mysqldate('d.m.y', $photo_event->getValue("pho_end")));
}

// einlesen der Veranstaltungsliste
$pho_id_condition = "";
if($photo_event->getValue("pho_id") > 0)
{
    $pho_id_condition = " AND pho_id <> ". $photo_event->getValue("pho_id");
}

$sql="  SELECT *
        FROM ". TBL_PHOTOS. "
        WHERE pho_org_shortname ='$g_organization'
        AND   pho_pho_id_parent IS NULL
        $pho_id_condition
        ORDER BY pho_begin DESC ";
error_log($sql);
$result_list = mysql_query($sql, $g_adm_con);
db_error($result_list,__FILE__,__LINE__);

//Parent
//Suchen nach Kindern, Funktion mit selbstaufruf
function subfolder($parent_id, $vorschub, $photo_event, $pho_id)
{
    global $g_adm_con;
    $vorschub = $vorschub."&nbsp;&nbsp;&nbsp;&nbsp;";

    //Erfassen der auszugebenden Veranstaltung
    $pho_id_condition = "";
    if($photo_event->getValue("pho_id") > 0)
    {
        $pho_id_condition = " AND pho_id <> ". $photo_event->getValue("pho_id");
    }    
    
    $sql = "SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_pho_id_parent = $parent_id 
            $pho_id_condition ";
    error_log($sql);
    $result_child = mysql_query($sql, $g_adm_con);
    db_error($result_child,__FILE__,__LINE__);

    while($adm_photo_child = mysql_fetch_array($result_child))
    {
        //Wenn die Elternveranstaltung von pho_id dann selected
        $selected = 0;
        if(($adm_photo_child["pho_id"] == $photo_event->getValue("pho_pho_id_parent"))
        ||  $adm_photo_child["pho_id"] == $pho_id)
        {
            $selected = " selected ";
        }

        echo"<option value=\"".$adm_photo_child["pho_id"]."\" $selected>".$vorschub."&#151;".$adm_photo_child["pho_name"]
        ."&nbsp(".mysqldate("y", $adm_photo_child["pho_begin"]).")</option>";

        subfolder($adm_photo_child["pho_id"], $vorschub, $photo_event, $pho_id);
    }//while
}//function

/******************************HTML-Kopf******************************************/

$g_layout['title'] = "Veranstaltungsverwaltung";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");
echo"<h1 class=\"moduleHeadline\">Fotogalerien - Veranstaltungsverwaltung</h1>";


/****************************Formular***********************************************/
//Kopfzeile
echo"
<div class=\"formHead\">";
    //bei neuer Veranstaltung
    if($_GET["job"]=="new")
    {
        echo "Neue Veranstaltung anlegen";
    }
    //bei bestehender Veranstaltung
    if($_GET["job"]=="change")
    {
            echo "Veranstaltung bearbeiten";
    }
echo"</div>";

//Body
echo"
<div class=\"formBody\">
    <form method=\"POST\" action=\"$g_root_path/adm_program/modules/photos/photo_event_function.php?pho_id=". $_GET["pho_id"];
        if($_GET["job"]=="new")
        {
            echo "&job=makenew\">";
        }
        if($_GET["job"]=="change")
        {
            echo "&job=makechange\">";
        }

        //Veranstaltung
        echo"
        <ul>
			<li><dl>
				<dt>Veranstaltung:</dt>
	            <dd>
	                <input type=\"text\" id=\"pho_name\" name=\"pho_name\" style=\"width: 300px;\" maxlength=\"50\" tabindex=\"1\" value=\"".$photo_event->getValue("pho_name")."\">
	                <span title=\"Pflichtfeld\" class=\"mandatoryFieldMarker\">*</span>
				</dd>
			</dl></li>";
				
	        	//Unterordnung
	        	echo"
			<li><dl>
	           <dt>in Ordner:</dt>
	           <dd>
	                <select size=\"1\" name=\"pho_pho_id_parent\" tabindex=\"2\">
	                    <option value=\"0\">Fotogalerien(Hauptordner)</option>";
	
	                   while($adm_photo_list = mysql_fetch_array($result_list))
	                    {
	                        //Wenn die Elternveranstaltung von pho_id dann selected
	                        $selected = 0;
	                        if(($adm_photo_list["pho_id"] == $photo_event->getValue("pho_pho_id_parent"))
	                        ||  $adm_photo_list["pho_id"] == $pho_id)
	                        {
	                            $selected = " selected ";
	                        }
	                        
	                        echo"<option value=\"".$adm_photo_list["pho_id"]."\" $selected style=\"maxlength: 40px;\">".$adm_photo_list["pho_name"]
	                        ."&nbsp;(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";
	                        
	                        //Auftruf der Funktion
	                        subfolder($adm_photo_list["pho_id"], "", $photo_event, $pho_id);
	                    }//while
	                    echo"
	                </select>
	            </dd>
			</dl></li>";
	
		        //Beginn
	    	    echo"
			<li><dl>
	            <dt>Beginn:</dt>
	            <dd>
	                <input type=\"text\" name=\"pho_begin\" size=\"10\" tabindex=\"3\" maxlength=\"10\" value=\"". $photo_event->getValue("pho_begin")."\">
	                <span title=\"Pflichtfeld\" class=\"mandatoryFieldMarker\">*</span>
	            </dd>
			</dl></li>";
	
		        //Ende
		        echo"
			<li><dl>
		        <dt>Ende:</dt>
	            <dd>
	                <input type=\"text\" name=\"pho_end\" size=\"10\" tabindex=\"4\" maxlength=\"10\" value=\"". $photo_event->getValue("pho_end")."\">
	            </dd>
			</dl></li>";
	
		        //Photographen
		        echo"
			<li><dl>
	            <dt>Fotografen:</dt>
	            <dd>
	                <input type=\"text\" name=\"pho_photographers\" style=\"width: 300px;\" tabindex=\"5\" maxlength=\"100\" value=\"".$photo_event->getValue("pho_photographers")."\">
	            </dd>
			</dl></li>";
	
		        //Freigabe
		        echo"
			<li><dl>
	            <dt>Sperren:</dt>
	            <dd>";
	                echo "<input type=\"checkbox\" name=\"pho_locked\" id=\"locked\" tabindex=\"6\" value=\"1\"";
	
	                if($photo_event->getValue("pho_locked") == 1)
	                {
	                    echo "checked = \"checked\" ";
	                }
	
	             echo"</dd>
			</dl></li>
		</ul>";

        //Submitbutton
        echo"<hr />
        <p>
            <button name=\"submit\" type=\"submit\" tabindex=\"8\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
                &nbsp;Speichern
            </button>
        </p>
    </form>
</div>


<ul class=\"iconTextLink\">
	<li>
		<a href=\"$g_root_path/adm_program/system/back.php\"><img class=\"iconLink\" src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
    	<a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
	</li>
	
	<li>
		<img src=\"$g_root_path/adm_program/images/help.png\" class=\"iconLink\" alt=\"Hilfe\" title=\"Hilfe\"
       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\">	
		<a onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=veranst_help','Message','width=500,height=400,left=310,top=200,scrollbars=yes'\")\">Hilfe</a>
	</li>
</div>


<script type=\"text/javascript\">
    <!--
        document.getElementById('veranstaltung').focus();
    -->
</script>";

/***********************************Ende********************************************/
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>