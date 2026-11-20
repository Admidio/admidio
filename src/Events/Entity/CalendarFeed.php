<?php
namespace Admidio\Events\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

/**
 * Creates a calendar feed object from the database table adm_calendar_feeds.
 *
 * A calendar feed belongs to one user and one organization and stores only the
 * SHA-256 hash of the token that grants access to the protected iCal feed.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class CalendarFeed extends Entity
{
    /**
     * Constructor that creates an object of a recordset of the table adm_calendar_feeds.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $feedId The recordset of the feed with this id will be loaded. If no id is set, an empty object is created.
     * @throws Exception
     */
    public function __construct(Database $database, int $feedId = 0)
    {
        parent::__construct($database, TBL_CALENDAR_FEEDS, 'cfd', $feedId);
    }

    /**
     * Return whether this feed may currently be used.
     * @return bool
     */
    public function isActive(): bool
    {
        if (!filter_var($this->getValue('cfd_active'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $expires = (string)$this->getValue('cfd_timestamp_expires', 'database');
        if ($expires === '') {
            return true;
        }

        $expiresTimestamp = strtotime($expires);

        return $expiresTimestamp === false || $expiresTimestamp > time();
    }
}
