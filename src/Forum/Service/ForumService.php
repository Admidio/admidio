<?php

namespace Admidio\Forum\Service;

use Admidio\Forum\Entity\Topic;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;
use DateTime;

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
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var string UUID of the category for which the topics should be filtered.
     */
    protected string $categoryUUID = '';

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     */
    public function __construct(Database $database, string $categoryUUID = '')
    {
        $this->db = $database;
        $this->categoryUUID = $categoryUUID;
    }

    /**
     * Read the data of the forum in an array. The returned array contains the following information
     * fot_uuid, fot_title, fot_views, fop_text, fot_timestamp_create, fot_usr_id_create,
     * cat_id, cat_name, usr_uuid, usr_login_name, usr_timestamp_change, surname, firstname, replies_count,
     * last_reply_uuid, last_reply_timestamp, last_reply_login_name, last_reply_surname, last_reply_firstname
     * @param int $offset Offset of the first record that should be returned.
     * @param int $limit Number of records that should be returned.
     * @return array Returns an array with all forum topics and their first post.
     * @throws Exception
     */
    public function getData(int $offset = 0, int $limit = 0): array
    {
        global $gDb, $gProfileFields, $gCurrentUser;

        $sqlConditions = '';
        $sqlLimitOffset = '';
        $sqlQueryParameters = array();
        $visibleCategoryIDs = $gCurrentUser->getAllVisibleCategories('FOT');

        if ($this->categoryUUID !== '') {
            $sqlConditions .= ' AND cat_uuid = ?';
            $sqlQueryParameters[] = $this->categoryUUID;
        }

        // Check if limit was set
        if ($limit > 0) {
            $sqlLimitOffset .= ' LIMIT ' . $limit;
        }
        if ($offset > 0) {
            $sqlLimitOffset .= ' OFFSET ' . $offset;
        }

        $sql = 'SELECT fot_uuid, fot_title, fot_views, first_post.fop_text, fot_timestamp_create, fot_usr_id_create,
                       cat_id, cat_name, usr.usr_uuid, usr.usr_login_name, usr.usr_timestamp_change,
                       cre_surname.usd_value AS surname, cre_firstname.usd_value AS firstname,
                       (SELECT COUNT(*) - 1 FROM ' . TBL_FORUM_POSTS . ' WHERE fop_fot_id = fot_id) AS replies_count,
                       last_reply.fop_uuid as last_reply_uuid, last_reply_usr.usr_uuid AS last_reply_usr_uuid,
                       last_reply.fop_timestamp_create AS last_reply_timestamp, last_reply_usr.usr_login_name AS last_reply_login_name,
                       last_reply_surname.usd_value AS last_reply_surname, last_reply_firstname.usd_value AS last_reply_firstname
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON fot_cat_id = cat_id
            INNER JOIN ' . TBL_FORUM_POSTS . ' as first_post
                    ON first_post.fop_id = fot_fop_id_first_post
            INNER JOIN ' . TBL_USERS . ' AS usr
                    ON usr.usr_id = fot_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_surname
                    ON cre_surname.usd_usr_id = usr.usr_id
                   AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS cre_firstname
                    ON cre_firstname.usd_usr_id = usr.usr_id
                   AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
             LEFT JOIN ' . TBL_FORUM_POSTS . ' AS last_reply
                    ON last_reply.fop_id = (SELECT MAX(fop_id) FROM ' . TBL_FORUM_POSTS . ' WHERE fop_fot_id = fot_id)
             LEFT JOIN ' . TBL_USERS . ' AS last_reply_usr
                    ON last_reply_usr.usr_id = last_reply.fop_usr_id_create
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_reply_surname
                    ON last_reply_surname.usd_usr_id = last_reply_usr.usr_id
                   AND last_reply_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN ' . TBL_USER_DATA . ' AS last_reply_firstname
                    ON last_reply_firstname.usd_usr_id = last_reply_usr.usr_id
                   AND last_reply_firstname.usd_usf_id = ? -- $firstNameUsfId
                 WHERE  cat_id IN (' . Database::getQmForValues($visibleCategoryIDs) . ')
                       ' . $sqlConditions . '
                 ORDER BY fot_timestamp_create DESC
                       ' . $sqlLimitOffset;

        $queryParameters = array_merge(array(
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'),
            (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id')
        ), $visibleCategoryIDs, $sqlQueryParameters);

        return $gDb->getArrayFromSql($sql, $queryParameters);
    }

    /**
     * Get number of available forum topics in the database.
     * @Return int Returns the total count of forum topics.
     * @throws Exception
     */
    public function getTopicCount(): int
    {
        global $gCurrentUser, $gDb;

        $visibleCategoryIDs = array_merge(array(0), $gCurrentUser->getAllVisibleCategories('FOT'));

        $sql = 'SELECT COUNT(*) AS count
                  FROM ' . TBL_FORUM_TOPICS . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = fot_cat_id
                 WHERE cat_id IN (' . Database::getQmForValues($visibleCategoryIDs) . ') ';

        $pdoStatement = $gDb->queryPrepared($sql, $visibleCategoryIDs);

        return (int)$pdoStatement->fetchColumn();
    }

    /**
     * Send a valid RSS feed of the forum to the browser. This feed will contain the latest 50 topics of all categories, that are
     * visible for guests. The feed will be generated in the format of an RSS feed.
     * @param string $organizationShortName The short name of the organization whose topics should be shown in the RSS feed.
     * @return void
     * @throws Exception
     */
    public function showRssFeed(string $organizationShortName): void
    {
        global $gSettingsManager, $gCurrentUser, $gCurrentOrganization, $gDb, $gL10n, $gCurrentOrgId;

        // Check if RSS is active...
        if (!$gSettingsManager->getBool('enable_rss')) {
            throw new Exception('SYS_RSS_DISABLED');
        }

        if ($organizationShortName !== '') {
            $organization = new Organization($gDb, $organizationShortName);
            $organizationName = $organization->getValue('org_longname');
            $gCurrentUser->setOrganization($organization->getValue('org_id'));
        } else {
            $organizationName = $gCurrentOrganization->getValue('org_longname');
        }

        // create RSS feed object with channel information
        $rss = new RssFeed(
            $organizationName . ' - ' . $gL10n->get('SYS_ANNOUNCEMENTS'),
            $gCurrentOrganization->getValue('org_homepage'),
            $gL10n->get('SYS_LATEST_FORUM_TOPICS_OF_ORGANIZATION', array($organizationName)),
            $organizationName
        );

        $forumTopics = $this->getData(0, 50);

        if (count($forumTopics) > 0) {
            foreach ($forumTopics as $topic) {
                // add entry to RSS feed
                $rss->addItem(
                    $topic['fot_title'],
                    $topic['fop_text'],
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/forum.php', array('mode' => 'topic', 'topic_uuid' => $topic['fot_uuid'],)),
                    $topic['firstname'] . ' ' . $topic['surname'],
                    DateTime::createFromFormat('Y-m-d H:i:s', $topic['fot_timestamp_create'])->format('r'),
                    $topic['cat_name'],
                    $topic['fot_uuid']
                );
            }
        }

        $gCurrentUser->setOrganization($gCurrentOrgId);
        $rss->getRssFeed();
    }

    /**
     * Save data from the topic form into the database.
     * @param string $topicUUID UUID if the topic that should be stored within this class
     * @return string UUID of the saved topic.
     * @throws Exception
     */
    public function saveTopic(string $topicUUID = ''): string
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
            }
        }

        if ($topic->save()) {
            // Notification email for new or changed entries to all members of the notification role
            $topic->sendNotification();
        }

        return $topic->getValue('fot_uuid');
    }
}
