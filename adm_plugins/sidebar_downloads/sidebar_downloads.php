<?php
/******************************************************************************
* sidebar_downloads
*
* Version 1.4.0
*
* Plugin das die aktuellsten X Downloads auflistet
*
*
* Compatible with Admidio version 3.0
*
* Copyright : (c) 2004 - 2008 The Admidio Team
* Homepage : http://www.admidio.org
* License : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
*
*****************************************************************************/

// create path to plugin
$plugin_folder_pos = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_file_pos = strpos(__FILE__, 'sidebar_downloads.php');
$plugin_folder = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);

if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}

require_once(PLUGIN_PATH. '/../adm_program/system/common.php');
require(PLUGIN_PATH. '/../adm_program/system/file_extension_icons.php');
require_once(PLUGIN_PATH. '/'.$plugin_folder.'/config.php');

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(isset($plg_downloads_count) == false || is_numeric($plg_downloads_count) == false)
{
    $plg_downloads_count = 10;
}

if(isset($plg_link_class_downl))
{
    $plg_link_class_downl = strip_tags($plg_link_class_downl);
}
else
{
    $plg_link_class_downl = '';
}

if(!isset($plg_show_upload_timestamp))
{
    $plg_show_upload_timestamp = true;
}

// Sprachdatei des Plugins einbinden
$gL10n->addLanguagePath(PLUGIN_PATH. '/'.$plugin_folder.'/languages');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_download_module'] == 1)
{
    echo '<div id="plugin_'. $plugin_folder. '" class="admidio-plugin-content">';
    if($plg_show_headline==1)
    {
        echo '<h3>'.$gL10n->get('PLG_DOWNLOADS_HEADLINE').'</h3>';
    }

    // erst pruefen, ob der User auch die entsprechenden Rechte hat
    // nun alle relevanten Downloads finden

    $sql = 'SELECT fil_timestamp, fil_name, fil_usr_id, fol_name, fol_path, fil_id, fil_fol_id
              FROM '. TBL_FILES. ', '. TBL_FOLDERS. '
             WHERE fil_fol_id = fol_id
             ORDER BY fil_timestamp DESC';

    $filesStatement = $gDb->query($sql);

    if($filesStatement->rowCount() > 0)
    {
        $anzahl = 0;

        while($plg_row = $filesStatement->fetchObject())
        {
            $errorCode     = '';
            $html          = '';
            $timestampHtml = '';

            echo '<div class="btn-group-vertical" role="group">';

            try
            {
                // get recordset of current file from databse
                $file = new TableFile($gDb);
                $file->getFileForDownload($plg_row->fil_id);
            }
            catch(AdmException $e)
            {
                $errorCode = $e->getMessage();

                if($errorCode != 'DOW_FOLDER_NO_RIGHTS')
                {
                    $e->showText();
                }
            }

            // only show download if user has rights to view folder
            if($errorCode != 'DOW_FOLDER_NO_RIGHTS')
            {
                //Ermittlung der Dateiendung
                $fileExtension  = mb_strtolower(substr($plg_row->fil_name, strrpos($plg_row->fil_name, '.')+1), 'UTF-8');

                //Auszugebendes Icon ermitteln
                $iconFile = 'page_white_question.png';
                if(array_key_exists($fileExtension, $icon_file_extension))
                {
                    $iconFile = $icon_file_extension[$fileExtension];
                }

                // if set in config file then show timestamp of file upload
                if($plg_show_upload_timestamp)
                {
                    // Vorname und Nachname abfragen (Upload der Datei)
                    $mein_user = new User($gDb, $gProfileFields, $plg_row->fil_usr_id);

                    $timestampHtml = '<img class="admidio-icon-info" data-html="true" src="'. THEME_PATH. '/icons/info.png" alt="'.$gL10n->get('SYS_FILE').'"
                        title="'. $plg_row->fil_timestamp. ',<br />'. $mein_user->getValue('FIRST_NAME'). ' '. $mein_user->getValue('LAST_NAME'). '" />';
                }

                echo '
                <a class="btn '.$plg_link_class_downl.'" href="'. $g_root_path. '/adm_program/modules/downloads/get_file.php?file_id='. $plg_row->fil_id. '"><img
                    src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'. $plg_row->fol_path. '/'. $plg_row->fol_name. '/"
                    title="'. $plg_row->fol_path. '/'. $plg_row->fol_name. '/" />'.$plg_row->fil_name.$timestampHtml.'</a>';

                $anzahl++;

                if ($anzahl == $plg_downloads_count)
                {
                    break;
                }
            }
        }

        echo '</div>';

         if ($anzahl == 0)
        {
            echo $gL10n->get('PLG_DOWNLOADS_NO_DOWNLOADS_AVAILABLE');
        }
    }
    else
    {
        echo $gL10n->get('PLG_DOWNLOADS_NO_DOWNLOADS_AVAILABLE');
    }
    echo '</div>';
}
