<?php
/******************************************************************************
 * Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : http://www.gnu.org/licenses/gpl-2.0.html GNU Public License 2
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien, die bearbeitet werden soll
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 *
 ****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/category_class.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_cat_id = 0;

// Uebergabevariablen pruefen

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK" && $_GET['type'] != "USF")
    {
        $g_message->show("invalid");
    }
    if($_GET['type'] == "ROL" && $g_current_user->assignRoles() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "LNK" && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "USF" && $g_current_user->editUser() == false)
    {
        $g_message->show("norights");
    }
}
else
{
    $g_message->show("invalid");
}

if(isset($_GET['cat_id']))
{
    if(is_numeric($_GET['cat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_cat_id = $_GET['cat_id'];
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// UserField-objekt anlegen
$category = new Category($g_db);

if($req_cat_id > 0)
{
    $category->getCategory($req_cat_id);
    
    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue("cat_org_id") >  0
    && $category->getValue("cat_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['categories_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['categories_request'] as $key => $value)
    {
        if(strpos($key, "cat_") == 0)
        {
            $category->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['categories_request']);
}

// Html-Kopf ausgeben
$g_layout['title'] = "Kategorie";
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
echo "
<form action=\"$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$req_cat_id&amp;type=". $_GET["type"]. "&amp;mode=1\" method=\"post\">
<div class=\"formLayout\" id=\"edit_categories_form\">
    <div class=\"formHead\">";
        if($req_cat_id > 0)
        {
            echo "Kategorie &auml;ndern";
        }
        else
        {
            echo "Kategorie anlegen";
        }
    echo "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"cat_name\">Name:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"cat_name\" name=\"cat_name\" size=\"30\" maxlength=\"30\" value=\"". htmlspecialchars($category->getValue("cat_name"), ENT_QUOTES). "\">
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt>
                        <label for=\"cat_hidden\"><img src=\"$g_root_path/adm_program/images/user_key.png\" alt=\"Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar\"></label>
                    </dt>
                    <dd>
                        <input type=\"checkbox\" id=\"cat_hidden\" name=\"cat_hidden\" ";
                            if($category->getValue("cat_hidden") == 1)
                            {
                                echo " checked ";
                            }
                            echo " value=\"1\" />
                        <label for=\"cat_hidden\">Kategorie nur f&uuml;r eingeloggte Benutzer sichtbar</label>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class=\"formSubmit\">
            <button id=\"speichern\" type=\"submit\" value=\"speichern\">
            <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
            &nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class=\"iconTextLinkList\">
    <li>
        <span class=\"iconTextLink\">
            <a href=\"$g_root_path/adm_program/system/back.php\"><img 
            src=\"$g_root_path/adm_program/images/back.png\" alt=\"Zur&uuml;ck\"></a>
            <a href=\"$g_root_path/adm_program/system/back.php\">Zur&uuml;ck</a>
        </span>
    </li>
</ul>

<script type=\"text/javascript\"><!--
    document.getElementById('cat_name').focus();
--></script>";

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>