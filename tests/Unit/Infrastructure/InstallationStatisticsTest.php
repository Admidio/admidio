<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\Database;
use PDOStatement;
use PHPUnit\Framework\TestCase;

class InstallationStatisticsTest extends TestCase
{
    private array $originalDatabase;
    private array $originalStatisticsUrl;
    private array $originalContext = array();

    protected function setUp(): void
    {
        // Exercise the installer fallback without inheriting another test's language or settings stubs.
        foreach (array('gL10n', 'gSettingsManager') as $name) {
            if (array_key_exists($name, $GLOBALS)) {
                $this->originalContext[$name] = $GLOBALS[$name];
            }
            unset($GLOBALS[$name]);
        }
        $this->originalStatisticsUrl = array_key_exists('gStatisticsUrl', $GLOBALS) ? array($GLOBALS['gStatisticsUrl']) : array();
        unset($GLOBALS['gStatisticsUrl']);
        $this->originalDatabase = array_key_exists('gDb', $GLOBALS) ? array($GLOBALS['gDb']) : array();
        foreach (array('TBL_PREFERENCES' => 'adm_preferences', 'TBL_ORGANIZATIONS' => 'adm_organizations') as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('queryPrepared', 'getVersion'))->getMock();
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('tenant');
        $database->method('queryPrepared')->willReturn($statement);
        $database->expects($this->never())->method('getVersion');
        $GLOBALS['gDb'] = $database;
    }

    protected function tearDown(): void
    {
        foreach (array('gL10n', 'gSettingsManager') as $name) {
            if (array_key_exists($name, $this->originalContext)) {
                $GLOBALS[$name] = $this->originalContext[$name];
            } else {
                unset($GLOBALS[$name]);
            }
        }
        $this->originalContext = array();
        if ($this->originalStatisticsUrl === array()) {
            unset($GLOBALS['gStatisticsUrl']);
        } else {
            $GLOBALS['gStatisticsUrl'] = $this->originalStatisticsUrl[0];
        }
        if ($this->originalDatabase === array()) {
            unset($GLOBALS['gDb']);
        } else {
            $GLOBALS['gDb'] = $this->originalDatabase[0];
        }
    }

    public static function databaseTypes(): array
    {
        return array(
            'MySQL' => array('mysql', '8.0.0'),
            'MariaDB' => array('mariadb', '10.11.0'),
            'PostgreSQL' => array('pgsql', '16.0')
        );
    }

    /** @dataProvider databaseTypes */
    public function testDatabaseTypeUsesDetectedServerType(string $type, string $version): void
    {
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('getInformation'))->getMock();
        $database->expects($this->once())->method('getInformation')->willReturn(array($type, $version));
        $GLOBALS['gDb'] = $database;

        $method = new \ReflectionMethod(InstallationStatistics::class, 'getDatabaseType');
        $this->assertSame($type, $method->invoke(null));
    }

    public function testReviewedSnapshotIsSentUnchangedWithoutCollectingNewData(): void
    {
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://installation.example.test');
        }
        $history = array();
        $client = static function (string $url, string $json) use (&$history): int {
            $history[] = array('url' => $url, 'body' => $json);
            return 200;
        };
        $statistics = $this->getMockBuilder(InstallationStatistics::class)
            ->setConstructorArgs(array('5.0.0', $client))
            ->onlyMethods(array('recordSuccessfulSend'))->getMock();
        $statistics->expects($this->once())->method('recordSuccessfulSend')->with($GLOBALS['gDb']);
        $snapshot = array(
            'id' => 'tenant', 'timestamp' => '2026-10-22 10:00:00',
            'php' => array('version' => '8.2.0', 'extensions' => array('PDO' => false)),
            'database' => array('type' => 'mysql', 'version' => '8.0.0'),
            'admidio' => array('version' => '5.1.0', 'previous_version' => '5.0.0', 'language' => 'pt-BR', 'plugins' => array())
        );
        $preview = $statistics->getJsonData($snapshot);

        // Only the canonical ID is read; collecting fresh technology data would fail this test.
        $this->assertTrue($statistics->send($snapshot));
        $this->assertCount(1, $history);
        $this->assertSame($preview, (string) $history[0]['body']);
        $this->assertInstanceOf(\stdClass::class, json_decode($preview, false, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($snapshot, json_decode($preview, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('https://www.admidio.org/api/installation-statistics/statistics.php', $history[0]['url']);
    }

    public function testConfiguredEndpointOverridesDefault(): void
    {
        $GLOBALS['gStatisticsUrl'] = 'https://local.example.test/api/statistics.php';
        $client = function (string $url, string $json): int {
            $this->assertSame($GLOBALS['gStatisticsUrl'], $url);
            return 503;
        };
        $statistics = new InstallationStatistics(null, $client);

        $this->assertFalse($statistics->send(array('id' => 'tenant')));
    }

    public static function failedStatuses(): array
    {
        return array('network failure' => array(0), 'redirect' => array(302), 'server error' => array(503));
    }

    public static function demoEndpoints(): array
    {
        return array(
            'unset endpoint' => array(null, false),
            'configured endpoint' => array('https://local.example.test/api/statistics.php', true)
        );
    }

    /** @dataProvider demoEndpoints */
    public function testDemoOnlySendsToConfiguredEndpoint(?string $url, bool $expectedSend): void
    {
        if ($url !== null) {
            $GLOBALS['gStatisticsUrl'] = $url;
        }
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('ADM_DEMO');
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('queryPrepared'))->getMock();
        $database->method('queryPrepared')->willReturnCallback(function (string $sql, array $params) use ($statement) {
            $this->assertSame('SELECT org_shortname FROM ' . TBL_ORGANIZATIONS . ' WHERE org_id = ?', $sql);
            $this->assertSame(array(1), $params);
            return $statement;
        });
        if (!$expectedSend) {
            $GLOBALS['gDb'] = $database;
        }
        $sent = false;
        $statistics = $this->getMockBuilder(InstallationStatistics::class)
            ->setConstructorArgs(array(null, static function () use (&$sent): int {
                $sent = true;
                return 503;
            }))
            ->onlyMethods(array('getInfoData', 'recordSuccessfulSend'))->getMock();
        $statistics->expects($this->never())->method('getInfoData');
        $statistics->expects($this->never())->method('recordSuccessfulSend');

        $this->assertFalse($statistics->send(array('id' => 'tenant')));
        $this->assertSame($expectedSend, $sent);
    }

    /** @dataProvider failedStatuses */
    public function testFailedResponseDoesNotRecordASuccessfulSend(int $statusCode): void
    {
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://installation.example.test');
        }
        $client = static fn(string $url, string $json): int => $statusCode;
        $statistics = $this->getMockBuilder(InstallationStatistics::class)
            ->setConstructorArgs(array(null, $client))
            ->onlyMethods(array('recordSuccessfulSend'))->getMock();
        $statistics->expects($this->never())->method('recordSuccessfulSend');

        $this->assertFalse($statistics->send(array('id' => 'tenant')));
    }

    public function testTimestampHasExplicitUtcTimezoneAndExtensionVersionsAreNullableStrings(): void
    {
        $method = new \ReflectionMethod(InstallationStatistics::class, 'getDateTime');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $method->invoke(null));
        $method = new \ReflectionMethod(InstallationStatistics::class, 'getPHPExtensions');
        foreach ($method->invoke(null) as $extension => $version) {
            $this->assertSame(phpversion($extension) === false ? null : phpversion($extension), $version);
        }
        foreach (array('system_tenant_id', 'system_statistics_last_sent') as $name) {
            $this->assertContains($name, \Admidio\Preferences\Service\PreferenceDefinitions::internalNames());
        }
    }

    public static function monthlyIntervals(): array
    {
        return array(
            'never sent' => array('', '2026-10-25 12:00:00', true),
            'ISO UTC recent' => array('2026-10-01T12:00:00Z', '2026-10-25 12:00:00', false),
            'ISO UTC due' => array('2026-09-25T12:00:00Z', '2026-10-25 12:00:01', true),
            'recent' => array('2026-10-01 12:00:00', '2026-10-25 12:00:00', false),
            'exact month' => array('2026-09-25 12:00:00', '2026-10-25 12:00:00', false),
            'over a month' => array('2026-09-25 12:00:00', '2026-10-25 12:00:01', true),
            'future date' => array('2026-11-25 12:00:00', '2026-10-25 12:00:00', false),
            'short month' => array('2026-01-31 12:00:00', '2026-02-28 12:00:01', true),
            'leap year' => array('2024-01-31 12:00:00', '2024-02-29 12:00:00', false),
            'invalid date' => array('invalid', '2026-10-25 12:00:00', true),
            'retry pending at month end' => array('retry:2026-03-31T13:00:00Z', '2026-03-31 12:59:59', false),
            'retry deadline' => array('retry:2026-03-31T13:00:00Z', '2026-03-31 13:00:00', true),
            'retry overdue' => array('retry:2026-03-31T13:00:00Z', '2026-03-31 13:00:01', true)
        );
    }

    /** @dataProvider monthlyIntervals */
    public function testMonthlyInterval(string $lastSent, string $now, bool $expected): void
    {
        $this->assertSame($expected, InstallationStatistics::isDue($lastSent,
            new \DateTimeImmutable($now, new \DateTimeZone('UTC'))));
    }

    public static function monthlySendResults(): array
    {
        return array('success' => array(200), 'failure' => array(503), 'exception' => array(null));
    }

    /** @dataProvider monthlySendResults */
    public function testMonthlySendReservesRetryAndReleasesTransactionBeforeTransport(?int $status): void
    {
        if (!defined('TBL_COMPONENTS')) {
            define('TBL_COMPONENTS', 'adm_components');
        }
        if (!defined('ADMIDIO_VERSION_TEXT')) {
            define('ADMIDIO_VERSION_TEXT', '6.0.0');
        }
        $originalLogger = $GLOBALS['gLogger'] ?? null;
        $GLOBALS['gLogger'] = new \Psr\Log\NullLogger();
        $GLOBALS['gStatisticsUrl'] = 'https://statistics.example.test';
        $lastSent = '';
        $inTransaction = false;
        $database = $this->getMockBuilder(Database::class)->disableOriginalConstructor()
            ->onlyMethods(array('queryPrepared', 'startTransaction', 'endTransaction', 'rollback', 'getInformation', 'getVersion'))->getMock();
        $database->method('getInformation')->willReturn(array('mysql', '8.0'));
        $database->method('getVersion')->willReturn('8.0');
        $database->method('startTransaction')->willReturnCallback(function () use (&$inTransaction): bool {
            $inTransaction = true;
            return true;
        });
        $database->method('endTransaction')->willReturnCallback(function () use (&$inTransaction): bool {
            $inTransaction = false;
            return true;
        });
        $database->expects($this->never())->method('rollback');
        $database->method('queryPrepared')->willReturnCallback(function (string $sql, array $params = array()) use (&$lastSent) {
            $value = '1';
            if (str_starts_with($sql, 'UPDATE ')) {
                $lastSent = $params[0];
            } elseif (($params[0] ?? null) === 'system_statistics_last_sent') {
                $value = $lastSent;
            } elseif (($params[0] ?? null) === 'system_tenant_id') {
                $value = 'tenant';
            }
            $statement = $this->createMock(PDOStatement::class);
            $statement->method('fetchColumn')->willReturn($value);
            $statement->method('rowCount')->willReturn(1);
            return $statement;
        });
        $GLOBALS['gDb'] = $database;
        $calls = 0;
        $client = function () use (&$calls, &$inTransaction, &$lastSent, $status): int {
            ++$calls;
            $this->assertFalse($inTransaction);
            $this->assertStringStartsWith('retry:', $lastSent);
            $deadline = new \DateTimeImmutable(substr($lastSent, 6));
            $this->assertEqualsWithDelta(time() + 3600, $deadline->getTimestamp(), 2);
            if ($status === null) {
                throw new \RuntimeException('Transport failed');
            }
            return $status;
        };
        try {
            InstallationStatistics::sendIfDue($database, 1, $client);
            $this->assertSame(1, $calls);
            $this->assertFalse($inTransaction);
            $this->assertSame($status !== 200, str_starts_with($lastSent, 'retry:'));
            $this->assertFalse(InstallationStatistics::isDue($lastSent));
            InstallationStatistics::sendIfDue($database, 1, $client);
            $this->assertSame(1, $calls);
        } finally {
            $GLOBALS['gLogger'] = $originalLogger;
        }
    }
}
