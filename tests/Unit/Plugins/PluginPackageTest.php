<?php
/**
 * Installing a plugin from an archive.
 *
 * Every case builds a real ZIP file and extracts into a real temporary directory, because this class
 * exists to defend against archives, and an archive that only exists as a stub cannot attack
 * anything. Nothing here touches the database.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginPackage;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use ZipArchive;

final class PluginPackageTest extends PluginTestCase
{
    /**
     * The two files every plugin has, so that a case only has to name what it is actually about.
     */
    private const ENTRY = "<?php\n";

    /**
     * The directory that stands in for plugins/ while a test runs.
     */
    private string $pluginsPath = '';

    /**
     * Every temporary directory a test created, removed afterwards.
     * @var array<int,string>
     */
    private array $rubbish = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginsPath = $this->makeDirectory('plugins');
        PluginRegistry::setPluginsPath($this->pluginsPath);
        // No plugin is installed in the database, which is the state a freshly unpacked plugin is in.
        PluginRegistry::setInstallations(array());
        PluginRegistry::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            self::removeRecursively($path);
        }
        $this->rubbish = array();

        PluginRegistry::setPluginsPath(null);
        PluginRegistry::setInstallations(null);
        PluginRegistry::reset();

        parent::tearDown();
    }

    /**
     * @testdox A well-formed archive becomes a plugin directory
     */
    public function testValidArchiveIsInstalled(): void
    {
        $archive = $this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "2.1.0"}',
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/src/Greeter.php' => self::ENTRY
        ));

        $this->assertSame('hello-store', PluginPackage::install($archive));

        $this->assertFileExists($this->pluginsPath . '/hello-store/plugin.json');
        $this->assertFileExists($this->pluginsPath . '/hello-store/plugin.php');
        $this->assertFileExists($this->pluginsPath . '/hello-store/src/Greeter.php');
    }

    /**
     * @testdox The installed plugin is only available, never enabled
     *
     * Putting files on disk is not a decision to run them. Enabling is the separate step, and it is
     * what writes to the database.
     */
    public function testInstalledPluginIsOnlyAvailable(): void
    {
        PluginPackage::install($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "2.1.0"}',
            'hello-store/plugin.php' => self::ENTRY
        )));

        /*
         * install() resets the registry, which drops the stubbed installation state with it, so the
         * state has to be declared again - a real request would ask the database at this point.
         */
        PluginRegistry::setInstallations(array());

        $plugin = PluginRegistry::get('hello-store');

        $this->assertNotNull($plugin);
        $this->assertSame('2.1.0', $plugin->version);
        $this->assertSame(PluginRegistry::STATE_AVAILABLE, PluginRegistry::getState($plugin));
    }

    /**
     * @testdox An entry that would escape the plugin directory is refused before anything is written
     * @dataProvider unsafeEntryNames
     */
    public function testTraversalIsRefused(string $entry): void
    {
        $archive = $this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'hello-store/plugin.php' => self::ENTRY,
            $entry => 'owned'
        ));

        try {
            PluginPackage::install($archive);
            $this->fail('the archive entry ' . $entry . ' was accepted');
        } catch (Exception $exception) {
            $this->assertStringContainsString('SYS_PLUGIN_PACKAGE_UNSAFE_ENTRY', $exception->getMessage());

            // The point of refusing at inspection time is that nothing reached the filesystem.
            $this->assertSame(
                array(),
                array_values(array_diff((array)scandir($this->pluginsPath), array('.', '..'))),
                'nothing may be written when an archive is refused'
            );
        }
    }

    /**
     * Entry names that must never be extracted.
     * @return array<string,array<int,string>>
     */
    public static function unsafeEntryNames(): array
    {
        return array(
            'parent reference' => array('hello-store/../../evil.php'),
            'parent as first segment' => array('../evil.php'),
            'absolute path' => array('/etc/evil.php'),
            'windows drive' => array('C:/evil.php')
        );
    }

    /**
     * @testdox An archive whose files are not inside one directory is refused
     */
    public function testArchiveWithoutASingleRootIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');

        PluginPackage::install($this->archive(array(
            'plugin.json' => '{"name": "Loose", "version": "1.0.0"}',
            'plugin.php' => self::ENTRY
        )));
    }

    /**
     * @testdox An archive holding two plugins is refused
     */
    public function testArchiveWithTwoRootsIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_NO_DIRECTORY');

        PluginPackage::install($this->archive(array(
            'one/plugin.json' => '{"name": "One", "version": "1.0.0"}',
            'one/plugin.php' => self::ENTRY,
            'two/plugin.json' => '{"name": "Two", "version": "1.0.0"}',
            'two/plugin.php' => self::ENTRY
        )));
    }

    /**
     * @testdox An archive without a manifest is refused
     */
    public function testArchiveWithoutAManifestIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_NO_MANIFEST');

        PluginPackage::install($this->archive(array(
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/readme.txt' => 'nothing to see'
        )));
    }

    /**
     * @testdox An archive without an entry file is refused
     */
    public function testArchiveWithoutAnEntryFileIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_NO_ENTRY_FILE');

        PluginPackage::install($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'hello-store/readme.txt' => 'a manifest alone is not a plugin'
        )));
    }

    /**
     * @testdox A directory name that is not a usable plugin ID is refused
     */
    public function testArchiveWithAnUnusableIdIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_INVALID_ID');

        PluginPackage::install($this->archive(array(
            'Hello Store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'Hello Store/plugin.php' => self::ENTRY
        )));
    }

    /**
     * @testdox An archive whose manifest is malformed is refused, and nothing is left behind
     *
     * The archive passes the gate - it has both files in one properly named directory - so this is
     * the case that proves the extracted plugin is read before it is moved into place.
     */
    public function testArchiveWithABrokenManifestIsRefused(): void
    {
        $archive = $this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", oh dear',
            'hello-store/plugin.php' => self::ENTRY
        ));

        try {
            PluginPackage::install($archive);
            $this->fail('a malformed manifest was accepted');
        } catch (Exception $exception) {
            $this->assertStringContainsString('SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST', $exception->getMessage());
            $this->assertDirectoryDoesNotExist($this->pluginsPath . '/hello-store');
        }
    }

    /**
     * @testdox An archive can be read without installing anything
     */
    public function testArchiveIsDescribedWithoutBeingInstalled(): void
    {
        $archive = $this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "description": "A store plugin.",'
                . ' "version": "2.1.0", "author": "Admidio Team", "icon": "bi-emoji-smile",'
                . ' "settings": {"hello_store_greeting": {"type": "string", "default": "Hi"}}}',
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/modules/index.php' => self::ENTRY
        ));

        $plugin = PluginPackage::describe($archive);

        $this->assertSame('hello-store', $plugin->id);
        $this->assertSame('Hello store', $plugin->name);
        $this->assertSame('A store plugin.', $plugin->description);
        $this->assertSame('2.1.0', $plugin->version);
        $this->assertSame('Admidio Team', $plugin->author);
        $this->assertSame(array('hello_store_greeting'), array_keys($plugin->settings));

        // Nothing was put in place, and nothing was left in the system temporary directory either.
        $this->assertFileDoesNotExist($this->pluginsPath . '/hello-store');
        $this->assertDirectoryDoesNotExist($plugin->path);
    }

    /**
     * @testdox An archive that could not be installed cannot be described either
     *
     * describe() goes through the very gate install() goes through, which is what makes it worth
     * running before an installation: an archive it accepts is one that will install.
     */
    public function testBrokenArchiveIsNotDescribed(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST');

        PluginPackage::describe($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store"}',
            'hello-store/plugin.php' => self::ENTRY
        )));
    }

    /**
     * @testdox A file that is not a ZIP archive is refused
     */
    public function testNonArchiveIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_NOT_A_ZIP');

        $path = $this->makeDirectory('upload') . '/not-a-zip.zip';
        file_put_contents($path, 'this is just text');

        PluginPackage::install($path);
    }

    /**
     * @testdox An upload that never arrived is refused
     */
    public function testMissingArchiveIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_UNREADABLE');

        PluginPackage::install($this->makeDirectory('upload') . '/never-uploaded.zip');
    }

    /**
     * @testdox A plugin that is already there is not overwritten unless that was asked for
     */
    public function testExistingPluginIsNotReplacedByDefault(): void
    {
        PluginPackage::install($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/marker.txt' => 'first'
        )));

        $second = $this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "2.0.0"}',
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/marker.txt' => 'second'
        ));

        try {
            PluginPackage::install($second);
            $this->fail('the existing plugin was overwritten without being asked');
        } catch (Exception $exception) {
            $this->assertStringContainsString('SYS_PLUGIN_ALREADY_EXISTS', $exception->getMessage());
            $this->assertSame('first', file_get_contents($this->pluginsPath . '/hello-store/marker.txt'));
        }
    }

    /**
     * @testdox Replacing a plugin leaves nothing of the previous version behind
     */
    public function testReplacingRemovesTheFilesOfThePreviousVersion(): void
    {
        PluginPackage::install($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'hello-store/plugin.php' => self::ENTRY,
            'hello-store/dropped-in-2.php' => self::ENTRY
        )));

        PluginPackage::install($this->archive(array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "2.0.0"}',
            'hello-store/plugin.php' => self::ENTRY
        )), true);

        $this->assertFileDoesNotExist($this->pluginsPath . '/hello-store/dropped-in-2.php');
        $this->assertSame('2.0.0', PluginRegistry::get('hello-store')?->version);
    }

    /**
     * @testdox An archive with more entries than a plugin could have is refused
     */
    public function testOversizedArchiveIsRefused(): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_TOO_LARGE');

        $entries = array(
            'hello-store/plugin.json' => '{"name": "Hello store", "version": "1.0.0"}',
            'hello-store/plugin.php' => self::ENTRY
        );
        for ($index = 0; $index <= PluginPackage::MAX_ENTRIES; ++$index) {
            $entries['hello-store/file' . $index . '.txt'] = '.';
        }

        PluginPackage::install($this->archive($entries));
    }


    /**
     * @testdox A plugin directory becomes an archive named after the plugin and its version
     */
    public function testArchiveIsBuilt(): void
    {
        $source = $this->plugin('hello-store', '2.1.0', array(
            'src/Greeter.php' => self::ENTRY,
            'languages/en.xml' => '<?xml version="1.0"?><resources></resources>'
        ));
        $out = $this->makeDirectory('out');

        $archive = PluginPackage::create($source, $out);

        // create() answers a path with forward slashes, whatever the platform hands it.
        $this->assertSame(str_replace(DIRECTORY_SEPARATOR, '/', $out) . '/hello-store-2.1.0.zip', $archive);
        $this->assertFileExists($archive);
    }

    /**
     * @testdox The archive holds everything under one directory named as the plugin
     */
    public function testArchiveHasTheExpectedLayout(): void
    {
        $source = $this->plugin('hello-store', '1.0.0', array(
            'src/Greeter.php' => self::ENTRY,
            'templates/plugin.hello-store.tpl' => 'x'
        ));

        $this->assertSame(
            array(
                'hello-store/plugin.json',
                'hello-store/plugin.php',
                'hello-store/src/Greeter.php',
                'hello-store/templates/plugin.hello-store.tpl'
            ),
            $this->entriesOf(PluginPackage::create($source, $this->makeDirectory('out')))
        );
    }

    /**
     * @testdox What belongs to working on a plugin is left out of its archive
     */
    public function testDevelopmentFilesAreExcluded(): void
    {
        $source = $this->plugin('hello-store', '1.0.0', array(
            '.git/config' => 'x',
            '.gitignore' => 'x',
            'node_modules/left-pad/index.js' => 'x',
            '.DS_Store' => 'x',
            'src/Greeter.php' => self::ENTRY
        ));

        $this->assertSame(
            array('hello-store/plugin.json', 'hello-store/plugin.php', 'hello-store/src/Greeter.php'),
            $this->entriesOf(PluginPackage::create($source, $this->makeDirectory('out')))
        );
    }

    /**
     * @testdox An archive that was just built installs
     *
     * The packager checks its own output with the gate the installer uses, so a plugin that packages
     * cleanly cannot fail to install for a reason the author could have been told about.
     */
    public function testBuiltArchiveInstalls(): void
    {
        $source = $this->plugin('hello-store', '3.0.0', array('src/Greeter.php' => self::ENTRY));
        $archive = PluginPackage::create($source, $this->makeDirectory('out'));

        // Install it into a plugins directory that does not have it.
        $this->pluginsPath = $this->makeDirectory('plugins-target');
        PluginRegistry::setPluginsPath($this->pluginsPath);
        PluginRegistry::setInstallations(array());
        PluginRegistry::reset();

        $this->assertSame('hello-store', PluginPackage::install($archive));
        $this->assertFileExists($this->pluginsPath . '/hello-store/src/Greeter.php');
    }

    /**
     * @testdox A broken plugin is not packaged
     */
    public function testBrokenPluginIsNotPackaged(): void
    {
        $directory = $this->makeDirectory('broken') . '/hello-store';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/plugin.json', '{"name": "Hello", oh dear');
        file_put_contents($directory . '/plugin.php', self::ENTRY);

        $this->expectExceptionMessage('SYS_PLUGIN_PACKAGE_BROKEN_MANIFEST');

        PluginPackage::create(Plugin::read($directory), $this->makeDirectory('out'));
    }

    /**
     * @testdox An archive cannot be written to a directory that is not there
     */
    public function testMissingOutputDirectoryIsRefused(): void
    {
        $source = $this->plugin('hello-store', '1.0.0', array());

        $this->expectExceptionMessage('SYS_PLUGIN_ARCHIVE_NOT_WRITABLE');

        PluginPackage::create($source, $this->temporaryPath('no-such-directory'));
    }

    /**
     * Write a plugin directory and read it back as a Plugin.
     * @param string $id
     * @param string $version
     * @param array<string,string> $files Further files, as relative path => content.
     * @return Plugin
     */
    private function plugin(string $id, string $version, array $files): Plugin
    {
        $directory = $this->makeDirectory('source') . '/' . $id;
        mkdir($directory, 0o700, true);

        file_put_contents($directory . '/plugin.json', '{"name": "Hello store", "version": "' . $version . '"}');
        file_put_contents($directory . '/plugin.php', self::ENTRY);

        foreach ($files as $relative => $content) {
            $path = $directory . '/' . $relative;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0o700, true);
            }
            file_put_contents($path, $content);
        }

        return Plugin::read($directory);
    }

    /**
     * The entries of an archive, sorted.
     * @param string $archivePath
     * @return array<int,string>
     */
    private function entriesOf(string $archivePath): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archivePath) === true);

        $entries = array();
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entries[] = $zip->statIndex($index)['name'];
        }
        $zip->close();

        sort($entries);

        return $entries;
    }

    /**
     * A path in the temporary directory that nothing has created.
     * @param string $purpose
     * @return string
     */
    private function temporaryPath(string $purpose): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . '/admidio-test-' . $purpose . '-' . uniqid('', true);
    }
    /**
     * Build a real ZIP file from name => content and return its path.
     * @param array<string,string> $entries
     * @return string
     */
    private function archive(array $entries): string
    {
        $path = $this->makeDirectory('archive') . '/package.zip';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }

    /**
     * A temporary directory that is removed when the test ends.
     * @param string $purpose
     * @return string
     */
    private function makeDirectory(string $purpose): string
    {
        $path = rtrim(sys_get_temp_dir(), '/\\') . '/admidio-test-' . $purpose . '-' . uniqid('', true);
        mkdir($path, 0o700, true);
        $this->rubbish[] = $path;

        return $path;
    }

    /**
     * @param string $path
     * @return void
     */
    private static function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }

        foreach (scandir($path) ?: array() as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::removeRecursively($path . '/' . $entry);
            }
        }

        @rmdir($path);
    }
}
