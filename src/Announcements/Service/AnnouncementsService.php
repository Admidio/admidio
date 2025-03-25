<?php

namespace Admidio\Announcements\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\RssFeed;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\Organizations\Entity\Organization;
use DateTime;

/**
 * @brief Class with various methods around the announcements module.
 *
 * This class adds some functions that are used in the announcements module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class AnnouncementsService
{
    /**
     * @var Database An object of the class Database for communication with the database
     */
    protected Database $db;
    /**
     * @var string UUID of the category for which the announcements should be filtered.
     */
    protected string $categoryUUID = '';

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $categoryUUID UUID of the category for which the announcements should be filtered.
     */
    public function __construct(Database $database, string $categoryUUID = '')
    {
        $this->db = $database;
        $this->categoryUUID = $categoryUUID;
    }

    /**
     * Read the data of the announcements in an array. The returned array contains the following information
     * cat.*, ann.*, surname, firstname, create_name, change_name, create_uuid, change_uuid
     * @param int $offset Offset of the first record that should be returned.
     * @param int $limit Number of records that should be returned.
     * @return array Returns an array with all announcements
     * @throws Exception
     */
    public function findAll(int $offset = 0, int $limit = 0): array
    {
        global $gDb, $gProfileFields, $gCurrentUser;

        $sqlConditions = '';
        $sqlLimitOffset = '';
        $sqlQueryParameters = array();
        $visibleCategoryIDs = $gCurrentUser->getAllVisibleCategories('ANN');

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

        $sql = 'SELECT cat.*, ann.*,
                       cre_surname.usd_value AS surname, cre_firstname.usd_value AS firstname,
                       cre_firstname.usd_value || \' \' || cre_surname.usd_value AS create_name,
                       cha_firstname.usd_value || \' \' || cha_surname.usd_value AS change_name,
                       cre_user.usr_uuid AS create_uuid, cha_user.usr_uuid AS change_uuid
                  FROM '.TBL_ANNOUNCEMENTS.' AS ann
            INNER JOIN '.TBL_CATEGORIES.' AS cat
                    ON cat_id = ann_cat_id
                  LEFT JOIN ' . TBL_USERS . ' AS cre_user
                    ON cre_user.usr_id = ann_usr_id_create
             LEFT JOIN '.TBL_USER_DATA.' AS cre_surname
                    ON cre_surname.usd_usr_id = ann_usr_id_create
                   AND cre_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN '.TBL_USER_DATA.' AS cre_firstname
                    ON cre_firstname.usd_usr_id = ann_usr_id_create
                   AND cre_firstname.usd_usf_id = ? -- $firstNameUsfId
             LEFT JOIN ' . TBL_USERS . ' AS cha_user
                    ON cha_user.usr_id = ann_usr_id_change
             LEFT JOIN '.TBL_USER_DATA.' AS cha_surname
                    ON cha_surname.usd_usr_id = ann_usr_id_change
                   AND cha_surname.usd_usf_id = ? -- $lastNameUsfId
             LEFT JOIN '.TBL_USER_DATA.' AS cha_firstname
                    ON cha_firstname.usd_usr_id = ann_usr_id_change
                   AND cha_firstname.usd_usf_id = ? -- $firstNameUsfId
                 WHERE cat_id IN ('.Database::getQmForValues($visibleCategoryIDs).')
                       ' . $sqlConditions . '
                 ORDER BY ann_timestamp_create DESC
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
     * Send a valid RSS feed of the announcements to the browser. This feed will contain the latest 50 announcements
     * of all categories, that are visible for guests. The feed will be generated in the format of an RSS feed.
     * @param string $organizationShortName The short name of the organization whose topics should be shown in the RSS feed.
     * @return void
     * @throws Exception
     */
    public function rssFeed(string $organizationShortName): void
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

        $announcements = $this->findAll(0, 50);

        if (count($announcements) > 0) {
            foreach ($announcements as $announcement) {
                // add entry to RSS feed
                $rss->addItem(
                    $announcement['ann_headline'],
                    $announcement['ann_description'],
                    SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/announcements.php', array('mode' => 'detail', 'ann_uuid' => $announcement['ann_uuid'],)),
                    $announcement['firstname'] . ' ' . $announcement['surname'],
                    DateTime::createFromFormat('Y-m-d H:i:s', $announcement['ann_timestamp_create'])->format('r'),
                    $announcement['cat_name'],
                    $announcement['ann_uuid']
                );
            }
        }

        $gCurrentUser->setOrganization($gCurrentOrgId);
        $rss->getRssFeed();
    }
}
