<?php

namespace Admidio\Tests\Unit\InstallationUpdate;

use Admidio\InstallationUpdate\Service\StatisticsConsent;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Database;
use PDOStatement;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\InstallationPresenter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class StatisticsConsentTest extends TestCase
{
    private array $originalSession;
    private array $originalGlobals;

    protected function setUp(): void
    {
        $this->originalSession = $_SESSION;
        unset($_SESSION['installationStatisticsAlreadyEnabled']);
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
        $this->assertNull(StatisticsConsent::selectedData(array('adm_send_statistics' => '0')));
        $this->assertNull(StatisticsConsent::selectedData(array()));
        // No settings manager, database or HTTP client is needed for an opted-out request.
        StatisticsConsent::send(null);
    }

    public function testSelectionUsesServerSnapshotAndSurvivesSessionCleanup(): void
    {
        $snapshot = array('id' => 'installation-tenant', 'admidio' => array('version' => '5.1.0', 'previous_version' => null));
        $_SESSION['installationStatisticsData'] = $snapshot;
        $selected = StatisticsConsent::selectedData(array('adm_send_statistics' => '1', 'id' => 'tampered'));
        $_SESSION = array();
        $this->assertSame($snapshot, $selected);
    }

    public function testMissingPreviewIsRejectedWhenSendingWasSelected(): void
    {
        unset($_SESSION['installationStatisticsData']);
        $this->expectException(Exception::class);
        StatisticsConsent::selectedData(array('adm_send_statistics' => '1'));
    }

    public static function previousVersions(): array
    {
        return array(
            'installation' => array(null, false),
            'update without consent' => array('5.0.0', false),
            'update with consent' => array('5.0.0', true)
        );
    }

    /** @dataProvider previousVersions */
    public function testSharedPreviewWorksWithoutInstalledTables(?string $previousVersion, bool $enabled): void
    {
        foreach (array('ADMIDIO_PATH' => dirname(__DIR__, 3), 'FOLDER_PLUGINS' => '/plugins',
            'ADMIDIO_VERSION_TEXT' => '5.1.0', 'TBL_PREFERENCES' => 'adm_preferences',
            'TBL_ORGANIZATIONS' => 'adm_organizations', 'TBL_COMPONENTS' => 'adm_components') as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        \Admidio\Infrastructure\Plugins\PluginRegistry::setInstallations(array());
        unset($GLOBALS['gSettingsManager']);
        if ($previousVersion !== null) {
            // The configured system language must take priority over the current UI language.
            $GLOBALS['gSettingsManager'] = new class {
                public function has(string $name): bool { return $name === 'system_language'; }
                public function getString(string $name): string { return 'pt-BR'; }
            };
        }
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('getInformation', 'getVersion', 'getTableColumns', 'queryPrepared'))->getMock();
        $database->method('getTableColumns')->willReturn(array('com_id', 'com_name_intern', 'com_version'));
        $database->method('getInformation')->willReturn(array('mysql', '8.0.40'));
        $database->method('getVersion')->willReturn('8.0.40');
        $database->expects($previousVersion === null ? $this->never() : $this->exactly(2))
            ->method('queryPrepared')->willReturnCallback(function (string $sql, array $params) use ($enabled): PDOStatement {
                $statement = $this->createMock(PDOStatement::class);
                $statement->method('fetchColumn')->willReturn(
                    $params[0] === 'system_statistics_enabled' ? ($enabled ? '1' : false) : 'stable-tenant'
                );
                return $statement;
            });
        $GLOBALS['gDb'] = $database;
        $GLOBALS['gDbType'] = 'mysql';
        $_SESSION['installationStatisticsData'] = array('id' => 'stable-tenant');
        $page = $this->getMockBuilder(InstallationPresenter::class)->disableOriginalConstructor()->getMock();
        $page->expects($this->exactly(5))->method('assignSmartyVariable')
            ->willReturnCallback(function (string $name, $value) use ($previousVersion, $enabled): void {
                if ($name === 'statisticsEnabled') {
                    $this->assertTrue($value);
                    return;
                }
                if ($name === 'statisticsInline') {
                    $this->assertSame($previousVersion !== null, $value);
                    return;
                }
                if ($name === 'statisticsAlreadyEnabled') {
                    $this->assertSame($enabled, $value);
                    return;
                }
                if ($name === 'statisticsSchemaOnly') {
                    $this->assertSame($previousVersion !== null, $value);
                    return;
                }
                $this->assertSame('statisticsJson', $name);
                $json = $value;
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if ($previousVersion !== null) {
                    $this->assertSame('<uuid>', $data['id']);
                    $this->assertSame(array('<plugin>' => '<version>'), $data['admidio']['plugins']);
                    $this->assertSame(array('<extension>' => '<version|null>'), $data['php']['extensions']);
                    $this->assertStringNotContainsString('stable-tenant', $json);
                    $this->assertStringNotContainsString('pt-BR', $json);
                    $this->assertStringNotContainsString('8.0.40', $json);
                    return;
                }
                $this->assertSame('stable-tenant', $data['id']);
                $this->assertSame($previousVersion, $data['admidio']['previous_version']);
                $this->assertSame($previousVersion === null ? 'de' : 'pt-BR', $data['admidio']['language']);

            });
        $form = new FormPresenter('confirmation', 'installation.install-admidio.tpl');
        StatisticsConsent::addToForm($form, $page, $previousVersion);
        $this->assertSame(!$enabled, isset($form->getElements()['adm_send_statistics']));
        $this->assertSame(!$enabled, isset($form->getElements()['adm_send_statistics']['attributes']['checked']));
        $this->assertSame($enabled ? $_SESSION['installationStatisticsData'] : null, StatisticsConsent::selectedData(array()));
        $this->assertSame($_SESSION['installationStatisticsData'], StatisticsConsent::selectedData(array('adm_send_statistics' => '1')));
        $this->assertSame('stable-tenant', StatisticsConsent::tenantId());
    }
}
