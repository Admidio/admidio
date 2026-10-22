<?php
/**
 * The tests of the example plugin, and the example of how a plugin is tested.
 *
 * They live in plugins/hello-world/tests/Unit/ and run with Admidio's own suite, because
 * phpunit.xml reads plugins/*[/]tests/Unit as well as tests/Unit. Nothing has to be registered and
 * the plugin does not have to be enabled.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\HelloWorld\Tests\Unit;

use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class ManifestTest extends PluginTestCase
{
    /**
     * The plugin, read from its own directory.
     */
    private function readPlugin(): Plugin
    {
        return Plugin::read(dirname(__DIR__, 2));
    }

    /**
     * A manifest Admidio refuses is a plugin that is simply not there, and the plugin manager only
     * reports it. Reading it in a test is what makes that visible.
     */
    public function testManifestIsValid(): void
    {
        $plugin = $this->readPlugin();

        $this->assertNull($plugin->error);
        $this->assertTrue($plugin->isValid());
        $this->assertSame('hello-world', $plugin->id);
    }

    /**
     * Every label a setting shows has to be a key of the plugin's own language file. A key that is
     * spelled wrong is not an error anywhere: it reaches the settings form as #PLG_...# and only a
     * person looking at the page would notice.
     */
    public function testEverySettingLabelIsATranslatedKey(): void
    {
        $strings = simplexml_load_file(dirname(__DIR__, 2) . '/languages/en.xml');
        $this->assertNotFalse($strings);

        $known = array();
        foreach ($strings->string as $string) {
            $known[(string)$string['name']] = true;
        }

        foreach ($this->readPlugin()->settings as $name => $setting) {
            foreach (array('label', 'description') as $field) {
                $key = $setting[$field] ?? '';
                if ($key === '') {
                    continue;
                }

                $this->assertTrue(
                    Language::isTranslationStringId($key),
                    sprintf('The %s of the setting %s is not a language key: %s', $field, $name, $key)
                );
                $this->assertArrayHasKey(
                    $key,
                    $known,
                    sprintf('The %s of the setting %s is not in languages/en.xml: %s', $field, $name, $key)
                );
            }
        }
    }

    /**
     * A plugin renaming one of its settings costs every administrator that setting on update,
     * because the stored row is keyed by the name and nothing migrates it.
     */
    public function testSettingNamesDoNotChange(): void
    {
        $this->assertSame(
            array('hello_world_greeting', 'hello_world_address', 'hello_world_decorate_headline'),
            array_keys($this->readPlugin()->settings)
        );
    }
}
