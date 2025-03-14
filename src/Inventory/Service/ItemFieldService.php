<?php

namespace Admidio\Inventory\Service;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Inventory\Entity\ItemField;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ItemFieldService
{
    protected ItemField $itemFieldRessource;
    protected Database $db;
    protected string $ID;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $profileFieldUUID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $itemFieldID = '')
    {
        $this->db = $database;
        $this->ID = $itemFieldID;
        $this->itemFieldRessource = new ItemField($database, $itemFieldID);
    }

    /**
     * Delete the current profile field form into the database.
     * @throws Exception
     */
    public function delete(): void
    {
        $this->itemFieldRessource->delete();
    }

    /**
     * Save data from the profile field form into the database.
     * @throws Exception
     */
    public function save(): void
    {
        global $gCurrentSession, $gCurrentOrgId, $gDb;

        // check form field input and sanitized it from malicious content
        $itemFieldsEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $itemFieldsEditForm->validate($_POST);

        if (isset($_POST['inf_name']) && $this->itemFieldRessource->getValue('inf_name') !== $_POST['inf_name']) {
            // See if the field already exists
            $sql = 'SELECT COUNT(*) AS count FROM ' . TBL_INVENTORY_FIELDS . '
            WHERE inf_name = ? -- $_POST[\'inf_name\']
            AND (inf_org_id = ? -- $gCurrentOrgId
                OR inf_org_id IS NULL)
            AND inf_id <> ? -- $getimfId;';
            $pdoStatement = $gDb->queryPrepared($sql, array($_POST['inf_name'], $gCurrentOrgId, $this->ID));

            if ($pdoStatement->fetchColumn() > 0) {
                throw new Exception('ORG_FIELD_EXIST');
            }
        }

        // organization id is always the current organization
        $this->itemFieldRessource->setValue('inf_org_id', $gCurrentOrgId);

        // write form values in user field object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'inf_')) {
                $this->itemFieldRessource->setValue($key, $value);
            }
        }

        $this->itemFieldRessource->save();
    }
}
