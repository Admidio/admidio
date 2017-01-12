<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_relation_types
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableUserRelationType
 * This class manages the set, update and delete in the table adm_user_relation_types
 */
class TableUserRelationType extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the id is set than the specific message will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $urtId    The recordset of the relationtype with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $urtId = 0)
    {
        parent::__construct($database, TBL_USER_RELATION_TYPES, 'urt', $urtId);
    }

    /**
     * @return string
     */
    public function getRelationTypeString()
    {
        if (!$this->isNewRecord())
        {
            $urtIdReverse = $this->getValue('urt_id_inverse');
            if (empty($urtIdReverse))
            {
                return 'unidirectional';
            }
            elseif ((int) $this->getValue('urt_id_inverse') === (int) $this->getValue('urt_id'))
            {
                return 'symmetrical';
            }
        }

        return 'asymmetrical';
    }

    /**
     * @return bool
     */
    public function isUnidirectional()
    {
        return $this->getRelationTypeString() === 'unidirectional';
    }

    /**
     * @return bool
     */
    public function isSymmetrical()
    {
        return $this->getRelationTypeString() === 'symmetrical';
    }

    /**
     * @return bool
     */
    public function isAsymmetrical()
    {
        return $this->getRelationTypeString() === 'asymmetrical';
    }

    /**
     * Returns the inverse relationtype.
     * @return null|\TableUserRelationType Returns the inverse relationtype
     */
    public function getInverse()
    {
        $inverse = new self($this->db, $this->getValue('urt_id_inverse'));

        if ($inverse->isNewRecord())
        {
            return null;
        }

        return $inverse;
    }
}
