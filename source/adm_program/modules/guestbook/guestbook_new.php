<?php
/******************************************************************************
 * Gaestebucheintraege anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id            - ID des Eintrages, der bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/guestbook_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}
elseif($g_preferences['enable_guestbook_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require("../../system/login_valid.php");
}


// Uebergabevariablen pruefen

if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $_GET["id"] = 0;
}

if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "Gästebuch";
}


// Falls ein Eintrag bearbeitet werden soll muss geprueft weden ob die Rechte gesetzt sind...
if ($_GET["id"] != 0)
{
    require("../../system/login_valid.php");

    if (!$g_current_user->editGuestbookRight())
    {
        $g_message->show("norights");
    }
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Gaestebuchobjekt anlegen
$guestbook = new Guestbook($g_db);

if($_GET["id"] > 0)
{
    $guestbook->getGuestbookEntry($_GET["id"]);
    
    // Pruefung, ob der Eintrag zur aktuellen Organisation gehoert
    if($guestbook->getValue("gbo_org_id") != $g_current_organization->getValue("org_id"))
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['guestbook_entry_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['guestbook_entry_request'] as $key => $value)
    {
        if(strpos($key, "gbo_") == 0)
        {
            $guestbook->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['guestbook_entry_request']);
}

// Wenn keine ID uebergeben wurde, der User aber eingeloggt ist koennen zumindest
// Name, Emailadresse und Homepage vorbelegt werden...
if ($_GET['id'] == 0 && $g_valid_login)
{
    $guestbook->setValue("gbo_name", $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname"));
    $guestbook->setValue("gbo_email", $g_current_user->getValue("E-Mail"));
    $guestbook->setValue("gbo_homepage", $g_current_user->getValue("Homepage"));
}

if (!$g_valid_login && $g_preferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = "SELECT count(*) FROM ". TBL_GUESTBOOK. "
             WHERE unix_timestamp(gbo_timestamp) > unix_timestamp()-". $g_preferences['flooding_protection_time']. "
               AND gbo_org_id = ". $g_current_organization->getValue("org_id"). "
               AND gbo_ip_address = '". $guestbook->getValue("gbo_ip_address"). "'";
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $g_message->show("flooding_protection", $g_preferences['flooding_protection_time']);
    }
}

// Html-Kopf ausgeben
if ($_GET['id'] > 0)
{
    $g_layout['title'] = "Gästebucheintrag ändern";
}
else
{
    $g_layout['title'] = "Gästebucheintrag anlegen";
}
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
if ($_GET['id'] > 0)
{
    $mode = "3";
}
else
{
    $mode = "1";
}

echo "
<form action=\"$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=". $_GET["id"]. "&amp;headline=". $_GET['headline']. "&amp;mode=$mode\" method=\"post\">
<div class=\"formLayout\" id=\"edit_guestbook_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"gbo_name\">Name:</label></dt>
                    <dd>";
                        if ($g_current_user->getValue("usr_id") > 0)
                        {
                            // Eingeloggte User sollen ihren Namen nicht aendern duerfen
                            echo "<input type=\"text\" id=\"gbo_name\" name=\"gbo_name\" class=\"readonly\" readonly=\"readonly\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". $guestbook->getValue("gbo_name"). "\" />";
                        }
                        else
                        {
                            echo "<input type=\"text\" id=\"gbo_name\" name=\"gbo_name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". $guestbook->getValue("gbo_name"). "\" />
                            <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>";
                        }
                    echo "</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"gbo_email\">Emailadresse:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"gbo_email\" name=\"gbo_email\" tabindex=\"2\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $guestbook->getValue("gbo_email"). "\" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"gbo_homepage\">Homepage:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"gbo_homepage\" name=\"gbo_homepage\" tabindex=\"3\" style=\"width: 350px;\" maxlength=\"50\" value=\"". $guestbook->getValue("gbo_homepage"). "\" />
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"gbo_text\">Text:</label>";
                        if ($g_preferences['enable_bbcode'] == 1)
                        {
                          echo "<br /><br />
                          <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"4\">Text formatieren</a>";
                        }
                    echo "</dt>
                    <dd>
                        <textarea id=\"gbo_text\" name=\"gbo_text\" tabindex=\"4\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". $guestbook->getValue("gbo_text"). "</textarea>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>";

            // Nicht eingeloggte User bekommen jetzt noch das Captcha praesentiert,
            // falls es in den Orgaeinstellungen aktiviert wurde...
            if (!$g_valid_login && $g_preferences['enable_guestbook_captcha'] == 1)
            {
                echo "
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <img src=\"$g_root_path/adm_program/system/captcha_class.php?id=". time(). "\" alt=\"Captcha\" />
                        </dd>
                    </dl>

                    <dl>
                       <dt><label for=\"captcha\">Bestätigungscode:</label></dt>
                       <dd>
                           <input type=\"text\" id=\"captcha\" name=\"captcha\" tabindex=\"5\" style=\"width: 200px;\" maxlength=\"8\" value=\"\" />
                           <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                           <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                                onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\" />
                       </dd>
                    </dl>
                </li>";
            }
        echo "</ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"6\">
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
</ul>";

if ($g_current_user->getValue("usr_id") == 0)
{
    $focusField = "name";
}
else
{
    $focusField = "text";
}

echo"
<script type=\"text/javascript\"><!--
    document.getElementById('$focusField').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>