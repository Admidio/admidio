<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\TenantStatistics;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class TenantStatisticsTest extends TestCase
{
    public function testReviewedSnapshotIsSentUnchangedWithoutCollectingNewData(): void
    {
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://installation.example.test');
        }
        $history = array();
        $handler = HandlerStack::create(new MockHandler(array(new Response(200))));
        $handler->push(Middleware::history($history));
        $statistics = new TenantStatistics('tenant', '5.0.0', new Client(array('handler' => $handler)));
        $snapshot = array(
            'id' => 'tenant', 'timestamp' => '2026-10-22 10:00:00',
            'php' => array('version' => '8.2.0', 'extensions' => array('PDO' => false)),
            'database' => array('type' => 'mysql', 'version' => '8.0.0'),
            'admidio' => array('version' => '5.1.0', 'previous_version' => '5.0.0', 'plugins' => array())
        );
        $preview = $statistics->getJsonData($snapshot);

        // No database globals exist here: using fresh data instead of the snapshot would fail.
        $this->assertTrue($statistics->sent($snapshot));
        $this->assertCount(1, $history);
        $this->assertSame($preview, (string) $history[0]['request']->getBody());
        $this->assertSame(array($snapshot), json_decode($preview, true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('application/json', $history[0]['request']->getHeaderLine('Content-Type'));
    }
}
