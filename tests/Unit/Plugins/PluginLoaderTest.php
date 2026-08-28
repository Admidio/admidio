<?php
/**
 * Loading a plugin. The entry files, classes and hooks are the real ones of the fixture plugins, so
 * what is asserted here is what an actual plugin would experience.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Preferences\Service\PreferenceDefinitions;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginLoaderTest extends PluginTestCase
{
    private mixed $script = null;

    protected function setUp(): void
    {
        parent::setUp();

        Hooks::reset();
        $GLOBALS['helloPluginEntryFileRuns'] = 0;
        $GLOBALS['pluginLoadOrder'] = array();

        // The guard reads the running script; PHPUnit's own is outside the plugins directory.
        $this->script = $_SERVER['SCRIPT_FILENAME'] ?? null;
    }

    protected function tearDown(): void
    {
        Hooks::reset();
        unset($GLOBALS['gSettingsManager']);
        if ($this->script === null) {
            unset($_SERVER['SCRIPT_FILENAME']);
        } else {
            $_SERVER['SCRIPT_FILENAME'] = $this->script;
        }

        parent::tearDown();
    }

    /**
     * @testdox The entry file runs once, its classes resolve and its hooks are registered
     */
    public function testLoadingRunsTheEntryFile(): void
    {
        $this->assertTrue(PluginLoader::load(PluginRegistry::get('hello')));

        $this->assertSame(1, $GLOBALS['helloPluginEntryFileRuns']);
        $this->assertTrue(PluginLoader::isLoaded('hello'));
        $this->assertTrue(Hooks::hasFilter('hello_greeting'));
        $this->assertSame('Hello Ada!', Hooks::applyTypedFilters('hello_greeting', 'Hello', 'Ada'));
        $this->assertTrue(class_exists('AdmidioPlugin\\Hello\\Greeter'), 'the plugin autoloader resolves plugin classes');
    }

    /**
     * @testdox Loading the same plugin again does not run its entry file again
     */
    public function testLoadingIsIdempotent(): void
    {
        PluginLoader::load(PluginRegistry::get('hello'));
        PluginLoader::load(PluginRegistry::get('hello'));

        $this->assertSame(1, $GLOBALS['helloPluginEntryFileRuns']);
    }

    /**
     * @testdox The settings that the manifest declares become known preferences
     */
    public function testSettingsAreRegistered(): void
    {
        PluginLoader::load(PluginRegistry::get('hello'));

        $registered = PreferenceDefinitions::all();
        $this->assertSame(array('default' => 'Hello', 'type' => 'string'), $registered['hello_greeting']);
        $this->assertSame(array('default' => '0', 'type' => 'bool'), $registered['hello_shout'],
            'the manifest may spell the type as "boolean"');
    }

    /**
     * @testdox The preference that enables a plugin is registered for every installed plugin
     */
    public function testEnabledFlagIsRegisteredForEveryInstalledPlugin(): void
    {
        PluginRegistry::setInstallations(array(
            'hello' => array('comId' => 1, 'version' => '1.2.0'),
            'no-entry' => array('comId' => 2, 'version' => '1.0.0')
        ));
        // "hello" is installed but switched off here, and "no-entry" cannot be loaded at all.
        $GLOBALS['gSettingsManager'] = new PluginLoaderSettingsDouble(array('plugin_hello_enabled' => '0'));

        PluginLoader::loadEnabled();

        $this->assertFalse(PluginLoader::isLoaded('hello'));
        $this->assertFalse(PluginLoader::isLoaded('no-entry'));

        $registered = PreferenceDefinitions::all();
        foreach (array('plugin_hello_enabled', 'plugin_no_entry_enabled') as $name) {
            $this->assertArrayHasKey($name, $registered,
                'a plugin that is not loaded must still be enableable again');
            $this->assertSame('bool', $registered[$name]['type']);
            $this->assertSame('1', $registered[$name]['default']);
        }
    }

    /**
     * @testdox A broken plugin is reported and skipped instead of taking the request down
     */
    public function testBrokenPluginIsSkipped(): void
    {
        $this->assertFalse(PluginLoader::load(PluginRegistry::get('broken-json')));

        $this->assertFalse(PluginLoader::isLoaded('broken-json'));
        $this->assertArrayHasKey('broken-json', PluginLoader::getFailures());
    }

    /**
     * @testdox loadEnabled() loads the enabled plugins in dependency order and announces itself
     */
    public function testLoadEnabled(): void
    {
        PluginRegistry::setInstallations(array(
            'dependent' => array('comId' => 1, 'version' => '1.0.0'),
            'hello' => array('comId' => 2, 'version' => '1.2.0')
        ));

        $announced = array();
        Hooks::addAction('plugins_loaded', function (array $plugins) use (&$announced) {
            $announced = array_keys($plugins);
        });

        PluginLoader::loadEnabled();

        $this->assertSame(array('hello', 'dependent'), $GLOBALS['pluginLoadOrder']);
        $this->assertSame(array('hello', 'dependent'), $announced);
    }

    /**
     * @testdox loadEnabled() does its work only once per request
     */
    public function testLoadEnabledRunsOnce(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 2, 'version' => '1.2.0')));

        PluginLoader::loadEnabled();
        PluginLoader::loadEnabled();

        $this->assertSame(1, $GLOBALS['helloPluginEntryFileRuns']);
    }

    /**
     * @testdox The template directory of a loaded plugin is offered to the template engine
     */
    public function testTemplateDirectories(): void
    {
        PluginLoader::load(PluginRegistry::get('hello'));

        $this->assertSame(
            array(self::fixturePath('hello') . '/' . Plugin::DIR_TEMPLATES),
            PluginLoader::getTemplateDirectories()
        );
    }

    /**
     * @testdox A page of an enabled plugin may be requested directly
     */
    public function testDirectPageRequestIsAllowed(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 2, 'version' => '1.2.0')));
        $_SERVER['SCRIPT_FILENAME'] = self::fixturePath('hello') . '/modules/list.php';

        PluginLoader::loadEnabled();

        $this->assertTrue(PluginLoader::isLoaded('hello'));
    }

    /**
     * @testdox A page of a plugin that is not enabled here is refused
     */
    public function testDirectPageRequestOfDisabledPluginIsRefused(): void
    {
        $_SERVER['SCRIPT_FILENAME'] = self::fixturePath('hello') . '/modules/list.php';

        $this->expectExceptionMessage('SYS_NO_RIGHTS');
        PluginLoader::loadEnabled();
    }

    /**
     * @testdox Only a declared page is an entry point, the entry file itself is not
     * @dataProvider forbiddenEntryPoints
     */
    public function testOnlyPagesAreEntryPoints(string $relativePath): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 2, 'version' => '1.2.0')));
        $_SERVER['SCRIPT_FILENAME'] = self::fixturePath('hello') . $relativePath;

        $this->expectExceptionMessage('SYS_INVALID_PAGE_VIEW');
        PluginLoader::loadEnabled();
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function forbiddenEntryPoints(): array
    {
        return array(
            'the entry file' => array('/plugin.php'),
            'a class' => array('/src/Greeter.php'),
            'a file that is not a page' => array('/modules/notes.txt')
        );
    }

    /**
     * @testdox A request outside the plugins directory is not affected
     */
    public function testRequestOutsideThePluginsDirectory(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 2, 'version' => '1.2.0')));
        $_SERVER['SCRIPT_FILENAME'] = ADMIDIO_PATH . '/modules/announcements.php';

        PluginLoader::loadEnabled();

        $this->assertTrue(PluginLoader::isLoaded('hello'));
    }

    /**
     * @testdox The plugin autoloader stays inside the mapped directories
     */
    public function testAutoloaderDoesNotResolveForeignClasses(): void
    {
        PluginLoader::load(PluginRegistry::get('hello'));

        $this->assertFalse(class_exists('AdmidioPlugin\\Hello\\DoesNotExist'));
        $this->assertFalse(class_exists('AdmidioPlugin\\Elsewhere\\Greeter'));
    }
}

/**
 * Answers the preferences the loader reads. The real SettingsManager needs a database, and like it
 * this double refuses a name it has no row for: registering a preference does not create it.
 */
final class PluginLoaderSettingsDouble
{
    /**
     * @param array<string,string> $values The preferences this organization actually has a row for.
     */
    public function __construct(private array $values = array())
    {
    }

    public function getBool(string $name, bool $update = false): bool
    {
        return $this->get($name) === '1';
    }

    public function has(string $name, bool $update = false): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function get(string $name, bool $update = false): string
    {
        if (!array_key_exists($name, $this->values)) {
            throw new \RuntimeException('Settings name "' . $name . '" does not exist!');
        }

        return $this->values[$name];
    }

    public function set(string $name, $value, bool $update = true): bool
    {
        $this->values[$name] = (string)$value;

        return true;
    }
}
