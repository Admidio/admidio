<?php
/**
 * Plugin Lifecycle Tests
 *
 * Tests PluginInstaller against a real database. Installing, updating, enabling, disabling and
 * removing a plugin each write to the component table, to the preferences of every organization, to
 * the menu and to the file system, so none of them can be described without a database - which is
 * why the unit test beside this one can only cover the guard that refuses a built-in plugin.
 *
 * The plugin under test is a copy of tests/fixtures/plugins/hello in a directory of the test run,
 * because removing a plugin deletes its files, and the pages are published into a directory of the
 * test run as well. Neither plugins/ nor modules/ of the checkout is touched.
 */

namespace Admidio\Tests\Integration\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginInstaller;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginPages;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Infrastructure\Plugins\PluginStore;
use Admidio\Infrastructure\Utils\FileSystemUtils;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\FilesystemTestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class PluginInstallerTest extends FilesystemTestCase
{
    /**
     * The fixture plugin, and the version its manifest declares.
     */
    private const PLUGIN_ID = 'hello';
    private const PLUGIN_VERSION = '1.2.0';

    /**
     * A plugin that requires "hello", so that a requirement can be left unsatisfied.
     */
    private const DEPENDENT_ID = 'dependent';

    /**
     * The node "Extensions" of the standard menu, below which a plugin gets its entry.
     */
    private const EXTENSIONS_NODE_ID = 3;

    /**
     * The directories the plugin classes are pointed at for the duration of one test.
     */
    private string $pluginsPath = '';
    private string $modulesPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $root = $this->createIsolatedDirectory('plugin-installer');
        $this->pluginsPath = $root . '/plugins';
        $this->modulesPath = $root . '/modules';
        FileSystemUtils::createDirectoryIfNotExists($this->pluginsPath);
        FileSystemUtils::createDirectoryIfNotExists($this->modulesPath);

        foreach (array(self::PLUGIN_ID, self::DEPENDENT_ID) as $fixture) {
            FileSystemUtils::copyDirectory(
                ADMIDIO_PATH . '/tests/fixtures/plugins/' . $fixture,
                $this->pluginsPath . '/' . $fixture
            );
        }

        PluginRegistry::setPluginsPath($this->pluginsPath);
        PluginRegistry::reset();
        PluginPages::setModulesPath($this->modulesPath);
        PluginLoader::reset();
    }

    protected function tearDown(): void
    {
        PluginRegistry::setPluginsPath(null);
        PluginRegistry::setInstallations(null);
        PluginRegistry::reset();
        PluginPages::setModulesPath(null);
        PluginLoader::reset();

        // A case that published a release points the store at its own catalogue and its own cache.
        PluginStore::setUrl(null);
        PluginStore::setCacheFile(null);
        PluginStore::reset();

        // the entry file of the fixture registers this filter every time it is included
        Hooks::reset('hello_greeting');

        parent::tearDown();
    }

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The plugin under test, read from the copy in the directory of this test.
     */
    private function plugin(string $id = self::PLUGIN_ID): Plugin
    {
        $plugin = PluginRegistry::get($id);
        $this->assertInstanceOf(Plugin::class, $plugin, 'The fixture plugin "' . $id . '" was not found.');

        return $plugin;
    }

    /**
     * The component record of the plugin, or an empty array while it has none.
     *
     * @return array<string,mixed>
     */
    private function component(string $id = self::PLUGIN_ID): array
    {
        $sql = 'SELECT * FROM ' . TBL_COMPONENTS . ' WHERE com_type = ? AND com_name_intern = ?';
        $row = $this->getDatabase()
            ->queryPrepared($sql, array(PluginRegistry::COMPONENT_TYPE, $id))
            ->fetch();

        return $row === false ? array() : $row;
    }

    /**
     * The preferences the plugin owns in one organization, as name => value.
     *
     * The names come from the production list, so a preference that PluginInstaller forgets to seed
     * or to remove is missing here rather than being missing from the assertion.
     *
     * @return array<string,string>
     */
    private function preferences(int $organizationId, Plugin $plugin): array
    {
        $names = PluginInstaller::getPreferenceNames($plugin);
        $placeholders = implode(', ', array_fill(0, count($names), '?'));

        $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . '
                 WHERE prf_org_id = ?
                   AND prf_name IN (' . $placeholders . ')';
        $rows = $this->getDatabase()
            ->queryPrepared($sql, array_merge(array($organizationId), $names))
            ->fetchAll();

        return array_column($rows, 'prf_value', 'prf_name');
    }

    /**
     * The menu entry of the plugin, or an empty array while it has none.
     *
     * @return array<string,mixed>
     */
    private function menuEntry(string $id = self::PLUGIN_ID): array
    {
        $sql = 'SELECT men_id, men_men_id_parent, men_com_id, men_name, men_url FROM ' . TBL_MENU . '
                 WHERE men_name_intern = ?';
        $row = $this->getDatabase()->queryPrepared($sql, array($id))->fetch();

        return $row === false ? array() : $row;
    }

    /**
     * The message a language key produces in this installation.
     *
     * Admidio\Infrastructure\Exception translates while it is constructed, so the message of an
     * exception is English text and not the key. Naming the key here keeps the assertion about
     * which string the code uses rather than about how that string is currently worded.
     *
     * @param array<int,string> $parameters
     */
    private function translated(string $key, array $parameters = array()): string
    {
        global $gL10n;

        return $gL10n->get($key, $parameters);
    }

    /**
     * Allow Admidio to write the pages of a plugin below the modules directory.
     */
    private function allowModulePages(): void
    {
        global $gSettingsManager;

        $gSettingsManager->set(PluginPages::SETTING, '1');
    }

    /**
     * Test that enabling a plugin nobody installed yet installs it
     *
     * @testdox The first organization that enables a plugin installs it
     */
    public function testEnableInstallsThePluginTheFirstTime(): void
    {
        global $gCurrentOrgId;

        $plugin = $this->plugin();
        $this->assertSame(PluginRegistry::STATE_AVAILABLE, PluginRegistry::getState($plugin));
        $this->assertSame(array(), $this->component());

        PluginInstaller::enable($plugin);

        // the component record, which is what "installed" means
        $component = $this->component();
        $this->assertNotSame(array(), $component, 'The plugin was enabled without a component record.');
        $this->assertSame(PluginRegistry::COMPONENT_TYPE, $component['com_type']);
        $this->assertSame(self::PLUGIN_VERSION, $component['com_version']);
        $this->assertSame('Hello', $component['com_name']);
        $this->assertValidUuid($component['com_uuid']);

        $this->assertTrue(PluginRegistry::isInstalled(self::PLUGIN_ID));
        $this->assertTrue(PluginRegistry::isEnabled(self::PLUGIN_ID));
        $this->assertSame(self::PLUGIN_VERSION, PluginRegistry::getInstalledVersion(self::PLUGIN_ID));
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState(self::PLUGIN_ID));

        // every preference the manifest declares, with the default the manifest declares
        $preferences = $this->preferences((int)$gCurrentOrgId, $plugin);
        $this->assertSame('1', $preferences[$plugin->getEnabledSettingName()]);
        $this->assertSame('Hello', $preferences['hello_greeting']);
        $this->assertSame('3', $preferences['hello_repeat']);
        $this->assertSame('ASC', $preferences['hello_order']);
        $this->assertCount(count(PluginInstaller::getPreferenceNames($plugin)), $preferences);

        // and the entry below "Extensions" that opens it
        $entry = $this->menuEntry();
        $this->assertNotSame(array(), $entry, 'The plugin was installed without a menu entry.');
        $this->assertSame(self::EXTENSIONS_NODE_ID, (int)$entry['men_men_id_parent']);
        $this->assertSame((int)$component['com_id'], (int)$entry['men_com_id']);
        $this->assertSame('Hello', $entry['men_name']);
    }

    /**
     * Test that the plugin is loaded by the operation that installs it
     *
     * @testdox Installing a plugin loads it, because its preferences cannot be seeded otherwise
     */
    public function testInstallLoadsThePluginItInstalls(): void
    {
        $runsBefore = (int)($GLOBALS['helloPluginEntryFileRuns'] ?? 0);

        PluginInstaller::enable($this->plugin());

        $this->assertSame($runsBefore + 1, (int)$GLOBALS['helloPluginEntryFileRuns']);
        $this->assertTrue(PluginLoader::isLoaded(self::PLUGIN_ID));
    }

    /**
     * Test that a second organization does not install the plugin again
     *
     * @testdox A plugin is installed once for the installation and enabled per organization
     */
    public function testEnablingForASecondOrganizationOnlyFlipsItsPreference(): void
    {
        $second = $this->getFixture()->createAndSaveOrganization('Second Organization', 'SECOND');

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);

        $componentId = (int)$this->component()['com_id'];

        // the preferences reached the organization that was not enabling anything
        $preferences = $this->preferences($second['org_id'], $plugin);
        $this->assertSame('Hello', $preferences['hello_greeting']);

        // The plugin was added to this installation, so it is off until an organization asks for
        // it. The row is there, because nothing but a preference enables a plugin.
        $this->assertSame('0', $preferences[$plugin->getEnabledSettingName()]);

        $this->assertSame(
            array('Test Organization'),
            PluginRegistry::getEnabledOrganizations(self::PLUGIN_ID)
        );

        // enabling it again for the organization that already has it changes nothing else
        PluginInstaller::enable($plugin);
        $this->assertSame($componentId, (int)$this->component()['com_id']);

        $sql = 'SELECT COUNT(*) FROM ' . TBL_COMPONENTS . ' WHERE com_type = ? AND com_name_intern = ?';
        $this->assertSame(
            1,
            (int)$this->getDatabase()
                ->queryPrepared($sql, array(PluginRegistry::COMPONENT_TYPE, self::PLUGIN_ID))
                ->fetchColumn()
        );
    }

    /**
     * Test that a plugin of the Admidio distribution does not have to be switched on
     *
     * @testdox A plugin of the Admidio distribution is enabled in an organization that never asked
     */
    public function testABuiltInPluginIsEnabledWithoutBeingAskedFor(): void
    {
        /*
         * An organization is created with exactly the registered defaults - the installation seeds
         * them, and so does OrganizationService::create() for one that is added later. What they
         * say about a plugin is therefore what an organization that was never asked gets.
         */
        PluginLoader::load($this->plugin());
        $defaults = PreferenceDefinitions::defaults();

        // the plugin under test was added to this installation, so it is off
        $this->assertSame('0', $defaults[$this->plugin()->getEnabledSettingName()]);

        // and the plugins of the distribution, which the test database was installed with, are on
        foreach (PluginRegistry::BUILT_IN as $id) {
            $name = 'plugin_' . str_replace('-', '_', $id) . '_enabled';
            $this->assertSame('1', $defaults[$name], $name . ' does not enable the plugin.');
        }
    }

    /**
     * Test that disabling keeps everything
     *
     * @testdox Disabling a plugin changes nothing but the preference that loads it
     */
    public function testDisableKeepsEverythingButThePreference(): void
    {
        global $gCurrentOrgId;

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);
        $componentId = (int)$this->component()['com_id'];

        PluginInstaller::disable($plugin);

        $this->assertFalse(PluginRegistry::isEnabled(self::PLUGIN_ID));
        $this->assertTrue(PluginRegistry::isInstalled(self::PLUGIN_ID));
        $this->assertSame(PluginRegistry::STATE_DISABLED, PluginRegistry::getState(self::PLUGIN_ID));

        // deciding against the plugin is what takes an organization off the list
        $this->assertSame(array(), PluginRegistry::getEnabledOrganizations(self::PLUGIN_ID));

        $this->assertSame($componentId, (int)$this->component()['com_id']);
        $this->assertNotSame(array(), $this->menuEntry());

        $preferences = $this->preferences((int)$gCurrentOrgId, $plugin);
        $this->assertSame('0', $preferences[$plugin->getEnabledSettingName()]);
        $this->assertSame('Hello', $preferences['hello_greeting']);
    }

    /**
     * Test that a setting an organization changed survives being switched off and on again
     *
     * @testdox A setting an organization changed is still there after disabling and enabling
     */
    public function testDisableAndEnableKeepAChangedSetting(): void
    {
        global $gCurrentOrgId, $gSettingsManager;

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);

        $gSettingsManager->set('hello_greeting', 'Moin');

        PluginInstaller::disable($plugin);
        PluginInstaller::enable($plugin);

        $preferences = $this->preferences((int)$gCurrentOrgId, $plugin);
        $this->assertSame('Moin', $preferences['hello_greeting']);
    }

    /**
     * Test the guards of the operations that need an installed plugin
     *
     * @testdox Disabling and updating refuse a plugin that is not installed
     */
    public function testDisableAndUpdateRefuseAPluginThatIsNotInstalled(): void
    {
        $plugin = $this->plugin();

        try {
            PluginInstaller::disable($plugin);
            $this->fail('disabling a plugin that is not installed was expected to fail');
        } catch (Exception $exception) {
            $this->assertSame($this->translated('SYS_PLUGIN_NOT_INSTALLED', array(self::PLUGIN_ID)), $exception->getMessage());
        }

        try {
            PluginInstaller::update($plugin);
            $this->fail('updating a plugin that is not installed was expected to fail');
        } catch (Exception $exception) {
            $this->assertSame($this->translated('SYS_PLUGIN_NOT_INSTALLED', array(self::PLUGIN_ID)), $exception->getMessage());
        }
    }

    /**
     * Test that a plugin is not installed twice
     *
     * @testdox Installing a plugin that is already installed is refused
     */
    public function testInstallRefusesAPluginThatIsAlreadyInstalled(): void
    {
        $plugin = $this->plugin();
        PluginInstaller::install($plugin);

        $this->expectExceptionMessage(
            $this->translated('SYS_PLUGIN_ALREADY_INSTALLED', array(self::PLUGIN_ID))
        );
        PluginInstaller::install($plugin);
    }

    /**
     * Test that an unsatisfied requirement stops the installation before it writes anything
     *
     * @testdox A plugin whose requirements are not met is not installed at all
     */
    public function testInstallRefusesAPluginWithAnUnsatisfiedRequirement(): void
    {
        $dependent = $this->plugin(self::DEPENDENT_ID);

        try {
            PluginInstaller::install($dependent);
            $this->fail('a plugin that requires a plugin nobody enabled was expected to fail');
        } catch (Exception $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        // nothing of it may be left behind, or the plugin administration would show it as installed
        $this->assertSame(array(), $this->component(self::DEPENDENT_ID));
        $this->assertFalse(PluginRegistry::isInstalled(self::DEPENDENT_ID));
        $this->assertSame(array(), $this->menuEntry(self::DEPENDENT_ID));
    }

    /**
     * Test that the requirement is satisfied once the required plugin is enabled
     *
     * @testdox A plugin is installed once the plugin it requires is enabled
     */
    public function testInstallAcceptsAPluginWhoseRequirementIsSatisfied(): void
    {
        PluginInstaller::enable($this->plugin());

        PluginInstaller::enable($this->plugin(self::DEPENDENT_ID));

        $this->assertTrue(PluginRegistry::isInstalled(self::DEPENDENT_ID));
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState(self::DEPENDENT_ID));
    }

    /**
     * Test that an update raises the version of the component
     *
     * @testdox A plugin whose files declare a newer version is updated to it
     */
    public function testUpdateRaisesTheVersionOfTheComponent(): void
    {
        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);
        $componentId = (int)$this->component()['com_id'];

        $this->writeManifestVersion('1.3.0');

        $updated = $this->plugin();
        $this->assertSame('1.3.0', $updated->version);
        $this->assertSame(PluginRegistry::STATE_UPDATE, PluginRegistry::getState($updated));

        PluginInstaller::update($updated);

        $this->assertSame($componentId, (int)$this->component()['com_id']);
        $this->assertSame('1.3.0', $this->component()['com_version']);
        $this->assertSame('1.3.0', PluginRegistry::getInstalledVersion(self::PLUGIN_ID));
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState(self::PLUGIN_ID));
    }

    /**
     * Test that an update fetches what the store publishes
     *
     * This is the one action the administrator performs, in the web interface and on the command
     * line alike: where the store has newer files they are put in place first, and the update
     * scripts then run against them.
     *
     * @testdox Updating a plugin puts the newer files of the store in place and then runs the scripts
     */
    public function testUpdateFetchesTheNewerFilesFromTheStore(): void
    {
        PluginInstaller::enable($this->plugin());
        $componentId = (int)$this->component()['com_id'];

        $this->publishRelease('1.4.0');

        $updated = PluginInstaller::updateWithNewerFiles($this->plugin());

        $this->assertSame('1.4.0', $updated->version);
        $this->assertSame($componentId, (int)$this->component()['com_id']);
        $this->assertSame('1.4.0', $this->component()['com_version']);
        $this->assertSame('1.4.0', PluginRegistry::getInstalledVersion(self::PLUGIN_ID));
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState(self::PLUGIN_ID));

        // The files are the ones of the release, not the ones that were there.
        $this->assertFileExists($this->pluginsPath . '/' . self::PLUGIN_ID . '/from-the-release.php');
    }

    /**
     * Test that an update without a published release is the update it always was
     *
     * @testdox Updating a plugin the store has nothing newer for runs its update scripts alone
     */
    public function testUpdateWithoutANewerReleaseOnlyRunsTheScripts(): void
    {
        PluginInstaller::enable($this->plugin());

        // The store offers exactly what is installed, so there is nothing to fetch.
        $this->publishRelease(self::PLUGIN_VERSION);
        $this->writeManifestVersion('1.3.0');

        $updated = PluginInstaller::updateWithNewerFiles($this->plugin());

        $this->assertSame('1.3.0', $updated->version);
        $this->assertSame('1.3.0', $this->component()['com_version']);
        $this->assertFileDoesNotExist($this->pluginsPath . '/' . self::PLUGIN_ID . '/from-the-release.php');
    }

    /**
     * Test that removing takes everything away
     *
     * @testdox Removing a plugin takes its files, its component, its menu entry and its preferences
     */
    public function testRemoveTakesEverythingAway(): void
    {
        global $gCurrentOrgId;

        $second = $this->getFixture()->createAndSaveOrganization('Second Organization', 'SECOND');

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);
        $this->assertNotSame(array(), $this->component());

        $deleted = PluginInstaller::remove($plugin);

        $this->assertTrue($deleted, 'The directory of the plugin was not deleted.');
        $this->assertDirectoryDoesNotExist($this->pluginsPath . '/' . self::PLUGIN_ID);

        $this->assertSame(array(), $this->component());
        $this->assertSame(array(), $this->menuEntry());
        $this->assertFalse(PluginRegistry::isInstalled(self::PLUGIN_ID));

        // in every organization, not only in the one that removed it
        $this->assertSame(array(), $this->preferences((int)$gCurrentOrgId, $plugin));
        $this->assertSame(array(), $this->preferences($second['org_id'], $plugin));
    }

    /**
     * Test that a plugin whose files are already gone can still be removed
     *
     * @testdox A plugin whose files are gone is removed by its id
     */
    public function testRemoveByIdCleansUpAnOrphanedPlugin(): void
    {
        global $gCurrentOrgId;

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);

        // what a deployment does that replaces plugins/ without asking Admidio
        FileSystemUtils::deleteDirectoryIfExists($this->pluginsPath . '/' . self::PLUGIN_ID, true);
        PluginRegistry::reset();

        $this->assertSame(PluginRegistry::STATE_ORPHANED, PluginRegistry::getState(self::PLUGIN_ID));

        $this->assertTrue(PluginInstaller::remove(self::PLUGIN_ID));

        $this->assertSame(array(), $this->component());
        $this->assertSame(array(), $this->menuEntry());
        $this->assertFalse(PluginRegistry::isInstalled(self::PLUGIN_ID));

        // the only preference whose name is still known without the manifest
        $sql = 'SELECT COUNT(*) FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ? AND prf_name = ?';
        $this->assertSame(
            0,
            (int)$this->getDatabase()
                ->queryPrepared($sql, array((int)$gCurrentOrgId, 'plugin_hello_enabled'))
                ->fetchColumn()
        );
    }

    /**
     * Test that the pages are only written when the administrator allows it
     *
     * @testdox The pages of a plugin stay out of the modules directory until it is allowed
     */
    public function testPagesAreNotPublishedUnlessAllowed(): void
    {
        $plugin = $this->plugin();

        PluginInstaller::enable($plugin);

        $this->assertFalse(PluginPages::isPublished($plugin));
        $this->assertDirectoryDoesNotExist($this->modulesPath . '/' . self::PLUGIN_ID);
    }

    /**
     * Test the pages that installing writes, and that removing takes away again
     *
     * @testdox Installing publishes the pages of a plugin and removing unpublishes them
     */
    public function testPagesArePublishedOnInstallAndRemovedAgain(): void
    {
        $this->allowModulePages();

        $plugin = $this->plugin();
        PluginInstaller::enable($plugin);

        $target = $this->modulesPath . '/' . self::PLUGIN_ID;
        $this->assertTrue(PluginPages::isPublished($plugin));

        // one stub per page of the plugin, and the marker that says who the directory belongs to
        foreach ($plugin->getPages() as $page) {
            $this->assertFileExists($target . '/' . $page);
        }
        $this->assertFileExists($target . '/' . PluginPages::MARKER_FILE);
        $this->assertFileExists($target . '/index.html');
        $this->assertSame(self::PLUGIN_ID, trim((string)file_get_contents($target . '/' . PluginPages::MARKER_FILE)));

        PluginInstaller::remove($plugin);

        $this->assertDirectoryDoesNotExist($target);
    }

    /**
     * Publish the plugin under test as a release of a catalogue this test controls.
     *
     * The archive holds the plugin as it is now, at the given version and with one file that only
     * that release has, so that a test can tell files that were fetched from files that were there.
     * The catalogue is a file on disk, which the store reads exactly as it reads a published one.
     * @param string $version
     * @return string Absolute path of the archive the catalogue offers.
     */
    private function publishRelease(string $version): string
    {
        $directory = $this->createIsolatedDirectory('plugin-release');

        FileSystemUtils::copyDirectory(
            $this->pluginsPath . '/' . self::PLUGIN_ID,
            $directory . '/' . self::PLUGIN_ID
        );

        $manifest = json_decode(
            (string)file_get_contents($directory . '/' . self::PLUGIN_ID . '/plugin.json'),
            true
        );
        $manifest['version'] = $version;
        file_put_contents(
            $directory . '/' . self::PLUGIN_ID . '/plugin.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents($directory . '/' . self::PLUGIN_ID . '/from-the-release.php', "<?php\n");

        $archive = $directory . '/' . self::PLUGIN_ID . '-' . $version . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not write the release archive ' . $archive . '.');
        }
        foreach (self::filesBelow($directory . '/' . self::PLUGIN_ID) as $relative => $absolute) {
            $zip->addFile($absolute, self::PLUGIN_ID . '/' . $relative);
        }
        $zip->close();

        $catalogue = $directory . '/plugins.json';
        file_put_contents($catalogue, (string)json_encode(array(
            'format' => PluginStore::FORMAT,
            'plugins' => array(array(
                'id' => self::PLUGIN_ID,
                'name' => 'Hello',
                'releases' => array(array(
                    'version' => $version,
                    'requires' => array('admidio' => '>=5.0'),
                    'download' => $archive
                ))
            ))
        )));

        // The cache never reaches adm_my_files, and the catalogue is read from this file alone.
        PluginStore::setCacheFile($directory . '/cache.json');
        PluginStore::setUrl($catalogue);
        PluginStore::reset();

        return $archive;
    }

    /**
     * Every file below a directory, by its path relative to it.
     * @param string $directory
     * @return array<string,string>
     */
    private static function filesBelow(string $directory): array
    {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($directory) + 1);
                $files[$relative] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Rewrite the version in the manifest of the copied plugin, as a new release of it would.
     */
    private function writeManifestVersion(string $version): void
    {
        $file = $this->pluginsPath . '/' . self::PLUGIN_ID . '/plugin.json';
        $manifest = json_decode((string)file_get_contents($file), true);
        $manifest['version'] = $version;

        file_put_contents($file, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        PluginRegistry::reset();
    }
}
