<?php
/******************************************************************************
 * Ausgabe der PHpInfo
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
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
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// Uebergabevariablen pruefen
if (isset($_GET['mode']))
{
    if (is_numeric($_GET['mode']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
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
    echo'
    <ul class="formFieldList">
        <li>
            <dl>
                <dt>Admidio-Version:</dt>
                <dd>'. ADMIDIO_VERSION. BETA_VERSION_TEXT.'&nbsp;
                    <a rel="colorboxHelp" href="'.$g_root_path.'/adm_program/system/update_check.php?show=2&amp;inline=true">auf Update prüfen</a>
                </dd>
            </dl>
        </li>';
                
        //php Version
        echo'
        <li>
            <dl>
                <dt>PHP Version:</dt><dd><span class="';
                if(substr(phpversion(), 0, 3)< 4.3)
                {
                    echo 'systeminfoBad">'.phpversion().'</span> &rarr; Admidio benötigt 4.3 oder höher';
                }
                else
                {
                    echo 'systeminfoGood">'.phpversion().'</span>';
                }
                echo'
                </dd>
            </dl>
        </li>';
                
        //sql-server Version
        echo'
        <li>
            <dl>
                <dt>MySQL Server Version:</dt><dd><span class="';
                if(substr($g_db->server_info(), 0, 3)< 4.1)
                {
                    echo 'systeminfoBad">'.$g_db->server_info().'</span> &rarr; Admidio benötigt 4.1 oder höher';
                }
                else
                {
                    echo 'systeminfoGood">'.$g_db->server_info().'</span>';
                }
                echo'
                </dd>
            </dl>
        </li>';
                
        //SafeMode
        echo'
        <li>
            <dl>
                <dt>Safe Mode:</dt><dd>';
                if(ini_get('safe_mode') == 1)
                {
                    echo '<span class="systeminfoBad">On</span> &rarr; problematisch bei Dateiuploads';
                }
                else
                {
                    echo '<span class="systeminfoGood">Off</span>';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Maximal Größe eines Posts
        echo'
        <li>
            <dl>
                <dt>Max. POST-Größe:</dt><dd>';
                if(ini_get('post_max_size')!='')
                {
                    echo ini_get('post_max_size');
                }
                else
                {
                    echo 'nicht gesetzt';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Arbeitsspeicher
        echo'
        <li>
            <dl>
                <dt>Arbeitsspeicher:</dt><dd>';
                if(ini_get('memory_limit')!='')
                {
                    echo ini_get('memory_limit');
                }
                else
                {
                    echo 'nicht gesetzt';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Dateiuploads
        echo'
        <li>
            <dl>
                <dt>Dateiuploads:</dt><dd>';
                if(ini_get('file_uploads') == 1)
                {
                    echo 'On';
                }
                else
                {
                    echo 'Off';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Max. Upload-Größe
        echo'
        <li>
            <dl>
                <dt>Max. Upload-Größe:</dt><dd>';
                if(ini_get('upload_max_filesize')!='')
                {
                    echo ini_get('upload_max_filesize');
                }
                else
                {
                    echo 'nicht gesetzt';
                }
                echo '</dd>
            </dl>
        </li>';     
                
        // Maximal bearbeitbare Bildgröße
        echo'
        <li>
            <dl>
                <dt>Max. bearbeitbare Bildgröße:</dt><dd>'.round((processableImageSize()/1000000), 2).' MegaPixel</dd>
            </dl>
        </li>';
                
        // Link zu php Info
        echo'
        <li>
            <dl>
                <dt>alle PHP-Informationen:</dt><dd><a href="systeminfo.php?mode=2" target="_blank">phpinfo()</a></dd>
                
            </dl>
        </li>';
        
        //Debugmodus       
        if(isset($g_debug))
        {
            echo'
            <li>
                <dl>';           
                    echo' <dt>Debugmodus:</dt><dd>';
                    if($g_debug == 1)
                    {
                        echo 'On';
                    }
                    else
                    {
                        echo 'Off';
                    }
                    echo'</dd>
                
                </dl>
            </li>';
        }  
    echo'</ul>';

} 
/************PHP Info*******************/
if($req_mode == 2)
{
    phpinfo();
}
?>