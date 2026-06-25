<?php
namespace Admidio\Events\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

/**
 * Creates an event recurrence object from the database table adm_event_recurrences.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class EventRecurrence extends Entity
{
    /**
     * Constructor that will create an object of a recordset of the table adm_event_recurrences.
     * If the id is set than the specific recurrence will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param int $recurrenceId The recordset of the recurrence with this id will be loaded.
     * @throws Exception
     */
    public function __construct(Database $database, int $recurrenceId = 0)
    {
        parent::__construct($database, TBL_EVENT_RECURRENCES, 'rer', $recurrenceId);
    }

    /**
     * Read a recurrence by the assigned master event id.
     * @param int $masterEventId ID of the master event.
     * @return bool Returns **true** if one record is found
     * @throws Exception
     */
    public function readDataByMasterEventId(int $masterEventId): bool
    {
        if ($masterEventId <= 0) {
            $this->clear();
            return false;
        }

        return $this->readDataByColumns(array('rer_dat_id_master' => $masterEventId));
    }
}
