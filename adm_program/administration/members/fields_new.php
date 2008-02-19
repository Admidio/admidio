<?php
/******************************************************************************
 * Profilfelder anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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

$_SESSION['navigation']->addUrl(CURRENT_URL);

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
    $html_disabled = " disabled=\"disabled\" ";
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
require(THEME_SERVER_PATH. "/overall_header.php");

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
                        value=\"". $user_field->getValue("usf_name"). "\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"usf_description\">Beschreibung:</label></dt>
                    <dd><textarea name=\"usf_description\" id=\"usf_description\" style=\"width: 330px;\" rows=\"2\" cols=\"40\">".
                        $user_field->getValue("usf_description"). "</textarea>
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
                                        echo " selected=\"selected\" ";
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
                            <img src=\"". THEME_PATH. "/icons/eye.png\" alt=\"Feld f&uuml;r alle Benutzer sichtbar\" />
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_hidden\" id=\"usf_hidden\" ";
                        if($user_field->getValue("usf_hidden") == 0)
                        {
                            echo " checked=\"checked\" ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_hidden\">Feld f&uuml;r alle Benutzer sichtbar</label>
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=field_hidden',this);\" onmouseout=\"ajax_hideTooltip()\"/>
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for=\"usf_disabled\">
                            <img src=\"". THEME_PATH. "/icons/textfield_key.png\" alt=\"Feld nur f&uuml;r berechtigte Benutzer editierbar\" />
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_disabled\" id=\"usf_disabled\" ";
                        if($user_field->getValue("usf_disabled") == 1)
                        {
                            echo " checked=\"checked\" ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_disabled\">Feld nur f&uuml;r berechtigte Benutzer editierbar</label>
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=field_disabled',this);\" onmouseout=\"ajax_hideTooltip()\" />
                    </dd>
                </dl>
            </li>            
            <li>
                <dl>
                    <dt>
                        <label for=\"usf_mandatory\">
                            <img src=\"". THEME_PATH. "/icons/asterisk_yellow.png\" alt=\"Pflichtfeld, muss vom Benutzer gef&uuml;llt werden\" />
                        </label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" name=\"usf_mandatory\" id=\"usf_mandatory\" ";
                        if($user_field->getValue("usf_mandatory") == 1)
                        {
                            echo " checked=\"checked\" ";
                        }
                        if($user_field->getValue("usf_name") == "Nachname"
                        || $user_field->getValue("usf_name") == "Vorname")
                        {
                            echo " disabled=\"disabled\" ";
                        }
                        echo " value=\"1\" />
                        <label for=\"usf_mandatory\">Pflichtfeld, muss vom Benutzer gef&uuml;llt werden</label>
                        <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=field_mandatory',this);\" onmouseout=\"ajax_hideTooltip()\" />
                    </dd>
                </dl>
            </li>            
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />
            &nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"". THEME_PATH. "/icons/back.png\" alt=\"Zurück\" /></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zurück</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('usf_name').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>