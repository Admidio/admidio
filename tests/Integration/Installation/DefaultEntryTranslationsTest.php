<?php

namespace Admidio\Tests\Integration\Installation;

use Admidio\Events\Entity\Room;
use Admidio\Infrastructure\Cli\CoreTasks;
use Admidio\Organizations\Entity\Organization;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\ProfileFields\Entity\ProfileField;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Infrastructure\Entity\Text;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\SystemMail;
use Admidio\InstallationUpdate\Service\UpdateStepsCode;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\ListConfiguration;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Users\Entity\User;

class DefaultEntryTranslationsTest extends DatabaseTestCase
{
    private Language $previousLanguage;
    private string $previousSender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousLanguage = $GLOBALS['gL10n'];
        $GLOBALS['gL10n'] = new Language('en');
        $this->previousSender = $GLOBALS['gSettingsManager']->getString('mail_sender_email');
        $GLOBALS['gSettingsManager']->set('mail_sender_email', 'sender@example.test');
    }

    protected function tearDown(): void
    {
        $GLOBALS['gL10n'] = $this->previousLanguage;
        $GLOBALS['gSettingsManager']->set('mail_sender_email', $this->previousSender);
        parent::tearDown();
    }

    public function testEveryInstalledDefaultIdExistsInTheEnglishReferenceFile(): void
    {
        $knownIds = array();
        foreach (simplexml_load_file(ADMIDIO_PATH . '/languages/en.xml')->string as $text) {
            $knownIds[(string) $text['name']] = true;
        }
        $columns = array(
            TBL_ROLES => array('rol_name', 'rol_description'),
            TBL_LISTS => array('lst_name'),
            TBL_ROOMS => array('room_name', 'room_description'),
            TBL_CATEGORY_REPORT => array('crt_name'),
            TBL_TEXTS => array('txt_text'),
        );
        foreach ($columns as $table => $fields) {
            foreach ($this->getDatabase()->queryPrepared('SELECT ' . implode(', ', $fields) . ' FROM ' . $table)->fetchAll() as $row) {
                foreach ($fields as $field) {
                    $this->assertArrayHasKey($row[$field], $knownIds, $table . '.' . $field);
                }
            }
        }
    }

    public function testInstallerStoresIdsAndAnExistingEntityFollowsTheLanguage(): void
    {
        $db = $this->getDatabase();
        $row = $db->queryPrepared('SELECT rol_id, rol_name, rol_description FROM ' . TBL_ROLES . ' WHERE rol_administrator = true')->fetch();
        $this->assertSame('SYS_ADMINISTRATOR', $row['rol_name']);
        $this->assertSame('INS_DESCRIPTION_ADMINISTRATOR', $row['rol_description']);
        $role = new Role($db, (int) $row['rol_id']);
        foreach (array('en', 'de') as $language) {
            $GLOBALS['gL10n'] = new Language($language);
            $this->assertSame($GLOBALS['gL10n']->get('SYS_ADMINISTRATOR'), $role->getValue('rol_name'));
            $this->assertSame($GLOBALS['gL10n']->get('INS_DESCRIPTION_ADMINISTRATOR'), $role->getValue('rol_description'));
            $this->assertSame('SYS_ADMINISTRATOR', $role->getValue('rol_name', 'database'));
            $role->setValue('rol_name', html_entity_decode($role->getValue('rol_name'), ENT_QUOTES, 'UTF-8'));
            $this->assertSame('SYS_ADMINISTRATOR', $role->getValue('rol_name', 'database'));
        }
        $role->setValue('rol_name', 'Our administrators');
        $this->assertSame('Our administrators', $role->getValue('rol_name', 'database'));

        $this->assertSame('INS_CONFERENCE_ROOM', $db->queryPrepared('SELECT room_name FROM ' . TBL_ROOMS)->fetchColumn());
        $this->assertSame('SYS_DATA_PROTECTION_PERMISSION_DESC', $db->queryPrepared("SELECT usf_description FROM " . TBL_USER_FIELDS . " WHERE usf_name_intern = 'DATA_PROTECTION_PERMISSION'")->fetchColumn());
        $this->assertSame('SYS_GENERAL_ROLE_ASSIGNMENT', $db->queryPrepared('SELECT crt_name FROM ' . TBL_CATEGORY_REPORT)->fetchColumn());
        $this->assertSame('SYS_SYSTEM', $db->queryPrepared('SELECT usr_login_name FROM ' . TBL_USERS . ' ORDER BY usr_id')->fetchColumn());
    }

    public function testMailIdsAreRenderedAndUnchangedTextareaInputKeepsTheId(): void
    {
        $db = $this->getDatabase();
        $text = new Text($db);
        $text->readDataByColumns(array('txt_name' => 'SYSMAIL_PASSWORD_RESET', 'txt_org_id' => $GLOBALS['gCurrentOrgId']));
        $this->assertSame('SYS_SYSMAIL_PASSWORD_RESET', $text->getValue('txt_text', 'database'));
        $userId = $db->queryPrepared("SELECT usr_id FROM " . TBL_USERS . " WHERE usr_login_name = 'admin'")->fetchColumn();
        $user = new User($db, $GLOBALS['gProfileFields'], (int) $userId);
        foreach (array('de', 'en') as $code) {
            $GLOBALS['gL10n'] = new Language($code);
            $rendered = $text->getValue('txt_text');
            $this->assertStringNotContainsString('<br', $rendered);
            $text->setValue('txt_text', str_replace("\r\n", "\n", html_entity_decode($rendered, ENT_QUOTES, 'UTF-8')));
            $this->assertSame('SYS_SYSMAIL_PASSWORD_RESET', $text->getValue('txt_text', 'database'));
            $mail = new SystemMail($db);
            $mail->setVariable(1, 'https://example.org/reset');
            $body = $mail->getMailText('SYSMAIL_PASSWORD_RESET', $user);
            $this->assertStringNotContainsString('SYS_SYSMAIL_', $body);
            $this->assertStringNotContainsString('#user_first_name#', $body);
            $this->assertStringContainsString('https://example.org/reset', $body);
        }
        $text->setValue('txt_text', 'Our own message');
        $this->assertSame('Our own message', $text->getValue('txt_text', 'database'));
    }

    public function testDescriptionsKeepIdsAfterRichTextEditorNormalization(): void
    {
        $db = $this->getDatabase();
        $roomId = (int) $db->queryPrepared('SELECT room_id FROM ' . TBL_ROOMS)->fetchColumn();
        $room = new Room($db, $roomId);
        $room->setValue('room_description', '<p>' . $room->getValue('room_description') . '</p>');
        $this->assertSame('INS_DESCRIPTION_CONFERENCE_ROOM', $room->getValue('room_description', 'database'));
        $room->setValue('room_description', '<p><strong>Our new description</strong></p>');
        $this->assertSame('<p><strong>Our new description</strong></p>', $room->getValue('room_description'));
        $fieldId = (int) $db->queryPrepared('SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = ?', array('FACEBOOK'))->fetchColumn();
        $field = new ProfileField($db, $fieldId);
        $field->setValue('usf_description', '<p>' . $field->getValue('usf_description') . '</p>');
        $this->assertSame('SYS_SOCIAL_NETWORK_FIELD_URL_DESC', $field->getValue('usf_description', 'database'));
    }

    public function testCliResolvesLocalizedDefaultNamesAndRejectsDuplicateRoleNames(): void
    {
        $GLOBALS['gL10n'] = new Language('de');
        $resolveRole = new \ReflectionMethod(CoreTasks::class, 'resolveGroup');
        $role = $resolveRole->invoke(null, $GLOBALS['gL10n']->get('SYS_MEMBER'));
        $this->assertSame('SYS_MEMBER', $role->getValue('rol_name', 'database'));
        $resolveRoom = new \ReflectionMethod(CoreTasks::class, 'resolveRoom');
        $room = $resolveRoom->invoke(null, $GLOBALS['gL10n']->get('INS_CONFERENCE_ROOM'));
        $this->assertSame('INS_CONFERENCE_ROOM', $room->getValue('room_name', 'database'));
        $uniqueRole = new \ReflectionMethod(CoreTasks::class, 'assertRoleNameUnique');
        $this->expectException(\Admidio\Infrastructure\Exception::class);
        $uniqueRole->invoke(null, $GLOBALS['gL10n']->get('SYS_MEMBER'), (int) $role->getValue('rol_cat_id'), 0);
    }

    public function testAnAdditionalOrganizationUsesIdsAfterChangingLanguage(): void
    {
        $db = $this->getDatabase();
        $profileFields = $GLOBALS['gProfileFields'];
        $GLOBALS['gL10n'] = new Language('de');
        $fixture = new AdmidioTestFixture($db);
        $data = $fixture->createAndSaveOrganization('German organization', 'GERMAN');
        $org = new Organization($db, $data['org_id']);
        $org->getSettingsManager()->setMulti(PreferenceDefinitions::defaults(), false);
        $adminId = (int) $db->queryPrepared('SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?', array('admin'))->fetchColumn();
        try {
            $org->createBasicData($adminId);
            $this->assertSame('SYS_ADMINISTRATOR', $db->queryPrepared('SELECT rol_name FROM ' . TBL_ROLES . ' INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id WHERE cat_org_id = ? AND rol_administrator = true', array($data['org_id']))->fetchColumn());
            $db->queryPrepared('UPDATE ' . TBL_TEXTS . ' SET txt_text = ? WHERE txt_org_id = ? AND txt_name = ?', array(str_replace('<br />', "\r\n", $GLOBALS['gL10n']->get('SYS_SYSMAIL_PASSWORD_RESET')), $data['org_id'], 'SYSMAIL_PASSWORD_RESET'));
            $db->queryPrepared('UPDATE ' . TBL_LISTS . ' SET lst_name = ?, lst_usr_id = ? WHERE lst_org_id = ? AND lst_name = ?', array($GLOBALS['gL10n']->get('INS_PHONE_LIST'), $adminId, $data['org_id'], 'INS_PHONE_LIST'));
            UpdateStepsCode::setDatabase($db);
            UpdateStepsCode::updateStep51TranslateDefaultEntries();
            $this->assertSame('INS_PHONE_LIST', $db->queryPrepared('SELECT lst_name FROM ' . TBL_LISTS . ' WHERE lst_org_id = ? AND lst_name = ?', array($data['org_id'], 'INS_PHONE_LIST'))->fetchColumn());
            $this->assertSame('SYS_SYSMAIL_PASSWORD_RESET', $db->queryPrepared('SELECT txt_text FROM ' . TBL_TEXTS . ' WHERE txt_org_id = ? AND txt_name = ?', array($data['org_id'], 'SYSMAIL_PASSWORD_RESET'))->fetchColumn());
        } finally {
            $GLOBALS['gProfileFields'] = $profileFields;
        }
    }

    public function testMigrationDoesNotTranslateAPersonalListWithAStandardName(): void
    {
        $db = $this->getDatabase();
        $list = new ListConfiguration($db);
        $name = $GLOBALS['gL10n']->get('INS_ADDRESS_LIST');
        $list->setValue('lst_name', $name);
        $list->setValue('lst_global', 0);
        $list->save();
        UpdateStepsCode::setDatabase($db);
        UpdateStepsCode::updateStep51TranslateDefaultEntries();
        $list->readDataById((int) $list->getValue('lst_id'));
        $GLOBALS['gL10n'] = new Language('de');
        $this->assertSame($name, $list->getValue('lst_name', 'database'));
        $this->assertSame($name, $list->getValue('lst_name'));
    }

    public function testMigrationRecognizesTheOriginalBoardNameWithAStraightApostrophe(): void
    {
        $db = $this->getDatabase();
        $boardId = (int)$db->queryPrepared('SELECT rol_id FROM ' . TBL_ROLES . ' WHERE rol_name = ?', array('INS_BOARD'))->fetchColumn();
        $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_name = ?, rol_description = ? WHERE rol_id = ?',
            array("Association's board", 'Administrative board of association', $boardId));
        UpdateStepsCode::setDatabase($db);
        UpdateStepsCode::updateStep51TranslateDefaultEntries();
        $board = new Role($db, $boardId);
        $this->assertSame('INS_BOARD', $board->getValue('rol_name', 'database'));
        $this->assertSame('INS_DESCRIPTION_BOARD', $board->getValue('rol_description', 'database'));
        $GLOBALS['gL10n'] = new Language('de');
        $this->assertSame($GLOBALS['gL10n']->get('INS_BOARD'), $board->getValue('rol_name'));
        $this->assertSame($GLOBALS['gL10n']->get('INS_DESCRIPTION_BOARD'), $board->getValue('rol_description'));
    }

    public function testMigrationConvertsMixedLanguagesKeepsCustomValuesAndIsRepeatable(): void
    {
        $db = $this->getDatabase();
        $german = new Language('de');
        $english = new Language('en');
        $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_name = ?, rol_description = ? WHERE rol_administrator = true', array($german->get('SYS_ADMINISTRATOR'), 'Our custom administrator description'));
        $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_name = ? WHERE rol_name = ?', array($english->get('SYS_MEMBER'), 'SYS_MEMBER'));
        $boardId = (int) $db->queryPrepared('SELECT rol_id FROM ' . TBL_ROLES . ' WHERE rol_name = ?', array('INS_BOARD'))->fetchColumn();
        $customName = strtolower($german->get('SYS_ADMINISTRATOR'));
        $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_name = ? WHERE rol_id = ?', array($customName, $boardId));
        $db->queryPrepared('UPDATE ' . TBL_LISTS . ' SET lst_name = ? WHERE lst_name = ?', array($german->get('INS_ADDRESS_LIST'), 'INS_ADDRESS_LIST'));
        $db->queryPrepared('UPDATE ' . TBL_ROOMS . ' SET room_name = ?, room_description = ?', array($english->get('INS_CONFERENCE_ROOM'), 'Our meeting room'));
        $db->queryPrepared('UPDATE ' . TBL_TEXTS . ' SET txt_text = ? WHERE txt_name = ?', array(str_replace('<br />', "\r\n", $GLOBALS['gL10n']->get('SYS_SYSMAIL_PASSWORD_RESET')), 'SYSMAIL_PASSWORD_RESET'));
        $db->queryPrepared('UPDATE ' . TBL_TEXTS . ' SET txt_text = ? WHERE txt_name = ?', array('Customized registration mail', 'SYSMAIL_REGISTRATION_NEW'));
        $db->queryPrepared('UPDATE ' . TBL_USER_FIELDS . ' SET usf_description = ? WHERE usf_name_intern = ?', array($german->get('SYS_SOCIAL_NETWORK_FIELD_URL_DESC'), 'FACEBOOK'));
        $db->queryPrepared('UPDATE ' . TBL_USERS . ' SET usr_login_name = ? WHERE usr_login_name = ?', array($german->get('SYS_SYSTEM'), 'SYS_SYSTEM'));
        $db->queryPrepared('UPDATE ' . TBL_USER_DATA . ' SET usd_value = ? WHERE usd_value = ?', array($german->get('SYS_SYSTEM'), 'SYS_SYSTEM'));

        UpdateStepsCode::setDatabase($db);
        UpdateStepsCode::updateStep51TranslateDefaultEntries();
        $snapshot = array();
        foreach (array(TBL_ROLES, TBL_LISTS, TBL_ROOMS, TBL_TEXTS, TBL_USER_FIELDS, TBL_USERS, TBL_USER_DATA) as $table) {
            $snapshot[$table] = $db->queryPrepared('SELECT * FROM ' . $table)->fetchAll();
        }
        UpdateStepsCode::updateStep51TranslateDefaultEntries();
        foreach ($snapshot as $table => $rows) {
            $this->assertSame($rows, $db->queryPrepared('SELECT * FROM ' . $table)->fetchAll());
        }
        $role = $db->queryPrepared('SELECT rol_name, rol_description FROM ' . TBL_ROLES . ' WHERE rol_administrator = true')->fetch();
        $this->assertSame('SYS_ADMINISTRATOR', $role['rol_name']);
        $this->assertSame('Our custom administrator description', $role['rol_description']);
        $this->assertSame($customName, $db->queryPrepared('SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_id = ?', array($boardId))->fetchColumn());
        $this->assertSame('INS_ADDRESS_LIST', $db->queryPrepared('SELECT lst_name FROM ' . TBL_LISTS . ' WHERE lst_name = ?', array('INS_ADDRESS_LIST'))->fetchColumn());
        $this->assertSame('Our meeting room', $db->queryPrepared('SELECT room_description FROM ' . TBL_ROOMS)->fetchColumn());
        $this->assertSame('Customized registration mail', $db->queryPrepared('SELECT txt_text FROM ' . TBL_TEXTS . ' WHERE txt_name = ?', array('SYSMAIL_REGISTRATION_NEW'))->fetchColumn());
        $this->assertSame('SYS_SYSTEM', $db->queryPrepared('SELECT usr_login_name FROM ' . TBL_USERS . ' ORDER BY usr_id')->fetchColumn());
        $this->assertSame('SYS_SOCIAL_NETWORK_FIELD_URL_DESC', $db->queryPrepared('SELECT usf_description FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = ?', array('FACEBOOK'))->fetchColumn());
        $this->assertSame('SYS_SYSMAIL_PASSWORD_RESET', $db->queryPrepared('SELECT txt_text FROM ' . TBL_TEXTS . ' WHERE txt_name = ?', array('SYSMAIL_PASSWORD_RESET'))->fetchColumn());
    }
}
