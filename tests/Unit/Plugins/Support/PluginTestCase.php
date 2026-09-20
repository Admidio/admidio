<?php

namespace Admidio\Tests\Unit\Plugins\Support;

use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Support\AdmidioTestCase;
use Psr\Log\NullLogger;

/**
 * Base class for the plugin tests.
 *
 * The plugin classes read a handful of bare global constants of the Admidio bootstrap. They come
 * from tests/constants.php, which the database-backed bootstrap uses as well: composer test:all
 * loads all test suites in one process, and a constant is decided by whoever defines it first.
 */
abstract class PluginTestCase extends AdmidioTestCase
{
    /**
     * The request globals the plugin classes read. A test may put a double in place; the whole
     * suite runs in one process, so what was there before has to come back - a global a test leaves
     * behind is read by code that runs long after it, and ChangeNotification::shutdown() reads
     * gSettingsManager when PHP ends.
     * @var array<int,string>
     */
    private const REQUEST_GLOBALS = array('gSettingsManager', 'gValidLogin');

    /**
     * The values of REQUEST_GLOBALS before the test, as name => array{0: bool, 1: mixed}.
     * @var array<string,array{0: bool, 1: mixed}>
     */
    private array $globals = array();

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/constants.php';

        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::REQUEST_GLOBALS as $name) {
            $this->globals[$name] = array(array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null);
        }

        PluginRegistry::setPluginsPath(self::fixturePath(''));
        PluginRegistry::setInstallations(array());
        PluginLoader::reset();

        // Admidio\Infrastructure\Exception translates its message, so a test that asserts on a
        // language key needs something that answers get(). This stub returns the key itself.
        $GLOBALS['gL10n'] = new PluginTestLanguage();
    }

    protected function tearDown(): void
    {
        foreach ($this->globals as $name => $previous) {
            if ($previous[0]) {
                $GLOBALS[$name] = $previous[1];
            } else {
                unset($GLOBALS[$name]);
            }
        }

        PluginRegistry::setPluginsPath(null);
        PluginRegistry::setInstallations(null);
        PluginRegistry::reset();
        PluginLoader::reset();
        unset($GLOBALS['gL10n']);

        parent::tearDown();
    }

    /**
     * Mark plugins as installed and switched on for the organization of this test.
     *
     * Installing a plugin and enabling it are two decisions, and nothing but the preference
     * plugin_<id>_enabled enables one, so a test that wants a plugin active has to say so. The
     * settings double it puts in place is restored by tearDown() like every other global.
     *
     * @param array<string,array{comId:int,version:string}> $installations
     */
    protected function setEnabledInstallations(array $installations): void
    {
        PluginRegistry::setInstallations($installations);

        $values = array();
        foreach (array_keys($installations) as $id) {
            $values['plugin_' . str_replace('-', '_', (string)$id) . '_enabled'] = '1';
        }

        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble($values);
    }

    /**
     * Absolute path of a fixture plugin directory, or of the fixture plugins directory itself.
     */
    protected static function fixturePath(string $plugin): string
    {
        $path = str_replace('\\', '/', dirname(__DIR__, 3)) . '/fixtures/plugins';

        return $plugin === '' ? $path : $path . '/' . $plugin;
    }
}

/**
 * Answers the few Language methods the plugin classes reach for. get() returns the key, so an
 * assertion can name the language string the code is expected to use.
 */
final class PluginTestLanguage
{
    /** @var array<int,string> */
    public array $folderPaths = array();

    /** The language of the installation, which a case may change to read a translated text. */
    private string $language = 'en';

    /**
     * @param array<int,string> $params
     */
    public function get(string $textId, array $params = array()): string
    {
        return $textId;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): bool
    {
        $this->language = $language;

        return true;
    }

    public function addLanguageFolderPath(string $path): bool
    {
        $this->folderPaths[] = $path;

        return true;
    }
}
