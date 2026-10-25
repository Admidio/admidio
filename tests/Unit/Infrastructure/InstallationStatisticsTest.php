<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\Database;
use PDOStatement;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class InstallationStatisticsTest extends TestCase
{
    private array $originalDatabase;

    protected function setUp(): void
    {
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
        if ($this->originalDatabase === array()) {
            unset($GLOBALS['gDb']);
        } else {
            $GLOBALS['gDb'] = $this->originalDatabase[0];
        }
    }

    public function testReviewedSnapshotIsSentUnchangedWithoutCollectingNewData(): void
    {
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://installation.example.test');
        }
        $history = array();
        $handler = HandlerStack::create(new MockHandler(array(new Response(200))));
        $handler->push(Middleware::history($history));
        $statistics = $this->getMockBuilder(InstallationStatistics::class)
            ->setConstructorArgs(array('tenant', '5.0.0', new Client(array('handler' => $handler))))
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
        $this->assertTrue($statistics->sent($snapshot));
        $this->assertCount(1, $history);
        $this->assertSame($preview, (string) $history[0]['request']->getBody());
        $this->assertInstanceOf(\stdClass::class, json_decode($preview, false, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($snapshot, json_decode($preview, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('application/json', $history[0]['request']->getHeaderLine('Content-Type'));
    }

    public function testFailedResponseDoesNotRecordASuccessfulSend(): void
    {
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://installation.example.test');
        }
        $client = new Client(array('handler' => HandlerStack::create(new MockHandler(array(new Response(503)))),
            'http_errors' => false));
        $statistics = $this->getMockBuilder(InstallationStatistics::class)
            ->setConstructorArgs(array('tenant', null, $client))
            ->onlyMethods(array('recordSuccessfulSend'))->getMock();
        $statistics->expects($this->never())->method('recordSuccessfulSend');

        $this->assertFalse($statistics->sent(array('id' => 'tenant')));
    }

    public static function monthlyIntervals(): array
    {
        return array(
            'never sent' => array('', '2026-10-25 12:00:00', true),
            'recent' => array('2026-10-01 12:00:00', '2026-10-25 12:00:00', false),
            'exact month' => array('2026-09-25 12:00:00', '2026-10-25 12:00:00', false),
            'over a month' => array('2026-09-25 12:00:00', '2026-10-25 12:00:01', true),
            'future date' => array('2026-11-25 12:00:00', '2026-10-25 12:00:00', false),
            'short month' => array('2026-01-31 12:00:00', '2026-02-28 12:00:01', true),
            'leap year' => array('2024-01-31 12:00:00', '2024-02-29 12:00:00', false),
            'invalid date' => array('invalid', '2026-10-25 12:00:00', true)
        );
    }

    /** @dataProvider monthlyIntervals */
    public function testMonthlyInterval(string $lastSent, string $now, bool $expected): void
    {
        $this->assertSame($expected, InstallationStatistics::isDue($lastSent,
            new \DateTimeImmutable($now, new \DateTimeZone('UTC'))));
    }
}
