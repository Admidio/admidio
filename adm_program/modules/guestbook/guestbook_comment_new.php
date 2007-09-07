<?php
/******************************************************************************
 * Gaestebuchkommentare anlegen
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Elmar Meuthen
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * id            - ID des Eintrages, dem ein Kommentar hinzugefuegt werden soll
 * cid           - ID des Kommentars der editiert werden soll
 * headline      - Ueberschrift, die ueber den Einraegen steht
 *                 (Default) Gaestebuch
 *
 *****************************************************************************/

require("../../system/common.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_guestbook_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

// Es muss ein (nicht zwei) Parameter uebergeben werden: Entweder id oder cid...
if (isset($_GET['id']) && isset($_GET['cid']))
{
    $g_message->show("invalid");
}

//Erst einmal die Rechte abklopfen...
if(($g_preferences['enable_guestbook_module'] == 2 || $g_preferences['enable_gbook_comments4all'] == 0)
&& isset($_GET['id']))
{
    // Falls anonymes kommentieren nicht erlaubt ist, muss der User eingeloggt sein zum kommentieren
    require("../../system/login_valid.php");

    if (!$g_current_user->commentGuestbookRight())
    {
        // der User hat kein Recht zu kommentieren
        $g_message->show("norights");
    }
}

if (isset($_GET['cid']))
{
    // Zum editieren von Kommentaren muss der User auch eingeloggt sein
    require("../../system/login_valid.php");

    if (!$g_current_user->editGuestbookRight())
    {
        // der User hat kein Recht Kommentare zu editieren
        $g_message->show("norights");
    }

}


// Uebergabevariablen pruefen
if (array_key_exists("id", $_GET))
{
    if (is_numeric($_GET["id"]) == false)
    {
        $g_message->show("invalid");
    }
}
elseif (array_key_exists("cid", $_GET))
{
    if (is_numeric($_GET["cid"]) == false)
    {
        $g_message->show("invalid");
    }
}
else
{
    $g_message->show("invalid");
}


if (array_key_exists("headline", $_GET))
{
    $_GET["headline"] = strStripTags($_GET["headline"]);
}
else
{
    $_GET["headline"] = "G&auml;stebuch";
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

if (isset($_SESSION['guestbook_comment_request']))
{
    $form_values = strStripSlashesDeep($_SESSION['guestbook_comment_request']);
    unset($_SESSION['guestbook_comment_request']);
}
else
{
    $form_values['name']  = "";
    $form_values['email'] = "";
    $form_values['entry']  = "";

    // Wenn eine cid uebergeben wurde, soll der Eintrag geaendert werden
    // -> Felder mit Daten des Kommentares vorbelegen

    if (isset($_GET['cid']))
    {
        $sql    = "SELECT * FROM ". TBL_GUESTBOOK. ",".TBL_GUESTBOOK_COMMENTS.  "
                    WHERE gbc_id = ". $_GET['cid']. " and gbo_org_id = ". $g_current_organization->getValue("org_id"). "
                      AND gbo_id = gbc_gbo_id";
        $result = $g_db->query($sql);

        if ($g_db->num_rows($result) > 0)
        {
            $row_ba = $g_db->fetch_object($result);

            $form_values['name']     = $row_ba->gbc_name;
            $form_values['entry']    = $row_ba->gbc_text;
            $form_values['email']    = $row_ba->gbc_email;
        }
        elseif ($g_db->num_rows($result) == 0)
        {
            //Wenn keine Daten zu der CID gefunden worden bzw. die CID einer anderen Orga gehört ist Schluss mit lustig...
            $g_message->show("invalid");
        }

    }

    // Wenn der User eingeloggt ist und keine cid uebergeben wurde
    // koennen zumindest Name und Emailadresse vorbelegt werden...
    if (!isset($_GET['cid']) && $g_valid_login)
    {
        $form_values['name']     = $g_current_user->getValue("Vorname"). " ". $g_current_user->getValue("Nachname");
        $form_values['email']    = $g_current_user->getValue("E-Mail");
    }
}


if (!$g_valid_login && $g_preferences['flooding_protection_time'] != 0)
{
    // Falls er nicht eingeloggt ist, wird vor dem Ausfuellen des Formulars noch geprueft ob der
    // User innerhalb einer festgelegten Zeitspanne unter seiner IP-Adresse schon einmal
    // einen GB-Eintrag erzeugt hat...
    $ipAddress = $_SERVER['REMOTE_ADDR'];

    $sql = "SELECT count(*) FROM ". TBL_GUESTBOOK_COMMENTS. "
            where unix_timestamp(gbc_timestamp) > unix_timestamp()-". $g_preferences['flooding_protection_time']. "
              and gbc_ip_address = '$ipAddress' ";
    $result = $g_db->query($sql);
    $row = $g_db->fetch_array($result);
    if($row[0] > 0)
    {
          //Wenn dies der Fall ist, gibt es natuerlich keinen Gaestebucheintrag...
          $g_message->show("flooding_protection", $g_preferences['flooding_protection_time']);
    }
}

// Html-Kopf ausgeben
$g_layout['title'] = $_GET["headline"];
require(SERVER_PATH. "/adm_program/layout/overall_header.php");

// Html des Modules ausgeben
if (isset($_GET['id']))
{
    $id   = $_GET['id'];
    $mode = "4";
    $headline = "Kommentar anlegen";
}
else
{
    $id   = $_GET['cid'];
    $mode = "8";
    $headline = "Kommentar editieren";
}

echo "
<form action=\"$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=$id&amp;headline=". $_GET['headline']. "&amp;mode=$mode\" method=\"post\">
<div class=\"formLayout\" id=\"edit_guestbook_comment_form\">
    <div class=\"formHead\">$headline</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"name\">Name:</label></dt>
                    <dd>";
                        if ($g_current_user->getValue("usr_id") > 0)
                        {
                            // Eingeloggte User sollen ihren Namen nicht aendern duerfen
                            echo "<input class=\"readonly\" readonly type=\"text\" id=\"name\" name=\"name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">";
                        }
                        else
                        {
                            echo "<input type=\"text\" id=\"name\" name=\"name\" tabindex=\"1\" style=\"width: 350px;\" maxlength=\"60\" value=\"". htmlspecialchars($form_values['name'], ENT_QUOTES). "\">
                            <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>";
                        }
                    echo "</dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"email\">Emailadresse:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"email\" name=\"email\" tabindex=\"2\" style=\"width: 350px;\" maxlength=\"50\" value=\"". htmlspecialchars($form_values['email'], ENT_QUOTES). "\">
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for=\"entry\">Kommentar:</label>";
                        if ($g_preferences['enable_bbcode'] == 1)
                        {
                          echo "<br><br>
                          <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode','Message','width=600,height=600,left=310,top=200,scrollbars=yes')\" tabindex=\"6\">Text formatieren</a>";
                        }
                    echo "</dt>
                    <dd>
                        <textarea  id=\"entry\" name=\"entry\" tabindex=\"3\" style=\"width: 350px;\" rows=\"10\" cols=\"40\">". htmlspecialchars($form_values['entry'], ENT_QUOTES). "</textarea>&nbsp;<span title=\"Pflichtfeld\" style=\"color: #990000;\">*</span>
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
                </li>
                <li>
                    <dl>
                           <dt><label for=\"captcha\">Best&auml;tigungscode:</label></dt>
                           <dd>
                               <input type=\"text\" id=\"captcha\" name=\"captcha\" tabindex=\"4\" style=\"width: 200px;\" maxlength=\"8\" value=\"\">
                               <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                               <img class=\"iconHelpLink\" src=\"$g_root_path/adm_program/images/help.png\" alt=\"Hilfe\" title=\"Hilfe\"
                                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help','Message','width=400,height=320,left=310,top=200,scrollbars=yes')\">
                           </dd>
                    </dl>
                </li>";
            }
        echo "</ul>
        
        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"5\">
                <img src=\"$g_root_path/adm_program/images/disk.png\" alt=\"Speichern\">
                &nbsp;Speichern</button>
        </div>";

    echo "</div>
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

require(SERVER_PATH. "/adm_program/layout/overall_footer.php");

?>