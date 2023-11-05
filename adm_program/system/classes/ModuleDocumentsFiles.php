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
class ModuleDocumentsFiles extends HtmlPage
{
    public function getVisibleFolderStructure ()
    {
        // Get all files of the current folder
        $sqlFiles = 'SELECT *
                       FROM '.TBL_FILES.'
                      INNER JOIN '.TBL_FOLDERS.' ON fol_id = fil_fol_id
                      WHERE fil_fol_id = ? -- $this->getValue(\'fol_id\')
                   ORDER BY fil_name';
        $filesStatement = $this->db->queryPrepared($sqlFiles, array((int) $this->getValue('fol_id')));

        // jetzt noch die Dateien ins Array packen:
        while ($rowFiles = $filesStatement->fetch()) {

    }
}
