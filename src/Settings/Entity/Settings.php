<?php
namespace Admidio\Settings\Entity;

use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Throwable;

/**
 * @brief Class manages access to database table adm_preferences
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class Settings extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_preferences.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @throws Exception
     */
    public function __construct(Database $database)
    {
        try {
            parent::__construct($database, TBL_SETTINGS, 'prf');
        } catch (Throwable ) {
            parent::__construct($database, TBL_PREFERENCES, 'prf');
        }
    }
    /**
     * Logs creation of the DB record -> For preferences, no need to log anything as
     * the actual value change from NULL to something will be logged as a modification
     * immediately after creation, anyway.
     *
     * @return true Returns **true** if no error occurred
     */
    public function logCreation(): bool { return true; }
    /**
     * Retrieve the list of database fields that are ignored for the changelog.
     * @return array Returns the list of database columns to be ignored for logging.
     */
    public function getIgnoredLogColumns(): array
    {
        return array_merge(parent::getIgnoredLogColumns(), ['prf_name', 'prf_org_id']);
    }
}
