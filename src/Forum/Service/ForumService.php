<?php
namespace Admidio\Forum\Service;

use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;

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
class ForumService
{
    protected Database $db;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Save data from the topic form into the database.
     * @param string $topicUUID UUID if the topic that should be stored within this class
     * @throws Exception
     */
    public function saveTopic(string $topicUUID = '')
    {
        global $gCurrentSession, $gDb;

        // check form field input and sanitized it from malicious content
        $topicEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $topicEditForm->validate($_POST);

        $topic = new Topic($gDb);
        if ($topicUUID !== '') {
            $topic->readDataByUuid($topicUUID);
        }

        // write form values in category object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'fot_') || str_starts_with($key, 'fop_')) {
                $topic->setValue($key, $value);
            }
        }

        $topic->save();
    }
}
