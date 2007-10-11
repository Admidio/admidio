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
 * job:    - new (neues Formular)
 *         - change (Formular fuer Aenderunmgen)
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
//Albumsuebergabe Numerisch und != Null?
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
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Fotoeventobjekt anlegen
$photo_event = new PhotoEvent($g_db);

// nur Daten holen, wenn Album editiert werden soll
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

// einlesen der Albumliste
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
$result_list = $g_db->query($sql);

//Parent
//Suchen nach Kindern, Funktion mit selbstaufruf
function subfolder($parent_id, $vorschub, $photo_event, $pho_id)
{
    global $g_db;
    $vorschub = $vorschub."&nbsp;&nbsp;&nbsp;&nbsp;";

    //Erfassen des auszugebenden Albums
    $pho_id_condition = "";
    if($photo_event->getValue("pho_id") > 0)
    {
        $pho_id_condition = " AND pho_id <> ". $photo_event->getValue("pho_id");
    }    
    
    $sql = "SELECT *
            FROM ". TBL_PHOTOS. "
            WHERE pho_pho_id_parent = $parent_id 
            $pho_id_condition ";
    $result_child = $g_db->query($sql);

    while($adm_photo_child = $g_db->fetch_array($result_child))
    {
        //Wenn die Elternveranstaltung von pho_id dann selected
        $selected = 0;
        if(($adm_photo_child["pho_id"] == $photo_event->getValue("pho_pho_id_parent"))
        ||  $adm_photo_child["pho_id"] == $pho_id)
        {
            $selected = " selected=\"selected\" ";
        }

        echo"<option value=\"".$adm_photo_child["pho_id"]."\" $selected>".$vorschub."&#151;".$adm_photo_child["pho_name"]
        ."&nbsp(".mysqldate("y", $adm_photo_child["pho_begin"]).")</option>";

        subfolder($adm_photo_child["pho_id"], $vorschub, $photo_event, $pho_id);
    }//while
}//function

/******************************HTML-Kopf******************************************/

$g_layout['title'] = "Foto-Album-Verwaltung";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");
echo"<h1 class=\"moduleHeadline\">Foto-Album-Verwaltung</h1>";


/****************************Formular***********************************************/

echo "
<form method=\"post\" action=\"$g_root_path/adm_program/modules/photos/photo_event_function.php?pho_id=". $_GET["pho_id"]. "&amp;job=". $_GET["job"]. "\">
<div class=\"formLayout\" id=\"photo_event_new_form\">
    <div class=\"formHead\">";
        //bei neuem Album
        if($_GET["job"]=="new")
        {
            echo "Neues Album anlegen";
        }
        //bei bestehendem Album
        if($_GET["job"]=="change")
        {
                echo "Album bearbeiten";
        }
    echo"</div>
    <div class=\"formBody\">";
        //Album
        echo"
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"pho_name\">Album:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"pho_name\" name=\"pho_name\" style=\"width: 300px;\" maxlength=\"50\" tabindex=\"1\" value=\"".$photo_event->getValue("pho_name")."\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>";
                
            //Unterordnung
            echo"
            <li>
                <dl>
                    <dt><label for=\"pho_pho_id_parent\">in Ordner:</label></dt>
                    <dd>
                        <select size=\"1\" id=\"pho_pho_id_parent\" name=\"pho_pho_id_parent\" style=\"max-width: 95%;\" tabindex=\"2\">
                            <option value=\"0\">Fotogalerien(Hauptordner)</option>";

                           while($adm_photo_list = $g_db->fetch_array($result_list))
                            {
                                //Wenn die Elternveranstaltung von pho_id dann selected
                                $selected = 0;
                                if(($adm_photo_list["pho_id"] == $photo_event->getValue("pho_pho_id_parent"))
                                ||  $adm_photo_list["pho_id"] == $pho_id)
                                {
                                    $selected = " selected=\"selected\" ";
                                }

                                echo"<option value=\"".$adm_photo_list["pho_id"]."\" $selected style=\"maxlength: 40px;\">".$adm_photo_list["pho_name"]
                                ."&nbsp;(".mysqldate("y", $adm_photo_list["pho_begin"]).")</option>";

                                //Auftruf der Funktion
                                subfolder($adm_photo_list["pho_id"], "", $photo_event, $pho_id);
                            }//while
                            echo"
                        </select>
                    </dd>
                </dl>
            </li>";
    
            //Beginn
            echo"
            <li>
                <dl>
                    <dt><label for=\"pho_begin\">Beginn:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"pho_begin\" name=\"pho_begin\" size=\"10\" tabindex=\"3\" maxlength=\"10\" value=\"". $photo_event->getValue("pho_begin")."\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>";
    
            //Ende
            echo"
            <li>
                <dl>
                    <dt><label for=\"pho_end\">Ende:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"pho_end\" name=\"pho_end\" size=\"10\" tabindex=\"4\" maxlength=\"10\" value=\"". $photo_event->getValue("pho_end")."\" />
                    </dd>
                </dl>
            </li>";
    
            //Photographen
            echo"
            <li>
                <dl>
                    <dt><label for=\"pho_photographers\">Fotografen:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"pho_photographers\" name=\"pho_photographers\" style=\"width: 300px;\" tabindex=\"5\" maxlength=\"100\" value=\"".$photo_event->getValue("pho_photographers")."\" />
                    </dd>
                </dl>
            </li>";
    
            //Freigabe
            echo"
            <li>
                <dl>
                    <dt><label for=\"pho_locked\">Sperren:</label></dt>
                    <dd>";
                        echo "<input type=\"checkbox\" id=\"pho_locked\" name=\"pho_locked\" tabindex=\"6\" value=\"1\"";

                        if($photo_event->getValue("pho_locked") == 1)
                        {
                            echo "checked = \"checked\" ";
                        }

                     echo" /></dd>
                </dl>
            </li>
        </ul>";

        //Submitbutton
        echo"<hr />
        <div class=\"formSubmit\">
            <button name=\"submit\" type=\"submit\" tabindex=\"8\" value=\"speichern\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\" />
                &nbsp;Speichern
            </button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
    
    <li>
        <span class=\"iconTextLink\">
            <img src=\"$g_root_path/adm_program/images/help.png\" class=\"iconLink\" alt=\"Hilfe\" title=\"Hilfe\"
            onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=photo_up_help','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" />   
            <a onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=veranst_help','Message','width=500,height=400,left=310,top=200,scrollbars=yes')\">Hilfe</a>
        </span>
    </li>
</ul>


<script type=\"text/javascript\">
    <!--
        document.getElementById('pho_name').focus();
    -->
</script>";

/***********************************Ende********************************************/
require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>