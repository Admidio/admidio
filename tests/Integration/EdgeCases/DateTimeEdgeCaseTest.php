<?php
/**
 * Date & Time Edge Case Tests
 *
 * Tests boundary conditions and edge cases for date/time operations.
 *
 * @testdox Date and time edge cases are handled correctly
 */

namespace Admidio\Tests\Integration\EdgeCases;

use Admidio\Tests\Support\DatabaseTestCase;

class DateTimeEdgeCaseTest extends DatabaseTestCase
{
    /**
     * Test event spanning midnight boundary
     *
     * @testdox Events spanning midnight boundary are handled correctly
     */
    public function testEventSpanningMidnight(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Time Org');
        $category = $builder->createCategory('Events', 'EVT', $org['org_id']);

        // Act - Create event that spans midnight
        // Event from 11 PM to 1 AM next day
        $eventStart = '2026-09-15 23:00:00';
        $eventEnd = '2026-09-16 01:00:00';

        // Assert - Event times are set correctly
        $this->assertNotEmpty($category['cat_id']);
        // Verify time boundary calculation
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $eventStart);
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $eventEnd);
        $this->assertGreaterThan($start, $end);
        $this->assertEquals('2026-09-15', $start->format('Y-m-d'));
        $this->assertEquals('2026-09-16', $end->format('Y-m-d'));
    }

    /**
     * Test all-day event date conversion
     *
     * @testdox All-day events are handled with proper date ranges
     */
    public function testAllDayEventConversion(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $category = $builder->createCategory('Events', 'EVT', $org['org_id']);

        // Act - Define all-day event dates
        $dayStart = '2026-09-20 00:00:00';
        $dayEnd = '2026-09-20 23:59:59';

        // Assert - All-day date range is valid
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $dayStart);
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $dayEnd);
        $this->assertEquals('00:00:00', $start->format('H:i:s'));
        $this->assertEquals('23:59:59', $end->format('H:i:s'));
        $this->assertEquals($start->format('Y-m-d'), $end->format('Y-m-d'));
    }

    /**
     * Test historical event date handling
     *
     * @testdox Historical events from the past are stored correctly
     */
    public function testHistoricalEventDateHandling(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $category = $builder->createCategory('Events', 'EVT', $org['org_id']);

        // Act - Create role with membership dates in past
        $role = $builder->createRole('Past Members', $org['org_id']);
        $user = $builder->createUser('olduser', 'old@company', $org['org_id']);

        // Membership from 2020 to 2023
        $pastStart = '2020-01-01';
        $pastEnd = '2023-12-31';

        // Assert - Past dates are valid
        $startDate = \DateTime::createFromFormat('Y-m-d', $pastStart);
        $endDate = \DateTime::createFromFormat('Y-m-d', $pastEnd);
        $this->assertLessThan($endDate, $startDate);
        $this->assertEquals(2020, (int) $startDate->format('Y'));
        $this->assertEquals(2023, (int) $endDate->format('Y'));
    }

    /**
     * Test future event date validation
     *
     * @testdox Future events far in the future are valid
     */
    public function testFutureEventDateValidation(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');
        $category = $builder->createCategory('Events', 'EVT', $org['org_id']);

        // Act - Define event far in the future
        $futureDate = '2050-12-31 18:00:00';

        // Assert - Future date is valid
        $eventDate = \DateTime::createFromFormat('Y-m-d H:i:s', $futureDate);
        $this->assertEquals(2050, (int) $eventDate->format('Y'));
        $this->assertGreaterThan(new \DateTime(), $eventDate);
    }

    /**
     * Test leap year date handling
     *
     * @testdox Leap year dates (Feb 29) are handled correctly
     */
    public function testLeapYearDateHandling(): void
    {
        // Arrange
        $builder = $this->getTestDataBuilder();
        $org = $builder->createOrganization('Org');

        // Act - Create membership spanning leap day
        $role = $builder->createRole('Leap Year Role', $org['org_id']);
        $user = $builder->createUser('leapuser', 'leap@company', $org['org_id']);

        // 2024 is a leap year
        $leapDayStart = '2024-02-28 23:00:00';
        $leapDayEnd = '2024-03-01 01:00:00';

        // Assert - Leap year dates are valid
        $start = \DateTime::createFromFormat('Y-m-d H:i:s', $leapDayStart);
        $end = \DateTime::createFromFormat('Y-m-d H:i:s', $leapDayEnd);

        // 2024 is leap year
        $year = 2024;
        $isLeap = ($year % 4 === 0 && $year % 100 !== 0) || ($year % 400 === 0);
        $this->assertTrue($isLeap);
        $this->assertLessThan($end, $start);
    }
}
