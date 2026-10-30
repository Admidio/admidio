<?php
/**
 * Birthday In Year Tests
 *
 * The birthday plugin moves every birthday into the current, the previous and the next year to find
 * the birthdays around today. A birthday on 29 February has no date in a year without a leap day.
 * Combining the year with month and day made PostgreSQL abort the whole query and MySQL drop the
 * member silently (issue #1535). The birthday is now shifted by whole years, which places it on
 * 28 February in those years and leaves every other date unchanged.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Birthday\Tests\Integration;

use Admidio\Tests\Support\DatabaseTestCase;
use AdmidioPlugin\Birthday\Birthday;

require_once dirname(__DIR__, 2) . '/src/Birthday.php';

class BirthdayInYearTest extends DatabaseTestCase
{
    /**
     * Run the expression of the plugin for one birthday and one reference date.
     */
    private function birthdayInYear(string $birthday, string $referenceDate, int $yearOffset): string
    {
        $sql = 'SELECT ' . Birthday::getBirthdayInYearSql($this->getDatabase()->getEngine(), 'bd.usd_value', $yearOffset) . ' AS bdate
                  FROM (SELECT CAST(? AS VARCHAR(10)) AS usd_value) AS bd';

        // the placeholder of the reference date precedes the one of the birthday in the statement
        $bdate = (string) $this->getDatabase()->queryPrepared($sql, array($referenceDate, $birthday))->fetchColumn();

        return substr($bdate, 0, 10);
    }

    /**
     * @return array<string, array{string, string, int, string}>
     */
    public static function birthdayProvider(): array
    {
        return array(
            'leap day in a year without leap day' => array('1964-02-29', '2027-06-01', 0, '2027-02-28'),
            'leap day in a leap year' => array('1964-02-29', '2028-06-01', 0, '2028-02-29'),
            'leap day in the previous year' => array('1964-02-29', '2028-01-10', -1, '2027-02-28'),
            'leap day in the next year' => array('1964-02-29', '2027-12-20', 1, '2028-02-29'),
            'day after the leap day' => array('1964-03-01', '2027-06-01', 0, '2027-03-01'),
            'ordinary birthday' => array('1980-07-15', '2027-06-01', 0, '2027-07-15'),
        );
    }

    /**
     * Test that a birthday is moved into the requested year
     *
     * @dataProvider birthdayProvider
     * @testdox A birthday on $birthday is celebrated on $expected (reference $referenceDate, offset $yearOffset)
     */
    public function testBirthdayIsMovedIntoTheRequestedYear(string $birthday, string $referenceDate, int $yearOffset, string $expected): void
    {
        $this->assertSame($expected, $this->birthdayInYear($birthday, $referenceDate, $yearOffset));
    }
}
