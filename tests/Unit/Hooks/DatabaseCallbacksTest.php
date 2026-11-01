<?php

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Tests\Unit\Hooks\Support\EntityHookTestCase;
use Admidio\Tests\Unit\Hooks\Support\FakeDatabase;
use PHPMailer\PHPMailer\Exception;

class DatabaseCallbacksTest extends EntityHookTestCase
{
    public function testImmediateNotificationFailureIsLoggedWithoutReportingAFailedSave(): void
    {
        $logger = $GLOBALS['gLogger'];
        $failureLogger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $failureLogger->expects($this->once())->method('error')->with(
            'DATABASE: after-commit callback failed',
            $this->callback(static fn(array $context): bool => $context['exception'] instanceof Exception),
        );
        $GLOBALS['gLogger'] = $failureLogger;
        try {
            (new FakeDatabase())->registerAfterCommit(static function (): void {
                throw new Exception('Simulated SMTP failure');
            });
        } finally {
            $GLOBALS['gLogger'] = $logger;
        }
    }

    public function testNotificationFailureDoesNotInterruptCommittedWork(): void
    {
        $db = new FakeDatabase();
        $db->queryPrepared('CREATE TABLE committed_work (id INTEGER)');
        $db->startTransaction();
        $db->queryPrepared('INSERT INTO committed_work (id) VALUES (?)', [1]);
        $completed = [];
        $db->registerAfterCommit(static function (): void {
            throw new Exception('Simulated SMTP failure');
        });
        $db->registerAfterCommit(static function () use (&$completed): void {
            $completed[] = 'next notification';
        });

        $this->assertTrue($db->endTransaction());
        $this->assertFalse($db->isInTransaction());
        $this->assertSame(['next notification'], $completed);
        $this->assertSame(1, (int) $db->queryPrepared('SELECT COUNT(*) FROM committed_work')->fetchColumn());
        $db->startTransaction();
        $db->endTransaction();
        $this->assertSame(['next notification'], $completed, 'Callbacks must not be retried after a failure.');
    }
}
