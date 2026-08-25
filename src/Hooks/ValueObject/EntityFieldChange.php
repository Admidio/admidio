<?php
namespace Admidio\Hooks\ValueObject;

/**
 * One changed column of an Entity, as it is handed to a persistence hook. The values are the raw
 * values of the database column, not the formatted ones that Entity::getValue() returns, because a
 * hook callback that synchronizes or forwards a change needs the value that was actually stored.
 *
 * The kind says how much the change is worth to a consumer:
 *
 * - **business** is ordinary data of the record;
 * - **technical** is bookkeeping that the record keeps about itself, the creator and editor columns
 *   and counters like the number of logins. A consumer that reacts to real changes should ignore
 *   these, which is what EntityChangeSet::getBusinessChanges() does for it;
 * - **redacted** is a column whose value must not leave the record, a password or a key. The change
 *   is reported, the values are replaced by EntityChangeSet::REDACTED_VALUE.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class EntityFieldChange
{
    public const KIND_BUSINESS = 'business';
    public const KIND_TECHNICAL = 'technical';
    public const KIND_REDACTED = 'redacted';

    /**
     * @param string $column Name of the database column, e.g. **usr_login_name**.
     * @param mixed $oldValue The value the database held, **null** for a creation.
     * @param mixed $newValue The value that was written, **null** for a deletion.
     * @param string $type The type of the database column, e.g. **varchar**.
     * @param string $kind One of the KIND_... constants.
     */
    public function __construct(
        public readonly string $column,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
        public readonly string $type,
        public readonly string $kind
    ) {
    }

    /**
     * Whether this is ordinary data of the record and not bookkeeping.
     * @return bool Returns **true** for a business change.
     */
    public function isBusiness(): bool
    {
        return $this->kind === self::KIND_BUSINESS;
    }

    /**
     * Whether the values of this change were withheld.
     * @return bool Returns **true** if the column is redacted.
     */
    public function isRedacted(): bool
    {
        return $this->kind === self::KIND_REDACTED;
    }
}
