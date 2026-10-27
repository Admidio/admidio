<?php
/**
 * The one definition of a valid membership
 *
 * A membership is valid from its start date up to, but not including, its end date: the end date is
 * the first day without membership.
 */

namespace Admidio\Tests\Unit;

use Admidio\Roles\Entity\Membership;
use Admidio\Tests\Support\AdmidioTestCase;

class MembershipValidityTest extends AdmidioTestCase
{
    /**
     * @testdox The SQL condition and its parameters for a prepared statement
     */
    public function testSqlCondition(): void
    {
        $this->assertSame(
            array('sql' => 'mem_begin <= ? AND mem_end > ?', 'params' => array('2026-10-27', '2026-10-27')),
            Membership::getSqlValidCondition('2026-10-27')
        );
        $this->assertSame(
            'mem.mem_begin <= ? AND mem.mem_end > ?',
            Membership::getSqlValidCondition('2026-10-27', 'mem')['sql']
        );
    }

    /**
     * @return array<string,array{0:string,1:string,2:bool}>
     */
    public static function periods(): array
    {
        return array(
            'starts today' => array('2026-10-27', '9999-12-31', true),
            'starts tomorrow' => array('2026-10-28', '9999-12-31', false),
            'ends tomorrow' => array('2020-01-01', '2026-10-28', true),
            'ends today' => array('2020-01-01', '2026-10-27', false),
            'ended yesterday' => array('2020-01-01', '2026-10-26', false)
        );
    }

    /**
     * @dataProvider periods
     * @testdox The PHP check agrees with the SQL condition
     */
    public function testPeriodCheck(string $begin, string $end, bool $valid): void
    {
        $this->assertSame($valid, Membership::isValidPeriod($begin, $end, '2026-10-27'));
    }
}
