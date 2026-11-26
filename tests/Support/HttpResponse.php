<?php

namespace Admidio\Tests\Support;

final class HttpResponse
{
    /** @param array<string,list<string>> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): string
    {
        return implode(', ', $this->headers[strtolower($name)] ?? []);
    }

    public function json(): array
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
