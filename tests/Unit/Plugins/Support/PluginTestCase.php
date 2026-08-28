<?php

namespace Admidio\Tests\Unit\Plugins\Support;

use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Support\AdmidioTestCase;
use Psr\Log\NullLogger;

/**
 * Base class for the plugin tests.
 *
 * The plugin classes read a handful of bare global constants of the Admidio bootstrap. Every
 * definition is guarded, because composer test:all loads all test suites in one process and the
 * database-backed bootstrap defines the same constants.
 */
abstract class PluginTestCase extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!defined('ADMIDIO_PATH')) {
            define('ADMIDIO_PATH', dirname(__DIR__, 4));
        }
        if (!defined('ADMIDIO_URL')) {
            define('ADMIDIO_URL', 'https://example.org/admidio');
        }
        if (!defined('FOLDER_PLUGINS')) {
            define('FOLDER_PLUGINS', '/plugins');
        }
        if (!defined('FOLDER_MODULES')) {
            define('FOLDER_MODULES', '/modules');
        }
        if (!defined('ADMIDIO_VERSION')) {
            define('ADMIDIO_VERSION', '5.1.0');
        }

        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    protected function setUp(): void
    {
        parent::setUp();

        PluginRegistry::setPluginsPath(self::fixturePath(''));
        PluginRegistry::setInstallations(array());
        PluginLoader::reset();

        // Admidio\Infrastructure\Exception translates its message, so a test that asserts on a
        // language key needs something that answers get(). This stub returns the key itself.
        $GLOBALS['gL10n'] = new PluginTestLanguage();
    }

    protected function tearDown(): void
    {
        PluginRegistry::setPluginsPath(null);
        PluginRegistry::setInstallations(null);
        PluginRegistry::reset();
        PluginLoader::reset();
        unset($GLOBALS['gL10n']);

        parent::tearDown();
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
 * Answers the two Language methods the plugin classes reach for. get() returns the key, so an
 * assertion can name the language string the code is expected to use.
 */
final class PluginTestLanguage
{
    /** @var array<int,string> */
    public array $folderPaths = array();

    /**
     * @param array<int,string> $params
     */
    public function get(string $textId, array $params = array()): string
    {
        return $textId;
    }

    public function addLanguageFolderPath(string $path): bool
    {
        $this->folderPaths[] = $path;

        return true;
    }
}
