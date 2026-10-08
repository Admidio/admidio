<?php
/**
 * DateTime utility unit tests
 *
 * These tests exercise production code without database, filesystem or network access.
 */

namespace Admidio\Tests\Unit;

use Admidio\Infrastructure\Utils\DateTimeUtils;
use Admidio\Tests\Support\AdmidioTestCase;
use DateTime;

class DateTimeUtilsTest extends AdmidioTestCase
{
    /**
     * @testdox Get localized weekday for short and long format in English and German
     */
    public function testGetLocalizedWeekday(): void
    {
        // 2026-05-19 is a Tuesday
        $tuesday = new DateTime('2026-05-19 14:00:00');

        $this->assertSame('Tue', DateTimeUtils::getLocalizedWeekday($tuesday, 'short', 'en'));
        $this->assertSame('Tuesday', DateTimeUtils::getLocalizedWeekday($tuesday, 'long', 'en'));
        $this->assertSame('Di', DateTimeUtils::getLocalizedWeekday($tuesday, 'short', 'de'));
        $this->assertSame('Dienstag', DateTimeUtils::getLocalizedWeekday($tuesday, 'long', 'de'));
        $this->assertSame('', DateTimeUtils::getLocalizedWeekday($tuesday, 'none', 'de'));
    }

    /**
     * @testdox Format date with localized weekday prefix
     */
    public function testFormatWithWeekday(): void
    {
        $tuesday = new DateTime('2026-05-19 14:00:00');

        $this->assertSame('Di, 19.05.2026', DateTimeUtils::formatWithWeekday($tuesday, 'short', 'd.m.Y', 'de'));
        $this->assertSame('Dienstag, 19.05.2026', DateTimeUtils::formatWithWeekday($tuesday, 'long', 'd.m.Y', 'de'));
        $this->assertSame('19.05.2026', DateTimeUtils::formatWithWeekday($tuesday, 'none', 'd.m.Y', 'de'));
        $this->assertSame('Tue, 19.05.2026', DateTimeUtils::formatWithWeekday($tuesday, 'short', 'd.m.Y', 'en'));
        $this->assertSame('Tuesday, 19.05.2026', DateTimeUtils::formatWithWeekday($tuesday, 'long', 'd.m.Y', 'en'));

        // HTML wrapped for column alignment
        $this->assertSame(
            '<span class="admidio-event-weekday admidio-event-weekday-short">Di,</span> 19.05.2026',
            DateTimeUtils::formatWithWeekday($tuesday, 'short', 'd.m.Y', 'de', true)
        );
        $this->assertSame(
            '<span class="admidio-event-weekday admidio-event-weekday-long">Dienstag,</span> 19.05.2026',
            DateTimeUtils::formatWithWeekday($tuesday, 'long', 'd.m.Y', 'de', true)
        );
        $this->assertSame(
            '19.05.2026',
            DateTimeUtils::formatWithWeekday($tuesday, 'none', 'd.m.Y', 'de', true)
        );
    }
}
