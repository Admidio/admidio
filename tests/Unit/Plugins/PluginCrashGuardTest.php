<?php
/**
 * The plugin crash guard: a fatal error while a plugin is loaded is a crash of that plugin and
 * nothing else is, the record it keeps, the rules that let a recorded plugin back in, and what the
 * registry does with it. The failures that end a PHP process are produced in a process of their own.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\PluginCrashGuard;
use Admidio\Infrastructure\Plugins\PluginLoader;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginCrashGuardTest extends PluginTestCase
{
    private string $recordFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        require_once self::fixturePath('crashing') . '/src/Crash.php';

        $this->recordFile = sys_get_temp_dir() . '/adm_plugins_failed_' . uniqid() . '.json';
        PluginCrashGuard::setRecordFile($this->recordFile);
        PluginCrashGuard::reset();
    }

    protected function tearDown(): void
    {
        if (is_file($this->recordFile)) {
            unlink($this->recordFile);
        }
        PluginCrashGuard::setRecordFile(null);
        PluginCrashGuard::reset();

        parent::tearDown();
    }

    /**
     * A file of the fixture plugin "crashing".
     */
    private static function crashingFile(string $file = 'src/Crash.php'): string
    {
        return self::fixturePath('crashing') . '/' . $file;
    }

    /**
     * @return array<string,array{0: int}>
     */
    public static function fatalErrorTypes(): array
    {
        return array(
            'E_ERROR' => array(E_ERROR),
            'E_PARSE' => array(E_PARSE),
            'E_COMPILE_ERROR' => array(E_COMPILE_ERROR),
            'E_CORE_ERROR' => array(E_CORE_ERROR),
            'E_USER_ERROR' => array(E_USER_ERROR)
        );
    }

    /**
     * @testdox A fatal error while a plugin is loaded is a crash of that plugin
     * @dataProvider fatalErrorTypes
     */
    public function testFatalErrorWhileLoading(int $type): void
    {
        PluginCrashGuard::beginLoading('crashing');
        $message = 'Declaration of Incompatible::count(string $mode): int must be compatible with Countable::count(): int';

        $this->assertSame(array(
            'plugin' => 'crashing',
            'error' => $message,
            'file' => self::crashingFile('src/Incompatible.php'),
            'line' => 12
        ), PluginCrashGuard::attributeError(array(
            'type' => $type,
            'message' => $message,
            'file' => self::crashingFile('src/Incompatible.php'),
            'line' => 12
        )));
    }

    /**
     * @testdox A fatal error is no crash of a plugin when no plugin is being loaded
     *
     * What a visitor sends with a request can end it with a fatal error, for example a TypeError
     * that a plugin does not catch. That is a bug to be reported, and must not be able to take the
     * plugin away from everybody.
     */
    public function testFatalErrorOutsideLoading(): void
    {
        $error = array('type' => E_ERROR, 'message' => 'Uncaught TypeError', 'file' => self::crashingFile(), 'line' => 5);

        $this->assertNull(PluginCrashGuard::attributeError($error), 'before a plugin is loaded');

        PluginCrashGuard::beginLoading('crashing');
        PluginCrashGuard::endLoading();
        $this->assertNull(PluginCrashGuard::attributeError($error), 'after the plugin was loaded');
    }

    /**
     * @testdox Loading a plugin ends with endLoading(), whether the plugin loads or fails
     */
    public function testLoaderEndsLoading(): void
    {
        $error = array('type' => E_ERROR, 'message' => 'Broken', 'file' => self::crashingFile(), 'line' => 5);

        PluginLoader::load(PluginRegistry::get('hello'));
        $this->assertNull(PluginCrashGuard::attributeError($error), 'a plugin that loads');

        PluginLoader::load(PluginRegistry::get('broken-json'));
        $this->assertNull(PluginCrashGuard::attributeError($error), 'a plugin that does not');
    }

    /**
     * @testdox An error PHP can continue after is no crash
     */
    public function testNonFatalErrorIsIgnored(): void
    {
        PluginCrashGuard::beginLoading('crashing');

        foreach (array(E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_WARNING, E_USER_NOTICE) as $type) {
            $this->assertNull(PluginCrashGuard::attributeError(array(
                'type' => $type, 'message' => 'Something', 'file' => self::crashingFile(), 'line' => 1
            )), 'error type ' . $type);
        }
        $this->assertNull(PluginCrashGuard::attributeError(null), 'a request without an error');
    }

    /**
     * @testdox Running out of memory or execution time is not attributed to the plugin that was loading
     */
    public function testResourceLimitsAreNotAttributed(): void
    {
        PluginCrashGuard::beginLoading('crashing');

        foreach (array(
            'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes)',
            'Out of memory (allocated 2097152) (tried to allocate 4096 bytes)',
            'Maximum execution time of 30 seconds exceeded'
        ) as $message) {
            $this->assertNull(PluginCrashGuard::attributeError(array(
                'type' => E_ERROR, 'message' => $message, 'file' => self::crashingFile(), 'line' => 5
            )), $message);
        }
    }

    /**
     * @testdox A crash is recorded with the versions it happened with
     */
    public function testRecord(): void
    {
        PluginCrashGuard::record('crashing', 'TypeError: Broken', self::crashingFile(), 27);

        $stored = json_decode((string)file_get_contents($this->recordFile), true);
        $this->assertSame(array('crashing'), array_keys($stored));
        $this->assertSame('2.0.0', $stored['crashing']['pluginVersion']);
        $this->assertSame(ADMIDIO_VERSION_TEXT, $stored['crashing']['admidioVersion']);
        $this->assertSame('TypeError: Broken', $stored['crashing']['error']);
        $this->assertSame('tests/fixtures/plugins/crashing/src/Crash.php', $stored['crashing']['file'],
            'the file is relative to the installation');
        $this->assertSame(27, $stored['crashing']['line']);
        $this->assertNotFalse(\DateTime::createFromFormat(DATE_ATOM, $stored['crashing']['time']));

        PluginCrashGuard::reset();
        $this->assertSame('TypeError: Broken', PluginCrashGuard::getActive()['crashing']['error']);
    }

    /**
     * @testdox A request records its first crash only
     */
    public function testOnlyFirstCrashIsRecorded(): void
    {
        PluginCrashGuard::record('crashing', 'First', self::crashingFile(), 1);
        PluginCrashGuard::record('hello', 'Consequence', self::fixturePath('hello') . '/plugin.php', 1);

        $this->assertSame(array('crashing'), array_keys(PluginCrashGuard::getActive()));
    }

    /**
     * @testdox A long error message is shortened
     */
    public function testLongErrorIsShortened(): void
    {
        PluginCrashGuard::record('crashing', str_repeat('x', 5000), self::crashingFile(), 1);

        $this->assertLessThan(1100, strlen(PluginCrashGuard::getActive()['crashing']['error']));
    }

    /**
     * Write the record the way an earlier request would have left it.
     * @param array<string,array<string,mixed>> $records
     */
    private function writeRecord(array $records): void
    {
        file_put_contents($this->recordFile, json_encode($records));
        PluginCrashGuard::reset();
    }

    /**
     * @return array<string,mixed>
     */
    private static function entry(string $pluginVersion, string $admidioVersion = ADMIDIO_VERSION_TEXT): array
    {
        return array(
            'pluginVersion' => $pluginVersion,
            'admidioVersion' => $admidioVersion,
            'error' => 'Broken',
            'file' => 'plugins/crashing/src/Crash.php',
            'line' => 1,
            'time' => '2026-10-08T12:00:00+02:00'
        );
    }

    /**
     * @testdox A new version of the plugin is tried again automatically
     */
    public function testNewPluginVersionIsTriedAgain(): void
    {
        $this->writeRecord(array('crashing' => self::entry('1.9.0')));

        $this->assertSame(array(), PluginCrashGuard::getActive());
    }

    /**
     * @testdox A new version of Admidio tries every recorded plugin again
     */
    public function testNewAdmidioVersionTriesAgain(): void
    {
        $this->writeRecord(array('crashing' => self::entry('2.0.0', '5.0.14')));

        $this->assertSame(array(), PluginCrashGuard::getActive());
    }

    /**
     * @testdox The entry of a plugin whose files are gone is ignored
     */
    public function testEntryOfRemovedPluginIsIgnored(): void
    {
        $this->writeRecord(array('gone' => self::entry('2.0.0'), 'crashing' => self::entry('2.0.0')));

        $this->assertSame(array('crashing'), array_keys(PluginCrashGuard::getActive()));
    }

    /**
     * @testdox Entries that no longer apply are dropped when a crash is written
     */
    public function testStaleEntriesAreDroppedOnWrite(): void
    {
        $this->writeRecord(array('gone' => self::entry('2.0.0'), 'hello' => self::entry('0.1.0')));

        PluginCrashGuard::record('crashing', 'Broken', self::crashingFile(), 1);

        $stored = json_decode((string)file_get_contents($this->recordFile), true);
        $this->assertSame(array('crashing'), array_keys($stored));
    }

    /**
     * @testdox A record that cannot be read keeps no plugin out
     */
    public function testUnreadableRecordIsEmpty(): void
    {
        file_put_contents($this->recordFile, '{"crashing": ');
        PluginCrashGuard::reset();

        $this->assertSame(array(), PluginCrashGuard::getActive());
    }

    /**
     * @testdox "Try again" removes the entry and leaves an empty record
     */
    public function testRemove(): void
    {
        $this->writeRecord(array('crashing' => self::entry('2.0.0'), 'hello' => self::entry('1.2.0')));

        $this->assertTrue(PluginCrashGuard::remove('crashing'));
        $this->assertSame(array('hello'), array_keys(PluginCrashGuard::getActive()));

        $this->assertFalse(PluginCrashGuard::remove('crashing'), 'nothing left to remove');

        $this->assertTrue(PluginCrashGuard::remove('hello'));
        $this->assertSame('', (string)file_get_contents($this->recordFile),
            'the file stays, so that a crash recorded in the meantime is not deleted with it');
        $this->assertSame(array(), PluginCrashGuard::getActive());
    }

    /**
     * @testdox "Try again" without a record does not create one
     */
    public function testRemoveWithoutRecord(): void
    {
        $this->assertFalse(PluginCrashGuard::remove('crashing'));
        $this->assertFileDoesNotExist($this->recordFile);
    }

    /**
     * @testdox A crashed plugin and the plugins that require it are not loaded
     */
    public function testCrashedPluginAndDependantsAreNotLoadable(): void
    {
        $this->setEnabledInstallations(array(
            'dependent' => array('comId' => 1, 'version' => '1.0.0'),
            'hello' => array('comId' => 2, 'version' => '1.2.0'),
            'crashing' => array('comId' => 3, 'version' => '2.0.0')
        ));
        $this->writeRecord(array('hello' => self::entry('1.2.0')));

        $this->assertSame(array('hello' => 'hello', 'dependent' => 'hello'), PluginRegistry::getExcluded());
        $this->assertSame(array('crashing'), array_map(static fn($plugin) => $plugin->id, PluginRegistry::getLoadable()));
        $this->assertSame(array('crashing' => '2.0.0'), PluginRegistry::getEnabledVersions(),
            'a plugin that is kept out does not satisfy a dependency');
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState('hello'),
            'the plugin stays enabled, the crash is not a preference');

        PluginCrashGuard::remove('hello');
        $this->assertSame(array('crashing', 'hello', 'dependent'),
            array_map(static fn($plugin) => $plugin->id, PluginRegistry::getLoadable()),
            'after "Try again" both are loaded again');
    }

    /**
     * @testdox A page of a crashed plugin is refused with the reason
     */
    public function testPageOfCrashedPluginIsRefused(): void
    {
        $this->setEnabledInstallations(array('hello' => array('comId' => 2, 'version' => '1.2.0')));
        $this->writeRecord(array('hello' => self::entry('1.2.0')));

        $this->expectExceptionMessage('SYS_PLUGIN_CRASHED_PAGE');
        PluginRegistry::resolvePage('hello', 'list.php');
    }

    /**
     * Run PHP code in a process of its own, with the guard's shutdown function registered as
     * PluginCrashGuard::activate() registers it in a web request.
     */
    private function runGuardedProcess(string $code): void
    {
        $script = sys_get_temp_dir() . '/adm_crash_guard_' . uniqid() . '.php';
        file_put_contents($script, '<?php
            require ' . var_export(ADMIDIO_PATH . '/vendor/autoload.php', true) . ';
            require ' . var_export(ADMIDIO_PATH . '/tests/constants.php', true) . ';
            \Admidio\Infrastructure\Plugins\PluginRegistry::setPluginsPath(' . var_export(self::fixturePath(''), true) . ');
            \Admidio\Infrastructure\Plugins\PluginCrashGuard::setRecordFile(' . var_export($this->recordFile, true) . ');
            register_shutdown_function(array(\Admidio\Infrastructure\Plugins\PluginCrashGuard::class, "handleShutdown"));
            ' . $code);

        try {
            exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 -d log_errors=0 ' . escapeshellarg($script) . ' 2>&1', $output, $exitCode);
            $this->assertSame(255, $exitCode, 'the process must end with a fatal error: ' . implode("\n", $output));
        } finally {
            unlink($script);
        }

        PluginCrashGuard::reset();
    }

    /**
     * @testdox An incompatible method signature in a plugin is recorded when PHP shuts down
     */
    public function testCompileErrorWhileLoadingIsRecordedAtShutdown(): void
    {
        $this->runGuardedProcess('\\Admidio\\Infrastructure\\Plugins\\PluginCrashGuard::beginLoading("crashing");
            require ' . var_export(self::crashingFile('src/Incompatible.php'), true) . ';');

        $crash = PluginCrashGuard::getActive()['crashing'] ?? null;
        $this->assertNotNull($crash);
        $this->assertSame('tests/fixtures/plugins/crashing/src/Incompatible.php', $crash['file']);
        $this->assertStringContainsString('must be compatible with Countable::count()', $crash['error']);
    }

    /**
     * @testdox A trigger_error(E_USER_ERROR) while a plugin is loaded is recorded when PHP shuts down
     */
    public function testUserErrorWhileLoadingIsRecordedAtShutdown(): void
    {
        $this->runGuardedProcess('\\Admidio\\Infrastructure\\Plugins\\PluginCrashGuard::beginLoading("crashing");
            trigger_error("The plugin gives up", E_USER_ERROR);');

        $this->assertSame('The plugin gives up', PluginCrashGuard::getActive()['crashing']['error'] ?? null);
    }

    /**
     * @testdox A fatal error after the plugins are loaded is not recorded
     */
    public function testCompileErrorAfterLoadingIsNotRecorded(): void
    {
        $this->runGuardedProcess('\\Admidio\\Infrastructure\\Plugins\\PluginCrashGuard::beginLoading("crashing");
            \\Admidio\\Infrastructure\\Plugins\\PluginCrashGuard::endLoading();
            require ' . var_export(self::crashingFile('src/Incompatible.php'), true) . ';');

        $this->assertSame(array(), PluginCrashGuard::getActive());
        $this->assertFileDoesNotExist($this->recordFile);
    }

    /**
     * @testdox A PHP Error that nothing caught, outside of loading, is not recorded
     */
    public function testUncaughtErrorOutsideLoadingIsNotRecorded(): void
    {
        $this->runGuardedProcess('require ' . var_export(self::crashingFile(), true) . ';
            \\AdmidioPlugin\\Crashing\\Crash::failUncaught();');

        $this->assertSame(array(), PluginCrashGuard::getActive());
        $this->assertFileDoesNotExist($this->recordFile);
    }
}
