<?php
/**
 * The change-tracking part of Entity::setValue(), extracted verbatim into
 * Support\EntityChangeTrackingFixed so the test cannot drift from the code: which value is
 * remembered as "previous", when a round trip clears the changed flag again, and how the boolean
 * and date/time types compare.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\Tests\Unit\Hooks\Support\EntityChangeTrackingFixed;

class EntityChangeTrackingTest extends AdmidioTestCase
{
    private function entity(): EntityChangeTrackingFixed
    {
        return new EntityChangeTrackingFixed(
            array('ann_headline' => 'A', 'ann_cat_id' => 3, 'ann_date' => '2026-01-01', 'ann_flag' => 1),
            array('ann_headline' => 'varchar', 'ann_cat_id' => 'integer', 'ann_date' => 'date', 'ann_flag' => 'boolean')
        );
    }

    public function testABThenCReportsThePersistedOldValueAAndKeepsC(): void
    {
        $e = $this->entity();
        $e->setValue('ann_headline', 'B');
        $e->setValue('ann_headline', 'C');

        $this->assertSame('A', $e->columnsInfos['ann_headline']['previousValue']);
        $this->assertTrue($e->columnsInfos['ann_headline']['changed']);
        $this->assertSame('C', $e->dbColumns['ann_headline']);
    }

    public function testABThenAIsNoEffectiveChange(): void
    {
        $e = $this->entity();
        $e->setValue('ann_headline', 'B');
        $e->setValue('ann_headline', 'A');

        $this->assertNull($e->columnsInfos['ann_headline']['previousValue']);
        $this->assertFalse($e->columnsInfos['ann_headline']['changed']);
        $this->assertFalse($e->columnsValueChanged);
        $this->assertSame('A', $e->dbColumns['ann_headline']);
    }

    public function testAThenBStillReportsA(): void
    {
        $e = $this->entity();
        $e->setValue('ann_headline', 'B');

        $this->assertSame('A', $e->columnsInfos['ann_headline']['previousValue']);
        $this->assertTrue($e->columnsInfos['ann_headline']['changed']);
        $this->assertTrue($e->columnsValueChanged);
    }

    public function testOneFieldRevertedAnotherStillChangedStaysDirty(): void
    {
        $e = $this->entity();
        $e->setValue('ann_headline', 'B');
        $e->setValue('ann_cat_id', 7);
        $e->setValue('ann_headline', 'A');

        $this->assertTrue($e->columnsValueChanged, 'one field reverted, another still changed -> still dirty');
        $this->assertNull($e->columnsInfos['ann_headline']['previousValue'], 'reverted field is clean');
        $this->assertFalse($e->columnsInfos['ann_headline']['changed']);
        $this->assertSame(3, $e->columnsInfos['ann_cat_id']['previousValue'], 'other field keeps its old value');
        $this->assertTrue($e->columnsInfos['ann_cat_id']['changed']);
    }

    public function testDateRoundTripIsNoEffectiveChange(): void
    {
        $e = $this->entity();
        $e->setValue('ann_date', '2026-02-02');
        $e->setValue('ann_date', '2026-01-01');

        $this->assertNull($e->columnsInfos['ann_date']['previousValue']);
        $this->assertFalse($e->columnsInfos['ann_date']['changed']);
    }

    public function testBooleanRoundTripIsNoEffectiveChange(): void
    {
        $e = $this->entity();
        $e->setValue('ann_flag', 0);
        $e->setValue('ann_flag', 1);

        $this->assertNull($e->columnsInfos['ann_flag']['previousValue']);
        $this->assertFalse($e->columnsInfos['ann_flag']['changed']);
    }

    public function testANewRecordKeepsEveryColumnMarkedForTheInsert(): void
    {
        $e = $this->entity();
        $e->insertRecord = true;
        foreach ($e->columnsInfos as $column => $_) {
            $e->columnsInfos[$column]['changed'] = true;
        }
        $e->setValue('ann_headline', 'B');
        $e->setValue('ann_headline', 'A');

        $this->assertTrue($e->columnsInfos['ann_headline']['changed']);
    }
}
