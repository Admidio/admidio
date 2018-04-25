<?php
/**
 ***********************************************************************************************
 * sidebar_downloads
 *
 * Plugin das die aktuellsten X Downloads auflistet
 *
 * Compatible with Admidio version 3.3
 *
 * @copyright 2004-2018 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');
require_once($rootPath . '/adm_program/system/file_extension_icons.php');
require_once(__DIR__ . '/config.php');

// pruefen, ob alle Einstellungen in config.php gesetzt wurden
// falls nicht, hier noch mal die Default-Werte setzen
if(!isset($plg_downloads_count) || !is_numeric($plg_downloads_count))
{
    $plg_downloads_count = 10;
}

if(!isset($plgMaxCharsFilename) || !is_numeric($plgMaxCharsFilename))
{
    $plgMaxCharsFilename = 0;
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

// check if the module is enabled
if ($gSettingsManager->getBool('enable_download_module'))
{
    $countVisibleDownloads = 0;
    $sqlCondition          = '';

    echo '<div id="plugin_'. $pluginFolder. '" class="admidio-plugin-content">';
    if($plg_show_headline)
    {
        echo '<h3>'.$gL10n->get('PLG_DOWNLOADS_HEADLINE').'</h3>';
    }

    if(!$gValidLogin)
    {
        $sqlCondition = ' AND fol_public = 1 ';
    }

    // read all downloads from database and then check the rights for each download
    $sql = 'SELECT fil_timestamp, fil_name, fil_usr_id, fol_name, fol_path, fil_id, fil_fol_id
              FROM '.TBL_FILES.'
        INNER JOIN '.TBL_FOLDERS.'
                ON fol_id = fil_fol_id
             WHERE fol_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                   '.$sqlCondition.'
          ORDER BY fil_timestamp DESC';

    $filesStatement = $gDb->queryPrepared($sql, array((int) $gCurrentOrganization->getValue('org_id')));

    if($filesStatement->rowCount() > 0)
    {
        while($rowFile = $filesStatement->fetch())
        {
            $errorCode = '';

            echo '<div class="btn-group-vertical" role="group">';

            try
            {
                // get recordset of current file from database
                $file = new TableFile($gDb);
                $file->getFileForDownload($rowFile['fil_id']);
            }
            catch(AdmException $e)
            {
                $errorCode = $e->getMessage();

                if($errorCode !== 'DOW_FOLDER_NO_RIGHTS')
                {
                    $e->showText();
                    // => EXIT
                }
            }

            // only show download if user has rights to view folder
            if($errorCode !== 'DOW_FOLDER_NO_RIGHTS')
            {
                // get filename without extension and extension separatly
                $fileName      = pathinfo($rowFile['fil_name'], PATHINFO_FILENAME);
                $fileExtension = strtolower(pathinfo($rowFile['fil_name'], PATHINFO_EXTENSION));
                $fullFolderFileName = $rowFile['fol_path']. '/'. $rowFile['fol_name']. '/'.$rowFile['fil_name'];
                $tooltip            = $fullFolderFileName;
                ++$countVisibleDownloads;

                // if max chars are set then limit characters of shown filename
                if($plgMaxCharsFilename > 0 && strlen($fileName) > $plgMaxCharsFilename)
                {
                    $fileName = substr($fileName, 0, $plgMaxCharsFilename). '...';
                }

                // get icon of file extension
                $iconFile = 'page_white_question.png';
                if(array_key_exists($fileExtension, $iconFileExtension))
                {
                    $iconFile = $iconFileExtension[$fileExtension];
                }

                // if set in config file then show timestamp of file upload
                if($plg_show_upload_timestamp)
                {
                    // Vorname und Nachname abfragen (Upload der Datei)
                    $user = new User($gDb, $gProfileFields, $rowFile['fil_usr_id']);

                    $tooltip .= '<br />'. $gL10n->get('PLG_DOWNLOADS_UPLOAD_FROM_AT', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $rowFile['fil_timestamp']));
                }

                echo '
                <a class="btn admidio-icon-link '.$plg_link_class_downl.'" data-toggle="tooltip" data-html="true" title="'. $tooltip. '" href="'. safeUrl(ADMIDIO_URL. FOLDER_MODULES. '/downloads/get_file.php', array('file_id' => $rowFile['fil_id'])). '"><img
                    src="'. THEME_URL. '/icons/'.$iconFile.'" alt="'. $fullFolderFileName. '/" />'.$fileName.'.'.$fileExtension. '</a>';

                if($countVisibleDownloads === $plg_downloads_count)
                {
                    break;
                }
            }

            echo '</div>';
        }
    }

    if($countVisibleDownloads === 0)
    {
        echo $gL10n->get('PLG_DOWNLOADS_NO_DOWNLOADS_AVAILABLE');
    }
    else
    {
        echo '<a class="btn '.$plg_link_class_downl.'" href="'.ADMIDIO_URL.FOLDER_MODULES.'/downloads/downloads.php">'.$gL10n->get('PLG_DOWNLOADS_MORE_DOWNLOADS').'</a>';
    }
    echo '</div>';
}
