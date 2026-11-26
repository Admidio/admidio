<?php
/**
 * App icon service unit tests
 *
 * The icon files are created in a temporary folder that is deleted after each test.
 */

namespace Admidio\Tests\Unit\Infrastructure;

use Admidio\Infrastructure\Service\AppIconService;
use Admidio\Tests\Support\AdmidioTestCase;

class AppIconServiceTest extends AdmidioTestCase
{
    private string $folder = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/admidio-app-icons-' . bin2hex(random_bytes(6));
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') as $file) {
            unlink($file);
        }
        rmdir($this->folder);
        parent::tearDown();
    }

    private function createIcon(string $fileName): string
    {
        $path = $this->folder . '/' . $fileName;
        file_put_contents($path, 'png');
        return $path;
    }

    /**
     * @testdox Without icon files no icon file is returned
     */
    public function testNoIconFiles(): void
    {
        foreach (array_keys(AppIconService::SIZES) as $size) {
            $this->assertNull(AppIconService::getIconFile($size, false, $this->folder));
        }
        $this->assertNull(AppIconService::getIconFile(512, true, $this->folder));
    }

    /**
     * @testdox The size-specific app icon is preferred over the general app icon and the default logo file
     */
    public function testLookupOrder(): void
    {
        $logo64 = $this->createIcon('admidio_logo_64.png');
        $touchIcon = $this->createIcon('apple-touch-icon.png');
        $this->assertSame($logo64, AppIconService::getIconFile(64, false, $this->folder));
        $this->assertSame($touchIcon, AppIconService::getIconFile(180, false, $this->folder));

        $general = $this->createIcon('app_icon.png');
        $this->assertSame($general, AppIconService::getIconFile(64, false, $this->folder));
        $this->assertSame($general, AppIconService::getIconFile(192, false, $this->folder));

        $specific = $this->createIcon('app_icon_192.png');
        $this->assertSame($specific, AppIconService::getIconFile(192, false, $this->folder));
        $this->assertSame($general, AppIconService::getIconFile(512, false, $this->folder));
    }

    /**
     * @testdox Unsupported sizes don't resolve to a file, even if a matching file name exists
     */
    public function testUnsupportedSize(): void
    {
        $this->createIcon('app_icon.png');
        $this->createIcon('app_icon_99.png');
        $this->assertNull(AppIconService::getIconFile(99, false, $this->folder));
        $this->assertNull(AppIconService::getIconFile(0, false, $this->folder));
        $this->assertNull(AppIconService::getIconFile(-192, false, $this->folder));
    }

    /**
     * @testdox The maskable icon uses only the maskable file names, the 512 variant first
     */
    public function testMaskableIcon(): void
    {
        $this->createIcon('app_icon.png');
        $this->createIcon('admidio_logo_512.png');
        $this->assertNull(AppIconService::getIconFile(512, true, $this->folder));

        $maskable = $this->createIcon('app_icon_maskable.png');
        $this->assertSame($maskable, AppIconService::getIconFile(512, true, $this->folder));

        $maskable512 = $this->createIcon('app_icon_maskable_512.png');
        $this->assertSame($maskable512, AppIconService::getIconFile(512, true, $this->folder));
    }
}
