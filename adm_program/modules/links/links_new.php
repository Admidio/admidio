<?php
/******************************************************************************
 * Links anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lnk_id        - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Links steht
 *                 (Default) Links
 *
 *****************************************************************************/

require('../../system/common.php');
require('../../system/login_valid.php');
require('../../system/classes/table_weblink.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show('module_disabled');
}


// Ist ueberhaupt das Recht vorhanden?
if (!$g_current_user->editWeblinksRight())
{
    $g_message->show('norights');
}

// Uebergabevariablen pruefen
if (array_key_exists('lnk_id', $_GET))
{
    if (is_numeric($_GET['lnk_id']) == false)
    {
        $g_message->show('invalid');
    }
}
else
{
    $_GET['lnk_id'] = 0;
}

if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = 'Links';
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Weblinkobjekt anlegen
$link = new TableWeblink($g_db, $_GET['lnk_id']);

if(isset($_SESSION['links_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['links_request'] as $key => $value)
    {
        if(strpos($key, 'lnk_') == 0)
        {
            $link->setValue($key, stripslashes($value));
        }
    }
    unset($_SESSION['links_request']);
}

// Html-Kopf ausgeben
if($_GET['lnk_id'] > 0)
{
    $g_layout['title'] = $_GET['headline']. ' ändern';
}
else
{
    $g_layout['title'] = $_GET['headline']. ' anlegen';
}
$javascript = "";
$javascript = "<script type=\"text/javascript\"><!--
   var vorbelegt = Array(false,false,false,false,false,false,false,false,false,false);
   var bbcodes = Array(\"[b]\",\"[/b]\",\"[u]\",\"[/u]\",\"[i]\",\"[/i]\",\"[big]\",\"[/big]\",\"[small]\",\"[/small]\",\"[center]\",\"[/center]\",\"[url=".$g_root_path."]\",\"[/url]\",\"[email=adresse@demo.de]\",\"[/email]\",\"[img]\",\"[/img]\");
   var bbids = Array(\"b\",\"u\",\"i\",\"big\",\"small\",\"center\",\"url\",\"email\",\"img\");
   var bbcodestext = Array(\"text_bold_point.png\",\"text_bold.png\",
                            \"text_underline_point.png\",\"text_underline.png\",
                            \"text_italic_point.png\",\"text_italic.png\",
                            \"text_bigger_point.png\",\"text_bigger.png\",
                            \"text_smaller_point.png\",\"text_smaller.png\",
                            \"text_align_center_point.png\",\"text_align_center.png\",
                            \"link_point.png\",\"link.png\",
                            \"email_point.png\",\"email.png\",
                            \"image_point.png\",\"image.png\");

    function emoticon(text)
    {
        var txtarea = document.getElementById('lnk_description');

        if (txtarea.createTextRange && txtarea.caretPos)
        {
            txtarea.caretPos.text = text;
        }
        else
        {
            txtarea.value  += text;
        }
        txtarea.focus();
    }

    function bbcode(nummer)
   {
      var arrayid;
      if (vorbelegt[nummer])
      {
         arrayid = nummer*2+1;
      } else
      {
         arrayid = nummer*2;
      };
      emoticon(bbcodes[arrayid]);
      document.getElementById(bbids[nummer]).src = '". THEME_PATH. "/icons/'+bbcodestext[arrayid];
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
    }
    --></script>";
$g_layout['header'] = '';
$g_layout['header'] .= $javascript;
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
if($_GET['lnk_id'] > 0)
{
    $new_mode = '3';
}
else
{
    $new_mode = '1';
}

echo '
<form action="'.$g_root_path.'/adm_program/modules/links/links_function.php?lnk_id='. $_GET['lnk_id']. '&amp;headline='. $_GET['headline']. '&amp;mode='.$new_mode.'" method="post">
<div class="formLayout" id="edit_links_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="lnk_name">Linkname:</label></dt>
                    <dd>
                        <input type="text" id="lnk_name" name="lnk_name" tabindex="1" style="width: 350px;" maxlength="250" value="'. $link->getValue('lnk_name'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_url">Linkadresse:</label></dt>
                    <dd>
                        <input type="text" id="lnk_url" name="lnk_url" tabindex="2" style="width: 350px;" maxlength="250" value="'. $link->getValue('lnk_url'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_cat_id">Kategorie:</label></dt>
                    <dd>
                        <select id="lnk_cat_id" name="lnk_cat_id" size="1" tabindex="3">
                            <option value=" "';
                                if($link->getValue('lnk_cat_id') == 0)
                                {
                                    echo ' selected="selected"';
                                }
                                echo '>- Bitte wählen -</option>';

                            $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
                                     WHERE cat_org_id = '. $g_current_organization->getValue('org_id'). '
                                       AND cat_type   = "LNK"
                                     ORDER BY cat_sequence ASC ';
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_object($result))
                            {
                                echo '<option value="'.$row->cat_id.'"';
                                    if($link->getValue('lnk_cat_id') == $row->cat_id)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                echo '>'.$row->cat_name.'</option>';
                            }
                        echo '</select>
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            ';
         if ($g_preferences['enable_bbcode'] == 1)
         {
            echo '
            <li>
                <dl>
                    <dt></dt>
                    <dd>
                        <div style="width: 350px;">
                            <div style="float: left;">
                                <a class="iconLink" href="javascript:bbcode(0)"><img id="b"
                                    src="'. THEME_PATH. '/icons/text_bold.png" title="Fett schreiben" alt="Fett schreiben" /></a>
                                <a class="iconLink" href="javascript:bbcode(1)"><img id="u"
                                    src="'. THEME_PATH. '/icons/text_underline.png" title="Text unterstreichen" alt="Text unterstreichen" /></a>
                                <a class="iconLink" href="javascript:bbcode(2)"><img id="i"
                                    src="'. THEME_PATH. '/icons/text_italic.png" title="Kursiv schreiben" alt="Kursiv schreiben" /></a>
                                <a class="iconLink" href="javascript:bbcode(3)"><img id="big"
                                    src="'. THEME_PATH. '/icons/text_bigger.png" title="Größer schreiben" alt="Größer schreiben" /></a>
                                <a class="iconLink" href="javascript:bbcode(4)"><img id="small"
                                    src="'. THEME_PATH. '/icons/text_smaller.png" title="Kleiner schreiben" alt="Kleiner schreiben" /></a>
                                <a class="iconLink" href="javascript:bbcode(5)"><img id="center"
                                    src="'. THEME_PATH. '/icons/text_align_center.png" title="Text zentrieren" alt="Text zentrieren" /></a>
                                <a class="iconLink" href="javascript:emoticon(\'[url=http://www.admidio.org]Linktext[/url]\')"><img id="url"
                                    src="'. THEME_PATH. '/icons/link.png" title="Link einfügen" alt="Link einfügen" /></a>
                                <a class="iconLink" href="javascript:emoticon(\'[email=name@admidio.org]Linktext[/email]\')"><img id="email"
                                    src="'. THEME_PATH. '/icons/email.png" title="E-Mail-Adresse einfügen" alt="E-Mail-Adresse einfügen" /></a>
                                <a class="iconLink" href="javascript:emoticon(\'[img]http://www.admidio.org/images/admidio_small.png[/img]\')"><img id="img"
                                    src="'. THEME_PATH. '/icons/image.png" title="Bild einfügen" alt="Bild einfügen" /></a>
                            </div>
                            <div style="float: right;">
                                <a class="iconLink" href="javascript:bbcodeclose()"><img id="all-closed"
                                    src="'. THEME_PATH. '/icons/delete.png" title="Alle Tags schließen" alt="Alle Tags schließen" /></a>
                                <img class="iconLink" src="'. THEME_PATH. '/icons/help.png"
                                    onclick="window.open(\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=bbcode&amp;window=true\',\'Message\',\'width=600,height=500,left=310,top=200,scrollbars=yes\')" 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=bbcode\',this);" 
                                    onmouseout="ajax_hideTooltip()" alt="Hilfe" title="" />
                            </div>
                        </div>
                    </dd>
                </dl>
            </li>';
         }
         echo '
            <li>
                <dl>
                    <dt><label for="lnk_description">Text:</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            echo '<br /><br />&nbsp;&nbsp;
                            <a class="iconLink" href="javascript:emoticon(\':)\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_smile.png" alt="Smile" /></a>
                            <a class="iconLink" href="javascript:emoticon(\';)\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_wink.png" alt="Wink" /></a>
                            <a class="iconLink" href="javascript:emoticon(\':D\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_grin.png" alt="Grin" /></a>
                            <a class="iconLink" href="javascript:emoticon(\':lol:\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_happy.png" alt="Happy" /></a>
                            <br />&nbsp;&nbsp;
                            <a class="iconLink" href="javascript:emoticon(\':(\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_unhappy.png" alt="Unhappy" /></a>
                            <a class="iconLink" href="javascript:emoticon(\':p\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_tongue.png" alt="Tongue" /></a>
                            <a class="iconLink" href="javascript:emoticon(\':o\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_surprised.png" alt="Surprised" /></a>
                            <a class="iconLink" href="javascript:emoticon(\':twisted:\')"><img
                                src="'. THEME_PATH. '/icons/smilies/emoticon_evilgrin.png" alt="Evilgrin" /></a>';
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="lnk_description" name="lnk_description" tabindex="4" style="width: 350px;" rows="10" cols="40">'. $link->getValue('lnk_description'). '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />

        <div class="formSubmit">
            <button name="speichern" type="submit" value="speichern" tabindex="5"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>

<script type="text/javascript"><!--
    document.getElementById(\'linkname\').focus();
--></script>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>