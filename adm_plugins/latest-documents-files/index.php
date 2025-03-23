<?php

use Admidio\Components\Entity\Component;
use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Users\Entity\User;

/**
 ***********************************************************************************************
 * Latest documents & files
 *
 * This plugin lists the latest documents and files uploaded by users
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
try {
    $rootPath = dirname(__DIR__, 2);
    $pluginFolder = basename(__DIR__);

    require_once($rootPath . '/adm_program/system/common.php');

    // only include config file if it exists
    if (is_file(__DIR__ . '/config.php')) {
        require_once(__DIR__ . '/config.php');
    }

    $latestDocumentsFilesPlugin = new Overview($pluginFolder);

    // set default values if there has benn no value stored in the config.php
    if (!isset($plgCountFiles) || !is_numeric($plgCountFiles)) {
        $plgCountFiles = 5;
    }

    if (!isset($plgMaxCharsFilename) || !is_numeric($plgMaxCharsFilename)) {
        $plgMaxCharsFilename = 0;
    }

    if (!isset($plg_show_upload_timestamp)) {
        $plg_show_upload_timestamp = true;
    }

    $countVisibleDownloads = 0;
    $sqlCondition = '';

    // check if the module is enabled
    if (Component::isVisible('DOCUMENTS-FILES')) {
        if (!$gValidLogin) {
            $sqlCondition = ' AND fol_public = true ';
        }

        $rootFolder = new Folder($gDb);
        $rootFolder->readDataByColumns(array('fol_org_id' => $gCurrentOrgId,
            'fol_fol_id_parent' => 'NULL',
            'fol_type' => 'DOCUMENTS'));
        $downloadFolder = $rootFolder->getValue('fol_path') . '/' . $rootFolder->getValue('fol_name');

        // read all downloads from database and then check the rights for each download
        $sql = 'SELECT fil_timestamp, fil_name, fil_usr_id, fol_name, fol_path, fil_id, fil_fol_id, fil_uuid
              FROM ' . TBL_FILES . '
        INNER JOIN ' . TBL_FOLDERS . '
                ON fol_id = fil_fol_id
             WHERE fol_org_id = ? -- $gCurrentOrgId
                   ' . $sqlCondition . '
          ORDER BY fil_timestamp DESC';

        $filesStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

        if ($filesStatement->rowCount() > 0) {
            $documentsFilesArray = array();

            while ($rowFile = $filesStatement->fetch()) {
                try {
                    // get recordset of current file from database
                    $file = new File($gDb);
                    $file->getFileForDownload($rowFile['fil_uuid']);

                    // get filename without extension and extension separately
                    $fileName = pathinfo($rowFile['fil_name'], PATHINFO_FILENAME);
                    $fullFolderFileName = $rowFile['fol_path'] . '/' . $rowFile['fol_name'] . '/' . $rowFile['fil_name'];
                    $tooltip = str_replace($downloadFolder, $gL10n->get('SYS_DOCUMENTS_FILES'), $fullFolderFileName);
                    ++$countVisibleDownloads;

                    // if max chars are set then limit characters of shown filename
                    if ($plgMaxCharsFilename > 0 && strlen($fileName) > $plgMaxCharsFilename) {
                        $fileName = substr($fileName, 0, $plgMaxCharsFilename) . '...';
                    }

                    // if set in config file then show timestamp of file upload
                    if ($plg_show_upload_timestamp) {
                        // Vorname und Nachname abfragen (Upload der Datei)
                        $user = new User($gDb, $gProfileFields, $rowFile['fil_usr_id']);

                        $tooltip .= '<br />' . $gL10n->get('PLG_LATEST_FILES_UPLOAD_FROM_AT', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $file->getValue('fil_timestamp')));
                    }

                    $documentsFilesArray[] = array(
                        'uuid' => $rowFile['fil_uuid'],
                        'icon' => $file->getIcon(),
                        'fileName' => $fileName,
                        'fileExtension' => $file->getFileExtension(),
                        'tooltip' => $tooltip
                    );

                    if ($countVisibleDownloads === $plgCountFiles) {
                        break;
                    }
                } catch (Exception $e) {
                    // do nothing and go to next file
                }
            }
            $latestDocumentsFilesPlugin->assignTemplateVariable('documentsFiles', $documentsFilesArray);
        }
    }

    if ($countVisibleDownloads === 0) {
        if ($gValidLogin) {
            $latestDocumentsFilesPlugin->assignTemplateVariable('message',$gL10n->get('PLG_LATEST_FILES_NO_DOWNLOADS_AVAILABLE'));
        } else {
            $latestDocumentsFilesPlugin->assignTemplateVariable('message',$gL10n->get('SYS_FOLDER_NO_FILES_VISITOR'));
        }
    }

    if (isset($page)) {
        echo $latestDocumentsFilesPlugin->html('plugin.latest-documents-files.tpl');
    } else {
        $latestDocumentsFilesPlugin->showHtmlPage('plugin.latest-documents-files.tpl');
    }
} catch (Throwable $e) {
    echo $e->getMessage();
}
