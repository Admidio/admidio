<?php

namespace Admidio\Tests\Unit\Infrastructure\Support;

/**
 * Returns the id of a text together with its parameters, so a test sees which text was chosen and
 * what was filled into it.
 */
final class SmtpOAuthLanguageDouble
{
    public function getLanguageIsoCode(): string
    {
        return 'en';
    }

    public function get(string $textId, array $params = array()): string
    {
        return $params === array() ? $textId : $textId . '(' . implode('|', $params) . ')';
    }
}
