<?php
/******************************************************************************
 * Ankuendigungen anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * ann_id    - ID der Ankuendigung, die bearbeitet werden soll
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/login_valid.php");
require("../../system/announcement_class.php");

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show("module_disabled");
}

if(!$g_current_user->editAnnouncements())
{
    $g_message->show("norights");
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_ann_id   = 0;
$req_headline = "Ankündigungen";

// Uebergabevariablen pruefen

if(isset($_GET['ann_id']))
{
    if(is_numeric($_GET['ann_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_ann_id = $_GET['ann_id'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET["headline"]);
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Ankuendigungsobjekt anlegen
$announcement = new Announcement($g_db);

if($req_ann_id > 0)
{
    $announcement->getAnnouncement($req_ann_id);
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($announcement->editRight() == false)
    {
        $g_message->show("norights");
    }
}

if(isset($_SESSION['announcements_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['announcements_request'] as $key => $value)
    {
        if(strpos($key, "ann_") == 0)
        {
            $announcement->setValue($key, stripslashes($value));
        }        
    }
    unset($_SESSION['announcements_request']);
}

// Html-Kopf ausgeben
if($req_ann_id > 0)
{
    $g_layout['title'] = $req_headline. " ändern";
}
else
{
    $g_layout['title'] = $req_headline. " anlegen";
}
$javascript = "";
$javascript = '<script language="javascript" type="text/javascript">

		var vorbelegt = Array(false,false,false,false,false,false,false,false,false,false);
		var bbcodes = Array("[b]","[\/b]","[u]","[\/u]","[i]","[\/i]","[big]","[\/big]","[small]","[\/small]","[center]","[\/center]","[url=http:\/\/www.Adresse.de]","[\/url]","[email=adresse@demo.de]","[\/email]","[img]","[\/img]");
		var bbcodestext = Array("<img src=\''. THEME_PATH. '/icons/text_bold_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_bold.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/text_underline_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_underline.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/text_italic_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_italic.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/text_bigger_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_bigger.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/text_smaller_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_smaller.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/text_align_center_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/text_align_center.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/link_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/link.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/email_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/email.png\' border=\'0\'/>",
		"<img src=\''. THEME_PATH. '/icons/image_point.png\' border=\'0\'/>","<img src=\''. THEME_PATH. '/icons/image.png\' border=\'0\'/>");
		function emoticon(text) 
		{
			var txtarea = document.post.ann_description;
			text = text + \' \';
			if (txtarea.createTextRange && txtarea.caretPos) 
			{
				var caretPos = txtarea.caretPos;
				caretPos.text = caretPos.text.charAt(caretPos.text.length - 1) == \'\' ? text + \'\' : text;
				txtarea.focus();
			} 
			else 
			{
				txtarea.value  += text;
				txtarea.focus();
			}
		}
		function bbcode(nummer) 
		{
			var arrayid;
			if (vorbelegt[nummer]) 
			{
				arrayid = nummer*2+1;
			} 
			else 
			{
				arrayid = nummer*2;
			};
			emoticon(bbcodes[arrayid]);
			document.getElementById(bbcodes[nummer*2]).innerHTML = bbcodestext[arrayid];
			vorbelegt[nummer] = !vorbelegt[nummer];
		}
		
		//Funktion schließt alle offnen Tags
		function bbcodeclose() 
		{
			for (var i=0;i<9;i++) 
			{
				if (vorbelegt[i]) 
				{
					bbcode(i);
				}
			}
		}</script>';
$g_layout['header'] = "";
$g_layout['header'] .= $javascript;
require(THEME_SERVER_PATH. "/overall_header.php");

// Html des Modules ausgeben
echo "
<form method=\"post\" name=\"post\" action=\"$g_root_path/adm_program/modules/announcements/announcements_function.php?ann_id=$req_ann_id&amp;headline=". $_GET['headline']. "&amp;mode=1\" >
<div class=\"formLayout\" id=\"edit_announcements_form\">
    <div class=\"formHead\">". $g_layout['title']. "</div>
    <div class=\"formBody\">
        <ul class=\"formFieldList\">
            <li>
                <dl>
                    <dt><label for=\"ann_headline\">Überschrift:</label></dt>
                    <dd>
                        <input type=\"text\" id=\"ann_headline\" name=\"ann_headline\" style=\"width: 350px;\" tabindex=\"1\" maxlength=\"100\" value=\"". $announcement->getValue("ann_headline"). "\" />
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>
			";
         if ($g_preferences['enable_bbcode'] == 1)
         {
            echo "
            <li>
                <dl>
                    <dt>&nbsp;</dt>
                    <dd>
                        <a href=\"javascript:bbcode(0)\" id=\"[b]\"><img src=\"". THEME_PATH. "/icons/text_bold.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(1)\" id=\"[u]\"><img src=\"". THEME_PATH. "/icons/text_underline.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(2)\" id=\"[i]\"><img src=\"". THEME_PATH. "/icons/text_italic.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(3)\" id=\"[big]\"><img src=\"". THEME_PATH. "/icons/text_bigger.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(4)\" id=\"[small]\"><img src=\"". THEME_PATH. "/icons/text_smaller.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(5)\" id=\"[center]\"><img src=\"". THEME_PATH. "/icons/text_align_center.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(6)\" id=\"[url=http://www.Adresse.de]\"><img src=\"". THEME_PATH. "/icons/link.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:bbcode(7)\" id=\"[email=adresse@demo.de]\"><img src=\"". THEME_PATH. "/icons/email.png\" border=\"0\"/></a>&nbsp;
                        <a href=\"javascript:emoticon('[img]www.Bild-Adresse.de[/img]')\" id=\"[img]\"><img src=\"". THEME_PATH. "/icons/image.png\" border=\"0\"/></a>&nbsp;&nbsp;
                        <a href=\"javascript:bbcodeclose()\" id=\"[img]\"><img src=\"". THEME_PATH. "/icons/cross.png\" border=\"0\"/></a>
                    </dd>
                </dl>
            </li>";
         }
         echo "
            <li>
                <dl>
                    <dt><label for=\"ann_description\">Text:</label>";
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                          //echo "<br /><br />
//                          <a href=\"#\" onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=bbcode&amp;window=true','Message','width=600,height=500,left=310,top=200,scrollbars=yes')\" tabindex=\"5\">Text formatieren</a>";
						  echo "<br /><br />&nbsp;&nbsp;
                        <a href=\"javascript:emoticon(':)')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_smile.png\" alt=\"Smile\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(';)')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_wink.png\" alt=\"Wink\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(':D')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_grin.png\" alt=\"Grin\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(':lol:')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_happy.png\" alt=\"Happy\" border=\"0\" /></a>
                        <br />&nbsp;&nbsp;
                        <a href=\"javascript:emoticon(':(')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_unhappy.png\" alt=\"Unhappy\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(':p')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_tongue.png\" alt=\"Tongue\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(':o')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_surprised.png\" alt=\"Surprised\" border=\"0\" /></a>
                        <a href=\"javascript:emoticon(':twisted:')\"><img src=\"". THEME_PATH. "/icons/smilies/emoticon_evilgrin.png\" alt=\"Evilgrin\" border=\"0\" /></a>";
                        } 
                    echo "</dt>
                    <dd>
                        <textarea id=\"ann_description\" name=\"ann_description\" style=\"width: 350px;\" tabindex=\"2\" rows=\"10\" cols=\"40\">". $announcement->getValue("ann_description"). "</textarea>
                        <span class=\"mandatoryFieldMarker\" title=\"Pflichtfeld\">*</span>
                    </dd>
                </dl>
            </li>";

            // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf "global" gesetzt werden
            if($g_current_organization->getValue("org_org_id_parent") > 0
            || $g_current_organization->hasChildOrganizations())
            {
                echo "
                <li>
                    <dl>
                        <dt>&nbsp;</dt>
                        <dd>
                            <input type=\"checkbox\" id=\"ann_global\" name=\"ann_global\" tabindex=\"3\" ";
                            if($announcement->getValue("ann_global") == 1)
                            {
                                echo " checked=\"checked\" ";
                            }
                            echo " value=\"1\" />
                            <label for=\"ann_global\">$req_headline f&uuml;r mehrere Organisationen sichtbar</label>
                            <img class=\"iconHelpLink\" src=\"". THEME_PATH. "/icons/help.png\" alt=\"Hilfe\" title=\"Hilfe\"                       onclick=\"window.open('$g_root_path/adm_program/system/msg_window.php?err_code=date_global&amp;window=true','Message','width=400,height=350,left=310,top=200,scrollbars=yes')\" onmouseover=\"ajax_showTooltip('$g_root_path/adm_program/system/msg_window.php?err_code=date_global',this);\" onmouseout=\"ajax_hideTooltip()\"  />
                        </dd>
                    </dl>
                </li>";
            }
        echo "</ul>

        <hr />

        <div class=\"formSubmit\">
            <button name=\"speichern\" type=\"submit\" value=\"speichern\" tabindex=\"4\"><img src=\"". THEME_PATH. "/icons/disk.png\" alt=\"Speichern\" />&nbsp;Speichern</button>
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
    document.getElementById('ann_headline').focus();
--></script>";

require(THEME_SERVER_PATH. "/overall_footer.php");

?>