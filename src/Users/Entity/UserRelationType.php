<?php

namespace Admidio\Users\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Changelog\Entity\LogChanges;
use Admidio\Infrastructure\Language;

/**
 * @brief Class manages access to database table adm_user_relation_types
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class UserRelationType extends Entity
{
    public const USER_RELATION_TYPE_UNIDIRECTIONAL = 'unidirectional';
    public const USER_RELATION_TYPE_SYMMETRICAL = 'symmetrical';
    public const USER_RELATION_TYPE_ASYMMETRICAL = 'asymmetrical';

    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the ID is set, then the specific message will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $urtId The recordset of the relation type with this id will be loaded. If id isn't set, then an empty object of the table is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $urtId = 0)
    {
        parent::__construct($database, TBL_USER_RELATION_TYPES, 'urt', $urtId);
    }

    /**
     * Returns the inverse relation type.
     * @return null|self Returns the inverse relation type
     * @throws Exception
     */
    public function getInverse(): ?UserRelationType
    {
        if (empty($this->getValue('urt_id_inverse'))) {
            return null;
        }
        $inverse = new self($this->db, $this->getValue('urt_id_inverse'));

        if ($inverse->isNewRecord()) {
            return null;
        }

        return $inverse;
    }

    /**
     * Get the string of the current relationship type.
     * @return string The relationship type could be **asymmetrical**, **symmetrical** or **unidirectional**
     * @throws Exception
     */
    public function getRelationTypeString(): string
    {
        if (!$this->isNewRecord()) {
            if (empty($this->getValue('urt_id_inverse'))) {
                return self::USER_RELATION_TYPE_UNIDIRECTIONAL;
            } elseif ((int)$this->getValue('urt_id_inverse') === (int)$this->getValue('urt_id')) {
                return self::USER_RELATION_TYPE_SYMMETRICAL;
            }
        }

        return self::USER_RELATION_TYPE_ASYMMETRICAL;
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with **setValue** then the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format For date or timestamp columns, the format should be the date/time format e.g. **d.m.Y = '02.04.2011'**.
     *                           For text columns, the format can be **database** that would return the original database value without any transformations
     * @return mixed Returns the value of the database column.
     *                         If the value was manipulated before with **setValue** then the manipulated value is returned.
     * @throws Exception
     */
    public function getValue(string $columnName, string $format = ''): mixed
    {
        global $gL10n;

        $value = parent::getValue($columnName, $format);

        // if a text is a translation-id, then translate it
        if (in_array($columnName, array('urt_name', 'urt_name_male', 'urt_name_female')) &&
            $format !== 'database' && Language::isTranslationStringId($value)) {
            $value = $gL10n->get($value);
        }

        return $value;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isAsymmetrical(): bool
    {
        return $this->getRelationTypeString() === self::USER_RELATION_TYPE_ASYMMETRICAL;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isSymmetrical(): bool
    {
        return $this->getRelationTypeString() === self::USER_RELATION_TYPE_SYMMETRICAL;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isUnidirectional(): bool
    {
        return $this->getRelationTypeString() === self::USER_RELATION_TYPE_UNIDIRECTIONAL;
    }

    /**
     * Adjust the changelog entry for this db record.
     * For user relation types, we want to show the inverse relation as related.
     * @param LogChanges $logEntry The log entry to adjust
     * @return void
     * @throws Exception
     */
    protected function adjustLogEntry(LogChanges $logEntry): void
    {
        $inverse = $this->getInverse();
        if ($inverse) {
            $logEntry->setLogRelated($inverse->getValue('urt_uuid'), $inverse->getValue('urt_name'));
        }
    }
}
