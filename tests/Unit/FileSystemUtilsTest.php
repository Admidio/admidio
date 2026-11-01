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
}
