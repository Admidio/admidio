<?php

namespace Admidio\Tests\Unit\CategoryReport;

use Admidio\CategoryReport\Service\CategoryReportOutput;
use PHPUnit\Framework\TestCase;

class CategoryReportOutputTest extends TestCase
{
    public function testCsvPreservesSpecialCharactersAndUnicode(): void
    {
        $rows = array(
            array('Number', 'Name', 'Description'),
            array(1, 'Müller, "Jörg"', "Zürich\n東京"),
            array(2, 'Zoë', 'Plain text')
        );
        $csv = CategoryReportOutput::createCsv($rows);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $parsed = array();
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            $parsed[] = $row;
        }
        fclose($stream);

        $this->assertSame(array_map(
            static fn(array $row): array => array_map('strval', $row),
            $rows
        ), $parsed);
    }

    public function testFilteringPreservesOrderAndRecalculatesSummary(): void
    {
        $rows = array(
            10 => array(1 => 'Anna', 2 => true),
            20 => array(1 => 'Béla', 2 => true),
            21 => array(1 => 'Béla Two', 2 => ''),
            22 => array(1 => 'Other', 2 => true),
            23 => array(1 => 'Total', 2 => 3)
        );
        $headers = array(1 => array('id' => 0), 2 => array('id' => 0));

        $filtered = CategoryReportOutput::filterRows(
            $rows,
            $headers,
            true,
            'Total',
            'béla',
            static fn(mixed $value, int $key): string => (string)$value
        );

        $this->assertSame(array(20, 21, 0), array_column($filtered, 'member'));
        $this->assertSame(array(false, false, true), array_column($filtered, 'summary'));
        $this->assertSame(array(1, 2, 0), array_column($filtered, 'number'));
        $this->assertSame(array(1 => 'Total', 2 => 1), $filtered[2]['data']);
    }
}
