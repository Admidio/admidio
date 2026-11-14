<?php

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Entity\Entity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Entity::valuesDiffer() decides whether a column counts as changed, and with it what is written to
 * the database and to the change history. A decimal column is read back from the database as a
 * string with all its decimals ("6.00"), while the code sets a number (6): comparing the two as
 * strings reported a change that none had made, for every credit, amount or price that was saved
 * again unchanged.
 */
class EntityValuesDifferTest extends TestCase
{
    private function differs(string $type, mixed $old, ?string $new): bool
    {
        $entity = (new ReflectionClass(Entity::class))->newInstanceWithoutConstructor();

        $infos = new \ReflectionProperty(Entity::class, 'columnsInfos');
        $infos->setAccessible(true);
        $infos->setValue($entity, array('col' => array('type' => $type)));

        $method = new \ReflectionMethod(Entity::class, 'valuesDiffer');
        $method->setAccessible(true);

        return $method->invoke($entity, 'col', $old, $new);
    }

    public static function decimalTypes(): array
    {
        return array(array('decimal'), array('numeric'), array('float'), array('double'), array('real'));
    }

    /**
     * @dataProvider decimalTypes
     */
    public function testTheSameNumberIsNotAChangeWhateverItsNotation(string $type): void
    {
        $this->assertFalse($this->differs($type, '6.00', '6'));
        $this->assertFalse($this->differs($type, '6.00', '6.0'));
        $this->assertFalse($this->differs($type, '6.10', '6.1'));
        $this->assertFalse($this->differs($type, '0.00', '0'));
        $this->assertFalse($this->differs($type, 0.0, '0'));
        $this->assertFalse($this->differs($type, '0', '0.00'));
    }

    /**
     * @dataProvider decimalTypes
     */
    public function testAnotherNumberIsAChange(string $type): void
    {
        $this->assertTrue($this->differs($type, '6.00', '7'));
        $this->assertTrue($this->differs($type, '6.00', '6.01'));
        $this->assertTrue($this->differs($type, '6.00', '0'));
        $this->assertTrue($this->differs($type, '0.00', '1'));
    }

    /**
     * @dataProvider decimalTypes
     */
    public function testAnUnsetValueIsNotTheNumberZero(string $type): void
    {
        $this->assertTrue($this->differs($type, null, '0'));
        $this->assertTrue($this->differs($type, '', '0.00'));
        $this->assertTrue($this->differs($type, '0.00', null));
        $this->assertTrue($this->differs($type, null, '6'));
        $this->assertFalse($this->differs($type, null, null));
        $this->assertFalse($this->differs($type, '', ''));
        $this->assertFalse($this->differs($type, null, ''));
    }

    /**
     * A float has 53 bits of mantissa, a decimal column can hold more digits than that.
     */
    public static function exactTypes(): array
    {
        return array(array('decimal'), array('numeric'));
    }

    /**
     * @dataProvider exactTypes
     */
    public function testExactNumbersAreComparedWithoutRounding(string $type): void
    {
        $this->assertTrue($this->differs($type, '9007199254740992.00', '9007199254740993'));
        $this->assertTrue($this->differs($type, '0.10000000000000000001', '0.1'));
        $this->assertFalse($this->differs($type, '9007199254740993.00', '9007199254740993'));
        $this->assertFalse($this->differs($type, '-0.00', '0'));
        $this->assertFalse($this->differs($type, '0.50', '.5'));
        $this->assertTrue($this->differs($type, '-1.50', '1.5'));
        $this->assertFalse($this->differs($type, '1000.00', '1e3'), 'A value in another notation is compared as a number.');
    }

    public function testOtherTypesAreStillComparedAsBefore(): void
    {
        $this->assertFalse($this->differs('varchar', 'abc', 'abc'));
        $this->assertTrue($this->differs('varchar', 'abc', 'abd'));
        $this->assertTrue($this->differs('varchar', '6.00', '6'), 'A string stays a string.');
        $this->assertFalse($this->differs('boolean', null, '0'));
        $this->assertTrue($this->differs('boolean', null, '1'));
        $this->assertFalse($this->differs('date', '2026-01-01', '2026-01-01'));
        $this->assertTrue($this->differs('date', '2026-01-01', '2026-01-02'));
        $this->assertFalse($this->differs('integer', '5', '5'));
        $this->assertTrue($this->differs('integer', '5', '6'));
    }
}
