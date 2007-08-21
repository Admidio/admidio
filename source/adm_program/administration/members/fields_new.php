<?php
/******************************************************************************
 * Profilfelder anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * usf_id: ID des Feldes, das bearbeitet werden soll
 *
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/user_field_class.php");

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->editUser())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_usf_id = 0;

// Uebergabevariablen pruefen

if(isset($_GET['usf_id']))
{
    if(is_numeric($_GET['usf_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_usf_id = $_GET['usf_id'];
}

$_SESSION['navigation']->addUrl($g_current_url);

// benutzerdefiniertes Feldobjekt anlegen
$user_field = new UserField($g_db);

if($req_usf_id > 0)
{
    $user_field->getUserField($req_usf_id);
    
    // Pruefung, ob das Feld zur aktuellen Organisation gehoert
    if($user_field->getValue("cat_org_id") >  0
    && $user_field->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['fields_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['fields_request'] as $key => $value)
    {
        // hidden muss 0 sein, wenn ein Haeckchen gesetzt wird
        if($key == "usf_hidden")
        {
            if($value == 1)
            {
                $value = 0;
            }
            else
            {
                $value = 1;
            }
        }
        
        if(strpos($key, "usf_") == 0)
        {
            $user_field->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['fields_request']);
}

$html_disabled = "";
if($user_field->getValue("usf_system") == 1)
{
    $html_disabled = " disabled ";
}

// zusaetzliche Daten fuer den Html-Kopf setzen
if($req_usf_id > 0)
{
    $g_layout['title']  = "Profilfeld &auml;ndern";
}
else
{
    $g_layout['title']  = "Profilfeld anlegen";
}

// Html-Kopf ausgeben
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

echo "
<form action=\"$g_root_path/adm_program/administration/members/fields_function.php?usf_id=$req_usf_id&amp;mode=1\" method=\"post\" id=\"edit_field\">
<div class=\"formLayout\" id=\"edit_fields_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"usf_name\">Name:</label></dt>
                    <dd><input type=\"text\" name=\"usf_name\" id=\"usf_name\" $html_disabled style=\"width: 150px;\" maxlength=\"15\"
                        value=\"". htmlspecialchars($user_field->getValue("usf_name"), ENT_QUOTES). "\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"usf_description\">Beschreibung:</label></dt>
                    <dd><textarea name=\"usf_description\" id=\"usf_description\" style=\"width: 330px;\" rows=\"2\">".
                        htmlspecialchars($user_field->getValue("usf_description"), ENT_QUOTES). "</textarea>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"usf_cat_id\">Kategorie:</label></dt>
                    <dd>
                        <select size=\"1\" name=\"usf_cat_id\" id=\"usf_cat_id\" $html_disabled>
                            <option value=\" \""; 
                                if($user_field->getValue("usf_cat_id") == 0) 
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">- Bitte w&auml;hlen -</option>";

                            $sql = "SELECT * FROM ". TBL_CATEGORIES. "
                                     WHERE (  cat_org_id = ". $g_current_organization->getValue("org_id"). "
                                           OR cat_org_id IS NULL )
                                       AND cat_type   = 'USF'
                                     ORDER BY cat_sequence ASC ";
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_object($result))
                            {
                                echo "<option value=\"$row->cat_id\"";
                                    if($user_field->getValue("usf_cat_id") == $row->cat_id)
                                    {
                                        echo " selected ";
                                    }
                                echo ">$row->cat_name</option>";
                            }
                        echo "</select>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"usf_type\">Datentyp:</label></dt>
                    <dd>
                        <select size=\"1\" name=\"usf_type\" id=\"usf_type\" $html_disabled>
                            <option value=\" \""; 
                                if(strlen($user_field->getValue("usf_type")) == 0) 
                                {
                                    echo " selected=\"selected\"";
                                }
                                echo ">- Bitte w&auml;hlen -</option>\n
                            <option value=\"DATE\""; 
                                if($user_field->getValue("usf_type") == "DATE") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Datum</option>\n
                            <option value=\"EMAIL\""; 
                                if($user_field->getValue("usf_type") == "EMAIL") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">E-Mail</option>\n
                            <option value=\"CHECKBOX\""; 
                                if($user_field->getValue("usf_type") == "CHECKBOX") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Ja / Nein</option>\n
                            <option value=\"TEXT\"";     
                                if($user_field->getValue("usf_type") == "TEXT") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Text (50 Zeichen)</option>\n
                            <option value=\"TEXT_BIG\""; 
                                if($user_field->getValue("usf_type") == "TEXT_BIG") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Text (255 Zeichen)</option>\n
                            <option value=\"URL\""; 
                                if($user_field->getValue("usf_type") == "URL") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">URL</option>\n
                            <option value=\"NUMERIC\"";  
                                if($user_field->getValue("usf_type") == "NUMERIC") 
                                {
                                    echo " selected=\"selected\""; 
                                }
                                echo ">Zahl</option>\n
                        </select>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt>
                        <label for=\"usf_hidden\">
                            <img src=\"$g_root_path/adm_program/images/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\">
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_hidden\" id=\"usf_hidden\" ";
                        if($user_field->getValue("usf_hidden") == 0)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_hidden\">Feld f&uuml;r alle Benutzer sichtbar&nbsp;</label>
                        <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_hidden','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for=\"usf_disabled\">
                            <img src=\"$g_root_path/adm_program/images/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\">
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_disabled\" id=\"usf_disabled\" ";
                        if($user_field->getValue("usf_disabled") == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_disabled\">Feld nur f&uuml;r berechtigte Benutzer editierbar&nbsp;</label>
                        <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_disabled','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for=\"usf_mandatory\">
                            <img src=\"$g_root_path/adm_program/images/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\">
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_mandatory\" id=\"usf_mandatory\" ";
                        if($user_field->getValue("usf_mandatory") == 1)
                        {
                            echo " checked ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_mandatory\">Pflichtfeld, muss vom Benutzer gef&uuml;llt werden&nbsp;</label>
                        <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                        onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=field_mandatory','Message','width=400,height=200,left=310,top=200,scrollbars=yes')\">
                    </dd>
                </dl>
            </li>            
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
            &nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLink\">
    <li>
        <a href=\"$g_root_path/adm_program/system/back.php\"><img 
        src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
        <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('usf_name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>