<?php

namespace Admidio\Tests\Unit\InstallationUpdate;

use Admidio\InstallationUpdate\Service\InstallationStatistics;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use PDOStatement;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class InstallationStatisticsTest extends TestCase
{
    private array $originalSession;
    private array $originalGlobals;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION;
        $this->originalGlobals = array();
        foreach (array('gL10n', 'gLogger', 'gDb', 'gDbType', 'gSettingsManager') as $name) {
            $this->originalGlobals[$name] = array_key_exists($name, $GLOBALS) ? array($GLOBALS[$name]) : array();
        }
        $GLOBALS['gL10n'] = new class {
            public function get(string $key, array $parameters = array()): string { return $key; }
            public function getLanguage(): string { return 'de'; }
        };
        $GLOBALS['gLogger'] = new NullLogger();
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->originalSession;
        foreach ($this->originalGlobals as $name => $value) {
            if ($value === array()) {
                unset($GLOBALS[$name]);
            } else {
                $GLOBALS[$name] = $value[0];
            }
        }
    }

    public function testOptOutDoesNotRequireAPreviewOrAccessStorageOrHttp(): void
    {
        unset($_SESSION['installationStatisticsData']);
        $this->assertNull(InstallationStatistics::selectedData(array('adm_send_statistics' => '0')));
        $this->assertNull(InstallationStatistics::selectedData(array()));
        // No settings manager, database or HTTP client is needed for an opted-out request.
        InstallationStatistics::send(null);
    }

    public function testSelectionUsesServerSnapshotAndSurvivesSessionCleanup(): void
    {
        $snapshot = array('id' => 'installation-tenant', 'admidio' => array('version' => '5.1.0', 'previous_version' => null));
        $_SESSION['installationStatisticsData'] = $snapshot;
        $selected = InstallationStatistics::selectedData(array('adm_send_statistics' => '1', 'id' => 'tampered'));
        $_SESSION = array();
        $this->assertSame($snapshot, $selected);
    }

    public function testMissingPreviewIsRejectedWhenSendingWasSelected(): void
    {
        unset($_SESSION['installationStatisticsData']);
        $this->expectException(Exception::class);
        InstallationStatistics::selectedData(array('adm_send_statistics' => '1'));
    }

    public static function previousVersions(): array
    {
        return array('installation' => array(null), 'update' => array('5.0.0'));
    }

    /** @dataProvider previousVersions */
    public function testSharedPreviewWorksWithoutInstalledTables(?string $previousVersion): void
    {
        foreach (array('ADMIDIO_PATH' => dirname(__DIR__, 3), 'FOLDER_PLUGINS' => '/plugins',
            'ADMIDIO_VERSION_TEXT' => '5.1.0', 'TBL_PREFERENCES' => 'adm_preferences',
            'TBL_ORGANIZATIONS' => 'adm_organizations') as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        unset($GLOBALS['gSettingsManager']);
        if ($previousVersion !== null) {
            // The configured system language must take priority over the current UI language.
            $GLOBALS['gSettingsManager'] = new class {
                public function has(string $name): bool { return $name === 'system_language'; }
                public function getString(string $name): string { return 'pt-BR'; }
            };
        }
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('getVersion', 'queryPrepared'))->getMock();
        $database->method('getVersion')->willReturn('8.0.40');
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('stable-tenant');
        $database->expects($previousVersion === null ? $this->never() : $this->once())
            ->method('queryPrepared')->willReturn($statement);
        $GLOBALS['gDb'] = $database;
        $GLOBALS['gDbType'] = 'mysql';
        $_SESSION['installationStatisticsData'] = array('id' => 'stable-tenant');
        $page = $this->getMockBuilder(InstallationPresenter::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->once())->method('assignSmartyVariable')->with('statisticsJson',
            $this->callback(function (string $json) use ($previousVersion): bool {
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame('stable-tenant', $data['id']);
                $this->assertSame($previousVersion, $data['admidio']['previous_version']);
                $this->assertSame($previousVersion === null ? 'de' : 'pt-BR', $data['admidio']['language']);

                return true;
            }));
        $form = new FormPresenter('confirmation', 'installation.install-admidio.tpl');
        InstallationStatistics::addToForm($form, $page, $previousVersion);
        $this->assertSame('checked', $form->getElements()['adm_send_statistics']['attributes']['checked']);
        $this->assertSame($_SESSION['installationStatisticsData'], InstallationStatistics::selectedData(array('adm_send_statistics' => '1')));
        $this->assertSame('stable-tenant', InstallationStatistics::tenantId());
    }
}
