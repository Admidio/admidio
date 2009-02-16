<?php
/******************************************************************************
 * Ausgabe der PHpInfo
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Jochen Erkens
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * mode         : 1 - Systeminformation
                : 2 - PHP-Info       
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// nur Webmaster duerfen Organisationen bearbeiten
if($g_current_user->isWebmaster() == false)
{
    $g_message->show('norights');
}

// Uebergabevariablen pruefen
if (isset($_GET['mode']))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show('invalid');
    }

    $req_mode = $_GET['mode'];

}
else
{
    $req_mode = 1;
}

/************Systeminformationen********/
if($req_mode == 1)
{
    echo'<dl>
        <dt>Admidio-Version:</dt>
        <dd>'. ADMIDIO_VERSION. BETA_VERSION_TEXT.'&nbsp;
            <a class="thickbox" href="'.$g_root_path.'/adm_program/system/update_check.php?show=2&amp;KeepThis=true&amp;TB_iframe=true&amp;height=300&amp;width=350">auf Update prüfen</a>
        </dd>';
        //php Version
        echo'<dt>PHP Version:</dt><dd><span class="';;
        if(substr(phpversion(), 0, 3)< 4.3)
        {
            echo 'systeminfoBad">'.phpversion().'</span> &rarr; Admidio benötigt 4.3 oder höher';
        }
        else
        {
            echo 'systeminfoGood">'.phpversion().'</span>';
        }
        echo'
        </dd>';
        //sql-server Version
        echo'<dt>MySQL Server Version:</dt><dd><span class="';;
        if(substr($g_db->server_info(), 0, 3)< 4.1)
        {
            echo 'systeminfoBad">'.$g_db->server_info().'</span> &rarr; Admidio benötigt 4.1 oder höher';
        }
        else
        {
            echo 'systeminfoGood">'.$g_db->server_info().'</span>';
        }
        echo'
        </dd>';
        //SafeMode
        echo'
        <dt>Safe Mode:</dt><dd>';
        if(ini_get('safe_mode') == 1)
        {
            echo '<span class="systeminfoBad">On</span> &rarr; problematisch bei Dateiuploads';
        }
        else
        {
            echo '<span class="systeminfoGood">Off</span>';
        }
        echo '</dd>';
        //Maximal Größe eines Posts
        echo'<dt>Max. POST-Größe:</dt><dd>'.ini_get('post_max_size').'</dd>';
        echo'<dt>Arbeitsspeicher:</dt><dd>'.ini_get('memory_limit').'</dd>';
        echo' <dt>Dateiuploads:</dt><dd>';
        if(ini_get('file_uploads') == 1)
        {
            echo 'On';
        }
        else
        {
            echo 'Off';
        }
        echo '</dd>
        <dt>Max. Upload-Größe:</dt><dd>'.ini_get('upload_max_filesize').'</dd>
        <dt>Max. bearbeitbare Bildgröße:</dt><dd>'.round((processableImageSize()/1000000), 2).' MegaPixel</dd>
        <dt>alle PHP-Informationen:</dt><dd><a href="systeminfo.php?mode=2" target="_blank2">phpinfo()</a></dd>
    </dl>';

} 
/************PHP Info*******************/
if($req_mode == 2)
{
    phpinfo();
}
?>