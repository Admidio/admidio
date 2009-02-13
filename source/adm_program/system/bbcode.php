<?php
/******************************************************************************
 * Script beinhaltet allgemeine Daten / Variablen, die fuer alle anderen
 * Scripte notwendig sind
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Funktionen   : getBBCodeJS (gibt JS für Headerbereich zurück, incl. skripttext)
 *                printBBcodeIcons (gibt Liste der Bearbeitungsicons aus)
 *                printEmoticons (gibt Emoticonlinks aus)
 *
 *****************************************************************************/
function getBBcodeJS($target_textbox)
{
    global $g_root_path;
    return ' <script type="text/javascript"><!--
    var vorbelegt = Array(false,false,false,false,false,false,false,false,false,false);
    var bbcodes = Array("[b]","[/b]","[u]","[/u]","[i]","[/i]","[big]","[/big]","[small]","[/small]","[center]","[/center]",
                        "[url='.$g_root_path.']","[/url]","[email=adresse@demo.de]","[/email]","[img]","[/img]");
    var bbids = Array("b","u","i","big","small","center","url","email","img");
    var bbcodestext = Array("text_bold_point.png","text_bold.png",
                            "text_underline_point.png","text_underline.png",
                            "text_italic_point.png","text_italic.png",
                            "text_bigger_point.png","text_bigger.png",
                            "text_smaller_point.png","text_smaller.png",
                            "text_align_center_point.png","text_align_center.png",
                            "link_point.png","link.png",
                            "email_point.png","email.png",
                            "image_point.png","image.png");

    function emoticon(text)
    {
        var txtarea = document.getElementById("'.$target_textbox.'");

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
        }
        else
        {
            arrayid = nummer*2;
        };
        emoticon(bbcodes[arrayid]);
        document.getElementById(bbids[nummer]).src = "'. THEME_PATH. '/icons/" + bbcodestext[arrayid];
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
    //--></script>';    
}

function printBBcodeIcons()
{
    global $g_root_path;
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
                            src="'.THEME_PATH.'/icons/delete.png" title="Alle Tags schließen" alt="Alle Tags schließen" /></a>
                        <a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=bbcode&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=520&amp;width=580"><img 
                            class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe anzeigen" title="Hilfe anzeigen" /></a>
                    </div>
                </div>
            </dd>
        </dl>
    </li>';
}

function printEmoticons()
{
    echo '<br /><br />&nbsp;&nbsp;
    <a class="iconLink" href="javascript:emoticon(\':)\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_smile.png" alt="Smile" /></a>
    <a class="iconLink" href="javascript:emoticon(\';)\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_wink.png" alt="Wink" /></a>
    <a class="iconLink" href="javascript:emoticon(\':D\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_grin.png" alt="Grin" /></a>
    <a class="iconLink" href="javascript:emoticon(\':p\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_tongue.png" alt="Tongue" /></a>
    <br />&nbsp;&nbsp;
    <a class="iconLink" href="javascript:emoticon(\':(\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_unhappy.png" alt="Unhappy" /></a>
    <a class="iconLink" href="javascript:emoticon(\':|\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_plain.png" alt="Plain" /></a>
    <a class="iconLink" href="javascript:emoticon(\':o\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_surprised.png" alt="Surprised" /></a>
    <a class="iconLink" href="javascript:emoticon(\':`(\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_crying.png" alt="Crying" /></a>
    <br />&nbsp;&nbsp;
    <a class="iconLink" href="javascript:emoticon(\'0:)\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_angel.png" alt="Angel" /></a>
    <a class="iconLink" href="javascript:emoticon(\'8)\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_glasses.png" alt="Glasses" /></a>
    <a class="iconLink" href="javascript:emoticon(\':twisted:\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_evilgrin.png" alt="Evilgrin" /></a>
    <a class="iconLink" href="javascript:emoticon(\':*\')"><img src="'.THEME_PATH.'/icons/smilies/emoticon_kiss.png" alt="Kiss" /></a>';
}
?>