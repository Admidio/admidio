<?php
require __DIR__ . '/EntityChangeTrackingFixed.php';

function show(string $case, $e, string $col, $expectedOld, $expectedChanged): void
{
    $prev = $e->columnsInfos[$col]['previousValue'];
    $changed = $e->columnsInfos[$col]['changed'];
    $ok = ($prev === $expectedOld) && ($changed === $expectedChanged);
    printf("%-6s %-56s previousValue=%-10s changed=%s\n",
        $ok ? '  ok' : 'FAIL', $case, var_export($prev, true), var_export($changed, true));
}
function flag(string $case, bool $ok): void { printf("%-6s %s\n", $ok ? '  ok' : 'FAIL', $case); }

$mk = fn() => new EntityChangeTrackingFixed(
    ['ann_headline' => 'A', 'ann_cat_id' => 3, 'ann_date' => '2026-01-01', 'ann_flag' => 1],
    ['ann_headline' => 'varchar', 'ann_cat_id' => 'integer', 'ann_date' => 'date', 'ann_flag' => 'boolean']);

$e = $mk(); $e->setValue('ann_headline', 'B'); $e->setValue('ann_headline', 'C');
show('A -> B -> C reports the persisted old value A', $e, 'ann_headline', 'A', true);
flag('  ... and the object holds C', $e->dbColumns['ann_headline'] === 'C');

$e = $mk(); $e->setValue('ann_headline', 'B'); $e->setValue('ann_headline', 'A');
show('A -> B -> A is no effective change', $e, 'ann_headline', null, false);
flag('  ... and columnsValueChanged is false again', $e->columnsValueChanged === false);
flag('  ... and the object holds A', $e->dbColumns['ann_headline'] === 'A');

$e = $mk(); $e->setValue('ann_headline', 'B');
show('A -> B still reports A', $e, 'ann_headline', 'A', true);
flag('  ... and columnsValueChanged is true', $e->columnsValueChanged === true);

$e = $mk(); $e->setValue('ann_headline', 'B'); $e->setValue('ann_cat_id', 7); $e->setValue('ann_headline', 'A');
flag('one field reverted, another still changed -> still dirty', $e->columnsValueChanged === true);
show('  ... reverted field is clean', $e, 'ann_headline', null, false);
show('  ... other field keeps its old value', $e, 'ann_cat_id', 3, true);

// date type round trip
$e = $mk(); $e->setValue('ann_date', '2026-02-02'); $e->setValue('ann_date', '2026-01-01');
show('date A -> B -> A is no effective change', $e, 'ann_date', null, false);

// boolean round trip
$e = $mk(); $e->setValue('ann_flag', 0); $e->setValue('ann_flag', 1);
show('boolean 1 -> 0 -> 1 is no effective change', $e, 'ann_flag', null, false);

// new record: nothing may be un-marked
$e = $mk(); $e->insertRecord = true;
foreach ($e->columnsInfos as $c => $_) { $e->columnsInfos[$c]['changed'] = true; }
$e->setValue('ann_headline', 'B'); $e->setValue('ann_headline', 'A');
flag('a new record keeps every column marked for the INSERT', $e->columnsInfos['ann_headline']['changed'] === true);
