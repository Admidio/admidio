<?php

namespace Admidio\Tests\Unit;

use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Tests\Support\AdmidioTestCase;

class FileSystemUtilsTest extends AdmidioTestCase
{
    /**
     * @dataProvider svgFilenames
     */
    public function testSvgUploadsAreNotAllowed(string $filename): void
    {
        $this->assertFalse(FileSystemUtils::allowedFileExtension($filename));
    }

    public static function svgFilenames(): array
    {
        return array(
            array('drawing.svg'),
            array('drawing.SVG'),
            array('drawing.SvG'),
            array('drawing.svgz'),
            array('drawing.SVGZ'),
            array('drawing.png.svg')
        );
    }

    public function testOtherSupportedUploadsRemainAllowed(): void
    {
        foreach (array('photo.jpg', 'photo.PNG', 'photo.webp', 'document.pdf', 'document.docx', 'data.csv', 'archive.zip') as $filename) {
            $this->assertTrue(FileSystemUtils::allowedFileExtension($filename), $filename);
        }
    }

    /**
     * @dataProvider blockedExtensions
     */
    public function testBlockedExtensionsAreRejected(string $extension): void
    {
        foreach (array($extension, strtoupper($extension)) as $variant) {
            $filename = 'upload.' . $variant;
            $this->assertTrue(FileSystemUtils::isBlockedFileExtension($filename));
            $this->assertFalse(FileSystemUtils::allowedFileExtension($filename));
        }
    }

    public static function blockedExtensions(): array
    {
        return array_map(static fn(string $extension): array => array($extension), array(
            'php', 'php3', 'php4', 'php5', 'pht', 'html', 'htm', 'phtml', 'shtml',
            'htaccess', 'htpasswd', 'pl', 'js', 'vbs', 'asp', 'asa', 'cer', 'asax',
            'swf', 'xap', 'cgi', 'ssi', 'phar', 'svg', 'svgz'
        ));
    }

    public function testSvgIsNoLongerInTheSupportedFileFormats(): void
    {
        $this->assertSame('application/octet-stream', FileSystemUtils::getFileMimeType('drawing.svg'));
        $this->assertSame('bi-file-earmark-fill', FileSystemUtils::getFileIcon('drawing.svg'));
        $this->assertFalse(FileSystemUtils::isViewableFileInBrowser('drawing.svg'));
    }

    /**
     * @dataProvider contentDispositions
     */
    public function testContentDisposition(string $disposition, string $fileName, string $expected): void
    {
        $this->assertSame($expected, FileSystemUtils::getContentDisposition($disposition, $fileName));
    }

    public static function contentDispositions(): array
    {
        return array(
            'ASCII name' => array('attachment', 'report.pdf', 'attachment; filename="report.pdf"; filename*=UTF-8\'\'report.pdf'),
            'umlauts: fallback with one underscore per character, real name in filename*' => array(
                'attachment',
                'Müller Übung.pdf',
                'attachment; filename="M_ller _bung.pdf"; filename*=UTF-8\'\'M%C3%BCller%20%C3%9Cbung.pdf'
            ),
            'inline' => array(
                'inline',
                'Sind die Lichter angezündet.pdf',
                'inline; filename="Sind die Lichter angez_ndet.pdf"; filename*=UTF-8\'\'Sind%20die%20Lichter%20angez%C3%BCndet.pdf'
            ),
            'characters outside of the Basic Latin block' => array(
                'attachment',
                'Straße – 北京.txt',
                'attachment; filename="Stra_e _ __.txt"; filename*=UTF-8\'\'Stra%C3%9Fe%20%E2%80%93%20%E5%8C%97%E4%BA%AC.txt'
            ),
            'quotes, percent sign and backslash' => array(
                'attachment',
                'A "b" 50% \\c.pdf',
                'attachment; filename="A _b_ 50_ _c.pdf"; filename*=UTF-8\'\'A%20%22b%22%2050%25%20%5Cc.pdf'
            ),
            'control characters are removed, they could break the header' => array(
                'attachment',
                "a\r\nX-Injected: 1\x00b.txt",
                'attachment; filename="aX-Injected: 1b.txt"; filename*=UTF-8\'\'aX-Injected%3A%201b.txt'
            ),
            'empty name' => array('attachment', '', 'attachment; filename="download"; filename*=UTF-8\'\'download'),
            'invalid UTF-8 falls back to a default name instead of breaking the header' => array(
                'attachment',
                "a\xFFb.txt",
                'attachment; filename="download"; filename*=UTF-8\'\'download'
            )
        );
    }

    /**
     * @dataProvider downloadNames
     */
    public function testSanitizedDownloadNameKeepsLettersOfAllLanguages(string $name, string $expected): void
    {
        $this->assertSame($expected, FileSystemUtils::getSanitizedDownloadName($name));
    }

    public static function downloadNames(): array
    {
        return array(
            'umlauts stay' => array('Müller Übung 1.pdf', 'Müller Übung 1.pdf'),
            'other letters stay' => array('Straße 北京.txt', 'Straße 北京.txt'),
            'path separators, quotes and special characters are replaced' => array('a/b\\c"d:e*f?g<h>i|j.txt', 'a_b_c_d_e_f_g_h_i_j.txt'),
            'control characters are replaced' => array("a\r\nb.txt", 'a__b.txt'),
            'invalid UTF-8 gives an empty name' => array("a\xFFb", '')
        );
    }
}
