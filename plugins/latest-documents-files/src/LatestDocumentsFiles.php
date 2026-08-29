<?php

namespace AdmidioPlugin\LatestDocumentsFiles;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Entity\Folder;
use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPanel;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginWidget;
use Admidio\UI\Presenter\PagePresenter;
use Admidio\Users\Entity\User;
use AdmidioPlugin\LatestDocumentsFiles\Presenter\LatestDocumentsFilesPreferencesPresenter;

use Exception;

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
final class LatestDocumentsFiles
{
    /**
     * The directory of this plugin, which is its only identity.
     */
    public const PLUGIN_ID = 'latest-documents-files';

    /**
     * Where the widget is placed on the overview page as long as nobody moved it.
     */
    public const DEFAULT_SEQUENCE = 5;

    /**
     * Announce the widget and the preferences panel. This is what plugin.php calls.
     * @return void
     */
    public static function register(): void
    {
        $plugin = PluginRegistry::get(self::PLUGIN_ID);
        if ($plugin === null) {
            return;
        }

        /*
         * The position of this plugin is stored under latest_documents_overview_sequence, without
         * the "files" the rest of its preferences carry. The name is kept as it is, because an
         * installation that has it would otherwise lose the position it chose.
         */
        PluginWidget::register($plugin, array(self::class, 'renderWidget'), array(
            'sequence' => self::DEFAULT_SEQUENCE,
            'sequencePreference' => 'latest_documents_overview_sequence'
        ));

        Hooks::addFilter(
            PluginPanel::HOOK,
            static function (array $panels) use ($plugin): array {
                global $gL10n;

                $panels[] = array(
                    'id' => PluginPanel::normalizeId($plugin->id),
                    'title' => $gL10n->get($plugin->name),
                    'icon' => $plugin->icon,
                    'sequence' => self::DEFAULT_SEQUENCE,
                    'create' => array(LatestDocumentsFilesPreferencesPresenter::class, 'createForm')
                );

                return $panels;
            },
            PluginPanel::DEFAULT_SEQUENCE,
            1,
            $plugin->id
        );
    }

    /**
     * Build the widget of the overview page. Whether it is shown at all was decided before this is
     * called, by the preference **latest_documents_files_plugin_enabled**.
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws Exception|\Smarty\Exception
     */
    public static function renderWidget(PagePresenter $page, Plugin $plugin): string
    {
        global $gSettingsManager, $gL10n, $gValidLogin;

        $variables = array('name' => $plugin->id, 'message' => '', 'documentsFiles' => array());
        $module = $gSettingsManager->getInt('documents_files_module_enabled');

        if ($module === 0) {
            $variables['message'] = $gL10n->get('SYS_MODULE_DISABLED');
        } elseif ($module === 1 || ($module === 2 && $gValidLogin)) {
            $documentsFilesArray = self::getDocumentsFilesData($plugin->getSettingValues());
            if (!empty($documentsFilesArray)) {
                $variables['documentsFiles'] = $documentsFilesArray;
            } elseif ($gValidLogin) {
                $variables['message'] = $gL10n->get('PLG_LATEST_DOCUMENTS_FILES_NO_DOWNLOADS_AVAILABLE');
            } else {
                $variables['message'] = $gL10n->get('SYS_FOLDER_NO_FILES_VISITOR');
            }
        } else {
            $variables['message'] = $gL10n->get('SYS_FOLDER_NO_FILES_VISITOR');
        }

        return $plugin->renderTemplate($page, 'plugin.latest-documents-files.tpl', $variables);
    }

    /**
     * Get the documents & files data
     * @param array<string,mixed> $config
     * @return array Returns the documents & files data
     * @throws Exception
     */
    private static function getDocumentsFilesData(array $config) : array
    {
        global $gValidLogin, $gCurrentOrgId, $gDb, $gL10n, $gProfileFields;

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
}
