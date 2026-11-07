<?php

namespace Admidio\Tests\Unit\Documents;

use Admidio\Documents\Entity\File;
use Admidio\Documents\Service\DocumentsService;
use Admidio\Tests\Support\AdmidioTestCase;

class DocumentsDownloadTest extends AdmidioTestCase
{
    /**
     * Exercise the download method with the real MIME type and viewability lookup.
     * Only database access and header emission are replaced.
     *
     * @dataProvider downloadTypes
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDownloadHeaders(string $filename, bool $inline, string $disposition, string $mimeType, ?string $contentDisposition = null): void
    {
        require __DIR__ . '/Support/download-headers.php';
        $GLOBALS['documentDownloadHeaders'] = array();

        $contents = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.domain)</script></svg>';
        $path = tempnam(sys_get_temp_dir(), 'admidio-download-');

        try {
            file_put_contents($path, $contents);

            $file = $this->getMockBuilder(File::class)
                ->disableOriginalConstructor()
                ->onlyMethods(array('getFullFilePath', 'getValue'))
                ->getMock();
            $file->method('getFullFilePath')->willReturn($path);
            $file->method('getValue')->willReturnCallback(function (string $column) use ($filename) {
                $this->assertSame('fil_name', $column);
                return $filename;
            });

            $service = $this->getMockBuilder(DocumentsService::class)
                ->disableOriginalConstructor()
                ->onlyMethods(array('prepareFileDownload'))
                ->getMock();
            $service->expects($this->once())->method('prepareFileDownload')->with('file-uuid')->willReturn($file);

            ob_start();
            try {
                $service->downloadFile('file-uuid', $inline);
                $body = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            $headers = $GLOBALS['documentDownloadHeaders'];
            // The name is sent as ASCII fallback (filename) and as UTF-8 (filename*, RFC 6266)
            $expectedDisposition = $contentDisposition ?? 'Content-Disposition: ' . $disposition
                . '; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
            $this->assertContains($expectedDisposition, $headers);
            $this->assertContains('Content-Type: ' . $mimeType, $headers);
            $this->assertContains('X-Content-Type-Options: nosniff', $headers);
            $this->assertContains('Content-Length: ' . strlen($contents), $headers);
            $this->assertSame($contents, $body);
        } finally {
            unlink($path);
        }
    }

    public static function downloadTypes(): array
    {
        return array(
            // SVG uploads are blocked. Existing files must still be served as generic attachments.
            'existing SVG view request' => array('payload.svg', true, 'attachment', 'application/octet-stream'),
            'existing uppercase SVG view request' => array('payload.SVG', true, 'attachment', 'application/octet-stream'),
            'existing SVG download' => array('payload.svg', false, 'attachment', 'application/octet-stream'),
            'PNG preview' => array('image.png', true, 'inline', 'image/png'),
            'PNG download' => array('image.png', false, 'attachment', 'image/png'),
            'PDF preview' => array('document.pdf', true, 'inline', 'application/pdf'),
            'text preview with active markup' => array('document.txt', true, 'inline', 'text/plain'),
            'archive view request' => array('archive.zip', true, 'attachment', 'application/zip'),
            'unknown file type' => array('document.unknown', true, 'attachment', 'application/octet-stream'),
            // Umlauts: ASCII fallback with one underscore per character, the real name in filename*
            'umlauts in the file name' => array(
                'Müller Übung.pdf',
                false,
                'attachment',
                'application/pdf',
                'Content-Disposition: attachment; filename="M_ller _bung.pdf"; filename*=UTF-8\'\'M%C3%BCller%20%C3%9Cbung.pdf'
            ),
            'umlauts in the file name, preview' => array(
                'Sind die Lichter angezündet.pdf',
                true,
                'inline',
                'application/pdf',
                'Content-Disposition: inline; filename="Sind die Lichter angez_ndet.pdf"; filename*=UTF-8\'\'Sind%20die%20Lichter%20angez%C3%BCndet.pdf'
            ),
            // quotes, percent signs and backslashes must not break the header of the fallback name
            'quotes, percent sign and backslash in the file name' => array(
                'A "b" 50% \\c.pdf',
                false,
                'attachment',
                'application/pdf',
                'Content-Disposition: attachment; filename="A _b_ 50_ _c.pdf"; filename*=UTF-8\'\'A%20%22b%22%2050%25%20%5Cc.pdf'
            ),
        );
    }
}
