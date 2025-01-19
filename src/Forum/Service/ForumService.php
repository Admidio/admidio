<?php

namespace Admidio\Forum\Service;

use Admidio\Categories\Entity\Category;
use Admidio\Forum\Entity\Post;
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
     * Get an array with all categories from the forum of this organization.
     * @param int $organizationID ID of the organization for which the categories should be loaded. Default is the current organization.
     * @return array<int,array> Array with all categories. Each category is an array with the keys 'cat_id', 'cat_uuid', 'cat_name', 'cat_default'
     * @throws Exception
     */
    public function getCategories(int $organizationID = 0): array
    {
        global $gCurrentOrgId;

        $categories = array();
        if ($organizationID === 0) {
            $organizationID = $gCurrentOrgId;
        }

        $sql = 'SELECT cat_id, cat_uuid, cat_name, cat_default
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_org_id = ? -- $gCurrentOrgId
                   AND cat_type = \'FOT\' ';
        $pdoStatement = $this->db->queryPrepared($sql, array($organizationID));

        while ($row = $pdoStatement->fetch()) {
            $categories[] = $row;
        }

        return $categories;
    }

    /**
     * Save data from the post form into the database.
     * @param string $postUUID UUID if the topic that should be saved.
     * @param string $topicUUID UUID if the topic that must be set if a new post is created.
     * @throws Exception
     */
    public function savePost(string $postUUID, string $topicUUID = '')
    {
        global $gCurrentSession, $gDb;

        // check form field input and sanitized it from malicious content
        $postEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $postEditForm->validate($_POST);

        $post = new Post($gDb);
        if ($postUUID !== '') {
            $post->readDataByUuid($postUUID);
        } else {
            $topic = new Topic($gDb);
            $topic->readDataByUuid($topicUUID);
            $post->setValue('fop_fot_id', $topic->getValue('fot_id'));
        }

        // write form values in post object
        foreach ($formValues as $key => $value) {
            $post->setValue($key, $value);
        }

        $post->save();
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

        // write form values in topic object
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, 'fot_') || str_starts_with($key, 'fop_')) {
                $topic->setValue($key, $value);
            } elseif ($key === 'adm_category_uuid') {
                $category = new Category($gDb);
                $category->readDataByUuid($value);
                $topic->setValue('fot_cat_id', $category->getValue('cat_id'));
            }
        }

        $topic->save();
    }
}
