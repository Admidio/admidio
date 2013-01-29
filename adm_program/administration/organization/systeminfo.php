<?php
/******************************************************************************
 * Ausgabe der PHpInfo
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * mode         : 1 - Systeminformation
                : 2 - PHP-Info       
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Uebergabevariablen pruefen
$getMode = admFuncVariableIsValid($_GET, 'mode', 'numeric', 1);

// nur Webmaster duerfen Organisationen bearbeiten
if($gCurrentUser->isWebmaster() == false)
{
    $gMessage->show($gL10n->get('UPD_CONNECTION_ERROR'));
}

/************Systeminformationen********/
if($getMode == 1)
{
    echo'
    <script type="text/javascript">
        $(document).ready(function()
        {           
            $("#linkCheckForUpdate").live("click", function()
            {
                $("#admVersion").empty();
                $("#admVersion").prepend("<img src=\''.THEME_PATH.'/icons/loader_inline.gif\' id=\'loadindicator\'/>").show();
                $.get("'.$g_root_path.'/adm_program/administration/organization/update_check.php", {mode:"2"}, function(htmlVersion){
                    $("#admVersion").empty();
                    $("#admVersion").append(htmlVersion);               
                });
                return false;
            });
        });
    </script>';    
        
    echo'
    <ul class="formFieldList">
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_ADMIDIO_VERSION').':</dt>
                <dd id="admVersion">'. ADMIDIO_VERSION. BETA_VERSION_TEXT.'
                    <a href="#" title="'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'" id="linkCheckForUpdate">'.$gL10n->get('SYS_CHECK_FOR_UPDATE').'</a>
                </dd>
            </dl>
        </li>';
                
        //php Version
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_PHP_VERSION').':</dt><dd><span class="';
                if(version_compare(phpversion(), MIN_PHP_VERSION) == -1)
                {
                    echo 'systeminfoBad">'.phpversion().'</span> &rarr; '.$gL10n->get('SYS_PHP_VERSION_REQUIRED', MIN_PHP_VERSION);
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
                <dt>'.$gDb->getName().'-'.$gL10n->get('SYS_VERSION').':</dt><dd><span class="';
                if(version_compare($gDb->getVersion(), $gDb->getMinVersion()) == -1)
                {
                    echo 'systeminfoBad">'.$gDb->getVersion().'</span> &rarr; '.$gL10n->get('SYS_DATABASE_VERSION_REQUIRED', $gDb->getMinVersion());
                }
                else
                {
                    echo 'systeminfoGood">'.$gDb->getVersion().'</span>';
                }
                echo'
                </dd>
            </dl>
        </li>';
                
        //SafeMode
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_SAFE_MODE').':</dt><dd>';
                if(ini_get('safe_mode') == 1)
                {
                    echo '<span class="systeminfoBad">'.$gL10n->get('SYS_ON').'</span> &rarr; '.$gL10n->get('SYS_SAFE_MODE_PROBLEM');
                }
                else
                {
                    echo '<span class="systeminfoGood">'.$gL10n->get('SYS_OFF').'</span>';
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Maximal Größe eines Posts
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_POST_MAX_SIZE').':</dt><dd>';
                if(ini_get('post_max_size')!='')
                {
                    echo ini_get('post_max_size');
                }
                else
                {
                    echo $gL10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Arbeitsspeicher
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_MEMORY_LIMIT').':</dt><dd>';
                if(ini_get('memory_limit')!='')
                {
                    echo ini_get('memory_limit');
                }
                else
                {
                    echo $gL10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Dateiuploads
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_FILE_UPLOADS').':</dt><dd>';
                if(ini_get('file_uploads') == 1)
                {
                    echo $gL10n->get('SYS_ON');
                }
                else
                {
                    echo $gL10n->get('SYS_OFF');
                }
                echo '</dd>
            </dl>
        </li>';
                
        //Max. Upload-Größe
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_UPLOAD_MAX_FILESIZE').':</dt><dd>';
                if(ini_get('upload_max_filesize')!='')
                {
                    echo ini_get('upload_max_filesize');
                }
                else
                {
                    echo $gL10n->get('SYS_NOT_SET');
                }
                echo '</dd>
            </dl>
        </li>';     
                
        // Maximal bearbeitbare Bildgröße
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE').':</dt><dd>'.round((admFuncProcessableImageSize()/1000000), 2).' '.$gL10n->get('SYS_MEGA_PIXEL').'</dd>
            </dl>
        </li>';
                
        // Link zu php Info
        echo'
        <li>
            <dl>
                <dt>'.$gL10n->get('SYS_PHP_INFO').':</dt><dd><a href="systeminfo.php?mode=2" target="_blank">phpinfo()</a></dd>
                
            </dl>
        </li>';
        
        //Debugmodus       
        if(isset($gDebug))
        {
            echo'
            <li>
                <dl>';           
                    echo' <dt>'.$gL10n->get('SYS_DEBUG_MODUS').':</dt><dd>';
                    if($gDebug == 1)
                    {
                        echo '<span class="systeminfoBad">'.$gL10n->get('SYS_ON').'</span>';
                    }
                    else
                    {
                        echo '<span class="systeminfoGood">'.$gL10n->get('SYS_OFF').'</span>';
                    }
                    echo'</dd>                
                </dl>
            </li>';
        }  
    echo'</ul>';

} 
/************PHP Info*******************/
if($getMode == 2)
{
    phpinfo();
}
?>