<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class with methods to display the module pages and helpful functions.
 *
 * This class adds some functions that are used in the documents and files module to keep the
 * code easy to read and short
 *
 * **Code example**
 * ```
 * // generate html output with available registrations
 * $page = new ModuleDocumentsFiles('admidio-groups-roles', $headline);
 * $page->createContentRegistrationList();
 * $page->show();
 * ```
 */
class ModuleDocumentsFiles
{
    public function getEditableFolderStructure (): array
    {
        global $gCurrentOrgId, $gDb, $gL10n;

        // read main documents folder
        $sqlFiles = 'SELECT fol_uuid, fol_id
                       FROM '.TBL_FOLDERS.'
                      WHERE fol_fol_id_parent IS NULL
                        AND fol_org_id = ? -- $gCurrentOrgId
                        AND fol_type   = \'DOCUMENTS\' ';
        $filesStatement = $gDb->queryPrepared($sqlFiles, array($gCurrentOrgId));

        $row = $filesStatement->fetch();

        $arrAllUpdatableFolders = array($row['fol_uuid'] => $gL10n->get('SYS_DOCUMENTS_FILES'));

        return $this->readFoldersWithUpdateRights($row['fol_id'], $arrAllUpdatableFolders);
    }

    private function readFoldersWithUpdateRights(string $folderID, array $arrAllUpdatableFolders, string $indent = ''): array
    {
        global $gDb;

        // read all folders
        $sqlFiles = 'SELECT fol_id
                       FROM '.TBL_FOLDERS.'
                      WHERE  fol_fol_id_parent = ? -- $folderID ';
        $filesStatement = $gDb->queryPrepared($sqlFiles, array($folderID));

        while($row = $filesStatement->fetch()) {
            $folder = new TableFolder($gDb, $row['fol_id']);

            if ($folder->hasUploadRight()) {
                $arrAllUpdatableFolders[$folder->getValue('fol_uuid')] = $indent.'- '.$folder->getValue('fol_name');

                $arrAllUpdatableFolders = $this->readFoldersWithUpdateRights($row['fol_id'], $arrAllUpdatableFolders, $indent.'&nbsp;&nbsp;&nbsp;');
            }
        }
        return $arrAllUpdatableFolders;
    }
}
