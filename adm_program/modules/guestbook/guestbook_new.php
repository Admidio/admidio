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

echo "
<script type=\"text/javascript\"><!--
function emoticon(text) 
{
	var txtarea = document.getElementById('gbo_text');

	if (txtarea.createTextRange && txtarea.caretPos) {
		txtarea.caretPos.text = text;
	} else {
		txtarea.value  += text;
	}
	txtarea.focus();
}

var vorbelegt = Array(false,false,false,false,false,false,false,false,false,false);
var bbcodes = Array(\"[b]\",\"[/b]\",\"[u]\",\"[/u]\",\"[i]\",\"[/i]\",\"[big]\",\"[/big]\",\"[small]\",\"[/small]\",\"[center]\",\"[/center]\",\"[url=".$g_root_path."]\",\"[/url]\",\"[email=adresse@demo.de]\",\"[/email]\",\"[img]\",\"[/img]\");
var bbcodestext = Array(\"text_bold_point.png\",\"text_bold.png\",
                        \"text_underline_point.png\",\"text_underline.png\",
                        \"text_italic_point.png\",\"text_italic.png\",
                        \"text_bigger_point.png\",\"text_bigger.png\",
                        \"text_smaller_point.png\",\"text_smaller.png\",
                        \"text_align_center_point.png\",\"text_align_center.png\",
                        \"link_point.png\",\"link.png\",
                        \"email_point.png\",\"email.png\",
                        \"image_point.png\",\"image.png\");


function bbcode(nummer)
{
   var arrayid;
   if (vorbelegt[nummer]) {
      arrayid = nummer*2+1;
   } else {
      arrayid = nummer*2;
   };
   emoticon(bbcodes[arrayid]);
   document.getElementById(bbcodes[nummer*2]).src = '". THEME_PATH. "/icons/'+bbcodestext[arrayid];
   vorbelegt[nummer] = !vorbelegt[nummer];
}

//Funktion schließt alle offnen Tags
function bbcodeclose() 
{
   for (var i=0;i<9;i++) {
      if (vorbelegt[i]) {
         bbcode(i);
      }
   }
}
--></script>
";

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
<form method=\"post\" action=\"$g_root_path/adm_program/modules/guestbook/guestbook_function.php?id=". $_GET["id"]. "&amp;headline=". $_GET['headline']. "&amp;mode=$mode\" >
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
            </li>";
         //Die Hilfe beim Nutzen von BBCode
         if ($g_preferences['enable_bbcode'] == 1)
         {
            echo "
            <li>
                <dl>
                    <dt>&nbsp;</dt>
                    <dd>
                        <div style=\"width: 350px;\">
                            <div style=\"float: left;\">
                                <a class=\"iconLink\" href=\"javascript:bbcode(0)\"><img id=\"[b]\" 
									src=\"". THEME_PATH. "/icons/text_bold.png\" title=\"Fett schreiben\" alt=\"Fett schreiben\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(1)\"><img id=\"[u]\" 
									src=\"". THEME_PATH. "/icons/text_underline.png\" title=\"Text unterstreichen\" alt=\"Text unterstreichen\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(2)\"><img id=\"[i]\" 
									src=\"". THEME_PATH. "/icons/text_italic.png\" title=\"Kursiv schreiben\" alt=\"Kursiv schreiben\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(3)\"><img id=\"[big]\" 
                                    src=\"". THEME_PATH. "/icons/text_bigger.png\" title=\"Größer schreiben\" alt=\"Größer schreiben\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(4)\"><img id=\"[small]\" 
                                    src=\"". THEME_PATH. "/icons/text_smaller.png\" title=\"Kleiner schreiben\" alt=\"Kleiner schreiben\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(5)\"><img id=\"[center]\" 
                                    src=\"". THEME_PATH. "/icons/text_align_center.png\" title=\"Text zentrieren\" alt=\"Text zentrieren\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(6)\"><img id=\"[url=".$g_root_path."]\" 
                                    src=\"". THEME_PATH. "/icons/link.png\" title=\"Link einfügen\" alt=\"Link einfügen\" /></a>
                                <a class=\"iconLink\" href=\"javascript:bbcode(7)\"><img id=\"[email=adresse@demo.de]\" 
                                    src=\"". THEME_PATH. "/icons/email.png\" title=\"E-Mail-Adresse einfügen\" alt=\"E-Mail-Adresse einfügen\" /></a>
                                <a class=\"iconLink\" href=\"javascript:emoticon('[img]".$g_root_path."[/img]')\"><img id=\"[img]\" 
                                    src=\"". THEME_PATH. "/icons/image.png\" title=\"Bild einfügen\" alt=\"Bild einfügen\" /></a>
                            </div>
                            <div style=\"float: right;\">
                                <a class=\"iconLink\" href=\"javascript:bbcodeclose()\"><img id=\"[all]\" 
                                    src=\"". THEME_PATH. "/icons/cross.png\" title=\"Alle Tags schließen\" alt=\"Alle Tags schließen\" /></a>
                                <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\" 
                                    onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode&amp;window=true','Message','width=600,height=500,left=310,top=200,scrollbars=yes')\" />
                            </div>
                        </div>
                    </dd>
                </dl>
            </li>";
         }
         echo "
            <li>
                <dl>
                    <dt><label for=\"gbo_text\">Text:</label>";
                        //Einfügen der Smilies
                        if ($g_preferences['enable_bbcode'] == 1)
                        {
                            echo "<br /><br />&nbsp;&nbsp;
                            <a class=\"iconLink\" href=\"javascript:emoticon(':)')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_smile.png\" alt=\"Smile\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(';)')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_wink.png\" alt=\"Wink\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(':D')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(':lol:')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_happy.png\" alt=\"Happy\" border=\"0\" /></a>
                            <br />&nbsp;&nbsp;
                            <a class=\"iconLink\" href=\"javascript:emoticon(':(')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_unhappy.png\" alt=\"Unhappy\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(':p')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(':o')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" border=\"0\" /></a>
                            <a class=\"iconLink\" href=\"javascript:emoticon(':twisted:')\"><img 
                                src=\"". THEME_PATH. "/icons/smilies/emoticon_evilgrin.png\" alt=\"Evilgrin\" border=\"0\" /></a>";
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
                           <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=captcha_help',this)\" onmouseout=\"ajax_hideTooltip()\" />
                       </dd>
                    </dl>
                </li>";
            }
        echo "</ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"6\"><img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />&nbsp;Speichern</button>
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
    $focusField = "gbo_name";
}
else
{
    $focusField = "gbo_text";
}

echo"
<script type=\"text/javascript\"><!--
    document.getElementById('$focusField').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>
