<?php
/**
 ***********************************************************************************************
 * Latest documents & files
 *
 * This plugin lists the latest documents and files uploaded by users
 *
 * Compatible with Admidio version 4.1
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

require_once($rootPath . '/adm_program/system/common.php');

// only include config file if it exists
if (is_file(__DIR__ . '/config.php')) {
    require_once(__DIR__ . '/config.php');
}

// set default values if there no value has been stored in the config.php
if (!isset($plgCountFiles) || !is_numeric($plgCountFiles)) {
    $plgCountFiles = 5;
}

if (!isset($plgMaxCharsFilename) || !is_numeric($plgMaxCharsFilename)) {
    $plgMaxCharsFilename = 0;
}

if (!isset($plg_show_upload_timestamp)) {
    $plg_show_upload_timestamp = true;
}

if (!isset($plg_show_headline) || !is_numeric($plg_show_headline)) {
    $plg_show_headline = 1;
}

// check if the module is enabled
if (Component::isVisible('DOCUMENTS-FILES')) {
    $countVisibleDownloads = 0;
    $sqlCondition          = '';

    echo '<div id="plugin-'. $pluginFolder. '" class="admidio-plugin-content">';
    if ($plg_show_headline) {
        echo '<h3>'.$gL10n->get('PLG_LATEST_FILES_HEADLINE').'</h3>';
    }

    if (!$gValidLogin) {
        $sqlCondition = ' AND fol_public = true ';
    }

    $rootFolder = new TableFolder($gDb);
    $rootFolder->readDataByColumns(array('fol_org_id' => $gCurrentOrgId,
                                         'fol_fol_id_parent' => 'NULL',
                                         'fol_type' => 'DOCUMENTS'));
    $downloadFolder = $rootFolder->getValue('fol_path') . '/' . $rootFolder->getValue('fol_name');

    // read all downloads from database and then check the rights for each download
    $sql = 'SELECT fil_timestamp, fil_name, fil_usr_id, fol_name, fol_path, fil_id, fil_fol_id, fil_uuid
              FROM '.TBL_FILES.'
        INNER JOIN '.TBL_FOLDERS.'
                ON fol_id = fil_fol_id
             WHERE fol_org_id = ? -- $gCurrentOrgId
                   '.$sqlCondition.'
          ORDER BY fil_timestamp DESC';

    $filesStatement = $gDb->queryPrepared($sql, array($gCurrentOrgId));

    if ($filesStatement->rowCount() > 0) {
        echo '<ul class="list-group list-group-flush">';

        while ($rowFile = $filesStatement->fetch()) {
            $errorCode = '';

            try {
                // get recordset of current file from database
                $file = new TableFile($gDb);
                $file->getFileForDownload($rowFile['fil_uuid']);
            } catch (AdmException $e) {
                $errorCode = $e->getMessage();

                if ($errorCode !== 'SYS_FOLDER_NO_RIGHTS') {
                    $e->showText();
                    // => EXIT
                }
            }

            // only show download if user has rights to view folder
            if ($errorCode !== 'SYS_FOLDER_NO_RIGHTS') {
                // get filename without extension and extension separatly
                $fileName      = pathinfo($rowFile['fil_name'], PATHINFO_FILENAME);
                $fullFolderFileName = $rowFile['fol_path']. '/'. $rowFile['fol_name']. '/'.$rowFile['fil_name'];
                $tooltip            = str_replace($downloadFolder, $gL10n->get('SYS_DOCUMENTS_FILES'), $fullFolderFileName);
                ++$countVisibleDownloads;

                // if max chars are set then limit characters of shown filename
                if ($plgMaxCharsFilename > 0 && strlen($fileName) > $plgMaxCharsFilename) {
                    $fileName = substr($fileName, 0, $plgMaxCharsFilename). '...';
                }

                // if set in config file then show timestamp of file upload
                if ($plg_show_upload_timestamp) {
                    // Vorname und Nachname abfragen (Upload der Datei)
                    $user = new User($gDb, $gProfileFields, $rowFile['fil_usr_id']);

                    $tooltip .= '<br />'. $gL10n->get('PLG_LATEST_FILES_UPLOAD_FROM_AT', array($user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'), $file->getValue('fil_timestamp')));
                }

                echo '<li class="list-group-item">
                    <a class="btn admidio-icon-link" data-toggle="tooltip" data-html="true" title="'. $tooltip. '" href="'. SecurityUtils::encodeUrl(ADMIDIO_URL. FOLDER_MODULES. '/documents-files/get_file.php', array('file_uuid' => $rowFile['fil_uuid'])). '">'.
                        '<i class="fas ' . $file->getFontAwesomeIcon() . '"></i>' . $fileName . '.' . $file->getFileExtension() . '</a>
                </li>';

                if ($countVisibleDownloads === $plgCountFiles) {
                    break;
                }
            }
        }

        if ($countVisibleDownloads > 0) {
            echo '<li class="list-group-item">
                <a class="btn admidio-icon-link" href="'.ADMIDIO_URL.FOLDER_MODULES.'/documents-files/documents_files.php"><i class="fas fa-list"></i>' . $gL10n->get('PLG_LATEST_FILES_MORE_DOWNLOADS').'</a>
            </li>';
        }
        echo '</ul>';
    }

    if ($countVisibleDownloads === 0) {
        if ($gValidLogin) {
            echo $gL10n->get('PLG_LATEST_FILES_NO_DOWNLOADS_AVAILABLE');
        } else {
            echo $gL10n->get('SYS_FOLDER_NO_FILES_VISITOR');
        }
    }
    echo '</div>';
}
