<?php
/******************************************************************************
 * Gemeinsame Funktionen fuer Update und Installation
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

function showPage($message, $next_url, $icon, $icon_text, $mode = 1)
{
    // Html des Modules ausgeben
    global $g_root_path, $g_l10n;
    
    if($mode == 1)
    {
        $headline = $g_l10n->get('INS_INSTALLATION_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT);
        $title    = $g_l10n->get('INS_INSTALLATION');
    }
    elseif($mode == 2)
    {
        $headline = $g_l10n->get('INS_UPDATE_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT);
        $title    = $g_l10n->get('INS_UPDATE');
    }
    elseif($mode == 3)
    {
        $headline = $g_l10n->get('INS_ADD_ANOTHER_ORGANIZATION');
        $title    = $g_l10n->get('INS_ADD_ORGANIZATION');
    }
    
    header('Content-type: text/html; charset=utf-8'); 
    echo '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="de" xml:lang="de">
    <head>
        <!-- (c) 2004 - 2011 The Admidio Team - http://www.admidio.org -->
        
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <meta name="author"   content="Admidio Team" />
        <meta name="robots"   content="noindex" />
        
        <title>Admidio - '. $title. '</title>

        <link rel="shortcut icon" type="image/x-icon" href="layout/favicon.png" />
        <link rel="stylesheet" type="text/css" href="layout/install.css" />
        <script type="text/javascript" src="'. $g_root_path. '/adm_program/system/js/common_functions.js"></script>
        
        <script><!--
            imgLoader = new Image();
            imgLoader.src = "../adm_themes/classic/icons/loader.gif";

            function startUpdate()
            {
                submit_button = document.getElementById("next_page");
                if(submit_button.value == "'.$g_l10n->get('INS_UPDATE_DATABASE').'"
                || submit_button.value == "'.$g_l10n->get('INS_INSTALL_ADMIDIO').'")
                {
                    submit_button.disabled  = true;
                    document.getElementById("btn_icon").src = imgLoader.src;
                    if(submit_button.value == "'.$g_l10n->get('INS_UPDATE_DATABASE').'")
                    {
                        document.getElementById("btn_text").innerHTML = "'.$g_l10n->get('INS_DATABASE_IS_UPDATED').'";
                    }
                    else
                    {
                        document.getElementById("btn_text").innerHTML = "'.$g_l10n->get('INS_DATABASE_WILL_BE_ESTABLISHED').'";
                    }
                }
                document.getElementById("adm_install").submit();
            }
        //--></script>
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
                        <button type="button" id="next_page" name="next_page" onclick="startUpdate()" value="'.$icon_text.'"><img id="btn_icon" src="layout/'. $icon. '" alt="'. $icon_text. '" />&nbsp;<span id="btn_text">'. $icon_text. '</span></button>';
                        if($icon == 'money.png')
                        {
                            echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <button type="button" onclick="self.location.href=\'../adm_program/index.php\'" value="'.$g_l10n->get('SYS_LATER').'"><img id="btn_icon" src="layout/application_view_list.png" alt="'. $g_l10n->get('SYS_LATER'). '" />&nbsp;'. $g_l10n->get('SYS_LATER'). '</button>';
                        }
                    echo '</div>
                </div>
            </div>
        </div>
        <div><img class="img_border" src="layout/border_bottom_big.png" alt="border" /></div>
        </div>
        </form>

        <script type="text/javascript"><!--
            document.getElementById("next_page").focus();
        //--></script>
    </body>
    </html>';
    exit();
}

// prueft, ob die Mindestvoraussetzungen bei PHP und MySQL eingehalten werden
function checkVersions(&$db, &$message)
{
    global $g_l10n;
    $message = '';
    $min_mysql_version = '4.1.0';
    $min_php_version   = '5.2.0';

    // Datenbank pruefen
    if(version_compare($db->server_info(), $min_mysql_version) == -1)
    {
        $message = $message. ' 
        <li>
            <dl>
                <dt>'.$g_l10n->get('INS_MYSQL_VERSION').':</dt>
                <dd><strong>'.$db->server_info().'</strong><br />'.
                    $g_l10n->get('INS_PHP_WRONG_PHP_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT, $min_mysql_version, '<a href="http://www.admidio.org/index.php?page=download">', '</a>').
                '</dd>
            </dl>
        </li>';
    }

    // PHP pruefen
    if(version_compare(phpversion(), $min_php_version) == -1)
    {
        $message = $message. ' 
        <li>
            <dl>
                <dt>'.$g_l10n->get('INS_PHP_VERSION').':</dt>
                <dd><strong>'.phpversion().'</strong><br />'.
                    $g_l10n->get('INS_PHP_WRONG_PHP_VERSION', ADMIDIO_VERSION. BETA_VERSION_TEXT, $min_php_version, '<a href="http://www.admidio.org/index.php?page=download">', '</a>').
                '</dd>
            </dl>
        </li>';
    }
    
    if(strlen($message) > 0)
    {
        $message = '
        <div class="groupBox">
            <div class="groupBoxHeadline"><img src="layout/warning.png" alt="'.$g_l10n->get('SYS_WARNING').'" />'.$g_l10n->get('SYS_WARNING').'</div>
            <div class="groupBoxBody">
                <ul class="formFieldList">'. $message. '</ul>
            </div>
        </div>';        
    }

    if(strlen($message) == 0)
    {
        return true;
    }
    return false;
}

?>