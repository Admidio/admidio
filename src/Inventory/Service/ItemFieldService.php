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
    public const MOVE_UP = 'UP';
    public const MOVE_DOWN = 'DOWN';

    protected ItemField $itemFieldRessource;
    protected Database $db;
    protected string $ID;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $itemFieldID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $itemFieldID = '')
    {
        $this->db = $database;
        $this->ID = $itemFieldID;
        $this->itemFieldRessource = new ItemField($database, $itemFieldID);
    }

    /**
     * Delete the current item field form database.
     * @throws Exception
     */
    public function delete(): bool
    {
        return $this->itemFieldRessource->delete();
    }

   /**
     * Profile field will change the sequence one step up or one step down.
     * @param string $mode mode if the item field move up or down, values are ProfileField::MOVE_UP, ProfileField::MOVE_DOWN
     * @return bool Return true if the sequence of the category could be changed, otherwise false.
     * @throws Exception
     */
    public function moveSequence(string $mode): bool
    {
        global $gCurrentOrgId;

        $infSequence = (int)$this->itemFieldRessource->getValue('inf_sequence');
        $sql = 'UPDATE ' . TBL_INVENTORY_FIELDS . '
                   SET inf_sequence = ? -- $usfSequence
                 WHERE inf_org_id   = ? -- $OrgId
                   AND inf_sequence = ? -- $usfSequence -/+ 1';

        // item field will get one number lower and therefore move a position up in the list
        if ($mode === self::MOVE_UP) {
            $newSequence = $infSequence - 1;
        } // item field will get one number higher and therefore move a position down in the list
        elseif ($mode === self::MOVE_DOWN) {
            $newSequence = $infSequence + 1;
        }

        // update the existing entry with the sequence of the field that should get the new sequence
        $this->db->queryPrepared($sql, array($infSequence, $gCurrentOrgId, $newSequence));

        $this->itemFieldRessource->setValue('inf_sequence', $newSequence);
        return $this->itemFieldRessource->save();
    }

    /**
     * Iem field will change the complete sequence.
     * @param array $sequence the new sequence of item fields (field IDs)
     * @return bool Return true if the sequence of the category could be changed, otherwise false.
     * @throws Exception
     */
    public function setSequence(array $sequence): bool
    {
        global $gCurrentOrgId;
        //$usfCatId = $this->getValue('usf_cat_id');
        $infId = $this->itemFieldRessource->getValue('inf_id');

        $sql = 'UPDATE ' . TBL_INVENTORY_FIELDS . '
                   SET inf_sequence = ? -- new order sequence
                 WHERE inf_id     = ? -- field ID;
                   AND inf_org_id   = ? -- $OrgId;
            ';

        $newSequence = -1;
        foreach ($sequence as $pos => $id) {
            if ($id == $infId) {
                // Store position for later update
                $newSequence = $pos;
            } else {
                $this->db->queryPrepared($sql, array($pos, $id, $gCurrentOrgId));
            }
        }

        if ($newSequence >= 0) {
            $this->itemFieldRessource->setValue('inf_sequence', $newSequence);
        }

        return $this->itemFieldRessource->save();
    }

    /**
     * Save data from the item field into database.
     * @throws Exception
     */
    public function save(): bool
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

        return $this->itemFieldRessource->save();
    }
}
