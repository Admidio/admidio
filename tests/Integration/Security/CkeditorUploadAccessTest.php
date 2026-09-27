<?php

namespace Admidio\Tests\Integration\Security;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\CkeditorUploadAccess;
use Admidio\Organizations\Entity\Organization;
use Admidio\Roles\Entity\RolesRights;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;
use Admidio\Preferences\ValueObject\SettingsManager;

class CkeditorUploadAccessTest extends DatabaseTestCase
{
    use PermissionContext;

    private function assertUploadDenied(string $editorId, User $user, SettingsManager $settings): void
    {
        try {
            CkeditorUploadAccess::authorizedFolder($editorId, $user, $settings);
            $this->fail('The upload should be denied for ' . $editorId);
        } catch (Exception $exception) {
            $this->assertSame('SYS_NO_RIGHTS', $exception->getTranslationId());
        }
    }

    public function testUploadsRequireTheEditorsModuleAndEditRights(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $org = $fixture->createAndSaveOrganization('Editor Uploads', 'editorup');
        $settings = (new Organization($db, $org['org_id']))->getSettingsManager();

        $editors = array(
            'ann_description' => array('ANN', 'announcements_module_enabled', 'announcements'),
            'dat_description' => array('EVT', 'events_module_enabled', 'events'),
            'fop_text' => array('FOT', 'forum_module_enabled', 'forum'),
            'lnk_description' => array('LNK', 'weblinks_module_enabled', 'weblinks'),
        );
        foreach ($editors as [$type, $moduleSetting]) {
            $fixture->createAndSaveCategory($type . ' uploads', $type, $org['org_id']);
            $settings->set($moduleSetting, '1');
        }

        $member = $fixture->createAndSaveUser('uploadmember', 'uploadmember@example.local');
        $memberUser = $this->loadUserInOrganization($member['usr_id'], $org['org_id']);
        foreach (array_keys($editors) as $editorId) {
            $this->assertUploadDenied($editorId, $memberUser, $settings);
        }

        $linkCategory = $fixture->createAndSaveCategory('Editable links', 'LNK', $org['org_id']);
        $editorRole = $fixture->createAndSaveRoleWithRights('Link editor', $org['org_id']);
        (new RolesRights($db, 'category_edit', $linkCategory['cat_id']))->saveRoles([$editorRole['rol_id']]);
        $fixture->assignUserToRole($member['usr_id'], $editorRole['rol_id']);
        $editorUser = $this->loadUserInOrganization($member['usr_id'], $org['org_id']);

        $this->assertSame('weblinks', CkeditorUploadAccess::authorizedFolder('lnk_description', $editorUser, $settings));
        $this->assertUploadDenied('ann_description', $editorUser, $settings);
        $settings->set('weblinks_module_enabled', '0');
        $this->assertUploadDenied('lnk_description', $editorUser, $settings);
        $settings->set('weblinks_module_enabled', '1');

        $adminRole = $fixture->createAndSaveRoleWithRights('Editor admin', $org['org_id'], array(
            'rol_administrator' => 1,
            'rol_announcements' => 1,
            'rol_events' => 1,
            'rol_forum_admin' => 1,
            'rol_weblinks' => 1,
        ));
        $admin = $fixture->createAndSaveUser('uploadadmin', 'uploadadmin@example.local');
        $fixture->assignUserToRole($admin['usr_id'], $adminRole['rol_id']);
        $adminUser = $this->loadUserInOrganization($admin['usr_id'], $org['org_id']);

        foreach ($editors as $editorId => [, , $folder]) {
            $this->assertSame($folder, CkeditorUploadAccess::authorizedFolder($editorId, $adminUser, $settings));
        }
        $this->assertSame('rooms', CkeditorUploadAccess::authorizedFolder('room_description', $adminUser, $settings));
        $this->assertSame('user_fields', CkeditorUploadAccess::authorizedFolder('usf_description', $adminUser, $settings));
        $this->assertUploadDenied('room_description', $editorUser, $settings);
        $this->assertUploadDenied('usf_description', $editorUser, $settings);

        $settings->set('events_module_enabled', '0');
        $this->assertUploadDenied('dat_description', $adminUser, $settings);
        $this->assertUploadDenied('room_description', $adminUser, $settings);
    }

    public function testMailAndUnsupportedEditorsAreRestricted(): void
    {
        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $org = $fixture->createAndSaveOrganization('Editor Uploads', 'editorup');
        $settings = (new Organization($db, $org['org_id']))->getSettingsManager();
        $member = $fixture->createAndSaveUser('uploadmember', 'uploadmember@example.local');
        $user = $this->loadUserInOrganization($member['usr_id'], $org['org_id']);

        $settings->set('mail_html_registered_users', '1');
        $settings->set('pm_module_enabled', '1');
        $settings->set('mail_module_enabled', '0');
        $this->assertSame('mail', CkeditorUploadAccess::authorizedFolder('msg_body', $user, $settings));

        $settings->set('pm_module_enabled', '0');
        $this->assertUploadDenied('msg_body', $user, $settings);
        $settings->set('mail_html_registered_users', '0');
        $this->assertUploadDenied('msg_body', $user, $settings);

        foreach (array('ecard_message', 'inf_description', 'plugin_CKEditor', 'unknown') as $editorId) {
            $this->assertUploadDenied($editorId, $user, $settings);
        }
    }
}
