<?php
/**
 * The plugin crash guard: which failures count as a crash of which plugin, the record it keeps, the
 * rules that let a recorded plugin back in, and what the registry does with it. The failures are
 * produced by the real code of the fixture plugin "crashing"; the two that end a PHP process are
 * produced in a process of their own.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\PluginCrashGuard;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use AdmidioPlugin\Crashing\Crash;

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
            'E_CORE_ERROR' => array(E_CORE_ERROR)
        );
    }

    /**
     * @testdox A fatal error in a file of a plugin is a crash of that plugin
     * @dataProvider fatalErrorTypes
     */
    public function testFatalErrorInPluginFile(int $type): void
    {
        $crash = PluginCrashGuard::attributeError(array(
            'type' => $type,
            'message' => 'Declaration of Incompatible::count(string $mode): int must be compatible with Countable::count(): int',
            'file' => self::crashingFile('src/Incompatible.php'),
            'line' => 12
        ));

        $this->assertSame(array(
            'plugin' => 'crashing',
            'error' => 'Declaration of Incompatible::count(string $mode): int must be compatible with Countable::count(): int',
            'file' => self::crashingFile('src/Incompatible.php'),
            'line' => 12
        ), $crash);
    }

    /**
     * @testdox An error PHP can continue after is no crash
     */
    public function testNonFatalErrorIsIgnored(): void
    {
        foreach (array(E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_ERROR) as $type) {
            $this->assertNull(PluginCrashGuard::attributeError(array(
                'type' => $type, 'message' => 'Something', 'file' => self::crashingFile(), 'line' => 1
            )), 'error type ' . $type);
        }
        $this->assertNull(PluginCrashGuard::attributeError(null), 'a request without an error');
    }

    /**
     * @testdox Running out of memory or execution time is not attributed to the plugin that ran last
     */
    public function testResourceLimitsAreNotAttributed(): void
    {
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
     * @testdox A fatal error outside of a loadable plugin belongs to nobody
     */
    public function testFatalErrorOutsidePlugins(): void
    {
        $files = array(
            'Admidio itself' => ADMIDIO_PATH . '/src/Infrastructure/Plugins/PluginLoader.php',
            'a directory that is no plugin' => self::fixturePath('gone') . '/plugin.php',
            'a plugin with a broken manifest' => self::fixturePath('broken-json') . '/plugin.php',
            'the plugins directory itself' => self::fixturePath('') . '/index.php'
        );

        foreach ($files as $case => $file) {
            $this->assertNull(PluginCrashGuard::attributeError(array(
                'type' => E_COMPILE_ERROR, 'message' => 'Broken', 'file' => $file, 'line' => 1
            )), $case);
        }
    }

    /**
     * @testdox An uncaught PHP Error is attributed through the first stack frame inside a plugin
     *
     * This is the message PHP leaves for error_get_last() when nothing caught a Throwable: the class,
     * the message and the stack trace. Here the TypeError is raised in Admidio, because the plugin
     * called it with an argument of the wrong type.
     */
    public function testUncaughtErrorAttributedByStackFrame(): void
    {
        $core = ADMIDIO_PATH . '/src/Infrastructure/Plugins/PluginRegistry.php';
        $message = 'Uncaught TypeError: PluginRegistry::get(): Argument #1 ($id) must be of type string, array given, called in '
            . self::crashingFile() . ' on line 27 and defined in ' . $core . ":178\n"
            . "Stack trace:\n"
            . '#0 ' . self::crashingFile() . '(27): Admidio\\Infrastructure\\Plugins\\PluginRegistry::get(Array)' . "\n"
            . '#1 ' . ADMIDIO_PATH . '/modules/overview.php(33): AdmidioPlugin\\Crashing\\Crash::call(Object(Closure))' . "\n"
            . "#2 {main}\n"
            . '  thrown';

        $crash = PluginCrashGuard::attributeError(array('type' => E_ERROR, 'message' => $message, 'file' => $core, 'line' => 178));

        $this->assertNotNull($crash);
        $this->assertSame('crashing', $crash['plugin']);
        $this->assertSame(self::crashingFile(), $crash['file']);
        $this->assertSame(27, $crash['line']);
        $this->assertStringStartsWith('TypeError: PluginRegistry::get()', $crash['error']);
        $this->assertStringNotContainsString('Stack trace', $crash['error'], 'the trace is not part of the error');
    }

    /**
     * @testdox An uncaught application exception is no crash, even in a file of a plugin
     */
    public function testUncaughtExceptionIsNoCrash(): void
    {
        $this->assertNull(PluginCrashGuard::attributeError(array(
            'type' => E_ERROR,
            'message' => "Uncaught RuntimeException: The plugin refused the operation. in " . self::crashingFile()
                . ":21\nStack trace:\n#0 {main}\n  thrown",
            'file' => self::crashingFile(),
            'line' => 21
        )));
    }

    /**
     * @testdox A PHP Error raised in a file of a plugin is a crash of that plugin
     */
    public function testThrowableAttributedByItsFile(): void
    {
        $crash = PluginCrashGuard::attributeThrowable(Crash::error());

        $this->assertNotNull($crash);
        $this->assertSame('crashing', $crash['plugin']);
        $this->assertSame(self::crashingFile(), str_replace('\\', '/', $crash['file']));
        $this->assertSame('TypeError: A plugin passed the wrong type.', $crash['error']);
    }

    /**
     * @testdox A PHP Error raised in Admidio on behalf of a plugin is a crash of that plugin
     */
    public function testThrowableAttributedByStackFrame(): void
    {
        $error = Crash::call(static fn(): \Error => new \Error('Call to undefined method Admidio::gone()'));

        $crash = PluginCrashGuard::attributeThrowable($error);

        $this->assertNotNull($crash);
        $this->assertSame('crashing', $crash['plugin']);
        $this->assertSame(self::crashingFile(), str_replace('\\', '/', $crash['file']));
    }

    /**
     * @testdox An application exception of a plugin is no crash
     *
     * A hook that throws one fails the operation it extends; that is the hook policy, not a crash.
     */
    public function testApplicationExceptionIsNoCrash(): void
    {
        $this->assertNull(PluginCrashGuard::attributeThrowable(Crash::exception()));
        $this->assertNull(PluginCrashGuard::attributeThrowable(new \Error('Raised by Admidio alone')));
    }

    /**
     * @testdox Outside of a guarded request a Throwable is not recorded
     */
    public function testInactiveGuardRecordsNothing(): void
    {
        PluginCrashGuard::handleThrowable(Crash::error());

        $this->assertFileDoesNotExist($this->recordFile);
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
        $this->assertTrue(PluginCrashGuard::hasCrashed('crashing'), 'read back from the file');
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
     * @testdox "Try again" removes the entry and the empty record with it
     */
    public function testRemove(): void
    {
        $this->writeRecord(array('crashing' => self::entry('2.0.0'), 'hello' => self::entry('1.2.0')));

        $this->assertTrue(PluginCrashGuard::remove('crashing'));
        $this->assertSame(array('hello'), array_keys(PluginCrashGuard::getActive()));

        $this->assertFalse(PluginCrashGuard::remove('crashing'), 'nothing left to remove');

        $this->assertTrue(PluginCrashGuard::remove('hello'));
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
    public function testCompileErrorIsRecordedAtShutdown(): void
    {
        $this->runGuardedProcess('require ' . var_export(self::crashingFile('src/Incompatible.php'), true) . ';');

        $crash = PluginCrashGuard::getActive()['crashing'] ?? null;
        $this->assertNotNull($crash);
        $this->assertSame('tests/fixtures/plugins/crashing/src/Incompatible.php', $crash['file']);
        $this->assertStringContainsString('must be compatible with Countable::count()', $crash['error']);
    }

    /**
     * @testdox A PHP Error that nothing caught is recorded when PHP shuts down
     */
    public function testUncaughtErrorIsRecordedAtShutdown(): void
    {
        $this->runGuardedProcess('require ' . var_export(self::crashingFile(), true) . ';
            \AdmidioPlugin\Crashing\Crash::failUncaught();');

        $crash = PluginCrashGuard::getActive()['crashing'] ?? null;
        $this->assertNotNull($crash);
        $this->assertSame('tests/fixtures/plugins/crashing/src/Crash.php', $crash['file']);
        $this->assertStringStartsWith('TypeError: ', $crash['error']);
    }
}
