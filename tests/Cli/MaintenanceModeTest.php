<?php
/**
 * Maintenance Mode Tests
 *
 * Tests the switch that closes an installation while an update runs. The state lives in a single
 * json file next to the other data, deliberately outside the database, so that it can be turned on
 * while the database is being changed or is unavailable.
 *
 * The state file belongs to the installation the tests run in, so every test refuses to run when
 * maintenance mode is already on and removes only what it turned on itself.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Utils\MaintenanceMode;
use Admidio\Tests\Support\DatabaseTestCase;

class MaintenanceModeTest extends DatabaseTestCase
{
    /**
     * The owner the tests use, so that nothing else is ever taken over.
     */
    private const OWNER = 'admidio-regression-test';

    protected function setUp(): void
    {
        parent::setUp();

        // never touch a maintenance mode that somebody else turned on
        if (MaintenanceMode::isEnabled()) {
            $this->markTestSkipped('Maintenance mode is already enabled in this installation.');
        }
    }

    protected function tearDown(): void
    {
        // whatever the test did, the installation has to be open again afterwards
        if (MaintenanceMode::isEnabled()) {
            $state = MaintenanceMode::getState();
            if ((string) ($state['owner'] ?? '') === self::OWNER) {
                MaintenanceMode::disable(self::OWNER);
            }
        }

        parent::tearDown();
    }

    /**
     * Test that the switch starts off
     *
     * @testdox An installation without a state file is not in maintenance mode
     */
    public function testInstallationWithoutAStateFileIsNotInMaintenanceMode(): void
    {
        $this->assertFalse(MaintenanceMode::isEnabled());
        $this->assertNull(MaintenanceMode::getState());
    }

    /**
     * Test what enabling records
     *
     * @testdox Enabling maintenance mode records what should be shown and for how long
     */
    public function testEnablingRecordsWhatShouldBeShown(): void
    {
        MaintenanceMode::enable('Update running', 'Back in a moment.', array('index.php'), 300, self::OWNER);

        $this->assertTrue(MaintenanceMode::isEnabled());

        $state = MaintenanceMode::getState();
        $this->assertEquals('Update running', $state['title']);
        $this->assertEquals('Back in a moment.', $state['message']);
        $this->assertEquals(300, $state['retryAfter']);
        $this->assertEquals(self::OWNER, $state['owner']);
        $this->assertEquals(array('index.php'), $state['allowedScripts']);

        // the state carries a version and the moment it started, so a stale one can be recognised
        $this->assertEquals(1, $state['schema']);
        $this->assertLessThanOrEqual(time(), $state['startedAt']);
    }

    /**
     * Test the defaults
     *
     * @testdox Enabling without a text falls back to a default title and message
     */
    public function testEnablingWithoutATextFallsBackToDefaults(): void
    {
        MaintenanceMode::enable('', '', array(), 120, self::OWNER);

        $state = MaintenanceMode::getState();
        $this->assertNotEmpty($state['title']);
        $this->assertNotEmpty($state['message']);
        $this->assertStringContainsString('maintenance', strtolower($state['message']));
        $this->assertEquals(array(), $state['allowedScripts']);
    }

    /**
     * Test that a retry interval is required
     *
     * @testdox Maintenance mode refuses a retry interval of zero
     */
    public function testMaintenanceModeRefusesARetryIntervalOfZero(): void
    {
        try {
            MaintenanceMode::enable('Update', 'Wait', array(), 0, self::OWNER);
            $this->fail('A retry interval of zero must be refused.');
        } catch (\UnexpectedValueException $e) {
            $this->assertStringContainsString('greater than zero', $e->getMessage());
        }

        // and nothing was written, so the installation is still open
        $this->assertFalse(MaintenanceMode::isEnabled());
    }

    /**
     * Test that a path may not leave the installation
     *
     * @testdox An allowed script that walks up the directory tree is refused
     */
    public function testAllowedScriptThatWalksUpTheTreeIsRefused(): void
    {
        try {
            MaintenanceMode::enable('Update', 'Wait', array('modules/../../etc/passwd'), 120, self::OWNER);
            $this->fail('A script path that leaves the installation must be refused.');
        } catch (\UnexpectedValueException $e) {
            $this->assertStringContainsString('script path', $e->getMessage());
        }

        $this->assertFalse(MaintenanceMode::isEnabled());
    }

    /**
     * Test that two operations do not fight over the switch
     *
     * @testdox Another operation cannot take over a running maintenance mode
     */
    public function testAnotherOperationCannotTakeOverARunningMaintenanceMode(): void
    {
        MaintenanceMode::enable('Update', 'Wait', array(), 120, self::OWNER);

        try {
            MaintenanceMode::enable('Something else', 'Wait', array(), 120, 'another-operation');
            $this->fail('A second operation must not take over maintenance mode.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('already enabled', $e->getMessage());
        }

        // the first operation still owns it and its text is untouched
        $this->assertEquals(self::OWNER, MaintenanceMode::getState()['owner']);
        $this->assertEquals('Update', MaintenanceMode::getState()['title']);
    }

    /**
     * Test that enabling twice is harmless
     *
     * @testdox The owner of maintenance mode may enable it again without an error
     */
    public function testOwnerMayEnableMaintenanceModeAgain(): void
    {
        MaintenanceMode::enable('Update', 'Wait', array(), 120, self::OWNER);
        $startedAt = MaintenanceMode::getState()['startedAt'];

        // an update that runs several steps should not have to check first
        MaintenanceMode::enable('Update', 'Wait', array(), 120, self::OWNER);

        $this->assertTrue(MaintenanceMode::isEnabled());
        $this->assertEquals($startedAt, MaintenanceMode::getState()['startedAt']);
    }

    /**
     * Test that only the owner may switch it off
     *
     * @testdox Only the operation that enabled maintenance mode may disable it
     */
    public function testOnlyTheOwnerMayDisableMaintenanceMode(): void
    {
        MaintenanceMode::enable('Update', 'Wait', array(), 120, self::OWNER);

        try {
            MaintenanceMode::disable('another-operation');
            $this->fail('A foreign operation must not be able to disable maintenance mode.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('owned by another operation', $e->getMessage());
        }
        $this->assertTrue(MaintenanceMode::isEnabled());

        $this->assertTrue(MaintenanceMode::disable(self::OWNER));
        $this->assertFalse(MaintenanceMode::isEnabled());
        $this->assertNull(MaintenanceMode::getState());
    }

    /**
     * Test that switching off twice is harmless
     *
     * @testdox Disabling a maintenance mode that is not running reports that nothing was done
     */
    public function testDisablingAMaintenanceModeThatIsNotRunningReportsNothing(): void
    {
        $this->assertFalse(MaintenanceMode::isEnabled());

        // no exception, so an update that failed early can always clean up
        $this->assertFalse(MaintenanceMode::disable(self::OWNER));
        $this->assertFalse(MaintenanceMode::disable());
    }
}
