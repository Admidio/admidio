<?php

namespace Admidio\InstallationUpdate\Service;

use Admidio\Infrastructure\Plugins\PluginManager;
use Admidio\Categories\Entity\Category;
use Admidio\Documents\Entity\Folder;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\Maintenance;
use Admidio\Inventory\Entity\ItemField;
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\StringUtils;
use DateTime;
use PDOException;
use Ramsey\Uuid\Uuid;
use Admidio\Infrastructure\Exception;
use RuntimeException;
use UnexpectedValueException;

// this must be declared for backwards compatibility. Can be removed if update scripts don't use it anymore
const TBL_DATES = TABLE_PREFIX . '_dates';

/**
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class UpdateStepsCode
{
    /**
     * @var Database
     */
    private static Database $db;

    /**
     * Set the database
     * @param Database $database The database instance
     */
    public static function setDatabase(Database $database): void
    {
        self::$db = $database;
    }

    /** Convert default texts to IDs once, after legacy updates have used their old names. */
    public static function updateStep51TranslateDefaultEntries(): void
    {
        $db = self::$db;
        $translations = array();
        $language = new Language(Language::REFERENCE_LANGUAGE);
        foreach (array_keys($language->getAvailableLanguages()) as $code) {
            $translations[] = new Language($code);
        }
        $candidates = array();
        // Only accept exact, unambiguous matches of the expected defaults.
        $findId = static function (string $value, array $ids, string $column) use ($translations, &$candidates): ?string {
            if (in_array($value, $ids, true)) {
                return $value;
            }
            $matches = array();
            foreach ($ids as $id) {
                if (!isset($candidates[$column][$id])) {
                    $variants = array();
                    foreach ($translations as $language) {
                        $text = $language->get($id);
                        if ($text === '#' . $id . '#') {
                            continue;
                        }
                        if ($column === 'txt_text') {
                            $text = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/', "\n", $text);
                        }
                        foreach (array($text, StringUtils::strStripTags(html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
                            str_replace(array('&rsquo;', '’'), "'", StringUtils::strStripTags(html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')))) as $variant) {
                            $variants[trim(str_replace("\r\n", "\n", $variant))] = true;
                        }
                    }
                    $candidates[$column][$id] = $variants;
                }
                if (isset($candidates[$column][$id][str_replace("\r\n", "\n", $value)])) {
                    $matches[$id] = true;
                }
            }
            return count($matches) === 1 ? array_key_first($matches) : null;
        };
        $convert = static function (string $table, string $key, string $column, array $ids, string $where = '1 = 1', array $params = array()) use ($db, $findId): void {
            $rows = $db->queryPrepared('SELECT ' . $key . ', ' . $column . ' FROM ' . $table . ' WHERE ' . $where, $params)->fetchAll();
            foreach ($rows as $row) {
                $value = (string) $row[$column];
                $id = $findId($value, $ids, $column);
                if ($id !== null && $id !== $value) {
                    // Compare in PHP, not in the database's possibly case/accent-insensitive collation.
                    $db->queryPrepared('UPDATE ' . $table . ' SET ' . $column . ' = ? WHERE ' . $key . ' = ?', array($id, $row[$key]));
                }
            }
        };

        $db->startTransaction();
        try {
            // The installer creates the disabled system account before any other user.
            $systemUser = $db->queryPrepared('SELECT usr_id, usr_login_name, usr_valid, usr_usr_id_create FROM ' . TBL_USERS . ' ORDER BY usr_id')->fetch();
            $systemId = null;
            if ($systemUser && !$systemUser['usr_valid'] && empty($systemUser['usr_usr_id_create'])
                && $findId((string) $systemUser['usr_login_name'], array('SYS_SYSTEM'), 'usr_login_name') === 'SYS_SYSTEM') {
                $systemId = (int) $systemUser['usr_id'];
                $convert(TBL_USERS, 'usr_id', 'usr_login_name', array('SYS_SYSTEM'), 'usr_id = ?', array($systemId));
                $convert(TBL_USER_DATA, 'usd_id', 'usd_value', array('SYS_SYSTEM'),
                    'usd_usr_id = ? AND usd_usf_id IN (SELECT usf_id FROM ' . TBL_USER_FIELDS . " WHERE usf_name_intern = 'LAST_NAME')", array($systemId));
            }

            $mails = Text::SYSTEM_MAIL_DEFAULTS;
            foreach ($mails as $name => $id) {
                $convert(TBL_TEXTS, 'txt_id', 'txt_text', array($id), 'txt_name = ?', array($name));
            }

            $convert(TBL_ROLES, 'rol_id', 'rol_name', array('SYS_ADMINISTRATOR', 'SYS_MEMBER', 'INS_BOARD'),
                "rol_cat_id IN (SELECT cat_id FROM " . TBL_CATEGORIES . " WHERE cat_type = 'ROL' AND cat_name_intern = 'COMMON')");
            $convert(TBL_ROLES, 'rol_id', 'rol_description', array('INS_DESCRIPTION_ADMINISTRATOR', 'INS_DESCRIPTION_MEMBER', 'INS_DESCRIPTION_BOARD'),
                "rol_cat_id IN (SELECT cat_id FROM " . TBL_CATEGORIES . " WHERE cat_type = 'ROL' AND cat_name_intern = 'COMMON')");

            // Additional organizations' default lists belong to their administrator. Personal lists are excluded.
            $convert(TBL_LISTS, 'lst_id', 'lst_name', array('INS_ADDRESS_LIST', 'INS_PHONE_LIST', 'SYS_CONTACT_DETAILS', 'INS_MEMBERSHIP', 'SYS_PARTICIPANTS', 'SYS_CONTACTS'),
                'lst_global = true');
            if ($systemId !== null) {
                $convert(TBL_ROOMS, 'room_id', 'room_name', array('INS_CONFERENCE_ROOM'), 'room_usr_id_create = ?', array($systemId));
                $convert(TBL_ROOMS, 'room_id', 'room_description', array('INS_DESCRIPTION_CONFERENCE_ROOM'), 'room_usr_id_create = ?', array($systemId));
            }
            $convert(TBL_CATEGORY_REPORT, 'crt_id', 'crt_name', array('SYS_GENERAL_ROLE_ASSIGNMENT'));

            $categories = array('COMMON' => 'SYS_COMMON', 'GROUPS' => 'INS_GROUPS', 'COURSES' => 'INS_COURSES',
                'TEAMS' => 'INS_TEAMS', 'EVENTS' => 'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION', 'INTERN' => 'INS_INTERN',
                'IMPORTANT' => 'SYS_IMPORTANT', 'TRAINING' => 'INS_TRAINING', 'BASIC_DATA' => 'SYS_BASIC_DATA',
                'SOCIAL_NETWORKS' => 'SYS_SOCIAL_NETWORKS', 'ADDIDIONAL_DATA' => 'INS_ADDIDIONAL_DATA');
            foreach ($categories as $name => $id) {
                $convert(TBL_CATEGORIES, 'cat_id', 'cat_name', array($id), 'cat_name_intern = ?', array($name));
            }
            $convert(TBL_USER_FIELDS, 'usf_id', 'usf_description', array('SYS_DATA_PROTECTION_PERMISSION_DESC'),
                'usf_name_intern = ?', array('DATA_PROTECTION_PERMISSION'));
            $convert(TBL_USER_FIELDS, 'usf_id', 'usf_description', array('SYS_SOCIAL_NETWORK_FIELD_URL_DESC'),
                "usf_name_intern IN ('BLUESKY', 'FACEBOOK', 'INSTAGRAM', 'LINKEDIN', 'MASTODON', 'XING')");
            $db->endTransaction();
        } catch (\Throwable $exception) {
            $db->rollback();
            throw $exception;
        }
    }

    /** Convert unchanged demo records to their translation IDs. */
    public static function updateStep51TranslateDemoEntries(): void
    {
        $demoOrganization = self::$db->queryPrepared('SELECT org_id FROM ' . TBL_ORGANIZATIONS . ' WHERE org_uuid = ?', array('f04eef83-91ad-40bf-8267-09cd40ce0799'))->fetch();
        if (!$demoOrganization) {
            return;
        }
        $entries = array(
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', 'e49d66f4-0546-4a23-bb57-27eb2b97d271', 'ann_headline', 'New jerseys', 'DEM_ANNOUNCEMENT_1_HEADLINE'),
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', 'e49d66f4-0546-4a23-bb57-27eb2b97d271', 'ann_description', 'Starting next season, there are new jerseys for all active players. These can be picked up before the first training at the trainer.', 'DEM_ANNOUNCEMENT_1_DESCRIPTION'),
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', 'e84aae2a-7e1d-4f91-b2e1-ead4bac900ed', 'ann_headline', 'Aerobics course', 'DEM_ANNOUNCEMENT_2_HEADLINE'),
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', 'e84aae2a-7e1d-4f91-b2e1-ead4bac900ed', 'ann_description', 'During the holidays we offer a <i>aerobic course</i> to all interested members.<br /><br />Registrations are accepted on our <b>homepage</b> or in our <b>office</b>.', 'DEM_ANNOUNCEMENT_2_DESCRIPTION'),
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', '934346cc-123c-4162-9506-86b07c6c08ce', 'ann_headline', 'Welcome to the demo area', 'DEM_ANNOUNCEMENT_3_HEADLINE'),
            array(TBL_ANNOUNCEMENTS, 'ann_uuid', '934346cc-123c-4162-9506-86b07c6c08ce', 'ann_description', '<p>In this area you can play around with Admidio and see whether the program\'s functions meet your needs.</p><p>We have also provided some test data so that you can see in the individual modules how this could look later on your site. However, emails are not actually sent in the demo area so that this function cannot be abused. You are welcome to play with this installation.</p><p>We have created a few test accounts with different rights:</p><p><span style="color:#008080;"><strong>Administrator</strong></span></p><table border="0" cellpadding="1" cellspacing="1" style="width: 100%;"><tbody><tr><td>Username:</td><td><strong>Admin</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rights:</td><td>Can see and edit everything. More rights are not possible :)</td></tr></tbody></table><p><span style="color:#008080;"><strong>Chairman</strong></span></p><table border="0" cellpadding="1" cellspacing="1" style="width: 100%;"><tbody><tr><td>Username:</td><td><strong>Chairman</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rights:</td><td>Can edit and view everything, except assigning roles and changing program/module settings.</td></tr></tbody></table><p><span style="color:#008080;"><strong>Member</strong></span></p><table border="0" cellpadding="1" cellspacing="1" style="width: 100%;"><tbody><tr><td>Username:</td><td><strong>Member</strong></td></tr><tr><td>Password:</td><td><strong>Admidio</strong></td></tr><tr><td>Rechte:</td><td>Can edit his profile and view lists of roles, where he is a member.</td></tr></tbody></table><p>Have fun trying !<br />The Admidio Team</p>', 'DEM_ANNOUNCEMENT_3_DESCRIPTION'),
            array(TBL_CATEGORIES, 'cat_uuid', '32edc214-cb7b-42f1-a4af-7336a28ada5e', 'cat_name', 'Admidio', 'DEM_CATEGORY_9_NAME'),
            array(TBL_CATEGORIES, 'cat_uuid', '9a4b3de1-3cab-40db-97f6-77719c731f01', 'cat_name', 'Admidio', 'DEM_CATEGORY_9_NAME'),
            array(TBL_CATEGORIES, 'cat_uuid', 'dd6630ae-4362-40cb-9c5a-a948e0365582', 'cat_name', 'Audio Equipment', 'DEM_CATEGORY_307_NAME'),
            array(TBL_CATEGORY_REPORT, 'crt_id', '1', 'crt_name', 'General role assignment', 'DEM_CATEGORY_REPORT_1_NAME'),
            array(TBL_CATEGORY_REPORT, 'crt_id', '2', 'crt_name', 'General role assignment', 'DEM_CATEGORY_REPORT_1_NAME'),
            array(TBL_EVENTS, 'dat_uuid', 'e539f6d4-a5ac-4536-8779-df203a83ef39', 'dat_headline', 'Youth training 1', 'DEM_EVENT_3_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', 'e539f6d4-a5ac-4536-8779-df203a83ef39', 'dat_description', 'Today we will put the focus on physical fitness and stamina.<br /><br />Please appear all in time with running shoes on the sports field!', 'DEM_EVENT_3_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', 'e539f6d4-a5ac-4536-8779-df203a83ef39', 'dat_location', 'Sports field Norwich', 'DEM_EVENT_3_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '2bc7d168-7b4e-4ec1-9765-18989e32030c', 'dat_headline', 'Barbecue', 'DEM_EVENT_4_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '2bc7d168-7b4e-4ec1-9765-18989e32030c', 'dat_description', 'Today we have our barbecue. In addition to crisp sausages, chops and bacon, there are also various salads.', 'DEM_EVENT_4_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', '10408fec-1534-4115-a83d-60681c13bcfd', 'dat_headline', 'Trainer course', 'DEM_EVENT_5_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '10408fec-1534-4115-a83d-60681c13bcfd', 'dat_description', 'A four-day training course for youth coaches from the tennis department :)', 'DEM_EVENT_5_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', '10408fec-1534-4115-a83d-60681c13bcfd', 'dat_location', 'Youth hostel Lyon', 'DEM_EVENT_5_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '0df388d7-b8f0-4c11-88f4-fbac697b2297', 'dat_headline', 'Computer course', 'DEM_EVENT_6_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '0df388d7-b8f0-4c11-88f4-fbac697b2297', 'dat_description', 'The focus of this course lies with the Office products.', 'DEM_EVENT_6_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', '0df388d7-b8f0-4c11-88f4-fbac697b2297', 'dat_location', 'Munich Marienplatz', 'DEM_EVENT_6_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '2a0151ef-2f03-4b6f-abe3-ce86d5a74ba8', 'dat_headline', 'Trip to Amsterdam', 'DEM_EVENT_7_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '2a0151ef-2f03-4b6f-abe3-ce86d5a74ba8', 'dat_description', 'On this hopefully sunny day it goes to Amsterdam.<br /><br />A canal cruise and a shopping trip are planned.', 'DEM_EVENT_7_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', '2a0151ef-2f03-4b6f-abe3-ce86d5a74ba8', 'dat_location', 'Amsterdam Gracht', 'DEM_EVENT_7_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '2c610a75-15e8-4ab2-9bd5-63769800d2e8', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '2c610a75-15e8-4ab2-9bd5-63769800d2e8', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '236c9f98-c826-4f42-a0e4-8421f83e11ff', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '236c9f98-c826-4f42-a0e4-8421f83e11ff', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '9dbbb1d4-ec43-4704-b4d5-3a4f29d5dab1', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '9dbbb1d4-ec43-4704-b4d5-3a4f29d5dab1', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '86c27d41-caf3-49b6-9d68-a079c532dbe3', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '86c27d41-caf3-49b6-9d68-a079c532dbe3', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', 'fadeff52-a0e0-4ab9-8e43-a2a0578ab5ed', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', 'fadeff52-a0e0-4ab9-8e43-a2a0578ab5ed', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', 'cd9f4490-ddae-4949-a083-a826a12ea3d1', 'dat_headline', 'Team training', 'DEM_EVENT_8_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', 'cd9f4490-ddae-4949-a083-a826a12ea3d1', 'dat_location', 'Sports hall Alpenstraße Salzburg', 'DEM_EVENT_8_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '6fa731ef-e166-49ed-bb56-182243cbc5c8', 'dat_headline', 'Yoga for beginners', 'DEM_EVENT_14_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '6fa731ef-e166-49ed-bb56-182243cbc5c8', 'dat_description', 'This course teaches the basics of yoga.<br /><br />A registration for this course is required.', 'DEM_EVENT_14_DESCRIPTION'),
            array(TBL_EVENTS, 'dat_uuid', '6fa731ef-e166-49ed-bb56-182243cbc5c8', 'dat_location', 'Madrid center', 'DEM_EVENT_14_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '7095ed97-9cf5-4247-b057-613164aaa512', 'dat_headline', 'Board meeting', 'DEM_EVENT_15_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '7095ed97-9cf5-4247-b057-613164aaa512', 'dat_location', 'Clubhouse', 'DEM_EVENT_15_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', '217da340-7419-4e07-8f5f-bf037cbd2a4f', 'dat_headline', 'Board meeting', 'DEM_EVENT_15_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', '217da340-7419-4e07-8f5f-bf037cbd2a4f', 'dat_location', 'Clubhouse', 'DEM_EVENT_15_LOCATION'),
            array(TBL_EVENTS, 'dat_uuid', 'b89b03a5-867b-4747-8429-981c76e0b61e', 'dat_headline', 'Team evening', 'DEM_EVENT_17_HEADLINE'),
            array(TBL_EVENTS, 'dat_uuid', 'b89b03a5-867b-4747-8429-981c76e0b61e', 'dat_location', 'Clubhouse', 'DEM_EVENT_15_LOCATION'),
            array(TBL_FORUM_POSTS, 'fop_uuid', 'e01359ab-98a3-4024-ae1e-55d2dfdfa002', 'fop_text', '<p>Hi everyone,<br>the new <strong>training plan</strong> for the upcoming season is now available in the members’ area.</p><p>We’ve adjusted some of the sessions to better fit everyone’s fitness levels and training goals.</p><p>There are also a few new activities designed for beginners who want to build endurance gradually.</p><p>I’d love to hear your thoughts — what do you think about the new structure?</p>', 'DEM_FORUM_POST_4_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '4a26f9a7-8810-407f-9ea6-2c5f5804eae1', 'fop_text', '<p>I really like the idea of adding more variety to the sessions.<br>Mixing running drills with strength exercises keeps things interesting.</p><p>Will we still have separate groups for different experience levels?</p>', 'DEM_FORUM_POST_5_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '7b1131e0-e70e-4fd9-8b87-ff083c76ee04', 'fop_text', '<p>Yes, Eric — we’ll continue with the same three training groups: beginners, intermediate, and advanced.</p><p>The goal is to make sure everyone trains at a pace that suits their current level while still being challenged.</p>', 'DEM_FORUM_POST_6_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '21de6139-e513-4c35-8243-d722232e6f9f', 'fop_text', '<p>Love it!<br>The new plan seems balanced and motivating.<br>Thanks to everyone who worked on organizing it — really appreciate the effort that goes into keeping this club running smoothly.</p>', 'DEM_FORUM_POST_7_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '02354806-5e4d-42a2-943b-0a7c04d3ec2b', 'fop_text', '<p>Hi everyone,<br>as we prepare for our upcoming club events, we’re looking for a few <strong>volunteers</strong> to help with organization and setup.</p><p>Tasks include welcoming guests, handing out water and snacks, and assisting with registration.</p><p>It’s a great way to get involved and meet other members — no special experience required!</p><p>Anyone interested?</p>', 'DEM_FORUM_POST_8_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '103e21c4-52b5-4170-ba49-0df98b92be25', 'fop_text', '<p>Count me in!<br>I can help with registration or setup — whatever’s needed.<br>Always happy to give something back to the club.</p>', 'DEM_FORUM_POST_9_TEXT'),
            array(TBL_FORUM_POSTS, 'fop_uuid', '2f19c641-617d-4ffa-b9d3-b5c5e6b8ee0b', 'fop_text', '<p>Hi everyone,<br>next week we’ll be organizing a <strong>community clean-up day</strong> at the club grounds.</p><p>It’s a great opportunity to keep our training area in top shape and spend some time together outside of regular practice.</p><p>All members are welcome to join — tools and materials will be provided by the club.</p><p>Thanks in advance to everyone who helps make our environment clean and welcoming for all!</p>', 'DEM_FORUM_POST_10_TEXT'),
            array(TBL_FORUM_TOPICS, 'fot_uuid', '85944966-4967-44f3-8b76-b6a425225970', 'fot_title', 'Thoughts on the New Training Plan?', 'DEM_FORUM_TOPIC_4_TITLE'),
            array(TBL_FORUM_TOPICS, 'fot_uuid', '5cb5159a-8cea-4dc2-a154-f9faadfc8d48', 'fot_title', 'Volunteers Needed for Upcoming Club Events', 'DEM_FORUM_TOPIC_5_TITLE'),
            array(TBL_FORUM_TOPICS, 'fot_uuid', 'a5807ef6-92c3-4eda-bb12-10c66ca0a9b8', 'fot_title', 'Community Clean-Up Day at the Club Grounds', 'DEM_FORUM_TOPIC_6_TITLE'),
            array(TBL_INVENTORY_ITEM_DATA, 'ind_id', '1', 'ind_value', 'Portable PA Speaker', 'DEM_INVENTORY_ITEM_DATA_1_VALUE'),
            array(TBL_INVENTORY_ITEM_DATA, 'ind_id', '3', 'ind_value', 'Folding Chairs', 'DEM_INVENTORY_ITEM_DATA_3_VALUE'),
            array(TBL_INVENTORY_ITEM_DATA, 'ind_id', '5', 'ind_value', 'Adjustable Microphone Stand', 'DEM_INVENTORY_ITEM_DATA_5_VALUE'),
            array(TBL_INVENTORY_ITEM_DATA, 'ind_id', '7', 'ind_value', 'Beamer', 'DEM_INVENTORY_ITEM_DATA_7_VALUE'),
            array(TBL_LINKS, 'lnk_uuid', '07bdb749-e925-4715-ba92-360bf3b2821d', 'lnk_name', 'Sample page', 'DEM_LINK_1_NAME'),
            array(TBL_LINKS, 'lnk_uuid', '07bdb749-e925-4715-ba92-360bf3b2821d', 'lnk_description', "On this site there\'s not much news :(", 'DEM_LINK_1_DESCRIPTION'),
            array(TBL_LINKS, 'lnk_uuid', 'ae39a20e-b5b2-4ebb-8b1a-882bd6d777d5', 'lnk_name', 'Admidio', 'DEM_CATEGORY_9_NAME'),
            array(TBL_LINKS, 'lnk_uuid', 'ae39a20e-b5b2-4ebb-8b1a-882bd6d777d5', 'lnk_description', 'The homepage of the <b>best</b> open source membership management in the net.', 'DEM_LINK_2_DESCRIPTION'),
            array(TBL_LINKS, 'lnk_uuid', '476855ec-6c36-449c-a4ac-c17b27a34e11', 'lnk_name', 'Forum', 'DEM_LINK_3_NAME'),
            array(TBL_LINKS, 'lnk_uuid', '476855ec-6c36-449c-a4ac-c17b27a34e11', 'lnk_description', 'The forum for the online membership management software. Here gets everyone support, who has encountered a problem while installing or setting up Admidio. But also suggestions and tips can be posted here.', 'DEM_LINK_3_DESCRIPTION'),
            array(TBL_LINKS, 'lnk_uuid', '69e19ac6-1744-495b-bd70-bf8c3baaf15c', 'lnk_name', 'Documentation', 'DEM_LINK_4_NAME'),
            array(TBL_LINKS, 'lnk_uuid', '69e19ac6-1744-495b-bd70-bf8c3baaf15c', 'lnk_description', 'The documentation for Admidio with valuable help and tips.', 'DEM_LINK_4_DESCRIPTION'),
            array(TBL_LINKS, 'lnk_uuid', '325564f8-4630-4efe-912b-79358b6cae98', 'lnk_name', 'GitHub', 'DEM_LINK_5_NAME'),
            array(TBL_LINKS, 'lnk_uuid', '325564f8-4630-4efe-912b-79358b6cae98', 'lnk_description', '<p>Our developement area at Github. If you want to help us and add some new feature to Admidio go there and get the code.</p>', 'DEM_LINK_5_DESCRIPTION'),
            array(TBL_LISTS, 'lst_uuid', '485a11f0-e4f9-4771-a71c-1eacff12dd4c', 'lst_name', 'Address list', 'DEM_LIST_1_NAME'),
            array(TBL_LISTS, 'lst_uuid', '914693d9-5e08-42f9-a97f-1b1e8ed8ae2a', 'lst_name', 'Phone list', 'DEM_LIST_2_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'c28f0e74-d95f-44d7-8e02-2ca80ee220ae', 'lst_name', 'Contact information', 'DEM_LIST_3_NAME'),
            array(TBL_LISTS, 'lst_uuid', '4e3d9b48-eeff-4760-98c6-a69b38221342', 'lst_name', 'Membership', 'DEM_LIST_4_NAME'),
            array(TBL_LISTS, 'lst_uuid', '3a28db85-bf2c-4828-82f7-f8c67a0ff692', 'lst_name', 'Social networks', 'DEM_LIST_5_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'ca9a32ec-efd2-46da-a9ee-8cf6aa0c179e', 'lst_name', 'Birthday', 'DEM_LIST_6_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'a4889bdb-e294-46a6-a76f-4456707012e3', 'lst_name', 'Website', 'DEM_LIST_7_NAME'),
            array(TBL_LISTS, 'lst_uuid', '82b5af7a-d535-4383-8f0e-befbd6e8d9e8', 'lst_name', 'Address list', 'DEM_LIST_1_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'df811fdd-89cd-49f2-9031-49a8aab71860', 'lst_name', 'Phone list', 'DEM_LIST_2_NAME'),
            array(TBL_LISTS, 'lst_uuid', '77afdf9a-1d25-4993-8680-6fc556c2972c', 'lst_name', 'Contact information', 'DEM_LIST_3_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'a834cebe-3592-4f05-8c9b-73551615f570', 'lst_name', 'Membership', 'DEM_LIST_4_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'afc87e5f-fffa-46f3-82d1-6e8c65472ae4', 'lst_name', 'Members', 'DEM_LIST_13_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'a94a023b-56fa-4d6e-b05e-267a3d37ba09', 'lst_name', 'Members', 'DEM_LIST_13_NAME'),
            array(TBL_LISTS, 'lst_uuid', 'd39d0642-c367-43ca-bc73-578219febbc6', 'lst_name', 'Contacts', 'DEM_LIST_15_NAME'),
            array(TBL_LISTS, 'lst_uuid', '6efad974-5b15-4bb7-a1f8-350fa4b7a452', 'lst_name', 'Contacts', 'DEM_LIST_15_NAME'),
            array(TBL_MESSAGES, 'msg_uuid', '39a0e3e2-8163-4cbf-bb9b-ac87dbf8ab77', 'msg_subject', 'Events on the website', 'DEM_MESSAGE_1_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', 'd12a108d-0bc7-468c-86a5-b05626a21f15', 'msg_subject', 'New module unlocked', 'DEM_MESSAGE_2_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', '479fb7ae-52eb-401e-aba7-a3ca74f69c32', 'msg_subject', 'New training times', 'DEM_MESSAGE_3_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', '42fb2917-a25b-4d1f-92ae-2cc837aae4a6', 'msg_subject', 'Invitation to members meeting', 'DEM_MESSAGE_4_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', '9be7e4cc-4cf9-4945-84e8-0cb387063501', 'msg_subject', 'Reserve room', 'DEM_MESSAGE_5_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', '62d38fd3-b392-43f5-983a-bf563e780c07', 'msg_subject', 'Membership fee missing', 'DEM_MESSAGE_6_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', '693a032a-fee7-4a03-8e21-05647b6b6848', 'msg_subject', 'Training', 'DEM_MESSAGE_7_SUBJECT'),
            array(TBL_MESSAGES, 'msg_uuid', 'c727b421-e303-4381-b097-9f6eeb56ca39', 'msg_subject', 'No access to documents', 'DEM_MESSAGE_8_SUBJECT'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '1', 'msc_message', '<p>Hi all,</p><br /><p>please maintain your schedules on the website so that all members have the opportunity to view and participate.</p><br /><p>Regards</p><br /><p>Paul</p><br />', 'DEM_MESSAGE_CONTENT_1_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '2', 'msc_message', '<p>Hello Board,</p><br /><p>I have now unlocked the <strong>Documents and Files</strong> module. Please log in and have a look at this module</p><br /><p>The module has among others. following functions:</p><br /><ul><br /> <li>Files and documents can be uploaded by the board</li><br /> <li>Files and documents can be downloaded by all members</li><br /> <li>Files and documents can be displayed directly on the web</li><br /><br /ul><br /><p>You can send feedback directly to me. </p><br /><p>Best regards</p><br /><p>Paul</p><br />', 'DEM_MESSAGE_CONTENT_2_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '3', 'msc_message', '<p>Hello everyone,</p><br /><p>I have put the new training times on the website.</p><br /><p>Many greetings</p><br /><p>Paul</p><br />', 'DEM_MESSAGE_CONTENT_3_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '4', 'msc_message', '<p>Dear Ladies and Gentlemen,</p><br /><p>the board of directors hereby invites you to the annual members meeting in our clubhouse.</p><br /><p>Yours sincerely</p><br /><p>Paul Schmidt</p><br />', 'DEM_MESSAGE_CONTENT_4_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '5', 'msc_message', 'Hi Paul,<br />can you reserve the room for the general meeting?<br />Greetings<br />Eric', 'DEM_MESSAGE_CONTENT_5_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '6', 'msc_message', "Hi Jennifer,<br />you haven\'t transferred your membership fee yet. <br />Can you please do it yet.<br />Regards<br />Eric", 'DEM_MESSAGE_CONTENT_6_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '7', 'msc_message', '<p>Hi Dana and Daria,</p><br /><p>are you coming for training next week?</p><br /><p>Please write me a short answer</p><br /><p>Many greetings</p><br /><p>Jennifer</p><br />', 'DEM_MESSAGE_CONTENT_7_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '8', 'msc_message', "Hi Paul,<br />unfortunately I don\'t have access to the documents.<br />Can you check it out.<br />Regards<br />Jennifer", 'DEM_MESSAGE_CONTENT_8_MESSAGE'),
            array(TBL_MESSAGES_CONTENT, 'msc_id', '9', 'msc_message', 'Hi Jennifer,<br />I have redeposited the rights. Please check this again.<br />Greetings<br />Paul', 'DEM_MESSAGE_CONTENT_9_MESSAGE'),
            array(TBL_ORGANIZATIONS, 'org_uuid', 'f04eef83-91ad-40bf-8267-09cd40ce0799', 'org_longname', 'Demo-Organisation', 'DEM_ORGANIZATION_1_LONGNAME'),
            array(TBL_ORGANIZATIONS, 'org_uuid', '8418cd76-3ac9-455f-bfb4-ed6561abdb7b', 'org_longname', 'Test-Organisation', 'DEM_ORGANIZATION_2_LONGNAME'),
            array(TBL_PHOTOS, 'pho_uuid', 'b4aaf3eb-8735-45b3-a2f0-f2a7e9d289eb', 'pho_name', 'Croatia', 'DEM_PHOTO_1_NAME'),
            array(TBL_PHOTOS, 'pho_uuid', 'b4aaf3eb-8735-45b3-a2f0-f2a7e9d289eb', 'pho_description', 'An unforgettable vacation in Croatia with most beautiful sunshine and much nature.', 'DEM_PHOTO_1_DESCRIPTION'),
            array(TBL_PHOTOS, 'pho_uuid', '3d45f9cf-957e-41be-bb48-f452429fcd05', 'pho_name', 'Plitvice lakes', 'DEM_PHOTO_2_NAME'),
            array(TBL_PHOTOS, 'pho_uuid', 'bf174cf8-f190-4898-bb3e-af881ad68780', 'pho_name', 'Krka', 'DEM_PHOTO_3_NAME'),
            array(TBL_PHOTOS, 'pho_uuid', 'f6af3421-f80c-4145-89f2-75bec24640b8', 'pho_name', 'Machu Picchu', 'DEM_PHOTO_4_NAME'),
            array(TBL_PHOTOS, 'pho_uuid', 'f6af3421-f80c-4145-89f2-75bec24640b8', 'pho_description', 'A trip to the legendary Inca city of Machu Picchu in the mountains of Peru.', 'DEM_PHOTO_4_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', 'a8fd58c3-c926-40ca-96fb-5db86bfe6a16', 'rol_name', 'Administrator', 'DEM_ROLE_1_NAME'),
            array(TBL_ROLES, 'rol_uuid', 'a8fd58c3-c926-40ca-96fb-5db86bfe6a16', 'rol_description', 'Group of system administrators', 'DEM_ROLE_1_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', 'd1dc4c6e-eb17-4d1a-a491-237257f6b1fb', 'rol_name', 'Member', 'DEM_ROLE_2_NAME'),
            array(TBL_ROLES, 'rol_uuid', 'd1dc4c6e-eb17-4d1a-a491-237257f6b1fb', 'rol_description', 'All organization members', 'DEM_ROLE_2_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '621fa25f-2fac-4310-af52-af939041cb66', 'rol_name', "Association\'s board", 'DEM_ROLE_3_NAME'),
            array(TBL_ROLES, 'rol_uuid', '621fa25f-2fac-4310-af52-af939041cb66', 'rol_description', 'Administrative board of association', 'DEM_ROLE_3_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '685c8a84-e58c-4d40-8297-8d2671e1fb89', 'rol_name', '1. youth team', 'DEM_ROLE_4_NAME'),
            array(TBL_ROLES, 'rol_uuid', '685c8a84-e58c-4d40-8297-8d2671e1fb89', 'rol_description', 'Young people between 12 and 15 years', 'DEM_ROLE_4_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '685c8a84-e58c-4d40-8297-8d2671e1fb89', 'rol_location', 'Sportplatz', 'DEM_ROLE_4_LOCATION'),
            array(TBL_ROLES, 'rol_uuid', '5f4fb933-806c-4161-a333-212cba85ae6c', 'rol_name', '2. youth team', 'DEM_ROLE_5_NAME'),
            array(TBL_ROLES, 'rol_uuid', '5f4fb933-806c-4161-a333-212cba85ae6c', 'rol_description', 'Young people between 16 and 18 years', 'DEM_ROLE_5_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '5f4fb933-806c-4161-a333-212cba85ae6c', 'rol_location', 'Sportplatz', 'DEM_ROLE_4_LOCATION'),
            array(TBL_ROLES, 'rol_uuid', '7a9e3ff4-197a-48db-9abc-c32c4cc79567', 'rol_name', 'Administrator', 'DEM_ROLE_1_NAME'),
            array(TBL_ROLES, 'rol_uuid', '7a9e3ff4-197a-48db-9abc-c32c4cc79567', 'rol_description', 'Group of system administrators', 'DEM_ROLE_1_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '77b0c6cc-cc66-4384-a34e-3277cdf081c6', 'rol_name', 'Member', 'DEM_ROLE_2_NAME'),
            array(TBL_ROLES, 'rol_uuid', '77b0c6cc-cc66-4384-a34e-3277cdf081c6', 'rol_description', 'All organization members', 'DEM_ROLE_2_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '515c99a1-28d6-4395-b966-4b04cd512f12', 'rol_name', '2026-03-01 17:00 Barbecue', 'DEM_ROLE_8_NAME'),
            array(TBL_ROLES, 'rol_uuid', '515c99a1-28d6-4395-b966-4b04cd512f12', 'rol_description', 'Today we have our barbecue. In addition to crisp sausages, chops and bacon, there are also various salads.', 'DEM_EVENT_4_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '1b3d4123-2898-40e5-b9c4-b4db65207133', 'rol_name', '2025-12-16 11:00 Yoga for beginners', 'DEM_ROLE_9_NAME'),
            array(TBL_ROLES, 'rol_uuid', '1b3d4123-2898-40e5-b9c4-b4db65207133', 'rol_description', 'This course teaches the basics of yoga.<br /><br />A registration for this course is required.', 'DEM_EVENT_14_DESCRIPTION'),
            array(TBL_ROLES, 'rol_uuid', '040b4f49-2e45-460a-a354-1004d8bef27e', 'rol_name', '2026-02-07 18:00 Board meeting', 'DEM_ROLE_10_NAME'),
            array(TBL_ROLES, 'rol_uuid', '7450a81b-5b69-43c6-906b-47e343ecb55f', 'rol_name', '2026-01-03 19:00 Board meeting', 'DEM_ROLE_11_NAME'),
            array(TBL_ROLES, 'rol_uuid', '3c16c9da-9425-4ee3-9b53-8aed1c19bc34', 'rol_name', '2025-12-03 17:00 Team evening', 'DEM_ROLE_12_NAME'),
            array(TBL_ROOMS, 'room_uuid', 'fcc15de8-0c3c-4e2a-a3a5-df20f0fee1c3', 'room_name', 'Meeting room', 'DEM_ROOM_1_NAME'),
            array(TBL_ROOMS, 'room_uuid', 'fcc15de8-0c3c-4e2a-a3a5-df20f0fee1c3', 'room_description', 'In this room meetings can take place. The room must be reserved in advance. A projector is available.', 'DEM_ROOM_1_DESCRIPTION'),
            array(TBL_ROOMS, 'room_uuid', '0faef968-2a2d-41bd-a668-911e322b4e50', 'room_name', 'Function room', 'DEM_ROOM_2_NAME'),
            array(TBL_ROOMS, 'room_uuid', '0faef968-2a2d-41bd-a668-911e322b4e50', 'room_description', "The function room can be used for birthday parties, annual meetings or party\'s. Advance booking is desirable.", 'DEM_ROOM_2_DESCRIPTION'),
            array(TBL_USER_FIELDS, 'usf_uuid', '89b33bc0-913a-404c-9899-e53ad5080fec', 'usf_name', 'Membership number', 'DEM_USER_FIELD_20_NAME'),
            array(TBL_USER_FIELDS, 'usf_uuid', '15b324bc-29d8-4b79-bee9-10072b8d7489', 'usf_name', 'Favorite color', 'DEM_USER_FIELD_21_NAME'),
            array(TBL_USER_FIELDS, 'usf_uuid', '15b324bc-29d8-4b79-bee9-10072b8d7489', 'usf_description', 'Any member may enter his favorite color', 'DEM_USER_FIELD_21_DESCRIPTION'),
        );
        foreach ($entries as $entry) {
            [$table, $key, $keyValue, $column, $expected, $translationId] = $entry;
            self::$db->queryPrepared('UPDATE ' . $table . ' SET ' . $column . ' = ? WHERE ' . $key . ' = ? AND ' . $column . ' = ?', array($translationId, $keyValue, $expected));
        }
    }

    /**
     * Re-hash existing SSO/OIDC token identifiers in place. As of this update, TokenEntity
     * stores only a SHA-256 hash of access/refresh token and auth code identifiers instead
     * of the plaintext value, so lookups by presented token now hash before querying.
     * Existing rows still hold the plaintext identifier and must be converted, or every
     * outstanding access/refresh token would stop validating immediately after the update.
     *
     * @throws Exception
     */
    public static function updateStep51HashSsoTokenIdentifiers(): void
    {
        $tokenColumns = array(
            TBL_OIDC_ACCESS_TOKENS  => array('id' => 'oat_id', 'token' => 'oat_token'),
            TBL_OIDC_REFRESH_TOKENS => array('id' => 'ort_id', 'token' => 'ort_token'),
            TBL_OIDC_AUTH_CODES     => array('id' => 'oac_id', 'token' => 'oac_token'),
        );

        foreach ($tokenColumns as $table => $columns) {
            $selectSql = 'SELECT ' . $columns['id'] . ' AS id, ' . $columns['token'] . ' AS token FROM ' . $table;
            $statement = self::$db->queryPrepared($selectSql);

            while ($row = $statement->fetch()) {
                // A 64-char lowercase hex string is already a SHA-256 hash - running this step
                // twice (e.g. on a retried update) must not double-hash already-migrated rows.
                if (preg_match('/^[0-9a-f]{64}$/', (string) $row['token']) === 1) {
                    continue;
                }

                $updateSql = 'UPDATE ' . $table . ' SET ' . $columns['token'] . ' = ? WHERE ' . $columns['id'] . ' = ?';
                self::$db->queryPrepared($updateSql, array(hash('sha256', (string) $row['token']), $row['id']));
            }
        }
    }

    /**
     * Report the OIDC clients whose subject is a value that can change or be reassigned.
     * OpenID Connect requires the subject to be unique and never reassigned, so a login name
     * and an e-mail address are no longer offered when a client is edited. The stored value
     * is deliberately left alone: changing it would give the relying party a new subject for
     * the same person, and every account it has bound to the old subject would be orphaned.
     * The administrator has to make that decision, so this step only names the clients.
     *
     * @throws Exception
     */
    public static function updateStep51WarnAboutMutableOIDCSubjects(): void
    {
        global $gLogger, $gL10n;

        $sql = 'SELECT ocl_client_name, ocl_userid_field
                  FROM ' . TBL_OIDC_CLIENTS . '
                 WHERE ocl_userid_field NOT IN (\'usr_uuid\', \'usr_id\')
                 ORDER BY ocl_client_name';
        $statement = self::$db->queryPrepared($sql);

        $clients = array();
        while ($row = $statement->fetch()) {
            $clients[] = $row['ocl_client_name'] . ' (' . $row['ocl_userid_field'] . ')';
        }

        if (count($clients) > 0) {
            $gLogger->warning($gL10n->get('INS_WARNING_SSO_OIDC_MUTABLE_SUBJECT', array(implode(', ', $clients))));
        }
    }

    /**
     * This method will convert the charset of the database tables to utf8mb4 if not already done.
     * This is necessary to support emojis and other special characters in the future.
     * @throws Exception
     */
    public static function updateStep51ConvertCharsetToUtf8mb4(): void
    {
        global $g_adm_db, $g_tbl_praefix;

        $sql = 'SELECT table_name
                  FROM information_schema.tables
                 WHERE table_schema = ? -- $g_adm_db
                   AND table_name LIKE ? -- $g_tbl_praefix
                   AND table_type = \'BASE TABLE\'
                   AND (table_collation NOT LIKE \'utf8mb4%\' OR table_collation IS NULL)';

        $admidioTables = self::$db->queryPrepared($sql, array($g_adm_db, $g_tbl_praefix . '_%'));
        while ($row = $admidioTables->fetch()) {
            $tableName = isset($row['table_name']) ? $row['table_name'] : (isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : null);
            $sql = 'ALTER TABLE ' . $tableName . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
            self::$db->queryPrepared($sql);
        }
    }

    /**
     * This method will check if there are still plugins in the old adm_plugins folder. If yes, it will try to move
     * them to the new plugins' folder. If there are old overview plugins, it will try to delete them because they
     * are no longer supported. If there are 3rd party plugins, it will try to move them to the new plugins folder
     * and update the menu entries if necessary. If there are problems with the file system operations, it will set
     * a cookie to show a warning after the update process.
     */
    public static function updateStep51CheckFor3rdPartyPlugins(): void
    {
        global $gLogger, $gL10n;

        $arrayOldOverviewPlugins = array('announcement-list', 'birthday', 'calendar', 'event-list', 'latest-documents-files', 'login_form', 'random_photo', 'who-is-online');
        $oldFolderPluginPath = ADMIDIO_PATH . '/adm_plugins';
        $gWarnOldPlugins = false;
        $gWarn3rdPartyPlugins = false;
        $gInfo3rdPartyPlugins = false;
        $gWarnOldPluginsFolder = false;

        // check if the old adm_plugins folder exists
        if (!is_dir($oldFolderPluginPath)) {
            return;
        }

        // get all folders inside the adm_plugins folder
        $pluginFolders = FileSystemUtils::getDirectoryContent($oldFolderPluginPath, false, true, array(FileSystemUtils::CONTENT_TYPE_DIRECTORY));
        foreach ($pluginFolders as $oldPluginPath => $type) {
            $folderName = basename($oldPluginPath);
            if (in_array($folderName, $arrayOldOverviewPlugins)) {
                // the old plugin is no longer supported, so we remove it
                try {
                    FileSystemUtils::deleteDirectoryIfExists($oldPluginPath, true);
                } catch (RuntimeException|UnexpectedValueException) {
                    // no rights to delete the old folder, then continue the update process
                    $gWarnOldPlugins = true;
                    continue;
                }
            } else {
                // there is a 3rd party plugin installed, so we try to move it to the new plugin folder
                $newPluginPath = ADMIDIO_PATH . FOLDER_PLUGINS . DIRECTORY_SEPARATOR . $folderName;
                try {
                    FileSystemUtils::moveDirectory($oldPluginPath, $newPluginPath);
                    // now we need to check if there is a menu entry for this plugin and if yes we need to update the path by replacing adm_plugins with plugins
                    $sql = 'UPDATE ' . TBL_MENU . ' SET men_url = REPLACE(men_url, \'adm_plugins/' . $folderName . '\', \'' . DIRECTORY_SEPARATOR . FOLDER_PLUGINS . DIRECTORY_SEPARATOR . $folderName . '\') WHERE men_url LIKE \'%adm_plugins/' . $folderName . '%\' ';
                    self::$db->queryPrepared($sql);
                    $gInfo3rdPartyPlugins = true;
                } catch (Exception|PDOException|RuntimeException|UnexpectedValueException) {
                    // no rights to move the old folder, then continue the update process
                    $gWarn3rdPartyPlugins = true;
                    continue;
                }
            }
        }

        if ($gWarnOldPlugins) {
            $gLogger->warning($gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins')));
        }
        if ($gWarn3rdPartyPlugins) {
            $gLogger->warning($gL10n->get('INS_WARNING_3RD_PARTY_PLUGINS_COULD_NOT_BE_MOVED', array('plugins', 'adm_plugins')));
        }
        if ($gInfo3rdPartyPlugins) {
            $gLogger->info($gL10n->get('INS_INFO_3RD_PARTY_PLUGINS_HAVE_BEEN_MOVED', array('plugins')));
        }

        if (!$gWarnOldPlugins && !$gWarn3rdPartyPlugins) {
            // if nothing happened we can delete the old adm_plugins folder
            try {
                FileSystemUtils::deleteDirectoryIfExists($oldFolderPluginPath);
            } catch (RuntimeException|UnexpectedValueException) {
                // no rights to delete the old folder, then continue the update process
                // but warn the user that the folder could not be deleted
                $gWarnOldPluginsFolder = true;
                $gLogger->warning($gL10n->get('INS_WARNING_OLD_ADM_PLUGINS_FOLDER_COULD_NOT_BE_DELETED', array('adm_plugins', 'adm_plugins')));
            }
        }

        // set a cookie to show the warnings/information after the update process
        if ($gWarnOldPlugins || $gWarn3rdPartyPlugins || $gWarnOldPluginsFolder || $gInfo3rdPartyPlugins) {
            $cookieValue = array(
                'warn_old_plugins' => $gWarnOldPlugins,
                'warn_3rd_party_plugins' => $gWarn3rdPartyPlugins,
                'warn_old_plugins_folder' => $gWarnOldPluginsFolder,
                'info_3rd_party_plugins' => $gInfo3rdPartyPlugins
            );
            setcookie('adm_update_plugins_warnings', json_encode($cookieValue), time() + 3600, '/');
        }
    }

    /**
     * This method will check if there are overview plugins available and if yes, it will try to install them.
     * Because we added the new column com_overview_plugin to the components table before, we need to reload the
     * database columns before we can check if the plugin is an overview plugin or not.
     * @return void
     * @throws Exception
     */
    public static function updateStep51InstallOverviewPlugins(): void
    {
        global $gDb;

        // because we added the new column com_overview_plugin to the components table before, we need to reload the database columns
        $gDb->initializeTableColumnProperties();

        $pluginManager = new PluginManager();
        $plugins = $pluginManager->getAvailablePlugins();

        foreach ($plugins as $plugin) {
            // check, if the plugin has an interface, if not, scip it
            if (!isset($plugin['interface']) || $plugin['interface'] == null) {
                continue;
            }
            // check if the plugin is an overview plugin, if so, install it
            $instance = $plugin['interface']::getInstance();
            if ($instance->isAdmidioPlugin()) {
                // Install the overview plugin
                $instance->doInstall();
            }
        }
    }

    /**
     * Give every organization a row for every preference of every plugin.
     *
     * Until now a plugin wrote its preferences with the settings manager of the organization the
     * administrator happened to be in, so every other organization had none. Reading one of them
     * there answered a registered default instead of a stored value, which is not what a
     * preference is.
     *
     * @throws Exception
     */
    public static function updateStep51SeedPluginPreferences(): void
    {
        $pluginManager = new PluginManager();
        $names = array();

        foreach ($pluginManager->getAvailablePlugins() as $plugin) {
            if (!isset($plugin['interface']) || $plugin['interface'] === null) {
                continue;
            }

            // reading the metadata registers the definitions of the plugin
            $instance = $plugin['interface']::getInstance();
            if ($instance->isInstalled()) {
                $names = array_merge($names, $instance->getPreferenceNames());
            }
        }

        PreferencesService::seedDefaults(array_values(array_unique($names)));
    }

    /**
     * This method will add a new profile field BlueSky to the database,
     * but only if the category social networks exists
     * @throws Exception
     */
    public static function updateStep51AddSocialNetworkProfileFields(): void
    {
        global $gProfileFields;

        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_name_intern = \'SOCIAL_NETWORKS\' ';
        $categoriesStatement = self::$db->queryPrepared($sql);

        if ($row = $categoriesStatement->fetch()) {
            $profileFields = $gProfileFields->getProfileFields();

            if (!array_key_exists('BLUESKY', $profileFields)) {
                $profileFieldLinkedIn = new ProfileField(self::$db);
                $profileFieldLinkedIn->saveChangesWithoutRights();
                $profileFieldLinkedIn->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldLinkedIn->setValue('usf_type', 'TEXT');
                $profileFieldLinkedIn->setValue('usf_name_intern', 'BLUESKY');
                $profileFieldLinkedIn->setValue('usf_name', 'SYS_BLUESKY');
                $profileFieldLinkedIn->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_URL_DESC');
                $profileFieldLinkedIn->setValue('usf_icon', 'bluesky');
                $profileFieldLinkedIn->setValue('usf_url', 'https://bsky.app/profile/#user_content#');
                $profileFieldLinkedIn->save();
            }
        }
    }

    /**
     * This method updates wrongly assigned select options for the inventory status field.
     * In previous new Admidio 5.0 installations and additional added organizations, the options were wrongly assigned
     * to the organization id instead of the field id. Therefore, we check whether options exist whose assigned id
     * matches the organization id of a STATUS field and update them to the corresponding field id.
     * @throws Exception
     */
    public static function updateStep50FixInventorySelectOptions(): void
    {
        $statusFields = array();
        $statusFieldOptions = array();

        // Select all item fields with internal name STATUS
        $sql = 'SELECT inf_id, inf_org_id FROM ' . TBL_INVENTORY_FIELDS . ' WHERE inf_name_intern = ?';
        $inventoryStatement = self::$db->queryPrepared($sql, array('STATUS'));

        while ($row = $inventoryStatement->fetch()) {
            $statusFields[] = array( 'id' => (int) $row['inf_id'], 'org_id' => (int) $row['inf_org_id']);
        }

        // Select all status field options present for this installation
        $sql = 'SELECT ifo_id, ifo_inf_id FROM ' . TBL_INVENTORY_FIELD_OPTIONS . ' WHERE ifo_value IN (?, ?)';
        $inventoryOptionStatement = self::$db->queryPrepared( $sql, array('SYS_INVENTORY_FILTER_IN_USE_ITEMS', 'SYS_INVENTORY_FILTER_RETIRED_ITEMS') );

        while ($row = $inventoryOptionStatement->fetch()) {
            $statusFieldOptions[] = array( 'id' => (int) $row['ifo_id'], 'inf_id' => (int) $row['ifo_inf_id']);
        }

        // check if there are options wrongly assigned to the organization id of a status field instead of the corresponding field id and update them
        foreach ($statusFieldOptions as $statusFieldOption) {
            foreach ($statusFields as $statusField) {
                if ($statusField['id'] === $statusFieldOption['inf_id']) {
                    // The option is already assigned to the field
                    continue 2;
                }

                if ($statusField['org_id'] === $statusFieldOption['inf_id']) {
                    // The option is wrongly assigned to the organization id
                    $sql = 'UPDATE ' . TBL_INVENTORY_FIELD_OPTIONS . ' SET ifo_inf_id = ? WHERE ifo_id = ?';
                    self::$db->queryPrepared($sql, array($statusField['id'], $statusFieldOption['id']));
                    continue 2;
                }
            }
        }
    }

    public static function updateStep50MoveFieldListValues(): void
    {
        global $gDbType;

        $sql = 'SELECT usf_id, usf_value_list
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_type IN (\'DROPDOWN\', \'RADIO_BUTTON\')';

        $userFieldsStatement = self::$db->queryPrepared($sql);
        while ($row = $userFieldsStatement->fetch()) {
            $values = explode("\n", $row['usf_value_list']);
            $values = array_map('trim', $values);

            // remove empty values
            $values = array_filter($values, function ($value) {
                return !empty($value);
            });

            if (count($values) > 0) {
                // insert the values into the user field options table
                foreach ($values as $key => $value) {
                    $sql = 'INSERT INTO ' . TBL_USER_FIELD_OPTIONS . ' (ufo_usf_id, ufo_value, ufo_sequence)
                             VALUES (?, ?, ?) -- $row[\'usf_id\'], -- $value, -- $key';

                    self::$db->queryPrepared($sql, array((int)$row['usf_id'], $value, $key + 1));
                }

                if ($gDbType === 'pgsql') {
                    $sqlUfoSequence = 'CAST(ufo_sequence AS CHAR)';
                } else {
                    $sqlUfoSequence = 'ufo_sequence';
                }

                // update the user field values to use the new option id
                $sql = 'UPDATE ' . TBL_USER_DATA . '
                           SET usd_value = (SELECT ufo_id
                                              FROM ' . TBL_USER_FIELD_OPTIONS . '
                                             WHERE ufo_usf_id = usd_usf_id
                                               AND ' . $sqlUfoSequence . ' = usd_value)
                         WHERE usd_usf_id = ? -- $row[\'usf_id\'] ';
                self::$db->queryPrepared($sql, array((int)$row['usf_id']));
            }
        }
    }

    /**
     * Add default fields for the inventory module.
     * @throws Exception
     */
    public static function updateStep50AddInventoryFields(): void
    {
        $arrItemFields = array(
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'ITEMNAME', 'inf_name' => 'SYS_INVENTORY_ITEMNAME', 'inf_description' => 'SYS_INVENTORY_ITEMNAME_DESC', 'inf_required_input' => 1, 'inf_sequence' => 0),
            array('inf_type' => 'CATEGORY', 'inf_name_intern' => 'CATEGORY', 'inf_name' => 'SYS_CATEGORY', 'inf_description' => 'SYS_INVENTORY_CATEGORY_DESC', 'inf_required_input' => 1, 'inf_sequence' => 1),
            array('inf_type' => 'DROPDOWN', 'inf_name_intern' => 'STATUS', 'inf_name' => 'SYS_INVENTORY_STATUS', 'inf_description' => 'SYS_INVENTORY_STATUS_DESC', 'inf_required_input' => 1, 'inf_sequence' => 2),
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'KEEPER', 'inf_name' => 'SYS_INVENTORY_KEEPER', 'inf_description' => 'SYS_INVENTORY_KEEPER_DESC', 'inf_required_input' => 0, 'inf_sequence' => 3),
            array('inf_type' => 'TEXT', 'inf_name_intern' => 'LAST_RECEIVER', 'inf_name' => 'SYS_INVENTORY_LAST_RECEIVER', 'inf_description' => 'SYS_INVENTORY_LAST_RECEIVER_DESC', 'inf_required_input' => 0, 'inf_sequence' => 4),
            array('inf_type' => 'DATE', 'inf_name_intern' => 'BORROW_DATE', 'inf_name' => 'SYS_INVENTORY_BORROW_DATE', 'inf_description' => 'SYS_INVENTORY_BORROW_DATE_DESC', 'inf_required_input' => 0, 'inf_sequence' => 5),
            array('inf_type' => 'DATE', 'inf_name_intern' => 'RETURN_DATE', 'inf_name' => 'SYS_INVENTORY_RETURN_DATE', 'inf_description' => 'SYS_INVENTORY_RETURN_DATE_DESC', 'inf_required_input' => 0, 'inf_sequence' => 6)
        );

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);
        // create item fields for each organization
        while ($row = $organizationStatement->fetch()) {
            foreach ($arrItemFields as $itemFieldData) {
                $itemField = new ItemField(self::$db);
                $itemField->saveChangesWithoutRights();
                $itemField->setValue('inf_org_id', (int)$row['org_id']);
                $itemField->setValue('inf_type', $itemFieldData['inf_type']);
                $itemField->setValue('inf_name_intern', $itemFieldData['inf_name_intern']);
                $itemField->setValue('inf_name', $itemFieldData['inf_name']);
                $itemField->setValue('inf_description', $itemFieldData['inf_description']);
                $itemField->setValue('inf_system', 1);
                $itemField->setValue('inf_required_input', (int)$itemFieldData['inf_required_input']);
                $itemField->setValue('inf_sequence', (int)$itemFieldData['inf_sequence']);
                $itemField->save();
            }

            // add default options for the status field
            $sql = 'SELECT inf_id FROM ' . TBL_INVENTORY_FIELDS . '
                 WHERE inf_name_intern = \'STATUS\' 
                 and inf_org_id = ? -- $row[org_id] ';
            $statusFieldId = self::$db->queryPrepared($sql, array((int)$row['org_id']))->fetchColumn();

            if ($statusFieldId !== false) {
                $arrStatusOptions = array(
                    array('ifo_value' => 'SYS_INVENTORY_FILTER_IN_USE_ITEMS', 'ifo_sequence' => 1),
                    array('ifo_value' => 'SYS_INVENTORY_FILTER_RETIRED_ITEMS', 'ifo_sequence' => 2),
                );

                foreach ($arrStatusOptions as $statusOption) {
                    $sql = 'INSERT INTO ' . TBL_INVENTORY_FIELD_OPTIONS . '
                         (ifo_inf_id, ifo_value, ifo_system, ifo_sequence)
                         VALUES (?, ?, ?, ?)';
                    self::$db->queryPrepared($sql, array($statusFieldId, $statusOption['ifo_value'], true, $statusOption['ifo_sequence']));
                }
            }
        }
    }

    /**
     * Create categories for the inventory for each organization.
     * @throws Exception
     */
    public static function updateStep50InventoryCategories(): void
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                           (cat_org_id, cat_uuid, cat_type, cat_name_intern, cat_name, cat_system, cat_default, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, ?, \'IVT\', \'COMMON\', \'SYS_COMMON\', false, true, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            self::$db->queryPrepared($sql, array((int)$row['org_id'], Uuid::uuid4(), $systemUserId, DATETIME_NOW));

            // set edit role rights to inventory categories for administrator role
            $sql = 'SELECT rol_id
                    FROM ' . TBL_ROLES . '
                    INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                    AND cat_org_id = ? -- $row[\'org_id\']
                    AND cat_type = \'ROL\'
                    WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\') ';
            $pdoStatement = self::$db->queryPrepared($sql, array($row['org_id'], $gL10n->get('SYS_ADMINISTRATOR')));
            if (($row2 = $pdoStatement->fetch()) !== false) {
                // set edit role rights to inventory categories for role administrator
                $category = new Category(self::$db);
                $category->readDataByColumns(array('cat_org_id' => (int)$row['org_id'], 'cat_type' => 'IVT'));

                $rightCategoryView = new RolesRights(self::$db, 'category_edit', $category->getValue('cat_id'));
                $rightCategoryView->saveRoles(array($row2['rol_id']));
            }
        }
    }

    /**
     * This method will update the sequence of the links in the database.
     * The sequence is used to sort the links within a category.
     * The sequence starts with 1 for each category and is incremented by 1 for each link.
     * @throws Exception
     */
    public static function updateStep50AddLinkSequence(): void
    {
        $sql = 'SELECT lnk_id, lnk_cat_id FROM ' . TBL_LINKS . ' ORDER BY lnk_cat_id, lnk_id';
        $statement = self::$db->queryPrepared($sql);
        $currentCatId = null;
        $sequence = 1;

        while ($row = $statement->fetch()) {
            if ($currentCatId !== $row['lnk_cat_id']) {
                $currentCatId = $row['lnk_cat_id'];
                $sequence = 1;
            }
            $updateSql = 'UPDATE ' . TBL_LINKS . ' SET lnk_sequence = ? WHERE lnk_id = ?';
            self::$db->queryPrepared($updateSql, [$sequence, $row['lnk_id']]);
            $sequence++;
        }
    }

    /**
     * Create categories for the forum and each organization.
     * @throws Exception
     */
    public static function updateStep50ForumCategories(): void
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            // create organization depending on category for events
            $category = new Category(self::$db);
            $category->setValue('cat_org_id', (int)$row['org_id']);
            $category->setValue('cat_type', 'FOT');
            $category->setValue('cat_name_intern', 'COMMON');
            $category->setValue('cat_name', $gL10n->get('SYS_COMMON'));
            $category->setValue('cat_default', '1');
            $category->save();

            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                     INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                       AND cat_org_id = ? -- $row[\'org_id\']
                       AND cat_type = \'ROL\'
                     WHERE rol_name = ? -- $gL10n->get(\'SYS_MEMBER\') ';
            $pdoStatement = self::$db->queryPrepared($sql, array($row['org_id'], $gL10n->get('SYS_MEMBER')));

            if (($row = $pdoStatement->fetch()) !== false) {
                // set edit role rights to forum categories for role member
                $rightCategoryView = new RolesRights(self::$db, 'category_edit', $category->getValue('cat_id'));
                $rightCategoryView->saveRoles(array($row['rol_id']));
            }
        }
    }

    /**
     * This method will add an uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep50AddUuid(): void
    {
        $updateTablesUuid = array(
            array('table' => TBL_MESSAGES_ATTACHMENTS, 'column_id' => 'msa_id', 'column_uuid' => 'msa_uuid'),
            array('table' => TBL_USER_RELATIONS, 'column_id' => 'ure_id', 'column_uuid' => 'ure_uuid')
        );

        foreach ($updateTablesUuid as $tableUuid) {
            $sql = 'SELECT ' . $tableUuid['column_id'] . '
                      FROM ' . $tableUuid['table'] . '
                     WHERE ' . $tableUuid['column_uuid'] . ' IS NULL ';
            $statement = self::$db->queryPrepared($sql);

            while ($row = $statement->fetch()) {
                $uuid = Uuid::uuid4();

                $sql = 'UPDATE ' . $tableUuid['table'] . ' SET ' . $tableUuid['column_uuid'] . ' = ? -- $uuid
                     WHERE ' . $tableUuid['column_id'] . ' = ? -- $row[$tableUuid[\'column_id\']]';
                self::$db->queryPrepared($sql, array($uuid, $row[$tableUuid['column_id']]));
            }
        }

        self::$db->initializeTableColumnProperties();
    }

    /**
     * Repair the path of the folders
     */
    public static function updateStep43RepairDocumentsPath(): void
    {
        $maintenance = new Maintenance(self::$db);
        $maintenance->repairDocumentsFilesPath();
    }

    /**
     * This method removes wrong configured visible roles of category Basic_Data
     * @throws Exception
     */
    public static function updateStep43RemoveInvalidVisibleRoleRights(): void
    {
        $sql = 'SELECT rrd_id
                  FROM ' . TBL_CATEGORIES . '
                 INNER JOIN ' . TBL_ROLES_RIGHTS . ' ON ror_name_intern = \'category_view\'
                 INNER JOIN ' . TBL_ROLES_RIGHTS_DATA . ' ON rrd_ror_id = ror_id
                   AND rrd_object_id = cat_id
                 WHERE cat_name_intern = \'BASIC_DATA\' ';
        $rolesRightsStatement = self::$db->queryPrepared($sql);

        while ($row = $rolesRightsStatement->fetch()) {
            // save roles to role right
            $rolesRights = new Entity(self::$db, TBL_ROLES_RIGHTS_DATA, 'rrd', (int)$row['rrd_id']);
            $rolesRights->delete();
        }
    }

    /**
     * This method will add a new profile field LinkedIn and Instagram to the database,
     * but only if the category social networks exists
     * @throws Exception
     */
    public static function updateStep43AddSocialNetworkProfileFields(): void
    {
        global $gProfileFields;

        $sql = 'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_name_intern = \'SOCIAL_NETWORKS\' ';
        $categoriesStatement = self::$db->queryPrepared($sql);

        if ($row = $categoriesStatement->fetch()) {
            $profileFields = $gProfileFields->getProfileFields();

            if (!array_key_exists('LINKEDIN', $profileFields)) {
                $profileFieldLinkedIn = new ProfileField(self::$db);
                $profileFieldLinkedIn->saveChangesWithoutRights();
                $profileFieldLinkedIn->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldLinkedIn->setValue('usf_type', 'TEXT');
                $profileFieldLinkedIn->setValue('usf_name_intern', 'LINKEDIN');
                $profileFieldLinkedIn->setValue('usf_name', 'SYS_LINKEDIN');
                $profileFieldLinkedIn->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_URL_DESC');
                $profileFieldLinkedIn->setValue('usf_icon', 'linkedin');
                $profileFieldLinkedIn->setValue('usf_url', 'https://www.linkedin.com/in/#user_content#');
                $profileFieldLinkedIn->save();
            }

            if (!array_key_exists('INSTAGRAM', $profileFields)) {
                $profileFieldInstagram = new ProfileField(self::$db);
                $profileFieldInstagram->saveChangesWithoutRights();
                $profileFieldInstagram->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldInstagram->setValue('usf_type', 'TEXT');
                $profileFieldInstagram->setValue('usf_name_intern', 'INSTAGRAM');
                $profileFieldInstagram->setValue('usf_name', 'SYS_INSTAGRAM');
                $profileFieldInstagram->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_URL_DESC');
                $profileFieldInstagram->setValue('usf_icon', 'instagram');
                $profileFieldInstagram->setValue('usf_url', 'https://www.instagram.com/#user_content#');
                $profileFieldInstagram->save();
            }

            if (!array_key_exists('MASTODON', $profileFields)) {
                $profileFieldInstagram = new ProfileField(self::$db);
                $profileFieldInstagram->saveChangesWithoutRights();
                $profileFieldInstagram->setValue('usf_cat_id', (int)$row['cat_id']);
                $profileFieldInstagram->setValue('usf_type', 'TEXT');
                $profileFieldInstagram->setValue('usf_name_intern', 'MASTODON');
                $profileFieldInstagram->setValue('usf_name', 'SYS_MASTODON');
                $profileFieldInstagram->setValue('usf_description', 'SYS_SOCIAL_NETWORK_FIELD_URL_DESC');
                $profileFieldInstagram->setValue('usf_icon', 'mastodon');
                $profileFieldInstagram->setValue('usf_url', 'https://mastodon.social/#user_content#');
                $profileFieldInstagram->save();
            }
        }
    }

    /**
     * This method will add a new systemmail text to the database table **adm_texts** for each
     * organization in the database.
     * @throws Exception
     */
    public static function updateStep43AddNewNotificationText(): void
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $textPasswordReset = new Text(self::$db);
            $textPasswordReset->setValue('txt_org_id', $row['org_id']);
            $textPasswordReset->setValue('txt_name', 'SYSMAIL_REGISTRATION_CONFIRMATION');
            $textPasswordReset->setValue('txt_text', $gL10n->get('SYS_SYSMAIL_REGISTRATION_CONFIRMATION'));
            $textPasswordReset->save();
        }
    }

    /**
     * This method only execute a SQL statement but because of the use of & it could not be done in our XML structure
     * @throws Exception
     */
    public static function updateStep41CleanUpRoleNames(): void
    {
        $sql = 'UPDATE ' . TBL_ROLES . ' SET rol_name = REPLACE(rol_name, \'&nbsp;&nbsp;\', \' \') ';
        self::$db->queryPrepared($sql);
    }

    /**
     * This method will add a new default list for the members management module. This list will be used to configure
     * and show the columns of the members management overview.
     * @throws Exception
     */
    public static function updateStep41CleanUpInternalNameProfileFields(): void
    {
        $sql = 'SELECT * FROM ' . TBL_USER_FIELDS;
        $userFieldsStatement = self::$db->queryPrepared($sql);

        while ($row = $userFieldsStatement->fetch()) {
            $userField = new ProfileField(self::$db);
            $userField->setArray($row);
            $userField->saveChangesWithoutRights();

            $userField->setValue('usf_name_intern',
                strtoupper(preg_replace('/[^A-Za-z0-9_]/', '',
                    str_replace(' ', '_', $userField->getValue('usf_name_intern')))));
            $userField->save();
        }
    }

    /**
     * This method will add an uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep41PostgreSqlSetBoolean(): void
    {
        $updateColumnsBoolean = array(
            array('table' => TBL_CATEGORIES, 'column' => 'cat_system'),
            array('table' => TBL_CATEGORIES, 'column' => 'cat_default'),
            array('table' => TBL_DATES, 'column' => 'dat_all_day'),
            array('table' => TBL_DATES, 'column' => 'dat_highlight'),
            array('table' => TBL_DATES, 'column' => 'dat_allow_comments'),
            array('table' => TBL_DATES, 'column' => 'dat_additional_guests'),
            array('table' => TBL_FILES, 'column' => 'fil_locked'),
            array('table' => TBL_FOLDERS, 'column' => 'fol_locked'),
            array('table' => TBL_FOLDERS, 'column' => 'fol_public'),
            array('table' => TBL_GUESTBOOK, 'column' => 'gbo_locked'),
            array('table' => TBL_GUESTBOOK_COMMENTS, 'column' => 'gbc_locked'),
            array('table' => TBL_LISTS, 'column' => 'lst_global'),
            array('table' => TBL_MEMBERS, 'column' => 'mem_leader'),
            array('table' => TBL_MENU, 'column' => 'men_node'),
            array('table' => TBL_MENU, 'column' => 'men_standard'),
            array('table' => TBL_PHOTOS, 'column' => 'pho_locked'),
            array('table' => TBL_ROLES, 'column' => 'rol_assign_roles'),
            array('table' => TBL_ROLES, 'column' => 'rol_approve_users'),
            array('table' => TBL_ROLES, 'column' => 'rol_announcements'),
            array('table' => TBL_ROLES, 'column' => 'rol_dates'),
            array('table' => TBL_ROLES, 'column' => 'rol_documents_files'),
            array('table' => TBL_ROLES, 'column' => 'rol_edit_user'),
            array('table' => TBL_ROLES, 'column' => 'rol_guestbook'),
            array('table' => TBL_ROLES, 'column' => 'rol_guestbook_comments'),
            array('table' => TBL_ROLES, 'column' => 'rol_mail_to_all'),
            array('table' => TBL_ROLES, 'column' => 'rol_photo'),
            array('table' => TBL_ROLES, 'column' => 'rol_profile'),
            array('table' => TBL_ROLES, 'column' => 'rol_weblinks'),
            array('table' => TBL_ROLES, 'column' => 'rol_all_lists_view'),
            array('table' => TBL_ROLES, 'column' => 'rol_default_registration'),
            array('table' => TBL_ROLES, 'column' => 'rol_valid'),
            array('table' => TBL_ROLES, 'column' => 'rol_system'),
            array('table' => TBL_ROLES, 'column' => 'rol_administrator'),
            array('table' => TBL_SESSIONS, 'column' => 'ses_reload'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_description_inline'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_system'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_disabled'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_hidden'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_mandatory'),
            array('table' => TBL_USER_FIELDS, 'column' => 'usf_registration'),
            array('table' => TBL_USERS, 'column' => 'usr_valid'),
            array('table' => TBL_USER_RELATION_TYPES, 'column' => 'urt_edit_user')
        );

        foreach ($updateColumnsBoolean as $columnsBoolean) {
            $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' drop default';
            self::$db->queryPrepared($sql);

            $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DATA TYPE boolean using ' . $columnsBoolean['column'] . '::integer::boolean';
            self::$db->queryPrepared($sql);

            if ($columnsBoolean['column'] === 'rol_valid') {
                $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DEFAULT true';
            } else {
                $sql = 'ALTER TABLE ' . $columnsBoolean['table'] . ' ALTER COLUMN ' . $columnsBoolean['column'] . ' SET DEFAULT false';
            }
            self::$db->queryPrepared($sql);
        }
    }

    /**
     * This method will move the folder with the ecard templates to the adm_my_files folder
     */
    public static function updateStep41MoveEcardTemplates(): void
    {
        global $gLogger;

        $ecardThemeFolder = ADMIDIO_PATH . FOLDER_THEMES . '/' . $GLOBALS['gSettingsManager']->getString('theme') . '/ecard_templates';
        $ecardMyFilesFolder = ADMIDIO_PATH . FOLDER_DATA . '/ecard_templates';

        if (is_dir($ecardThemeFolder)) {
            try {
                FileSystemUtils::copyDirectory($ecardThemeFolder, $ecardMyFilesFolder);
            } catch (RuntimeException $exception) {
                $gLogger->error('Could not copy directory from ' . $ecardThemeFolder . ' to ' . $ecardMyFilesFolder . '. Please check if Admidio have write rights within adm_my_files.');
                return;
                // => EXIT
            }

            try {
                FileSystemUtils::deleteDirectoryIfExists($ecardThemeFolder);
            } catch (RuntimeException $exception) {
                // no rights to delete the old folder, then continue the update process
                return;
                // => EXIT
            }
        }
    }

    /**
     * This method will migrate the database entries
     * from plugin Kategoriereport (table adm_plugin_preferences)
     * to module category report (table adm_category_report).
     * @throws Exception
     */
    public static function updateStep41CategoryReportMigration(): void
    {
        global $gL10n, $gProfileFields;

        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            $orgId = (int)$organization['org_id'];
            $config = array();

            // check whether a configdata.php exists for the category report plugin
            $file = ADMIDIO_PATH . FOLDER_PLUGINS . '/kategoriereport/configdata.php';
            if (file_exists($file)) {
                include $file; // the value of $dbtoken is required here

                // check whether the table 'adm_plugin_preferences' exists
                $tableName = TABLE_PREFIX . '_plugin_preferences';
                $sql = 'SHOW TABLES LIKE \'' . $tableName . '\' ';
                $tableExistStatement = self::$db->queryPrepared($sql);

                if ($tableExistStatement->rowCount()) {
                    // Read in configuration(s) with 'PKR_...'
                    $sql = 'SELECT plp_id, plp_name, plp_value
                 	          FROM ' . $tableName . '
                 	         WHERE plp_name LIKE ?
                 	           AND (plp_org_id = ?
                     	        OR plp_org_id IS NULL ) ';
                    $statement = self::$db->queryPrepared($sql, array('PKR__%', $orgId));

                    while ($row = $statement->fetch()) {
                        $array = explode('__', $row['plp_name']);

                        if ((str_starts_with($row['plp_value'], '((')) && (str_ends_with($row['plp_value'], '))'))) {
                            $row['plp_value'] = substr($row['plp_value'], 2, -2);
                            $config[$array[2]] = explode($dbtoken, $row['plp_value']);
                        } else {
                            $config[$array[2]] = $row['plp_value'];
                        }
                    }
                }
            }

            // if $config is still empty now, then there was no configuration data of the plugin
            // --> create sample configuration
            if (empty($config)) {
                $config['col_desc'] = array($gL10n->get('SYS_GENERAL_ROLE_ASSIGNMENT'));
                $config['col_fields'] = array('p' . $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('LAST_NAME', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('STREET', 'usf_id') . ',' .
                    'p' . $gProfileFields->getProperty('CITY', 'usf_id'));
                $config['selection_role'] = array('');
                $config['selection_cat'] = array('');
                $config['number_col'] = array(0);
                $config['config_default'] = 0;

                // Read out the role IDs of the "Administrator", "Board" and "Member" roles
                $role = new Entity(self::$db, TBL_ROLES, 'rol');
                $role->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'rol_cat_id');
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('SYS_ADMINISTRATOR'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('INS_BOARD'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
                if ($role->readDataByColumns(array('rol_name' => $gL10n->get('SYS_MEMBER'), 'cat_org_id' => $orgId))) {
                    $config['col_fields'][0] .= ',r' . $role->getValue('rol_id');
                }
            }

            // Write "Kategoriereport" configurations or sample configuration into adm_category_report table
            foreach ($config['col_desc'] as $i => $dummy) {
                $categoryReport = new Entity(self::$db, TBL_CATEGORY_REPORT, 'crt');

                $categoryReport->setValue('crt_org_id', $orgId);
                $categoryReport->setValue('crt_name', $config['col_desc'][$i]);
                $categoryReport->setValue('crt_col_fields', $config['col_fields'][$i]);
                $categoryReport->setValue('crt_selection_role', $config['selection_role'][$i]);
                $categoryReport->setValue('crt_selection_cat', $config['selection_cat'][$i]);
                $categoryReport->setValue('crt_number_col', $config['number_col'][$i]);
                $categoryReport->save();

                if ($config['config_default'] == $i) {
                    $sql = 'UPDATE ' . TBL_PREFERENCES . '
                               SET prf_value  = ? -- $categoryReport->getValue(\'crt_id\')
                             WHERE prf_org_id = ? -- $orgId
                               AND prf_name   = \'category_report_default_configuration\'';
                    self::$db->queryPrepared($sql, array((int)$categoryReport->getValue('crt_id'), $orgId));
                }
            }
        }
    }

    /**
     * This method will add an uuid to each row of the tables adm_users and adm_roles
     * @throws Exception
     */
    public static function updateStep41AddUuid(): void
    {
        $updateTablesUuid = array(
            array('table' => TBL_ANNOUNCEMENTS, 'column_id' => 'ann_id', 'column_uuid' => 'ann_uuid'),
            array('table' => TBL_CATEGORIES, 'column_id' => 'cat_id', 'column_uuid' => 'cat_uuid'),
            array('table' => TBL_DATES, 'column_id' => 'dat_id', 'column_uuid' => 'dat_uuid'),
            array('table' => TBL_FILES, 'column_id' => 'fil_id', 'column_uuid' => 'fil_uuid'),
            array('table' => TBL_FOLDERS, 'column_id' => 'fol_id', 'column_uuid' => 'fol_uuid'),
            array('table' => TBL_GUESTBOOK, 'column_id' => 'gbo_id', 'column_uuid' => 'gbo_uuid'),
            array('table' => TBL_GUESTBOOK_COMMENTS, 'column_id' => 'gbc_id', 'column_uuid' => 'gbc_uuid'),
            array('table' => TBL_LINKS, 'column_id' => 'lnk_id', 'column_uuid' => 'lnk_uuid'),
            array('table' => TBL_PHOTOS, 'column_id' => 'pho_id', 'column_uuid' => 'pho_uuid'),
            array('table' => TBL_LISTS, 'column_id' => 'lst_id', 'column_uuid' => 'lst_uuid'),
            array('table' => TBL_MENU, 'column_id' => 'men_id', 'column_uuid' => 'men_uuid'),
            array('table' => TBL_MEMBERS, 'column_id' => 'mem_id', 'column_uuid' => 'mem_uuid'),
            array('table' => TBL_MESSAGES, 'column_id' => 'msg_id', 'column_uuid' => 'msg_uuid'),
            array('table' => TBL_ORGANIZATIONS, 'column_id' => 'org_id', 'column_uuid' => 'org_uuid'),
            array('table' => TBL_ROLES, 'column_id' => 'rol_id', 'column_uuid' => 'rol_uuid'),
            array('table' => TBL_ROOMS, 'column_id' => 'room_id', 'column_uuid' => 'room_uuid'),
            array('table' => TBL_USERS, 'column_id' => 'usr_id', 'column_uuid' => 'usr_uuid'),
            array('table' => TBL_USER_FIELDS, 'column_id' => 'usf_id', 'column_uuid' => 'usf_uuid'),
            array('table' => TBL_USER_RELATION_TYPES, 'column_id' => 'urt_id', 'column_uuid' => 'urt_uuid')
        );

        foreach ($updateTablesUuid as $tableUuid) {
            $sql = 'SELECT ' . $tableUuid['column_id'] . '
                      FROM ' . $tableUuid['table'] . '
                     WHERE ' . $tableUuid['column_uuid'] . ' IS NULL ';
            $statement = self::$db->queryPrepared($sql);

            while ($row = $statement->fetch()) {
                $uuid = Uuid::uuid4();

                $sql = 'UPDATE ' . $tableUuid['table'] . ' SET ' . $tableUuid['column_uuid'] . ' = ? -- $uuid
                     WHERE ' . $tableUuid['column_id'] . ' = ? -- $row[$tableUuid[\'column_id\']]';
                self::$db->queryPrepared($sql, array($uuid, $row[$tableUuid['column_id']]));
            }
        }

        self::$db->initializeTableColumnProperties();
    }

    /**
     * This method will add a new default list for the members management module. This list will be used to configure
     * and show the columns of the members management overview.
     * @throws Exception
     */
    public static function updateStep41AddMembersManagementDefaultList(): void
    {
        global $gL10n, $gProfileFields;

        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            // add default configuration
            $userManagementList = new ListConfiguration(self::$db);
            $userManagementList->setValue('lst_name', $gL10n->get('SYS_CONTACTS'));
            $userManagementList->setValue('lst_org_id', (int)$organization['org_id']);
            $userManagementList->setValue('lst_global', 1);
            $userManagementList->addColumn((int)$gProfileFields->getProperty('LAST_NAME', 'usf_id'), 0, 'ASC');
            $userManagementList->addColumn((int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id'), 0, 'ASC');
            $userManagementList->addColumn('usr_login_name');
            $userManagementList->addColumn((int)$gProfileFields->getProperty('GENDER', 'usf_id'));
            $userManagementList->addColumn((int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id'));
            $userManagementList->addColumn((int)$gProfileFields->getProperty('CITY', 'usf_id'));
            $userManagementList->addColumn('usr_timestamp_change');
            $userManagementList->save();

            // save default list to preferences
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ? -- $userManagementList->getValue(\'lst_id\')
                     WHERE prf_org_id = ? -- $organization[\'org_id\']
                       AND prf_name = \'members_list_configuration\' ';
            self::$db->queryPrepared($sql, array($userManagementList->getValue('lst_id'), (int)$organization['org_id']));
        }
    }

    /**
     * This method will add a new systemmail text to the database table **adm_texts** for each
     * organization in the database.
     * @throws Exception
     */
    public static function updateStep41AddSystemmailText(): void
    {
        global $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $textPasswordReset = new Text(self::$db);
            $textPasswordReset->setValue('txt_org_id', $row['org_id']);
            $textPasswordReset->setValue('txt_name', 'SYSMAIL_PASSWORD_RESET');
            $textPasswordReset->setValue('txt_text', $gL10n->get('SYS_SYSMAIL_PASSWORD_RESET'));
            $textPasswordReset->save();
        }
    }

    /**
     * This method will migrate the recipients of messages from the database column msg_usr_id_receiver
     * to the new table adm_messages_recipients. There each recipient will be added in a separate row that
     * reference to the message.
     * @throws Exception
     */
    public static function updateStep41MigrateMessageRecipients(): void
    {
        $sql = 'SELECT msg_id, msg_usr_id_receiver FROM ' . TBL_MESSAGES;
        $messagesStatement = self::$db->queryPrepared($sql);

        while ($row = $messagesStatement->fetch()) {
            $messageRecipient = new Entity(self::$db, TBL_MESSAGES_RECIPIENTS, 'msr');
            $recipientsSplit = explode('|', $row['msg_usr_id_receiver']);

            foreach ($recipientsSplit as $recipients) {
                $messageRecipient->clear();
                $messageRecipient->setValue('msr_msg_id', $row['msg_id']);

                if (str_contains($recipients, ':')) {
                    $groupSplit = explode(':', $recipients);
                    $groupIdAndStatus = explode('-', trim($groupSplit[1]));
                    $messageRecipient->setValue('msr_rol_id', $groupIdAndStatus[0]);

                    // set mode of the role (active, former, former and active)
                    if (count($groupIdAndStatus) === 1) {
                        $messageRecipient->setValue('msr_role_mode', 0);
                    } else {
                        $messageRecipient->setValue('msr_role_mode', $groupIdAndStatus[1]);
                    }
                } else {
                    $messageRecipient->setValue('msr_usr_id', (int)trim($recipients));
                }
                $messageRecipient->save();
            }
        }
    }

    /**
     * This method adds the email template to the preferences
     * @throws Exception
     */
    public static function updateStep40AddEmailTemplate(): void
    {
        if (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/template.html')) {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'template.html\' WHERE prf_name = \'mail_template\'';
        } elseif (file_exists(ADMIDIO_PATH . FOLDER_DATA . '/mail_templates/default.html')) {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'default.html\' WHERE prf_name = \'mail_template\'';
        } else {
            $sql = 'UPDATE ' . TBL_PREFERENCES . ' SET prf_value = \'\' WHERE prf_name = \'mail_template\'';
        }
        self::$db->queryPrepared($sql);
    }

    /**
     * Rename the existing folder of the old download module to the new documents and files module
     * with the prefix 'documents' and the shortname of the current organization.
     * @throws Exception
     */
    public static function updateStep40RenameDownloadRootFolder(): void
    {
        global $gLogger;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $organization = new Organization(self::$db, $rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM ' . TBL_FOLDERS . '
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = self::$db->queryPrepared($sql, array($rowId));

            if ($rowFolder = $folderStatement->fetch()) {
                $folder = new Folder(self::$db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', Folder::getRootFolderName('documents', $organization->getValue('org_shortname')));
                $folder->save();

                $sql = 'UPDATE ' . TBL_FOLDERS . '
                           SET fol_path = REPLACE(fol_path, \'/' . $rowFolder['fol_name'] . '\', \'/' . Folder::getRootFolderName('documents', $organization->getValue('org_shortname')) . '\')
                         WHERE fol_org_id = ' . $rowId;
                self::$db->query($sql); // TODO add more params

                if (is_dir($folderOldName)) {
                    try {
                        //rename($folderOldName, $folder->getFullFolderPath());
                        FileSystemUtils::moveDirectory($folderOldName, $folder->getFullFolderPath());
                    } catch (RuntimeException $exception) {
                        $gLogger->error('Could not move directory!', array('from' => $folderOldName, 'to' => $folder->getFullFolderPath()));
                        // TODO
                    }
                }
            }
        }
    }

    /**
     * This method will migrate all names of the event roles from the former technical name to the name of the event
     * @throws Exception
     * @throws \Exception
     */
    public static function updateStep40RenameParticipationRoles(): void
    {
        global $gSettingsManager;

        $sql = 'SELECT *
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'EVENTS\' ';
        $rolesStatement = self::$db->queryPrepared($sql);

        while ($row = $rolesStatement->fetch()) {
            $role = new Entity(self::$db, TBL_ROLES, 'rol');
            $role->setArray($row);
            $role->saveChangesWithoutRights();

            $sql = 'SELECT *
                      FROM ' . TABLE_PREFIX . '_dates
                     WHERE dat_rol_id = ? ';
            $eventStatement = self::$db->queryPrepared($sql, array($role->getValue('rol_id')));
            $eventRow = $eventStatement->fetch();

            $datetime = new DateTime($eventRow['dat_begin']);
            $beginDate = $datetime->format($gSettingsManager->getString('system_date')) . ' ';

            if ($eventRow['dat_all_day'] != 1) {
                $datetime = new DateTime($eventRow['dat_begin']);
                $beginDate .= $datetime->format($gSettingsManager->getString('system_time'));
            }

            $role->setValue('rol_name', $beginDate . ' ' . $eventRow['dat_headline']);
            $role->setValue('rol_description', substr($eventRow['dat_description'], 0, 3999));
            $role->save();
        }
    }

    /**
     * This method adds a new global list configuration for participants of events.
     * @throws Exception
     */
    public static function updateStep33AddDefaultParticipantList(): void
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            // Add new list configuration
            $sql = 'INSERT INTO ' . TBL_LISTS . '
                           (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global)
                    VALUES (?, ?, ?, ?, 1) -- $rowId, $systemUserId, $gL10n->get(\'SYS_PARTICIPANTS\'), DATETIME_NOW';
            $params = array(
                $rowId,
                $systemUserId,
                $gL10n->get('SYS_PARTICIPANTS'),
                DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            // Add list columns
            $sql = 'SELECT lst_id
                      FROM ' . TBL_LISTS . '
                     WHERE lst_name = ? -- $gL10n->get(\'SYS_PARTICIPANTS\')
                       AND lst_org_id = ? -- $rowId';
            $listStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_PARTICIPANTS'), $rowId));
            $listId = (int)$listStatement->fetchColumn();

            $sql = 'INSERT INTO ' . TBL_LIST_COLUMNS . '
                           (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter)
                    VALUES (?, 1, (SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = \'LAST_NAME\'),  NULL, \'ASC\', NULL) -- $listId
                         , (?, 2, (SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = \'FIRST_NAME\'), NULL, NULL,    NULL) -- $listId
                         , (?, 3, NULL, \'mem_approved\',     NULL,    NULL) -- $listId
                         , (?, 4, NULL, \'mem_comment\',      NULL,    NULL) -- $listId
                         , (?, 5, NULL, \'mem_count_guests\', NULL,    NULL) -- $listId';
            self::$db->queryPrepared($sql, array($listId, $listId, $listId, $listId, $listId));

            // Set as default configuration list
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value = ? -- $listId
                     WHERE prf_name = \'dates_default_list_configuration\'
                       AND prf_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($listId, $rowId));
        }
    }

    /**
     * This method adds new categories for all organizations.
     * @throws Exception
     */
    public static function updateStep33AddGlobalCategories(): void
    {
        global $gCurrentOrganization;

        if ($gCurrentOrganization->countAllRecords() > 1) {
            $categoryAnnouncement = new Category(self::$db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new Category(self::$db);
            $categoryEvents->setValue('cat_type', 'DAT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new Category(self::$db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }
    }

    /**
     * Update the existing category confirmation of participation and make it
     * organization depending.
     * @throws Exception
     */
    public static function updateStep33EventCategory(): void
    {
        global $g_organization, $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            if ($g_organization === $row['org_shortname']) {
                $sql = 'UPDATE ' . TBL_CATEGORIES . '
                           SET cat_name_intern = \'EVENTS\'
                             , cat_name   = ? -- $gL10n->get(\'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\')
                             , cat_org_id = ? -- $rowId
                         WHERE cat_org_id IS NULL
                           AND cat_type        = \'ROL\'
                           AND cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\' ';
                self::$db->queryPrepared($sql, array($gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'), $rowId));
            } else {
                // create organization depending on category for events
                $category = new Category(self::$db);
                $category->setValue('cat_org_id', $rowId);
                $category->setValue('cat_type', 'ROL');
                $category->setValue('cat_name', $gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'));
                $category->setValue('cat_hidden', '1');
                $category->setValue('cat_system', '1');
                $category->save();

                // now set name intern explicit to EVENTS
                $category->setValue('cat_name_intern', 'EVENTS');
                $category->save();

                // all existing events of this organization must get the new category
                $sql = 'UPDATE ' . TBL_ROLES . '
                           SET rol_cat_id = ? -- $category->getValue(\'cat_id\')
                         WHERE rol_id IN (SELECT dat_rol_id
                                            FROM ' . TBL_DATES . '
                                      INNER JOIN ' . TBL_CATEGORIES . '
                                              ON cat_id = dat_cat_id
                                           WHERE dat_rol_id IS NOT NULL
                                             AND cat_org_id = ?) -- $rowId';
                self::$db->queryPrepared($sql, array((int)$category->getValue('cat_id'), $rowId));
            }
        }
    }

    /**
     * This method migrate the data of the table adm_date_role to the table adm_roles_rights_data.
     * @throws Exception
     */
    public static function updateStep33MigrateDatesRightsToFolderRights(): void
    {
        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM ' . TBL_ROLES_RIGHTS . '
                 WHERE ror_name_intern = \'event_participation\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', dtr_rol_id, dtr_dat_id, ?, ? -- $GLOBALS[\'gCurrentUserId\'], DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_date_role
                 WHERE dtr_rol_id IS NOT NULL';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));

        // if no roles were set than we must assign all default registration roles because now we need at least 1 role
        // so that someone could register to the event
        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', rol_id, dat_id, ?, ? -- $GLOBALS[\'gCurrentUserId\'], DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_dates
            INNER JOIN ' . TABLE_PREFIX . '_categories AS cdat
                    ON cdat.cat_id = dat_cat_id
            INNER JOIN ' . TABLE_PREFIX . '_date_role
                    ON dtr_dat_id = dat_id
            INNER JOIN ' . TABLE_PREFIX . '_categories AS rdat
                    ON rdat.cat_org_id = cdat.cat_org_id
            INNER JOIN ' . TABLE_PREFIX . '_roles
                    ON rol_cat_id = rdat.cat_id
                 WHERE dat_rol_id IS NOT NULL
                   AND dtr_rol_id IS NULL
                   AND rdat.cat_type = \'ROL\'
                   AND rol_default_registration = 1';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));
    }

    /**
     * This method update the security settings for menus to standard values
     * @throws Exception
     */
    public static function updateStep33MigrateToStandardMenu(): void
    {
        // add new module menu to components table
        $sql = 'INSERT INTO ' . TBL_COMPONENTS . '
                       (com_type, com_name, com_name_intern, com_version, com_beta)
                VALUES (\'MODULE\', \'SYS_MENU\', \'MENU\', ?, ?) -- ADMIDIO_VERSION, ADMIDIO_VERSION_BETA';
        self::$db->queryPrepared($sql, array(ADMIDIO_VERSION, ADMIDIO_VERSION_BETA));

        // Menu entries for the standard installation
        $sql = 'INSERT INTO ' . TBL_MENU . '
                       (men_com_id, men_men_id_parent, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
                VALUES (NULL, NULL, 1, 1, 1, \'modules\', NULL, \'\', \'SYS_MODULES\', \'\')
                     , (NULL, NULL, 1, 2, 1, \'administration\', NULL, \'\', \'SYS_ADMINISTRATION\', \'\')
                     , (NULL, NULL, 1, 3, 1, \'plugins\', NULL, \'\', \'SYS_PLUGINS\', \'\')
                     , (NULL, 1, 0, 1, 1, \'overview\', \'' . FOLDER_MODULES . '/overview.php\', \'home.png\', \'SYS_OVERVIEW\', \'\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'DOCUMENTS-FILES\'), 1, 0, 3, 1, \'documents-files\', \'' . FOLDER_MODULES . '/documents-files/documents_files.php\', \'fa-file-download\', \'SYS_DOCUMENTS_FILES\', \'SYS_DOCUMENTS_FILES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'GROUPS-ROLES\'), 1, 0, 7, 1, \'groups-roles\', \'' . FOLDER_MODULES . '/groups-roles/groups_roles.php\', \'fa-user-tie\', \'SYS_GROUPS_ROLES\', \'SYS_GROUPS_ROLES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'ANNOUNCEMENTS\'), 1, 0, 2, 1, \'announcements\', \'' . FOLDER_MODULES . '/announcements.php\', \'announcements.png\', \'SYS_ANNOUNCEMENTS\', \'SYS_ANNOUNCEMENTS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'PHOTOS\'), 1, 0, 5, 1, \'photo\', \'' . FOLDER_MODULES . '/photos/photos.php\', \'photo.png\', \'SYS_PHOTOS\', \'SYS_PHOTOS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'GUESTBOOK\'), 1, 0, 6, 1, \'guestbook\', \'' . FOLDER_MODULES . '/guestbook/guestbook.php\', \'guestbook.png\', \'GBO_GUESTBOOK\', \'GBO_GUESTBOOK_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'DATES\'), 1, 0, 8, 1, \'dates\', \'' . FOLDER_MODULES . '/events/events.php\', \'dates.png\', \'SYS_EVENTS\', \'SYS_EVENTS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'LINKS\'), 1, 0, 9, 1, \'weblinks\', \'' . FOLDER_MODULES . '/links/links.php\', \'weblinks.png\', \'SYS_WEBLINKS\', \'SYS_WEBLINKS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'BACKUP\'), 2, 0, 4, 1, \'dbback\', \'' . FOLDER_MODULES . '/backup/backup.php\', \'backup.png\', \'SYS_DATABASE_BACKUP\', \'SYS_DATABASE_BACKUP_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'PREFERENCES\'), 2, 0, 6, 1, \'orgprop\', \'' . FOLDER_MODULES . '/preferences.php\', \'options.png\', \'SYS_SETTINGS\', \'ORG_ORGANIZATION_PROPERTIES_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MESSAGES\'), 1, 0, 4, 1, \'mail\', \'' . FOLDER_MODULES . '/messages/messages_write.php\', \'email.png\', \'SYS_EMAIL\', \'SYS_EMAIL_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'REGISTRATION\'), 2, 0, 1, 1, \'newreg\', \'' . FOLDER_MODULES . '/registration.php\', \'new_registrations.png\', \'SYS_NEW_REGISTRATIONS\', \'SYS_MANAGE_NEW_REGISTRATIONS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MEMBERS\'), 2, 0, 2, 1, \'usrmgt\', \'' . FOLDER_MODULES . '/members/members.php\', \'user_administration.png\', \'SYS_USER_MANAGEMENT\', \'SYS_MEMBERS_DESC\')
                     , ((SELECT com_id FROM ' . TBL_COMPONENTS . ' WHERE com_name_intern = \'MENU\'), 2, 0, 5, 1, \'menu\', \'' . FOLDER_MODULES . '/menu/menu.php\', \'application_view_tile.png\', \'SYS_MENU\', \'\')';
        self::$db->query($sql);
    }

    /**
     * This method set the approval states for all members of an event in the past to confirmed.
     * @throws Exception
     */
    public static function updateStep33SetParticipantsApprovalStates(): void
    {
        $sql = 'UPDATE ' . TBL_MEMBERS . '
                           SET mem_approved = 2
                         WHERE mem_approved IS NULL
                           AND mem_begin < ? -- DATE_NOW
                           AND mem_rol_id IN (SELECT rol_id
                                                FROM ' . TBL_ROLES . '
                                          INNER JOIN ' . TBL_CATEGORIES . '
                                                  ON cat_id = rol_cat_id
                                               WHERE cat_name_intern = \'EVENTS\'
                                                 AND rol_id IN (SELECT dat_rol_id
                                                                  FROM ' . TBL_DATES . '
                                                                 WHERE dat_rol_id = rol_id))';

        self::$db->queryPrepared($sql, array(DATE_NOW));
    }

    /**
     * This method add all roles to the role right category_view if the role had set the flag cat_hidden = 1
     * @throws Exception
     */
    public static function updateStep33VisibleCategories(): void
    {
        $sql = 'SELECT cat_id, cat_org_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type IN (\'ANN\', \'DAT\', \'LNK\', \'USF\')
                   AND cat_org_id IS NOT NULL
                   AND cat_hidden = 1 ';
        $categoryStatement = self::$db->queryPrepared($sql);

        while ($row = $categoryStatement->fetch()) {
            $roles = array();
            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = true
                       AND cat_name_intern <> \'EVENTS\'
                       AND cat_org_id = ? -- $row[\'cat_org_id\']';
            $rolesStatement = self::$db->queryPrepared($sql, array((int)$row['cat_org_id']));

            while ($rowRole = $rolesStatement->fetch()) {
                $roles[] = (int)$rowRole['rol_id'];
            }

            // save roles to role right
            $rightCategoryView = new RolesRights(self::$db, 'category_view', (int)$row['cat_id']);
            $rightCategoryView->saveRoles($roles);
        }
    }

    /**
     * This method renames the download folders of the different organizations to the new secure filename pattern
     * @throws Exception
     */
    public static function updateStep33DownloadOrgFolderName(): void
    {
        global $gLogger;

        $sql = 'SELECT org_shortname FROM ' . TBL_ORGANIZATIONS;
        $pdoStatement = self::$db->queryPrepared($sql);

        while ($orgShortname = $pdoStatement->fetchColumn()) {
            $path = ADMIDIO_PATH . FOLDER_DATA . '/download_';
            $orgNameOld = str_replace(array(' ', '.', ',', '\'', '"', '´', '`'), '_', $orgShortname);
            $orgNameNew = FileSystemUtils::getSanitizedPathEntry($orgShortname);

            if ($orgNameOld !== $orgNameNew) {
                try {
                    FileSystemUtils::moveDirectory($path . strtolower($orgNameOld), $path . strtolower($orgNameNew));
                } catch (RuntimeException $exception) {
                    $gLogger->error('Could not move directory!', array('from' => $path . strtolower($orgNameOld), 'to' => $path . strtolower($orgNameNew)));
                    // TODO
                }
            }
        }
    }

    /**
     * This method removes expired messengers like Google Plus, AOL Messenger and Yahoo. Messenger from the system.
     * @throws Exception
     */
    public static function updateStep33RemoveExpiredMessengers(): void
    {
        $sql = 'SELECT usf_id
                  FROM ' . TBL_USER_FIELDS . '
                 WHERE usf_name_intern IN (\'AOL_INSTANT_MESSENGER\', \'GOOGLE_PLUS\', \'YAHOO_MESSENGER\')';
        $messengerStatement = self::$db->queryPrepared($sql);

        while ($row = $messengerStatement->fetch()) {
            // save roles to role right
            $rightCategoryView = new ProfileField(self::$db, (int)$row['usf_id']);
            $rightCategoryView->delete();
        }
    }

    /**
     * This method add new categories for announcements to the database.
     * @throws Exception
     */
    public static function updateStep32AddAnnouncementsCategories(): void
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM ' . TBL_USERS . '
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int)$systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $sql = 'INSERT INTO ' . TBL_CATEGORIES . '
                           (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    0, 1, 0, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW
                         , (?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', 0, 0, 0, 2, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            $params = array(
                $rowId, $systemUserId, DATETIME_NOW,
                $rowId, $systemUserId, DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            $sql = 'UPDATE ' . TBL_ANNOUNCEMENTS . '
                       SET ann_cat_id = (SELECT cat_id
                                           FROM ' . TBL_CATEGORIES . '
                                          WHERE cat_type = \'ANN\'
                                            AND cat_name_intern = \'COMMON\'
                                            AND cat_org_id = ? ) -- $rowId
                     WHERE ann_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($rowId, $rowId));
        }
    }

    /**
     * This method installs the default user relation types
     * @throws Exception
     */
    public static function updateStep32InstallDefaultUserRelationTypes(): void
    {
        $sql = 'INSERT INTO ' . TBL_USER_RELATION_TYPES . '
                       (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
                VALUES (1, \'SYS_PARENT\',      \'SYS_FATHER\',           \'SYS_MOTHER\',             2, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (2, \'SYS_CHILD\',       \'SYS_SON\',              \'SYS_DAUGHTER\',           1, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (3, \'SYS_SIBLING\',     \'SYS_BROTHER\',          \'SYS_SISTER\',             3, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (4, \'SYS_SPOUSE\',      \'SYS_HUSBAND\',          \'SYS_WIFE\',               4, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (5, \'SYS_COHABITANT\',  \'SYS_COHABITANT_MALE\',  \'SYS_COHABITANT_FEMALE\',  5, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (6, \'SYS_COMPANION\',   \'SYS_BOYFRIEND\',        \'SYS_GIRLFRIEND\',         6, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (7, \'SYS_SUPERIOR\',    \'SYS_SUPERIOR_MALE\',    \'SYS_SUPERIOR_FEMALE\',    8, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')
                     , (8, \'SYS_SUBORDINATE\', \'SYS_SUBORDINATE_MALE\', \'SYS_SUBORDINATE_FEMALE\', 7, ' . $GLOBALS['gCurrentUserId'] . ', \'' . DATETIME_NOW . '\')';
        self::$db->query($sql);
    }

    /**
     * This method migrate the data of the table adm_folder_roles to the
     * new table adm_roles_rights_data.
     * @throws Exception
     */
    public static function updateStep32MigrateToFolderRights(): void
    {
        global $g_organization;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM ' . TBL_ROLES_RIGHTS . '
                 WHERE ror_name_intern = \'folder_view\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO ' . TBL_ROLES_RIGHTS_DATA . '
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT ' . $rolesRightId . ', flr_rol_id, flr_fol_id, ?, ? -- $gCurrentUserId, DATETIME_NOW
                  FROM ' . TABLE_PREFIX . '_folder_roles ';
        self::$db->queryPrepared($sql, array($GLOBALS['gCurrentUserId'], DATETIME_NOW));

        // add new right folder_update to adm_roles_rights
        $sql = 'SELECT fol_id
                  FROM ' . TBL_FOLDERS . '
                 WHERE fol_type = \'DOWNLOAD\'
                   AND fol_name = \'download\' ';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $folderId = (int)$rolesRightsStatement->fetchColumn();

        $sql = 'SELECT rol_id
                  FROM ' . TBL_ROLES . '
             LEFT JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
             LEFT JOIN ' . TBL_ORGANIZATIONS . '
                    ON org_id = cat_org_id
                 WHERE rol_download  = 1
                   AND org_shortname = ? -- $g_organization';
        $rolesDownloadStatement = self::$db->queryPrepared($sql, array($g_organization));

        $rolesArray = array();
        while ($roleId = $rolesDownloadStatement->fetchColumn()) {
            $rolesArray[] = (int)$roleId;
        }

        // get recordset of current folder from database
        $folder = new Folder(self::$db, $folderId);
        $folder->addRolesOnFolder('folder_upload', $rolesArray);
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization
     * @throws Exception
     */
    public static function updateStep32NewDownloadRootFolderName(): void
    {
        global $gLogger, $g_organization;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while ($row = $organizationStatement->fetch()) {
            $rowId = (int)$row['org_id'];

            $organization = new Organization(self::$db, $rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM ' . TBL_FOLDERS . '
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = self::$db->queryPrepared($sql, array($rowId));

            if ($rowFolder = $folderStatement->fetch()) {
                $folder = new Folder(self::$db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', Folder::getRootFolderName('documents', $organization->getValue('org_shortname')));
                $folder->save();

                $sql = 'UPDATE ' . TBL_FOLDERS . '
                           SET fol_path = REPLACE(fol_path, \'/' . $rowFolder['fol_name'] . '\', \'/' . Folder::getRootFolderName('documents', $organization->getValue('org_shortname')) . '\')
                         WHERE fol_org_id = ' . $rowId;
                self::$db->query($sql); // TODO add more params

                if ($row['org_shortname'] === $g_organization && is_dir($folderOldName)) {
                    try {
                        FileSystemUtils::moveDirectory($folderOldName, $folder->getFullFolderPath());
                    } catch (RuntimeException $exception) {
                        $gLogger->error('Could not move directory!', array('from' => $folderOldName, 'to' => $folder->getFullFolderPath()));
                        // TODO
                    }
                }
            } else {
                $sql = 'INSERT INTO ' . TBL_FOLDERS . '
                               (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_timestamp)
                        VALUES (?, \'DOWNLOAD\', ?, ?, 0, 1, ?) -- $rowId, Folder::getRootFolderName(), FOLDER_DATA, DATETIME_NOW';
                $params = array(
                    $rowId,
                    Folder::getRootFolderName('documents', $organization->getValue('org_shortname')),
                    FOLDER_DATA,
                    DATETIME_NOW
                );
                self::$db->queryPrepared($sql, $params);
            }
        }
    }

    /**
     * This method renames the role 'webmaster' to 'administrator'.
     * @throws Exception
     */
    public static function updateStep32RenameWebmasterToAdministrator(): void
    {
        global $gL10n;

        $sql = 'UPDATE ' . TBL_ROLES . '
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')_1
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR') . '_1', $gL10n->get('SYS_ADMINISTRATOR')));

        $sql = 'UPDATE ' . TBL_ROLES . '
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_WEBMASTER\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), $gL10n->get('SYS_WEBMASTER')));
    }

    /**
     * Check all folders in adm_my_files and set the rights to default folder mode-rights
     * @param string $folder
     * @return bool
     */
    public static function updateStep32RewriteFolderRights(string $folder = ''): bool
    {
        if (!FileSystemUtils::isUnixWithPosix()) {
            return false;
        }

        if ($folder === '') {
            $folder = ADMIDIO_PATH . FOLDER_DATA;
        }

        try {
            FileSystemUtils::chmodDirectory($folder, FileSystemUtils::DEFAULT_MODE_DIRECTORY, true);

            return true;
        } catch (RuntimeException $exception) {
            return false;
        }
    }

    /**
     * This method set the default configuration for all organizations
     * @throws Exception
     */
    public static function updateStep31SetDefaultConfiguration(): void
    {
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray = $organizationsStatement->fetchAll();

        foreach ($organizationsArray as $organization) {
            $orgId = (int)$organization['org_id'];

            $sql = 'SELECT lst_id
                      FROM ' . TBL_LISTS . '
                     WHERE lst_default = 1
                       AND lst_org_id  = ? -- $orgId';
            $defaultListStatement = self::$db->queryPrepared($sql, array($orgId));
            $listId = (int)$defaultListStatement->fetchColumn();

            // save default list to preferences
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value  = ? -- $listId
                     WHERE prf_name   = \'lists_default_configuation\'
                       AND prf_org_id = ? -- $orgId';
            self::$db->queryPrepared($sql, array($listId, $orgId));
        }
    }

    /**
     * This method deletes all roles that belongs to still deleted events.
     * @throws Exception
     */
    public static function updateStep30DeleteDateRoles(): void
    {
        $sql = 'SELECT rol_id
                  FROM ' . TBL_ROLES . '
            INNER JOIN ' . TBL_CATEGORIES . '
                    ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\'
                   AND NOT exists (SELECT 1
                                     FROM ' . TBL_DATES . '
                                    WHERE dat_rol_id = rol_id)';
        $rolesStatement = self::$db->queryPrepared($sql);

        while ($roleId = $rolesStatement->fetchColumn()) {
            $role = new Entity(self::$db, TBL_ROLES, 'rol', (int)$roleId);
            $role->delete(); // TODO Exception handling
        }
    }
}
