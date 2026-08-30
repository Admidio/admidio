<?php
/**
 * The catalogue of plugins published for Admidio.
 *
 * Every case writes a real catalogue file and reads it back through the real class. The catalogue is
 * read with file_get_contents(), so a path on disk exercises the same code a URL would - what is not
 * exercised here is the network itself.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginStore;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginStoreTest extends PluginTestCase
{
    /**
     * Every temporary file a test created.
     * @var array<int,string>
     */
    private array $rubbish = array();

    /**
     * Where this test keeps the catalogue cache.
     */
    private string $cacheFile = '';

    /**
     * Every temporary directory a test created.
     * @var array<int,string>
     */
    private array $directories = array();

    protected function setUp(): void
    {
        parent::setUp();

        // The cache is exercised for real, but never in the adm_my_files of the installation.
        $this->cacheFile = $this->temporaryFile('admidio-store-cache-', '.json');
        $this->rubbish[] = $this->cacheFile;
        PluginStore::setCacheFile($this->cacheFile);
        PluginStore::reset();
    }

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            @unlink($path);
        }
        $this->rubbish = array();

        foreach ($this->directories as $path) {
            self::removeRecursively($path);
        }
        $this->directories = array();

        // The developer override is read from config.php globals, so a case that set them clears them.
        unset($GLOBALS['gDebug'], $GLOBALS['gPluginStoreUrl']);

        PluginRegistry::setPluginsPath(null);
        PluginRegistry::setInstallations(null);
        PluginStore::setUrl(null);
        PluginStore::setCacheFile(null);
        PluginStore::reset();

        parent::tearDown();
    }

    /**
     * @testdox A plugin whose release this Admidio satisfies is offered
     */
    public function testMatchingReleaseIsOffered(): void
    {
        $this->catalogue(array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $available = PluginStore::getPlugins();

        $this->assertCount(1, $available);
        $this->assertSame('dummy-plugin', $available[0]['id']);
        $this->assertSame('1.0.0', $available[0]['release']['version']);
    }

    /**
     * @testdox A plugin for an older Admidio is not offered
     *
     * This is what keeps the plugins of the previous runtime out of the store: they are listed in
     * the catalogue with a constraint this Admidio does not satisfy, so nothing that cannot work is
     * ever installable.
     */
    public function testReleaseForAnotherAdmidioIsNotOffered(): void
    {
        $this->catalogue(array(
            $this->entry('old-plugin', array(
                array('version' => '4.0.4', 'requires' => array('admidio' => '>=5.0 <5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertSame(array(), PluginStore::getPlugins());
    }

    /**
     * @testdox The newest release this Admidio satisfies is the one offered
     */
    public function testNewestMatchingReleaseWins(): void
    {
        $this->catalogue(array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/1.zip'),
                array('version' => '2.3.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/2.zip'),
                array('version' => '9.0.0', 'requires' => array('admidio' => '>=6.0'), 'download' => 'https://example.org/9.zip')
            ))
        ));

        $available = PluginStore::getPlugins();

        $this->assertCount(1, $available);
        $this->assertSame('2.3.0', $available[0]['release']['version']);
    }

    /**
     * @testdox A plugin that is already on disk stays in the list and is marked as installed
     *
     * A plugin disappearing from the store the moment it is installed reads as the store losing
     * it. It stays, without an action, so that the catalogue is what it says it is.
     */
    public function testInstalledPluginIsMarked(): void
    {
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '9.9.9', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $plugins = PluginStore::getPlugins();

        $this->assertCount(1, $plugins);
        $this->assertTrue($plugins[0]['installed']);
        $this->assertSame('1.2.0', $plugins[0]['installedVersion'], 'the version on disk, not the one offered');
        $this->assertSame('9.9.9', $plugins[0]['release']['version']);
    }

    /**
     * @testdox A plugin this installation does not have is not marked as installed
     */
    public function testUninstalledPluginIsNotMarked(): void
    {
        $this->catalogue(array(
            $this->entry('not-here', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $plugins = PluginStore::getPlugins();

        $this->assertCount(1, $plugins);
        $this->assertFalse($plugins[0]['installed']);
        $this->assertSame('', $plugins[0]['installedVersion']);
    }

    /**
     * @testdox Installing a plugin the installation already has is refused
     *
     * The store no longer hides an installed plugin, so it now has to refuse one. The refusal comes
     * from the extraction, which will not overwrite a plugin unless it was told to.
     */
    public function testInstallingAnAlreadyInstalledPluginIsRefused(): void
    {
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '9.9.9', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->expectExceptionMessage('SYS_PLUGIN_ALREADY_EXISTS');

        PluginStore::install('hello');
    }

    /**
     * @testdox An entry without a usable release or a usable ID is skipped
     */
    public function testUnusableEntriesAreSkipped(): void
    {
        $this->catalogue(array(
            array('id' => 'Not An Id', 'releases' => array(array('version' => '1.0.0', 'download' => 'https://example.org/d.zip'))),
            array('id' => 'no-releases', 'releases' => array()),
            array('id' => 'no-download', 'releases' => array(array('version' => '1.0.0'))),
            array('id' => 'no-version', 'releases' => array(array('download' => 'https://example.org/d.zip'))),
            $this->entry('good-one', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertSame(array('good-one'), array_column(PluginStore::getPlugins(), 'id'));
    }

    /**
     * @testdox A catalogue that cannot be reached leaves the store empty and says why
     */
    public function testUnreachableCatalogueIsReported(): void
    {
        PluginStore::setUrl(sys_get_temp_dir() . '/admidio-no-such-catalogue-' . uniqid() . '.json');

        $this->assertSame(array(), PluginStore::getPlugins());
        $this->assertStringContainsString('could not be read', (string)PluginStore::getError());
    }

    /**
     * @testdox A catalogue that is not JSON leaves the store empty and says why
     */
    public function testMalformedCatalogueIsReported(): void
    {
        $this->writeCatalogue('{ "format": 1, oh dear');

        $this->assertSame(array(), PluginStore::getPlugins());
        $this->assertStringContainsString('not valid JSON', (string)PluginStore::getError());
    }

    /**
     * @testdox A catalogue in a format this Admidio does not know is refused rather than guessed at
     */
    public function testUnknownFormatIsRefused(): void
    {
        $this->writeCatalogue(json_encode(array('format' => 99, 'plugins' => array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ))));

        $this->assertSame(array(), PluginStore::getPlugins());
        $this->assertStringContainsString('format 99', (string)PluginStore::getError());
    }

    /**
     * @testdox The catalogue is read once per request
     */
    public function testCatalogueIsReadOncePerRequest(): void
    {
        $file = $this->writeCatalogue(json_encode(array('format' => 1, 'plugins' => array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ))));

        $this->assertCount(1, PluginStore::getPlugins());

        // What is on disk changes; what this request answers must not.
        file_put_contents($file, json_encode(array('format' => 1, 'plugins' => array())));

        $this->assertCount(1, PluginStore::getPlugins(), 'the catalogue must not be fetched again');
    }

    /**

    /**
     * @testdox A plugin the catalogue does not offer cannot be installed by asking for it
     */
    public function testInstallingSomethingNotOfferedIsRefused(): void
    {
        $this->catalogue(array(
            $this->entry('offered', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->expectExceptionMessage('SYS_PLUGIN_STORE_NOT_OFFERED');

        PluginStore::install('something-else');
    }

    /**
     * @testdox A plugin whose release is for another Admidio cannot be installed either
     *
     * getPlugins() already leaves it without a release, and install() goes through the same list
     * rather than reading the entry again - so asking for it by name is refused for the same reason.
     */
    public function testInstallingAReleaseForAnotherAdmidioIsRefused(): void
    {
        $this->catalogue(array(
            $this->entry('old-plugin', array(
                array('version' => '4.0.0', 'requires' => array('admidio' => '>=5.0 <5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->expectExceptionMessage('SYS_PLUGIN_STORE_NOT_OFFERED');

        PluginStore::install('old-plugin');
    }

    /**
     * @testdox A catalogue that is not the developer's own may only name an address to download from
     *
     * This is what keeps a catalogue on somebody else's host from pointing the installer at a file
     * of this installation. setUrl() is the test's stand-in for the developer override, so the rule
     * is exercised by taking that away.
     */
    public function testRemoteCatalogueMayNotNameALocalFile(): void
    {
        $file = $this->catalogue(array(
            $this->entry('dummy-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'adm_my_files/config.php')
            ))
        ));

        /*
         * Read the catalogue while it is still the developer's, then drop the override: the
         * catalogue of this request stays, but it is no longer one that may name a local file.
         */
        $this->assertCount(1, PluginStore::getPlugins());
        self::forgetDeveloperCatalogue();

        $this->expectExceptionMessage('SYS_PLUGIN_STORE_DOWNLOAD_NOT_A_URL');

        PluginStore::install('dummy-plugin');
    }

    /**
     * @testdox The developer's own catalogue may name a file inside the installation
     */
    public function testDeveloperCatalogueMayNameALocalArchive(): void
    {
        $archive = $this->temporaryFile('admidio-store-archive-', '.zip');
        $this->rubbish[] = $archive;

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        $zip->addFromString('store-plugin/plugin.json', '{"name": "Store plugin", "version": "1.0.0"}');
        $zip->addFromString('store-plugin/plugin.php', "<?php\n");
        $zip->close();

        $plugins = $this->makePluginsDirectory();

        $this->catalogue(array(
            $this->entry('store-plugin', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => $archive)
            ))
        ));

        $this->assertSame('store-plugin', PluginStore::install('store-plugin'));
        $this->assertFileExists($plugins . '/store-plugin/plugin.json');
        $this->assertFileExists($archive, 'the catalogue archive itself must not be consumed');
    }


    /**
     * @testdox A catalogue path in config.php is taken to be inside the installation
     *
     * The person editing config.php and the Admidio reading it do not necessarily see the same
     * filesystem - an installation in a container sees its own path - so a path is resolved against
     * the installation rather than taken as the host wrote it.
     */
    public function testConfiguredPathIsResolvedAgainstTheInstallation(): void
    {
        $GLOBALS['gDebug'] = true;
        $GLOBALS['gPluginStoreUrl'] = 'adm_my_files/plugin-store/plugins.json';

        $this->assertSame(
            ADMIDIO_PATH . '/adm_my_files/plugin-store/plugins.json',
            PluginStore::getUrl()
        );
    }

    /**
     * @testdox A catalogue address in config.php is left exactly as it is
     */
    public function testConfiguredUrlIsLeftAlone(): void
    {
        $GLOBALS['gDebug'] = true;
        $GLOBALS['gPluginStoreUrl'] = 'https://example.org/mine/plugins.json';

        $this->assertSame('https://example.org/mine/plugins.json', PluginStore::getUrl());
    }

    /**
     * @testdox An absolute catalogue path in config.php is left exactly as it is
     */
    public function testConfiguredAbsolutePathIsLeftAlone(): void
    {
        $GLOBALS['gDebug'] = true;
        $GLOBALS['gPluginStoreUrl'] = '/srv/catalogues/plugins.json';

        $this->assertSame('/srv/catalogues/plugins.json', PluginStore::getUrl());
    }

    /**
     * @testdox Without debugging the configured catalogue is ignored
     *
     * A line left over from development must not quietly repoint a production installation at a
     * catalogue nobody is watching, because the store hands the installer PHP to run.
     */
    public function testConfiguredCatalogueIsIgnoredWithoutDebug(): void
    {
        $GLOBALS['gDebug'] = false;
        $GLOBALS['gPluginStoreUrl'] = 'https://example.org/mine/plugins.json';

        $this->assertSame(ADMIDIO_HOMEPAGE . 'plugins.json', PluginStore::getUrl());
        $this->assertFalse(PluginStore::isDeveloperCatalogue());
    }

    /**
     * @testdox A catalogue release newer than the files on disk is offered as an update
     */
    public function testNewerReleaseIsFound(): void
    {
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '9.9.9', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $release = PluginStore::getNewerRelease('hello');

        $this->assertNotNull($release);
        $this->assertSame('9.9.9', $release['version']);
    }

    /**
     * @testdox A catalogue release that is not newer than the files is not an update
     */
    public function testOlderOrEqualReleaseIsNotAnUpdate(): void
    {
        // The hello fixture declares 1.2.0.
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '1.2.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertNull(PluginStore::getNewerRelease('hello'));
    }

    /**
     * @testdox A plugin the catalogue does not know has no update from the store
     *
     * This is the plugin somebody uploaded from a file. Updating it is still one action for the
     * administrator; it just has no files to fetch, so only its update scripts run.
     */
    public function testPluginOutsideTheCatalogueHasNoStoreUpdate(): void
    {
        $this->catalogue(array(
            $this->entry('something-else', array(
                array('version' => '9.9.9', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->assertNull(PluginStore::getNewerRelease('hello'));
    }

    /**
     * @testdox An unreachable catalogue means no update, not an error
     *
     * The plugin manager asks this while it builds every row, so it has to answer even when the
     * store is down - otherwise a store nobody can reach would take the page with it.
     */
    public function testUnreachableCatalogueMeansNoUpdate(): void
    {
        PluginStore::setUrl($this->temporaryFile('admidio-no-catalogue-', '.json'));

        $this->assertNull(PluginStore::getNewerRelease('hello'));
    }

    /**
     * @testdox Updating the files of a plugin the catalogue has nothing newer for is refused
     */
    public function testUpdatingFilesWithoutANewerReleaseIsRefused(): void
    {
        $this->catalogue(array(
            $this->entry('hello', array(
                array('version' => '1.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => 'https://example.org/d.zip')
            ))
        ));

        $this->expectExceptionMessage('SYS_PLUGIN_STORE_NOTHING_NEWER');

        PluginStore::updateFiles('hello');
    }

    /**
     * @testdox Updating the files replaces them with the newer release
     */
    public function testUpdatingFilesReplacesThem(): void
    {
        $plugins = $this->makePluginsDirectory();

        // The plugin as it is now, with a file the next version drops.
        mkdir($plugins . '/store-plugin', 0o700, true);
        file_put_contents($plugins . '/store-plugin/plugin.json', '{"name": "Store plugin", "version": "1.0.0"}');
        file_put_contents($plugins . '/store-plugin/plugin.php', "<?php\n");
        file_put_contents($plugins . '/store-plugin/dropped-in-2.php', "<?php\n");
        PluginRegistry::reset();

        $archive = $this->temporaryFile('admidio-store-update-', '.zip');
        $this->rubbish[] = $archive;

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        $zip->addFromString('store-plugin/plugin.json', '{"name": "Store plugin", "version": "2.0.0"}');
        $zip->addFromString('store-plugin/plugin.php', "<?php\n");
        $zip->close();

        $this->catalogue(array(
            $this->entry('store-plugin', array(
                array('version' => '2.0.0', 'requires' => array('admidio' => '>=5.1'), 'download' => $archive)
            ))
        ));

        $this->assertSame('2.0.0', PluginStore::updateFiles('store-plugin'));

        PluginRegistry::setInstallations(array());
        $this->assertSame('2.0.0', PluginRegistry::get('store-plugin')?->version);
        $this->assertFileDoesNotExist($plugins . '/store-plugin/dropped-in-2.php');
    }
    /**
     * One catalogue entry, with only what the store needs.
     * @param string $id
     * @param array<int,array<string,mixed>> $releases
     * @return array<string,mixed>
     */
    private function entry(string $id, array $releases): array
    {
        return array(
            'id' => $id,
            'name' => ucfirst($id),
            'description' => array('en' => 'A plugin for testing.'),
            'author' => 'The Admidio Team',
            'releases' => $releases
        );
    }

    /**
     * Write a catalogue of these entries and point the store at it.
     * @param array<int,array<string,mixed>> $entries
     * @return string The catalogue file.
     */
    private function catalogue(array $entries): string
    {
        return $this->writeCatalogue((string)json_encode(array(
            'format' => PluginStore::FORMAT,
            'updated' => '2026-08-30',
            'plugins' => $entries
        )));
    }

    /**
     * Write this text as the catalogue and point the store at it.
     * @param string $body
     * @return string The catalogue file.
     */
    private function writeCatalogue(string $body): string
    {
        $file = $this->temporaryFile('admidio-catalogue-', '.json');
        file_put_contents($file, $body);
        $this->rubbish[] = $file;

        PluginStore::setUrl($file);

        return $file;
    }


    /**
     * Make the catalogue of this request stop counting as the developer's own, without forgetting
     * it. setUrl(null) would also drop the catalogue, and these cases need it kept.
     * @return void
     */
    private static function forgetDeveloperCatalogue(): void
    {
        $url = new \ReflectionProperty(PluginStore::class, 'url');
        $url->setAccessible(true);
        $url->setValue(null, null);
    }

    /**
     * A directory that stands in for plugins/ while a test installs into it.
     * @return string
     */
    private function makePluginsDirectory(): string
    {
        $path = $this->temporaryFile('admidio-store-plugins-', '');
        mkdir($path, 0o700, true);
        $this->directories[] = $path;

        PluginRegistry::setPluginsPath($path);
        PluginRegistry::setInstallations(array());
        PluginRegistry::reset();

        return $path;
    }
    /**
     * A path in the temporary directory that nothing else is using.
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    private function temporaryFile(string $prefix, string $suffix): string
    {
        return sys_get_temp_dir() . '/' . $prefix . uniqid('', true) . $suffix;
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
