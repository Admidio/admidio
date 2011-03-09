<?php
/******************************************************************************
 * Ausgabe der PHpInfo
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
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
                <dt>'.$g_l10n->get('SYS_ADMIDIO_VERSION').':</dt>
                <dd>'. ADMIDIO_VERSION. BETA_VERSION_TEXT.'&nbsp;
                    <a rel="colorboxHelp" href="'.$g_root_path.'/adm_program/system/update_check.php?show=2&amp;inline=true" title="'.$g_l10n->get('SYS_CHECK_FOR_UPDATE').'">'.$g_l10n->get('SYS_CHECK_FOR_UPDATE').'</a>
                </dd>
            </dl>
        </li>';
                
        //php Version
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_PHP_VERSION').':</dt><dd><span class="';
                if(substr(phpversion(), 0, 3)< 5.2)
                {
                    echo 'systeminfoBad">'.phpversion().'</span> &rarr; '.$g_l10n->get('SYS_PHP_VERSION_REQUIRED');
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
                <dt>'.$g_l10n->get('SYS_MYSQL_VERSION').':</dt><dd><span class="';
                if(substr($g_db->server_info(), 0, 3)< 4.1)
                {
                    echo 'systeminfoBad">'.$g_db->server_info().'</span> &rarr; '.$g_l10n->get('SYS_MYSQL_VERSION_REQUIRED');
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
                <dt>'.$g_l10n->get('SYS_SAFE_MODE').':</dt><dd>';
                if(ini_get('safe_mode') == 1)
                {
                    echo '<span class="systeminfoBad">'.$g_l10n->get('SYS_ON').'</span> &rarr; '.$g_l10n->get('SYS_SAFE_MODE_PROBLEM');
                }
                else
                {
                    echo '<span class="systeminfoGood">'.$g_l10n->get('SYS_OFF').'</span>';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Maximal Größe eines Posts
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_POST_MAX_SIZE').':</dt><dd>';
                if(ini_get('post_max_size')!='')
                {
                    echo ini_get('post_max_size');
                }
                else
                {
                    echo $g_l10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Arbeitsspeicher
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_MEMORY_LIMIT').':</dt><dd>';
                if(ini_get('memory_limit')!='')
                {
                    echo ini_get('memory_limit');
                }
                else
                {
                    echo $g_l10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Dateiuploads
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_FILE_UPLOADS').':</dt><dd>';
                if(ini_get('file_uploads') == 1)
                {
                    echo $g_l10n->get('SYS_ON');
                }
                else
                {
                    echo $g_l10n->get('SYS_OFF');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Max. Upload-Größe
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_UPLOAD_MAX_FILESIZE').':</dt><dd>';
                if(ini_get('upload_max_filesize')!='')
                {
                    echo ini_get('upload_max_filesize');
                }
                else
                {
                    echo $g_l10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';     
                
        // Maximal bearbeitbare Bildgröße
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE').':</dt><dd>'.round((processableImageSize()/1000000), 2).' '.$g_l10n->get('SYS_MEGA_PIXEL').'</dd>
            </dl>
        </li>';
                
        // Link zu php Info
        echo'
        <li>
            <dl>
                <dt>'.$g_l10n->get('SYS_PHP_INFO').':</dt><dd><a href="systeminfo.php?mode=2" target="_blank">phpinfo()</a></dd>
                
            </dl>
        </li>';
        
        //Debugmodus       
        if(isset($g_debug))
        {
            echo'
            <li>
                <dl>';           
                    echo' <dt>'.$g_l10n->get('SYS_DEBUG_MODUS').':</dt><dd>';
                    if($g_debug == 1)
                    {
                        echo $g_l10n->get('SYS_ON');
                    }
                    else
                    {
                        echo $g_l10n->get('SYS_OFF');
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