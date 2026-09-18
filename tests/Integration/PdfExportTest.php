<?php

namespace Admidio\Tests\Integration;

use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Infrastructure\Utils\PdfUtils;
use Com\Tecnick\Pdf\Parser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the PDF dependency without a database or an initialized web request.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PdfExportTest extends TestCase
{
    public function testPortraitExport(): void
    {
        $this->assertExport('P');
    }

    public function testLandscapeExport(): void
    {
        $this->assertExport('L');
    }

    private function assertExport(string $orientation): void
    {
        $directory = sys_get_temp_dir() . '/admidio-pdf-test-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $cwd = getcwd();
        // Web entrypoints and CLI jobs need not run from the project root.
        chdir($directory);
        $file = $directory . '/member.report final';

        try {
            $pdf = PdfUtils::createDocument($orientation);
            $pdf->setPrintHeader(true);
            $pdf->setPrintFooter(false);
            $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->SetMargins(10, 20, 10);
            $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
            $pdf->setHeaderMargin(10);
            $pdf->setHeaderData('', 0, 'Export heading', '');
            $pdf->SetFont('times', '', 10);
            $pdf->AddPage();

            $this->assertSame($orientation === 'P', $pdf->getPageWidth() < $pdf->getPageHeight());
            $html = '<table border="1" cellpadding="1"><tr><th><b>Name</b></th><th>Details</th></tr>';
            for ($row = 1; $row <= 100; ++$row) {
                $html .= '<tr><td>Member ' . $row . '</td><td><i>Registered</i></td></tr>';
            }
            $pdf->writeHTML($html . '</table>', true, false, true);
            $data = $pdf->Output('', 'S');
            $this->assertGreaterThan(1, $pdf->getNumPages());
            $this->assertStringStartsWith('%PDF-', $data);

            // The extensionless/dotted path must survive without a renamed sibling file.
            FileSystemUtils::writeFile($file, $data);
            $this->assertSame($data, file_get_contents($file));
            $this->assertSame(array(basename($file)), array_values(array_diff(scandir($directory), array('.', '..'))));

            // Decode PDF streams to verify actual rendered content, not just a PDF header.
            [, $objects] = (new Parser())->parse($data);
            $strings = array();
            array_walk_recursive($objects, static function ($value) use (&$strings): void {
                if (is_string($value)) {
                    $strings[] = $value;
                }
            });
            $content = implode("\n", $strings);
            $this->assertStringContainsString('(Export heading)', $content);
            $this->assertStringContainsString('(Member 1)', $content);
            $this->assertStringContainsString('(Member 100)', $content);
            $this->assertStringContainsString('(Registered)', $content);
        } finally {
            chdir($cwd);
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
