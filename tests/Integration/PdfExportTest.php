<?php

namespace Admidio\Tests\Integration;

use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PdfUtils;
use Admidio\Roles\ValueObject\ListData;
use Com\Tecnick\Pdf\Parser\Parser;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

/**
 * Exercises the PDF dependency without a database or an initialized web request.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PdfExportTest extends TestCase
{
    public function testListDataExportWithoutLegacyTcpdf(): void
    {
        $directory = sys_get_temp_dir() . '/admidio-list-pdf-test-' . bin2hex(random_bytes(8));
        mkdir($directory);
        define('ADMIDIO_PATH', $directory);
        define('FOLDER_TEMP_DATA', '');
        $file = $directory . '/member.report.pdf';

        try {
            $this->assertFalse(class_exists('TCPDF'));
            $list = new ListData();
            $list->setColumnHeadlines(array('Name', 'Number'));
            $list->setDataByArray(array(array('Member One', '123')));
            $export = $list->createExportFile('member.report', 'pdf');
            $this->assertSame($file, $export['path']);
            $this->assertSame('application/pdf', $export['contentType']);
            $data = file_get_contents($file);
            $this->assertStringStartsWith('%PDF-', $data);
            [, $objects] = (new Parser())->parse($data);
            $content = serialize($objects);
            $this->assertStringContainsString('(Member One)', $content);
            $this->assertStringContainsString('(123)', $content);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public static function exportTemplates(): array
    {
        $cases = array();
        foreach (array('groups-roles.list.tpl', 'category-report.list.tpl', 'inventory.list.export.tpl') as $template) {
            foreach (array('P', 'L') as $orientation) {
                $cases[$template . '-' . $orientation] = array($template, $orientation);
            }
        }
        return $cases;
    }

    /** @dataProvider exportTemplates */
    public function testExport(string $template, string $orientation): void
    {
        $directory = sys_get_temp_dir() . '/admidio-pdf-test-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $cwd = getcwd();
        // Web entrypoints and CLI jobs need not run from the project root.
        chdir($directory);
        $file = $directory . '/member.report final';

        try {
            $pdf = PdfUtils::createDocument($orientation, 'Export heading');
            $page = $pdf->page->getPage();
            $this->assertSame($orientation === 'P', $page['width'] < $page['height']);
            $smarty = new Smarty();
            $smarty->setTemplateDir(dirname(__DIR__, 2) . '/themes/simple/templates');
            mkdir($directory . '/compiled');
            $smarty->setCompileDir($directory . '/compiled');
            $rows = array();
            for ($row = 1; $row <= 100; ++$row) {
                $rows[] = array('id' => 'row' . $row, 'data' => array('Member ' . $row, '<i>Registered</i>'));
            }
            $smarty->assign(array(
                'classTable' => '',
                'attributes' => array('border' => '1', 'cellpadding' => '1'),
                'exportMode' => true,
                'subHeadline' => 'Section heading',
                'headers' => array('Name', 'Details'),
                'headersStyle' => 'font-size:10pt;background-color:#C7C7C7;',
                'rowsStyle' => 'font-size:10pt;',
                'columnAlign' => array('left', 'left'),
                'column_align' => array('start', 'start'),
                'rows' => $rows,
            ));
            $pdf->writeTable($smarty->fetch('modules/' . $template));
            $data = $pdf->getOutPDFString();
            $this->assertGreaterThan(1, count($pdf->page->getPages()));
            $this->assertStringStartsWith('%PDF-', $data);

            // The extensionless/dotted path must survive without a renamed sibling file.
            FileSystemUtils::writeFile($file, $data);
            $this->assertSame($data, file_get_contents($file));
            $this->assertSame(array('compiled', basename($file)), array_values(array_diff(scandir($directory), array('.', '..'))));

            // Decode PDF streams to verify actual rendered content, not just a PDF header.
            [, $objects] = (new Parser())->parse($data);
            $strings = array();
            array_walk_recursive($objects, static function ($value) use (&$strings): void {
                if (is_string($value)) {
                    $strings[] = $value;
                }
            });
            $content = implode("\n", $strings);
            $this->assertSame(count($pdf->page->getPages()), substr_count($content, '(Export heading)'));
            $this->assertStringContainsString('0.780392 0.780392 0.780392 rg', $content);
            if ($template !== 'inventory.list.export.tpl') {
                $this->assertSame(1, substr_count($content, '(Section heading)'));
            }
            $this->assertStringContainsString('(Member 1)', $content);
            $this->assertStringContainsString('(Member 100)', $content);
            $this->assertStringContainsString('(Registered)', $content);

            // Repeated table headers must keep the same horizontal position on every page.
            preg_match_all('/([-\d.]+)\s+[-\d.]+\s+Td\s+\(Name\)/', $content, $headings);
            $this->assertCount(count($pdf->page->getPages()), $headings[1]);
            foreach ($headings[1] as $x) {
                $this->assertEqualsWithDelta((float) $headings[1][0], (float) $x, 0.01);
            }
            preg_match('/([-\d.]+)\s+[-\d.]+\s+Td\s+\(Member 1\)/', $content, $firstRow);
            $this->assertNotEmpty($firstRow);
            $this->assertEqualsWithDelta((float) $firstRow[1], (float) $headings[1][0], 0.01);
        } finally {
            chdir($cwd);
            if (is_file($file)) {
                unlink($file);
            }
            foreach (glob($directory . '/compiled/*') as $compiledFile) {
                unlink($compiledFile);
            }
            if (is_dir($directory . '/compiled')) {
                rmdir($directory . '/compiled');
            }
            rmdir($directory);
        }
    }
}
