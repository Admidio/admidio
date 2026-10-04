<?php
/**
 * The example plugin that ships in plugins/hello-world.
 *
 * It is documentation that runs, so it has to stay valid: a change to the manifest format or to the
 * conventions has to fail here rather than in the example a plugin author copies.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class HelloWorldPluginTest extends PluginTestCase
{
    private function example(): Plugin
    {
        return Plugin::read(ADMIDIO_PATH . '/plugins/hello-world');
    }

    /**
     * @testdox The shipped example plugin is a valid plugin
     */
    public function testExampleIsValid(): void
    {
        $plugin = $this->example();

        $this->assertNull($plugin->error);
        $this->assertSame('hello-world', $plugin->id);
        $this->assertSame('PLG_HELLO_WORLD_NAME', $plugin->name);
        $this->assertSame('1.1.0', $plugin->version);
        $this->assertSame(array(), $plugin->checkRequirements());
    }

    /**
     * @testdox It uses its own namespace and keeps it inside the plugin
     */
    public function testExampleAutoload(): void
    {
        $plugin = $this->example();

        $this->assertSame(array('AdmidioPlugin\\HelloWorld\\'), array_keys($plugin->autoload));
        $this->assertFileExists($plugin->autoload['AdmidioPlugin\\HelloWorld\\'] . '/Greeting.php');
    }

    /**
     * @testdox Its preferences carry the name of the plugin and are valid preference names
     */
    public function testExampleSettings(): void
    {
        $plugin = $this->example();

        $this->assertSame(
            array('hello_world_greeting', 'hello_world_address', 'hello_world_decorate_headline'),
            array_keys($plugin->settings)
        );
        $this->assertSame('plugin_hello_world_enabled', $plugin->getEnabledSettingName());

        // The example shows both forms an enum may declare its values in.
        $this->assertSame(
            array('first_name', 'full_name', 'login_name'),
            $plugin->settings['hello_world_address']['values']
        );
        $this->assertSame(
            'PLG_HELLO_WORLD_ADDRESS_FIRST_NAME',
            $plugin->settings['hello_world_address']['valueLabels']['first_name']
        );

        foreach (array_merge(array($plugin->getEnabledSettingName()), array_keys($plugin->settings)) as $name) {
            $this->assertMatchesRegularExpression('/^[a-z0-9](_?[a-z0-9])*$/', $name,
                'a preference name Admidio would refuse to register');
        }
    }

    /**
     * @testdox It has one page, and every conventional directory it declares exists
     */
    public function testExampleLayout(): void
    {
        $plugin = $this->example();

        $this->assertSame(array('index.php'), $plugin->getPages());
        $this->assertNotNull($plugin->getDirectory(Plugin::DIR_LANGUAGES));
        $this->assertNotNull($plugin->getDirectory(Plugin::DIR_TEMPLATES));
        $this->assertFileExists($plugin->getEntryFile());
    }

    /**
     * @testdox Every language key the example uses is defined in its own language file
     */
    public function testExampleLanguageKeys(): void
    {
        $plugin = $this->example();
        $xml = simplexml_load_file($plugin->getDirectory(Plugin::DIR_LANGUAGES) . '/en.xml');
        $this->assertNotFalse($xml);

        $defined = array();
        foreach ($xml->string as $string) {
            $defined[] = (string)$string['name'];
        }

        $used = array($plugin->name, $plugin->description);
        foreach ($plugin->settings as $setting) {
            $used[] = $setting['label'];
            $used[] = $setting['description'];
            // The names an enum gives its values are language keys just like the label.
            $used = array_merge($used, array_values($setting['valueLabels']));
        }
        foreach (array('/plugin.php', '/src/Greeting.php', '/modules/index.php', '/templates/plugin.hello-world.tpl') as $file) {
            preg_match_all('/PLG_HELLO_WORLD_[A-Z_]+/', (string)file_get_contents($plugin->path . $file), $matches);
            $used = array_merge($used, $matches[0]);
        }

        foreach (array_unique(array_filter($used)) as $key) {
            $this->assertContains($key, $defined, 'the example uses the undefined language key ' . $key);
        }
    }

    /**
     * @testdox Its entry file refuses to be an entry point of its own
     */
    public function testEntryFileRefusesDirectAccess(): void
    {
        $this->assertStringContainsString(
            "realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__",
            (string)file_get_contents($this->example()->getEntryFile())
        );
    }
}
