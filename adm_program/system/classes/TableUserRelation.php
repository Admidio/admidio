<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_relations
 *
 * @copyright 2004-2023 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * This class manages the set, update and delete in the table adm_user_relations
 */
class TableUserRelation extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the id is set than the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int      $ureId    The recordset of the relation with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $ureId = 0)
    {
        parent::__construct($database, TBL_USER_RELATIONS, 'ure', $ureId);
    }

    /**
     * Returns the inverse relation.
     * @return null|self Returns the inverse relation
     */
    public function getInverse()
    {
        $relationType = new TableUserRelationType($this->db, (int) $this->getValue('ure_urt_id'));
        if ($relationType->getValue('urt_id_inverse') === null) {
            return null;
        }

        $selectColumns = array(
            'ure_urt_id'  => (int) $relationType->getValue('urt_id_inverse'),
            'ure_usr_id1' => (int) $this->getValue('ure_usr_id2'),
            'ure_usr_id2' => (int) $this->getValue('ure_usr_id1')
        );
        $inverse = new self($this->db);
        $inverse->readDataByColumns($selectColumns);

        if ($inverse->isNewRecord()) {
            return null;
        }

        return $inverse;
    }

    /**
     * Deletes the selected record of the table and initializes the class
     * @param bool $deleteInverse
     * @return bool Returns **true** if no error occurred
     */
    public function delete($deleteInverse = true)
    {
        $this->db->startTransaction();

        if ($deleteInverse) {
            $inverse = $this->getInverse();
            if ($inverse) {
                $inverse->delete(false);
            }
        }
        parent::delete();

        return $this->db->endTransaction();
    }
}
