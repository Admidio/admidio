<?php
/**
 * The automatic check for a new release of Admidio and the notice of the administrators
 * (PreferencesService::checkForUpdateIfDue() and getAvailableUpdate()). The update information of
 * the Admidio server is replaced by a text of the test, so no request leaves the machine.
 */

namespace Admidio\Tests\Integration\Preferences;

use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\PermissionContext;

final class UpdateCheckTest extends AdministratorTestCase
{
    use PermissionContext;

    private const PREFERENCES = array('system_update_check', 'system_update_check_time', 'system_update_information');

    /** @var array<string,string> */
    private array $previous = array();

    protected function setUp(): void
    {
        parent::setUp();

        global $gSettingsManager;

        // The settings manager keeps its values beyond the transaction of the test.
        foreach (self::PREFERENCES as $name) {
            $this->previous[$name] = $gSettingsManager->getString($name);
        }
        $gSettingsManager->setMulti(array('system_update_check' => '1', 'system_update_check_time' => '0', 'system_update_information' => ''));
    }

    protected function tearDown(): void
    {
        global $gSettingsManager;

        $gSettingsManager->setMulti($this->previous);

        parent::tearDown();
    }

    /**
     * A service that reads the given update information instead of the one of the Admidio server.
     * @param string|false $updateInformation
     */
    private function service(string|false $updateInformation): PreferencesService
    {
        return new class ($updateInformation) extends PreferencesService {
            public int $requests = 0;

            public function __construct(private readonly string|false $updateInformation)
            {
            }

            protected function readUpdateInformation(): string|false
            {
                ++$this->requests;

                return $this->updateInformation;
            }
        };
    }

    private function storeResult(string $stableVersion, string $betaVersion = '', string $betaRelease = ''): void
    {
        global $gSettingsManager;

        $gSettingsManager->set('system_update_information', json_encode(array(
            'stableVersion' => $stableVersion, 'betaVersion' => $betaVersion, 'betaRelease' => $betaRelease, 'checkedAt' => time()
        )));
    }

    /**
     * @testdox The check asks the server at most twice a day and keeps what it found
     */
    public function testCheckIsDueTwiceADay(): void
    {
        global $gSettingsManager;

        $service = $this->service("Version=99.0.0\nBeta-Version=99.1.0\nBeta-Release=2\n");

        $this->assertTrue($service->checkForUpdateIfDue());
        $this->assertFalse($service->checkForUpdateIfDue(), 'not again within 12 hours');
        $this->assertSame(1, $service->requests);
        $stored = json_decode($gSettingsManager->getString('system_update_information'), true);
        $this->assertSame(array('99.0.0', '99.1.0', '2'), array($stored['stableVersion'], $stored['betaVersion'], $stored['betaRelease']));
        $this->assertSame('99.0.0', $service->getAvailableUpdate());

        $gSettingsManager->set('system_update_check_time', (string) (time() - PreferencesService::UPDATE_CHECK_INTERVAL_SECONDS - 1));
        $this->assertTrue($service->checkForUpdateIfDue(), 'due again after 12 hours');
        $this->assertSame(2, $service->requests);
    }

    /**
     * @testdox A server that cannot be reached is not asked again on every page, and the last result stays
     */
    public function testFailedCheckWaitsAsWell(): void
    {
        global $gSettingsManager;

        $this->storeResult('99.0.0');
        $service = $this->service(false);

        $this->assertTrue($service->checkForUpdateIfDue());
        $this->assertFalse($service->checkForUpdateIfDue());
        $this->assertSame(1, $service->requests);
        $this->assertGreaterThan(0, $gSettingsManager->getInt('system_update_check_time'));
        $this->assertSame('99.0.0', $service->getAvailableUpdate(), 'what the last check found is kept');
    }

    /**
     * @testdox Only the requests of administrators check, so that no member waits for the server
     */
    public function testOnlyAdministratorsCheck(): void
    {
        global $gCurrentOrgId;

        $fixture = new AdmidioTestFixture($this->getDatabase());
        $member = $fixture->createAndSaveUser('update_check_member', 'update-check@example.org', $gCurrentOrgId);
        $service = $this->service("Version=99.0.0\n");

        $checked = $this->withCurrentUser($this->loadUserInOrganization((int) $member['usr_id'], $gCurrentOrgId), $gCurrentOrgId, true,
            static fn (): bool => $service->checkForUpdateIfDue());
        $this->assertFalse($checked);

        $checked = $this->withCurrentUser($this->loadUserInOrganization((int) $member['usr_id'], $gCurrentOrgId), $gCurrentOrgId, false,
            static fn (): bool => $service->checkForUpdateIfDue());
        $this->assertFalse($checked, 'nor a visitor who is not logged in');
        $this->assertSame(0, $service->requests);
    }

    /**
     * @testdox The preference switches the check and the notice off
     */
    public function testSwitchedOff(): void
    {
        global $gSettingsManager;

        $this->storeResult('99.0.0');
        $gSettingsManager->set('system_update_check', '0');
        $service = $this->service("Version=99.0.0\n");

        $this->assertFalse($service->checkForUpdateIfDue());
        $this->assertSame(0, $service->requests);
        $this->assertSame('', $service->getAvailableUpdate());
    }

    /**
     * @testdox What another session or maintenance:run checked is seen at once, although the session keeps the preferences
     */
    public function testResultOfAnotherSession(): void
    {
        global $gCurrentOrgId;

        $service = $this->service("Version=99.0.0\n");
        $this->assertSame('', $service->getAvailableUpdate());

        $other = $this->settingsOf($gCurrentOrgId);
        $other->set('system_update_information', json_encode(array('stableVersion' => '99.0.0', 'betaVersion' => '', 'betaRelease' => '')));
        $other->set('system_update_check_time', (string) time());

        $this->assertSame('99.0.0', $service->getAvailableUpdate());
        $this->assertFalse($service->checkForUpdateIfDue(), 'the check of the other session counts');
        $this->assertSame(0, $service->requests);
    }

    /**
     * @testdox The check in the preferences offers to start the update when this installation is offered a release
     */
    public function testCheckOffersTheUpdate(): void
    {
        $updater = '/install/core-update/index.php';

        $this->assertStringContainsString($updater, $this->service("Version=99.0.0\n")->showUpdateInfo());
        $this->assertStringNotContainsString($updater, $this->service("Version=1.0.0\n")->showUpdateInfo(), 'nothing newer');
        $this->assertStringNotContainsString($updater, $this->service(false)->showUpdateInfo(), 'no connection');
    }

    /**
     * @testdox The notice names a newer stable release, a newer beta only to a beta, and ends with the update
     */
    public function testAvailableUpdate(): void
    {
        $service = $this->service(false);

        $this->storeResult(ADMIDIO_VERSION);
        $this->assertSame('', $service->getAvailableUpdate(), 'the installed version itself, as after the update');

        $this->storeResult('1.0.0', '2.0.0', '1');
        $this->assertSame('', $service->getAvailableUpdate(), 'older versions');

        // A beta of the installed version that is one release further.
        $this->storeResult('1.0.0', ADMIDIO_VERSION, (string) ((int) ADMIDIO_VERSION_BETA + 1));
        $this->assertSame((int) ADMIDIO_VERSION_BETA > 0 ? ADMIDIO_VERSION . ' Beta ' . ((int) ADMIDIO_VERSION_BETA + 1) : '',
            $service->getAvailableUpdate(), 'a beta is only offered to a beta installation');

        $this->storeResult('99.0.0', '99.1.0', '1');
        $this->assertSame('99.0.0', $service->getAvailableUpdate(), 'the stable release before a beta');

        $this->storeResult('');
        $this->assertSame('', $service->getAvailableUpdate(), 'nothing found');
    }
}
