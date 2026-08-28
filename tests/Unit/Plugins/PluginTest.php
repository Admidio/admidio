<?php
/**
 * The manifest reader. Every case runs against a real fixture directory below
 * tests/fixtures/plugins, because reading a plugin is a filesystem operation and a stub of the
 * filesystem would only prove that the stub matches the assumption.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginTest extends PluginTestCase
{
    /**
     * @testdox A complete manifest is read into the descriptor
     */
    public function testValidPluginIsRead(): void
    {
        $plugin = Plugin::read(self::fixturePath('hello'));

        $this->assertNull($plugin->error);
        $this->assertTrue($plugin->isValid());
        $this->assertSame('hello', $plugin->id);
        $this->assertSame('Hello', $plugin->name);
        $this->assertSame('1.2.0', $plugin->version);
        $this->assertSame('bi-emoji-smile', $plugin->icon);
        $this->assertSame(self::fixturePath('hello') . '/plugin.php', $plugin->getEntryFile());
    }

    /**
     * @testdox An autoload mapping becomes an absolute directory inside the plugin
     */
    public function testAutoloadIsResolved(): void
    {
        $plugin = Plugin::read(self::fixturePath('hello'));

        $this->assertSame(array('AdmidioPlugin\\Hello\\'), array_keys($plugin->autoload));
        $this->assertSame(
            realpath(self::fixturePath('hello') . '/src'),
            str_replace('/', DIRECTORY_SEPARATOR, $plugin->autoload['AdmidioPlugin\\Hello\\'])
        );
    }

    /**
     * @testdox The declared settings keep their type and default
     */
    public function testSettingsAreRead(): void
    {
        $plugin = Plugin::read(self::fixturePath('hello'));

        $this->assertSame(array('hello_greeting', 'hello_shout'), array_keys($plugin->settings));
        $this->assertSame('Hello', $plugin->settings['hello_greeting']['default']);
        $this->assertFalse($plugin->settings['hello_shout']['default']);
        $this->assertSame('boolean', $plugin->settings['hello_shout']['type']);
    }

    /**
     * @testdox Only the conventional directories that exist are reported
     */
    public function testConventionalDirectories(): void
    {
        $plugin = Plugin::read(self::fixturePath('hello'));

        $this->assertNotNull($plugin->getDirectory(Plugin::DIR_LANGUAGES));
        $this->assertNotNull($plugin->getDirectory(Plugin::DIR_TEMPLATES));
        $this->assertNull($plugin->getDirectory(Plugin::DIR_DB_SCRIPTS));
    }

    /**
     * @testdox The preference that enables a plugin is derived from its ID
     */
    public function testEnabledSettingName(): void
    {
        $this->assertSame('plugin_hello_enabled', Plugin::read(self::fixturePath('hello'))->getEnabledSettingName());
        $this->assertSame('plugin_no_entry_enabled', Plugin::read(self::fixturePath('no-entry'))->getEnabledSettingName());
    }

    /**
     * @testdox Reading a broken plugin reports the problem instead of throwing
     * @dataProvider brokenPlugins
     */
    public function testBrokenPluginIsReported(string $directory, string $expectedFragment): void
    {
        $plugin = Plugin::read(self::fixturePath($directory));

        $this->assertFalse($plugin->isValid());
        $this->assertStringContainsString($expectedFragment, (string)$plugin->error);
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function brokenPlugins(): array
    {
        return array(
            'invalid JSON' => array('broken-json', 'not a valid JSON object'),
            'no entry file' => array('no-entry', 'entry file plugin.php is missing'),
            'autoload leaves the plugin' => array('escaping', 'is not a directory inside the plugin'),
            'autoload claims the core namespace' => array('core-namespace', 'belongs to the Admidio core')
        );
    }

    /**
     * @testdox A directory that is not there at all is reported as a missing manifest
     */
    public function testMissingDirectory(): void
    {
        $plugin = Plugin::read(self::fixturePath('does-not-exist'));

        $this->assertFalse($plugin->isValid());
        $this->assertStringContainsString('manifest plugin.json is missing', (string)$plugin->error);
    }

    /**
     * @testdox A plugin ID is a lowercase directory name with single hyphens
     */
    public function testValidId(): void
    {
        $this->assertTrue(Plugin::isValidId('hello'));
        $this->assertTrue(Plugin::isValidId('who-is-online'));
        $this->assertTrue(Plugin::isValidId('a1'));
        $this->assertFalse(Plugin::isValidId('Hello'));
        $this->assertFalse(Plugin::isValidId('hello_world'));
        $this->assertFalse(Plugin::isValidId('-hello'));
        $this->assertFalse(Plugin::isValidId('hello--world'));
        $this->assertFalse(Plugin::isValidId('../hello'));
    }

    /**
     * @testdox A version constraint is a list of terms that all have to match
     * @dataProvider constraints
     */
    public function testVersionMatches(string $version, string $constraint, bool $expected): void
    {
        $this->assertSame($expected, Plugin::versionMatches($version, $constraint));
    }

    /**
     * @return array<string,array{0:string,1:string,2:bool}>
     */
    public static function constraints(): array
    {
        return array(
            'empty matches everything' => array('5.1.0', '', true),
            'star matches everything' => array('5.1.0', '*', true),
            'bare version means at least' => array('5.1.0', '5.0', true),
            'bare version below' => array('4.9.0', '5.0', false),
            'range inside' => array('5.1.0', '>=5.0 <6.0', true),
            'range above' => array('6.0.0', '>=5.0 <6.0', false),
            'exact' => array('5.1.0', '=5.1.0', true),
            'excluded' => array('5.1.0', '!=5.1.0', false),
            'unreadable constraint never passes' => array('5.1.0', 'whatever', false)
        );
    }

    /**
     * @testdox An unsatisfied requirement is reported for each missing piece
     */
    public function testRequirementProblems(): void
    {
        $plugin = Plugin::read(self::fixturePath('dependent'));

        $this->assertSame(array('hello' => '>=1.0'), $plugin->requires['plugins']);
        $this->assertSame(array(), $plugin->checkRequirements(array('hello' => '1.2.0')));
        $this->assertSame(
            array('The plugin "hello" is required, but it is not installed and enabled.'),
            $plugin->checkRequirements(array())
        );
        $this->assertSame(
            array('The plugin "hello" >=1.0 is required, but version 0.9.0 is installed.'),
            $plugin->checkRequirements(array('hello' => '0.9.0'))
        );
    }

    /**
     * @testdox A plugin that cannot be read reports its problem as a requirement too
     */
    public function testBrokenPluginFailsTheRequirementCheck(): void
    {
        $this->assertNotSame(array(), Plugin::read(self::fixturePath('broken-json'))->checkRequirements());
    }

    /**
     * @testdox The URLs of a plugin point into its own directory
     *
     * Which form a page URL takes depends on whether the pages are published below modules/, which
     * PluginPagesTest covers; here the pages are not published.
     */
    public function testUrls(): void
    {
        $plugin = Plugin::read(self::fixturePath('hello'));

        $this->assertSame(ADMIDIO_URL . '/plugins/hello/modules/list.php', $plugin->getUrl('list.php'));
        $this->assertSame(ADMIDIO_URL . '/plugins/hello/assets/css/hello.css', $plugin->getAssetUrl('css/hello.css'));
        $this->assertSame(
            ADMIDIO_URL . '/plugins/dependent/plugin.php',
            Plugin::read(self::fixturePath('dependent'))->getUrl(),
            'a plugin without pages keeps the entry file as its menu URL'
        );
    }
}
