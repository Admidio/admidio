<?php

namespace LatestDocumentsFiles\classes;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Plugins\Overview;
use Admidio\Infrastructure\Plugins\PluginAbstract;
use Admidio\Users\Entity\User;

use InvalidArgumentException;
use Exception;
use Throwable;

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
class LatestDocumentsFiles extends PluginAbstract
{
    /**
     * Get the documents & files data
     * @return array Returns the documents & files data
     */
    private static function getDocumentsFilesData() : array
    {
        global $gValidLogin, $gCurrentOrgId, $gDb, $gL10n, $gProfileFields;

        $config = self::getPluginConfigValues();
        $documentsFilesArray = array();
        $countVisibleDownloads = 0;
        $sqlCondition = '';

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
                    if ($config['latest_documents_files_max_chars_filename'] > 0 && strlen($fileName) > $config['latest_documents_files_max_chars_filename']) {
                        $fileName = substr($fileName, 0, $config['latest_documents_files_max_chars_filename']) . '...';
                    }

                    // if set in config file then show timestamp of file upload
                    if ($config['latest_documents_files_show_upload_timestamp']) {
                        // Vorname und Nachname abfragen (Upload der Datei)
                        $user = new User($gDb, $gProfileFields, $rowFile['fil_usr_id']);

                        $tooltip .= '<br />' . $gL10n->get('PLG_LATEST_DOCUMENTS_FILES_UPLOAD_FROM_AT', array($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'), $file->getValue('fil_timestamp')));
                    }

                    $documentsFilesArray[] = array(
                        'uuid' => $rowFile['fil_uuid'],
                        'icon' => $file->getIcon(),
                        'fileName' => $fileName,
                        'fileExtension' => $file->getFileExtension(),
                        'tooltip' => $tooltip
                    );

                    if ($countVisibleDownloads === $config['latest_documents_files_files_count']) {
                        break;
                    }
                } catch (Exception $e) {
                    // do nothing and go to next file
                }
            }
        }

        return $documentsFilesArray;
    }

    /**
     * @param PagePresenter $page
     * @throws InvalidArgumentException
     * @throws Exception
     * @return bool
     */
    public static function doRender($page = null) : bool
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        // show the latest documents & files list
        try {
            $rootPath = dirname(__DIR__, 3);
            $pluginFolder = basename(self::$pluginPath);

            require_once($rootPath . '/system/common.php');

            $latestDocumentsFilesPlugin = new Overview($pluginFolder);

            // check if the plugin is installed
            if (!self::isInstalled()) {
                throw new InvalidArgumentException($gL10n->get('SYS_PLUGIN_NOT_INSTALLED'));
            }

            if ($gSettingsManager->getInt('documents_files_module_enabled') > 0) {
                if (($gSettingsManager->getInt('documents_files_module_enabled') === 1 || ($gSettingsManager->getInt('documents_files_module_enabled') === 2 && $gValidLogin)) &&
                    ($gSettingsManager->getInt('latest_documents_files_plugin_enabled') === 1 || ($gSettingsManager->getInt('latest_documents_files_plugin_enabled') === 2 && $gValidLogin))) {
                    $documentsFilesArray = self::getDocumentsFilesData();
                    if (!empty($documentsFilesArray)) {
                        $latestDocumentsFilesPlugin->assignTemplateVariable('documentsFiles', $documentsFilesArray);
                    } else {
                        if ($gValidLogin) {
                            $latestDocumentsFilesPlugin->assignTemplateVariable('message', $gL10n->get('PLG_LATEST_DOCUMENTS_FILES_NO_DOWNLOADS_AVAILABLE'));
                        } else {
                            $latestDocumentsFilesPlugin->assignTemplateVariable('message', $gL10n->get('SYS_FOLDER_NO_FILES_VISITOR'));
                        }
                    }
                } else {
                    $latestDocumentsFilesPlugin->assignTemplateVariable('message',$gL10n->get('SYS_FOLDER_NO_FILES_VISITOR'));
                }
            } else {
                $latestDocumentsFilesPlugin->assignTemplateVariable('message', $gL10n->get('SYS_MODULE_DISABLED'));
            }
            
            if (isset($page)) {
                echo $latestDocumentsFilesPlugin->html('plugin.latest-documents-files.tpl');
            } else {
                $latestDocumentsFilesPlugin->showHtmlPage('plugin.latest-documents-files.tpl');
            }
        } catch (Throwable $e) {
            echo $e->getMessage();
        }

        return true;
    }
}