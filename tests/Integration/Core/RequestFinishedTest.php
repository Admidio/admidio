<?php
/**
 * The action request_finished
 *
 * Work that does not have to delay the answer runs in request_finished, at the very end of the
 * request and - under PHP-FPM - after the response was sent. A request that left a transaction open
 * does not dispatch it, because everything a listener wrote would be rolled back.
 */

namespace Admidio\Tests\Integration\Core;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\DatabaseTestCase;

class RequestFinishedTest extends DatabaseTestCase
{
    private int $calls = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once ADMIDIO_PATH . FOLDER_SYSTEM . '/bootstrap/shutdown.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->calls = 0;
        Hooks::addAction('request_finished', function (): void {
            ++$this->calls;
        }, 10, null, 'request-finished-test');
    }

    protected function tearDown(): void
    {
        Hooks::removeAction('request_finished', 'request-finished-test');
        parent::tearDown();
    }

    /**
     * @testdox The listeners run at the end of a request without an open transaction
     */
    public function testTheActionIsDispatched(): void
    {
        $database = $GLOBALS['gDb'];
        $GLOBALS['gDb'] = $this->createConfiguredMock(Database::class, array('isInTransaction' => false));
        try {
            admShutdown();
        } finally {
            $GLOBALS['gDb'] = $database;
        }

        $this->assertSame(1, $this->calls);
    }

    /**
     * @testdox A request that left a transaction open does not dispatch the action
     */
    public function testAnOpenTransactionSuppressesTheAction(): void
    {
        // DatabaseTestCase runs every test inside a transaction
        $this->assertTrue($this->getDatabase()->isInTransaction());

        admShutdown();

        $this->assertSame(0, $this->calls);
    }
}
