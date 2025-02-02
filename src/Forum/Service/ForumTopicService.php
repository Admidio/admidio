<?php

namespace Admidio\Forum\Service;

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
class ForumTopicService
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var string UUID of the topic.
     */
    protected string $topicUUID = '';

    /**
     * Constructor creates the page object and initialized all parameters.
     * @param string $topicUUID UUID of the topic.
     */
    public function __construct(Database $database, string $topicUUID = '')
    {
        $this->db = $database;
        $this->topicUUID = $topicUUID;
    }

    /**
     * Read the posts of a topic in an array. The returned array contains the following information
     * fot_uuid, fot_title, fot_views, fop_uuid, fop_text, fop_timestamp_create, fop_usr_id_create,
     * fop_timestamp_change, cat_name, usr_uuid, usr_timestamp_change, surname, firstname
     * @param int $offset Offset of the first record that should be returned.
     * @param int $limit Number of records that should be returned.
     * @return array Returns an array with all posts of a topic.
     * @throws Exception
     */
    public function getData(int $offset = 0, int $limit = 0): array
    {
        global $gDb, $gProfileFields;

        $sqlLimitOffset = '';
        $sqlQueryParameters = array();

        $topic = new Topic($gDb);
        $topic->readDataByUuid($this->topicUUID);
        if (!$topic->isVisible()) {
            throw new Exception('Topic is not visible for the current user!');
        }

        // Check if limit was set
        if ($limit > 0) {
            $sqlLimitOffset .= ' LIMIT ' . $limit;
        }
        if ($offset > 0) {
            $sqlLimitOffset .= ' OFFSET ' . $offset;
        }

        $sql = 'SELECT fot_uuid, fot_title, fot_views, fop_uuid, fop_text, fop_timestamp_create, fop_usr_id_create,
                       fop_timestamp_change, cat_name, usr_uuid, usr_timestamp_change,
                       cre_surname.usd_value AS surname, cre_firstname.usd_value AS firstname
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON fot_cat_id = cat_id
            INNER JOIN ' . TBL_FORUM_POSTS . '
                    ON fop_fot_id = fot_id
            INNER JOIN ' . TBL_USERS . '
                    ON usr_id = fop_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                    ON cre_surname.usd_usr_id = usr_id
                   AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                    ON cre_firstname.usd_usr_id = usr_id
                   AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
                 WHERE fot_uuid = ? -- $topicUUID
                 ORDER BY fop_timestamp_create
                       ' . $sqlLimitOffset;

        $queryParameters = array_merge(array(
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            $this->topicUUID
        ), $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Save data from the post form into the database.
     * @param string $postUUID UUID if the topic that should be saved.
     * @param string $topicUUID UUID if the topic that must be set if a new post is created.
     * @return string UUID of the saved post.
     * @throws Exception
     */
    public function savePost(string $postUUID, string $topicUUID = ''): string
    {
        global $gCurrentSession, $gDb, $gCurrentUser;

        // check form field input and sanitized it from malicious content
        $postEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $postEditForm->validate($_POST);

        $post = new Post($gDb);
        if ($postUUID !== '') {
            $post->readDataByUuid($postUUID);

            if (!$gCurrentUser->administrateForum() && $post->getValue('fop_usr_id_create') !== $gCurrentUser->getValue('usr_id')) {
                throw new Exception('You are not allowed to edit this post.');
            }
        } else {
            $topic = new Topic($gDb);
            $topic->readDataByUuid($topicUUID);
            $post->setValue('fop_fot_id', $topic->getValue('fot_id'));

            if (!in_array($topic->getValue('cat_uuid'), $gCurrentUser->getAllEditableCategories('FOT', 'uuid'))) {
                throw new Exception('You are not allowed to create a post in this category.');
            }
        }

        // write form values in post object
        foreach ($formValues as $key => $value) {
            $post->setValue($key, $value);
        }

        $post->save();
        return $post->getValue('fop_uuid');
    }
}
