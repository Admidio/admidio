<?php

namespace Admidio\Tests\Unit\Infrastructure\Support;

use Psr\Log\AbstractLogger;

/**
 * Keeps every log record, so a test can check what would have been written to the log files.
 */
final class SmtpOAuthLoggerDouble extends AbstractLogger
{
    /** @var array<int,array{level:string,message:string,context:array}> */
    public array $records = array();

    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        $this->records[] = array('level' => (string)$level, 'message' => (string)$message, 'context' => $context);
    }

    /**
     * @return string All messages of the records, one per line.
     */
    public function messages(): string
    {
        return implode("\n", array_column($this->records, 'message'));
    }
}
