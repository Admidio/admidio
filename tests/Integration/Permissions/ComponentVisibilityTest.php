<?php
/**
 * Component Visibility Tests
 *
 * Tests Component::isVisible() and Component::isAdministrable(), which combine the module
 * preference of the organization with the role rights of the current user.
 */

namespace Admidio\Tests\Integration\Permissions;

use Admidio\Components\Entity\Component;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;

class ComponentVisibilityTest extends DatabaseTestCase
{
    use PermissionContext;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * Test that CORE is visible to a logged-in user
     *
     * @testdox CORE is visible to a logged-in user
     */
    public function testCoreIsVisibleWhenLoggedIn(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Visible Org', 'visorg');
        $user = $fixture->createAndSaveUser('coreuser', 'core@example.local');

        $currentUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $visible = $this->withCurrentUser($currentUser, $org['org_id'], true, static function () {
            return Component::isVisible('CORE');
        });

        $this->assertTrue($visible);
    }

    /**
     * Test that CORE is hidden from a visitor
     *
     * @testdox CORE is not visible to a visitor
     */
    public function testCoreIsHiddenFromVisitor(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Visible Org', 'visorg');
        $user = $fixture->createAndSaveUser('visitor', 'visitor@example.local');

        $currentUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $visible = $this->withCurrentUser($currentUser, $org['org_id'], false, static function () {
            return Component::isVisible('CORE');
        });

        $this->assertFalse($visible);
    }

    /**
     * Test that a module set to "everybody" is visible without a login
     *
     * @testdox A module enabled for everybody is visible without a login
     */
    public function testModuleEnabledForEverybodyIsVisibleToVisitors(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Module Org', 'modorg');
        $user = $fixture->createAndSaveUser('moduser', 'mod@example.local');

        // an organization created by the fixture has no preferences, so the module setting
        // has to be written before it can be read
        $this->settingsOf($org['org_id'])->set('announcements_module_enabled', '1');

        $currentUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $visible = $this->withCurrentUser($currentUser, $org['org_id'], false, static function () {
            return Component::isVisible('ANNOUNCEMENTS');
        });

        $this->assertTrue($visible);
    }

    /**
     * Test that a disabled module is not visible at all
     *
     * @testdox A disabled module is not visible even to a logged-in user
     */
    public function testDisabledModuleIsNotVisible(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Module Org', 'modorg');
        $user = $fixture->createAndSaveUser('moduser', 'mod@example.local');

        $this->settingsOf($org['org_id'])->set('announcements_module_enabled', '0');

        $currentUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $visible = $this->withCurrentUser($currentUser, $org['org_id'], true, static function () {
            return Component::isVisible('ANNOUNCEMENTS');
        });

        $this->assertFalse($visible);
    }

    /**
     * Test the "registered users only" setting
     *
     * @testdox A module restricted to registered users is hidden from visitors
     */
    public function testModuleForRegisteredUsersOnly(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Module Org', 'modorg');
        $user = $fixture->createAndSaveUser('moduser', 'mod@example.local');

        $this->settingsOf($org['org_id'])->set('announcements_module_enabled', '2');

        $currentUser = $this->loadUserInOrganization($user['usr_id'], $org['org_id']);

        $forVisitor = $this->withCurrentUser($currentUser, $org['org_id'], false, static function () {
            return Component::isVisible('ANNOUNCEMENTS');
        });
        $forMember = $this->withCurrentUser($currentUser, $org['org_id'], true, static function () {
            return Component::isVisible('ANNOUNCEMENTS');
        });

        $this->assertFalse($forVisitor);
        $this->assertTrue($forMember);
    }

    /**
     * Test that administrating a module needs the matching role right
     *
     * @testdox Administrating a module needs both visibility and the role right
     */
    public function testAdministrableNeedsVisibilityAndRight(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Module Org', 'modorg');
        $this->settingsOf($org['org_id'])->set('announcements_module_enabled', '1');

        $role = $fixture->createAndSaveRoleWithRights('Announcers', $org['org_id'], ['rol_announcements' => 1]);
        $announcer = $fixture->createAndSaveUser('announcer', 'ann@example.local');
        $reader = $fixture->createAndSaveUser('reader', 'read@example.local');
        $fixture->assignUserToRole($announcer['usr_id'], $role['rol_id']);

        $announcerUser = $this->loadUserInOrganization($announcer['usr_id'], $org['org_id']);
        $readerUser = $this->loadUserInOrganization($reader['usr_id'], $org['org_id']);

        $announcerMay = $this->withCurrentUser($announcerUser, $org['org_id'], true, static function () {
            return Component::isAdministrable('ANNOUNCEMENTS');
        });
        $readerMay = $this->withCurrentUser($readerUser, $org['org_id'], true, static function () {
            return Component::isAdministrable('ANNOUNCEMENTS');
        });

        $this->assertTrue($announcerMay);
        $this->assertFalse($readerMay);
    }

    /**
     * Test that a hidden module cannot be administrated either
     *
     * @testdox A user with the right cannot administrate a disabled module
     */
    public function testAdministrableIsFalseForHiddenModule(): void
    {
        $fixture = $this->getFixture();
        $org = $fixture->createAndSaveOrganization('Module Org', 'modorg');
        $this->settingsOf($org['org_id'])->set('announcements_module_enabled', '0');

        $role = $fixture->createAndSaveRoleWithRights('Announcers', $org['org_id'], ['rol_announcements' => 1]);
        $announcer = $fixture->createAndSaveUser('announcer', 'ann@example.local');
        $fixture->assignUserToRole($announcer['usr_id'], $role['rol_id']);

        $announcerUser = $this->loadUserInOrganization($announcer['usr_id'], $org['org_id']);

        // the right is there
        $this->assertTrue($announcerUser->isAdministratorAnnouncements());

        // but the module is switched off, so it cannot be administrated
        $mayAdministrate = $this->withCurrentUser($announcerUser, $org['org_id'], true, static function () {
            return Component::isAdministrable('ANNOUNCEMENTS');
        });
        $this->assertFalse($mayAdministrate);
    }
}
