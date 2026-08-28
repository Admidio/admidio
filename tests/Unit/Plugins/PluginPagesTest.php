<?php
/**
 * Publishing the pages of a plugin below modules/.
 *
 * The stubs are written into a real temporary directory and the assertions are made on the files
 * that end up there, because what this class does is filesystem work. The only double is a five-line
 * stand-in for the settings manager, which needs a database that a unit test does not have; it
 * answers one preference and nothing else.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPages;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginPagesTest extends PluginTestCase
{
    private string $modulesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulesPath = sys_get_temp_dir() . '/adm-plugin-pages-' . bin2hex(random_bytes(6));
        mkdir($this->modulesPath, 0777, true);
        PluginPages::setModulesPath($this->modulesPath);

        $GLOBALS['gSettingsManager'] = new PluginPagesSettingsDouble(true);
        $GLOBALS['helloPageRuns'] = array();
    }

    protected function tearDown(): void
    {
        PluginPages::setModulesPath(null);
        unset($GLOBALS['gSettingsManager']);
        self::removeDirectory($this->modulesPath);

        parent::tearDown();
    }

    /**
     * @testdox Only the PHP files directly below the pages directory count as pages
     */
    public function testPagesAreTheTopLevelPhpFiles(): void
    {
        $plugin = PluginRegistry::get('hello');

        $this->assertTrue($plugin->hasPages());
        $this->assertSame(array('index.php', 'list.php'), $plugin->getPages());
        $this->assertFalse(PluginRegistry::get('dependent')->hasPages());
    }

    /**
     * @testdox Publishing writes one stub per page, a marker and a listing guard
     */
    public function testPublishWritesStubs(): void
    {
        $this->assertTrue(PluginPages::publish(PluginRegistry::get('hello')));

        $this->assertTrue(PluginPages::isPublished('hello'));
        $this->assertFileExists($this->modulesPath . '/hello/index.php');
        $this->assertFileExists($this->modulesPath . '/hello/list.php');
        $this->assertFileExists($this->modulesPath . '/hello/index.html');
        $this->assertSame('hello', trim((string)file_get_contents($this->modulesPath . '/hello/' . PluginPages::MARKER_FILE)));
        $this->assertFileDoesNotExist($this->modulesPath . '/hello/notes.txt');
    }

    /**
     * @testdox A generated stub is valid PHP that hands over to the page of its plugin
     */
    public function testStubContent(): void
    {
        PluginPages::publish(PluginRegistry::get('hello'));
        $stub = (string)file_get_contents($this->modulesPath . '/hello/list.php');

        $this->assertStringContainsString("require_once(__DIR__ . '/../../system/common.php');", $stub);
        $this->assertStringContainsString(
            "Admidio\\Infrastructure\\Plugins\\PluginRegistry::requirePage('hello', 'list.php');",
            $stub
        );

        $file = $this->modulesPath . '/hello/list.php';
        exec('php -l ' . escapeshellarg($file), $output, $status);
        $this->assertSame(0, $status, 'the generated stub must be valid PHP: ' . implode("\n", $output));
    }

    /**
     * @testdox Nothing is written while the administrator has not allowed it
     */
    public function testPublishingIsOffByDefault(): void
    {
        $GLOBALS['gSettingsManager'] = new PluginPagesSettingsDouble(false);

        $this->assertFalse(PluginPages::publish(PluginRegistry::get('hello')));
        $this->assertFalse(PluginPages::isPublished('hello'));
        $this->assertDirectoryDoesNotExist($this->modulesPath . '/hello');
    }

    /**
     * @testdox Publishing again drops a page the plugin no longer has
     */
    public function testPublishRewritesTheDirectory(): void
    {
        PluginPages::publish(PluginRegistry::get('hello'));
        file_put_contents($this->modulesPath . '/hello/gone.php', '<?php');

        PluginPages::publish(PluginRegistry::get('hello'));

        $this->assertFileDoesNotExist($this->modulesPath . '/hello/gone.php');
        $this->assertFileExists($this->modulesPath . '/hello/list.php');
    }

    /**
     * @testdox A directory of the Admidio core is never touched, whatever the plugin is called
     */
    public function testCoreDirectoryIsNeverOverwrittenOrRemoved(): void
    {
        mkdir($this->modulesPath . '/hello');
        file_put_contents($this->modulesPath . '/hello/hello.php', '<?php // a core module');

        $this->assertSame(
            'The module directory "hello" already belongs to Admidio.',
            PluginPages::getObstacle(PluginRegistry::get('hello'))
        );
        $this->assertFalse(PluginPages::publish(PluginRegistry::get('hello')));

        PluginPages::unpublish('hello');

        $this->assertFileExists($this->modulesPath . '/hello/hello.php');
    }

    /**
     * @testdox A core module file of the same name blocks publishing too
     */
    public function testCoreModuleFileBlocksPublishing(): void
    {
        file_put_contents($this->modulesPath . '/hello.php', '<?php // a core module');

        $this->assertSame(
            'The module "hello.php" already belongs to Admidio.',
            PluginPages::getObstacle(PluginRegistry::get('hello'))
        );
    }

    /**
     * @testdox Unpublishing removes exactly what was generated
     */
    public function testUnpublish(): void
    {
        PluginPages::publish(PluginRegistry::get('hello'));
        PluginPages::unpublish('hello');

        $this->assertFalse(PluginPages::isPublished('hello'));
        $this->assertDirectoryDoesNotExist($this->modulesPath . '/hello');
    }

    /**
     * @testdox The URL of a page follows whichever form is live
     */
    public function testUrlFollowsThePublishedState(): void
    {
        $plugin = PluginRegistry::get('hello');

        $this->assertSame(ADMIDIO_URL . '/plugins/hello/modules/list.php', $plugin->getUrl('list.php'));
        $this->assertSame(ADMIDIO_URL . '/plugins/hello/modules/index.php', $plugin->getUrl());

        PluginPages::publish($plugin);

        $this->assertSame(ADMIDIO_URL . '/modules/hello/list.php', $plugin->getUrl('list.php'));
        $this->assertSame(ADMIDIO_URL . '/modules/hello/index.php', $plugin->getUrl());
    }

    /**
     * @testdox A plugin without pages keeps the entry file as its menu URL
     */
    public function testPluginWithoutPages(): void
    {
        $plugin = PluginRegistry::get('dependent');

        $this->assertSame(ADMIDIO_URL . '/plugins/dependent/plugin.php', $plugin->getUrl());
        $this->assertFalse(PluginPages::publish($plugin));
    }

    /**
     * @testdox Syncing publishes the installed plugins and clears everything else
     */
    public function testSyncAll(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));

        $this->assertSame(array('hello' => 'published'), PluginPages::syncAll());
        $this->assertTrue(PluginPages::isPublished('hello'));

        $GLOBALS['gSettingsManager'] = new PluginPagesSettingsDouble(false);

        $this->assertSame(array('hello' => 'unpublished'), PluginPages::syncAll());
        $this->assertFalse(PluginPages::isPublished('hello'));
    }

    /**
     * @testdox Changing the preference publishes or removes the stubs on the next request
     */
    public function testReconcileFollowsThePreference(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));
        $settings = $GLOBALS['gSettingsManager'];

        $this->assertSame(array('hello' => 'published'), PluginPages::reconcile());
        $this->assertTrue(PluginPages::isPublished('hello'));

        $this->assertSame(array(), PluginPages::reconcile(), 'nothing changed, so nothing is written again');

        $settings->set(PluginPages::SETTING, '0');

        $this->assertSame(array('hello' => 'unpublished'), PluginPages::reconcile());
        $this->assertFalse(PluginPages::isPublished('hello'));
        $this->assertSame(array(), PluginPages::reconcile());
    }

    /**
     * @testdox An obstacle is reported once and not retried on every request
     */
    public function testReconcileDoesNotRetryAnObstacle(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));
        mkdir($this->modulesPath . '/hello');
        file_put_contents($this->modulesPath . '/hello/hello.php', '<?php // a core module');

        $this->assertSame(
            array('hello' => 'The module directory "hello" already belongs to Admidio.'),
            PluginPages::reconcile()
        );
        $this->assertSame(array(), PluginPages::reconcile());
    }

    /**
     * @testdox Syncing removes the stubs of a plugin whose files are gone
     */
    public function testSyncRemovesOrphanedStubs(): void
    {
        mkdir($this->modulesPath . '/vanished');
        file_put_contents($this->modulesPath . '/vanished/list.php', '<?php');
        file_put_contents($this->modulesPath . '/vanished/' . PluginPages::MARKER_FILE, "vanished\n");

        $this->assertSame(array('vanished' => 'unpublished'), PluginPages::syncAll());
        $this->assertDirectoryDoesNotExist($this->modulesPath . '/vanished');
    }

    /**
     * @testdox An uninstalled plugin is not published, even when publishing is allowed
     */
    public function testSyncSkipsUninstalledPlugins(): void
    {
        $this->assertSame(array(), PluginPages::syncAll());
        $this->assertFalse(PluginPages::isPublished('hello'));
    }

    /**
     * @testdox requirePage runs the page of an enabled plugin and refuses anything else
     */
    public function testRequirePage(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));

        PluginRegistry::requirePage('hello', 'list.php');
        $this->assertSame(array('list.php'), $GLOBALS['helloPageRuns']);

        $this->expectExceptionMessage('SYS_INVALID_PAGE_VIEW');
        PluginRegistry::requirePage('hello', '../plugin.php');
    }

    /**
     * @testdox A page of a plugin that is not installed here is refused
     */
    public function testRequirePageOfDisabledPlugin(): void
    {
        $this->expectExceptionMessage('SYS_NO_RIGHTS');
        PluginRegistry::requirePage('hello', 'list.php');
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? self::removeDirectory($child) : @unlink($child);
        }

        @rmdir($path);
    }
}

/**
 * Answers the one preference PluginPages reads. The real SettingsManager needs a database.
 */
final class PluginPagesSettingsDouble
{
    /** @var array<string,string> */
    public array $values = array();

    public function __construct(bool $allowed)
    {
        $this->values[PluginPages::SETTING] = $allowed ? '1' : '0';
    }

    public function getBool(string $name, bool $update = false): bool
    {
        return ($this->values[$name] ?? '0') === '1';
    }

    public function has(string $name, bool $update = false): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name, bool $update = false): string
    {
        return $this->values[$name] ?? '';
    }

    public function set(string $name, $value, bool $update = true): bool
    {
        $this->values[$name] = (string)$value;

        return true;
    }
}
