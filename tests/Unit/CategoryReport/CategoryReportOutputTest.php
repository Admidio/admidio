<?php

namespace Admidio\Tests\Unit\CategoryReport;

use Admidio\CategoryReport\Service\CategoryReportOutput;
use Admidio\Infrastructure\Utils\SpreadsheetUtils;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Smarty\Smarty;

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

    public function testCsvNeutralizesSpreadsheetFormulas(): void
    {
        $csv = CategoryReportOutput::createCsv(array(array(
            '=HYPERLINK("https://example.org")',
            '+SUM(1,2)',
            '-cmd|calc',
            '@SUM(1,2)',
            '-5',
            'ordinary'
        )));
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $row = fgetcsv($stream, escape: '');
        fclose($stream);

        $this->assertSame(array(
            '\'=HYPERLINK("https://example.org")',
            '\'+SUM(1,2)',
            '\'-cmd|calc',
            '\'@SUM(1,2)',
            '-5',
            'ordinary'
        ), $row);
    }

    public function testReportTemplateEscapesTextAndRendersMarkedHtml(): void
    {
        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->assign(array(
            'classTable' => '',
            'attributes' => array(),
            'columnAlign' => array('left', 'center'),
            'headers' => array('Role <Admin> & "Lead"', 'Selected'),
            'rows' => array(array(
                'id' => 'row-1',
                'data' => array(
                    'Board <script>alert("x")</script> & Team',
                    CategoryReportOutput::html('<i class="bi bi-check-lg"></i>')
                )
            ))
        ));

        $html = $smarty->fetch('modules/category-report.list.tpl');

        $this->assertStringContainsString('Role &lt;Admin&gt; &amp; &quot;Lead&quot;', $html);
        $this->assertStringContainsString(
            'Board &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; Team',
            $html
        );
        $this->assertStringContainsString('<i class="bi bi-check-lg"></i>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testSpreadsheetFormulaTextIsWrittenExplicitlyAsText(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $values = array('=1+1', '+SUM(1,2)', '-cmd|calc', '@SUM(1,2)');

        foreach ($values as $index => $value) {
            $coordinate = 'A' . ($index + 1);
            SpreadsheetUtils::setCellValue($sheet, $coordinate, $value);
            $this->assertSame($value, $sheet->getCell($coordinate)->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($coordinate)->getDataType());
        }
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
