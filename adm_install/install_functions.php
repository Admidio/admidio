<?php
/******************************************************************************
 * Gemeinsame Funktionen fuer Update und Installation
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function showPage($message, $next_url, $icon, $icon_text, $mode = 1)
{
    // Html des Modules ausgeben
    global $g_root_path;
    
    if($mode == 1)
    {
        $headline = "Installation der Version ". ADMIDIO_VERSION. BETA_VERSION_TEXT;
        $title    = "Installation";
    }
    elseif($mode == 2)
    {
        $headline = "Update auf Version ". ADMIDIO_VERSION. BETA_VERSION_TEXT;
        $title    = "Update";
    }
    elseif($mode == 3)
    {
        $headline = "Weitere Organisation hinzufügen";
        $title    = "Organisation hinzufügen";
    }
    
    header('Content-type: text/html; charset=utf-8'); 
    echo '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
    <head>
        <!-- (c) 2004 - 2009 The Admidio Team - http://www.admidio.org -->
        
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="author"   content="Admidio Team" />
        <meta name="robots"   content="noindex" />
        
        <title>Admidio - '. $title. '</title>

        <link rel="stylesheet" type="text/css" href="layout/install.css" />
        <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>

        <!--[if lt IE 7]>
        <script type="text/javascript"><!--
            window.attachEvent("onload", correctPNG);
        --></script>
        <![endif]-->
        
        <script><!--
            imgLoader = new Image();
            imgLoader.src = "../adm_themes/classic/icons/loader.gif";

            function startUpdate()
            {
                submit_button = document.getElementById(\'next_page\');
                if(submit_button.value == \'Datenbank aktualisieren\'
                || submit_button.value == \'Admidio installieren\')
                {
                    submit_button.disabled  = true;
                    document.getElementById(\'btn_icon\').src = imgLoader.src;
                    if(submit_button.value == \'Datenbank aktualisieren\')
                    {
                        document.getElementById(\'btn_text\').innerHTML = \'Datenbank wird aktualisiert\';
                    }
                    else
                    {
                        document.getElementById(\'btn_text\').innerHTML = \'Datenbank wird eingerichtet\';
                    }
                }
                document.getElementById(\'adm_install\').submit();
            }
        --></script>
    </head>
    <body>
        <form id="adm_install" action="'. $next_url. '" method="post">
        <div id="page">
        <div><img class="img_border" src="layout/border_top_big.png" alt="border" /></div>
        <div id="content_left" class="content">&nbsp;
            <div class="formLayout" id="installation_form">
                <div class="formHead" style="text-align: left; letter-spacing: 0em;">'. $headline. '</div>
    
                <div class="formBody" style="text-align: left;">
                    <p class="bigFontSize">'.
                        $message.
                    '</p>
    
                    <div class="formSubmit">
                        <button type="button" id="next_page" name="next_page" value="'. $icon_text. '" onclick="startUpdate()"><img id="btn_icon" src="layout/'. $icon. '" alt="'. $icon_text. '" />&nbsp;<span id="btn_text">'. $icon_text. '</span></button>
                    </div>            
                </div>
            </div>
        </div>
        <div><img class="img_border" src="layout/border_bottom_big.png" alt="border" /></div>
        </div>
        </form>

        <script type="text/javascript"><!--
            document.getElementById(\'next_page\').focus();
        --></script>
    </body>
    </html>';
    exit();
}

?>