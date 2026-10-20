<?php
/**
 * How Session::start() treats session data that this version of Admidio cannot read, as after an
 * update. PHP refuses to start a session once output has been written, which PHPUnit has done, so
 * every case runs Session::start() in a PHP process of its own with a session file prepared here.
 */

namespace Admidio\Tests\Unit\Session;

use Admidio\Tests\Support\AdmidioTestCase;

final class SessionStartTest extends AdmidioTestCase
{
    private const SESSION_ID = 'probe0123456789abcdef';

    private string $directory = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/adm_session_start_' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: array() as $file) {
            unlink($file);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    /**
     * Start the session with the given stored data in a process of its own.
     * @return array{id: string, keys: array<int,string>, data: array<string,mixed>, output: string}
     */
    private function start(string $storedData): array
    {
        file_put_contents($this->directory . '/sess_' . self::SESSION_ID, $storedData);
        file_put_contents($this->directory . '/start.php', '<?php
            ob_start();
            require ' . var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true) . ';
            // The classes as this version has them; the stored data was written by another one.
            class Probe { public int $count = 0; }
            define("ADMIDIO_VERSION_TEXT", "6.0.0");
            define("ADMIDIO_URL_PATH", "");
            define("HTTPS", false);
            define("DOMAIN", "localhost");
            $gLogger = new Psr\Log\NullLogger();
            $gSetCookieForDomain = false;
            session_save_path(' . var_export($this->directory, true) . ');
            $_COOKIE["ADMIDIO_test_SESSION_ID"] = ' . var_export(self::SESSION_ID, true) . ';
            session_id(' . var_export(self::SESSION_ID, true) . ');
            Admidio\Session\Entity\Session::start("ADMIDIO_test");
            $output = ob_get_clean();
            echo json_encode(array("id" => session_id(), "keys" => array_keys($_SESSION),
                "data" => array_map(static fn($value) => is_object($value) ? get_class($value) : $value, $_SESSION), "output" => $output));
        ');

        $process = proc_open(array(PHP_BINARY, $this->directory . '/start.php'), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $result = json_decode((string)$stdout, true);
        $this->assertIsArray($result, $stdout . $stderr);

        return $result;
    }

    /**
     * @testdox Data written by this version is kept
     */
    public function testReadableDataIsKept(): void
    {
        $result = $this->start('admidioVersion|s:5:"6.0.0";gLayoutReduced|b:1;');

        $this->assertSame(self::SESSION_ID, $result['id']);
        $this->assertSame(array('admidioVersion' => '6.0.0', 'gLayoutReduced' => true), $result['data']);
        $this->assertSame('', $result['output']);
    }

    /**
     * @testdox Data written by another version is emptied, but the session ID and so the login stay
     */
    public function testDataOfAnotherVersionIsEmptied(): void
    {
        foreach (array(
            'an older version' => 'admidioVersion|s:6:"5.0.17";gLayoutReduced|b:1;',
            'a version before the marker' => 'gLayoutReduced|b:1;'
        ) as $case => $data) {
            $result = $this->start($data);

            $this->assertSame(self::SESSION_ID, $result['id'], $case);
            $this->assertSame(array('admidioVersion' => '6.0.0'), $result['data'], $case);
        }
    }

    /**
     * @testdox Objects that no longer fit their class are dropped without a message
     */
    public function testUnreadableObjectsAreDroppedQuietly(): void
    {
        foreach (array(
            'a property the class no longer has' => 'admidioVersion|s:5:"6.0.0";gCurrentUser|O:5:"Probe":2:{s:5:"count";i:1;s:11:"assignRoles";b:1;}',
            'a property of another type' => 'admidioVersion|s:5:"6.0.0";gCurrentUser|O:5:"Probe":1:{s:5:"count";s:3:"abc";}'
        ) as $case => $data) {
            $result = $this->start($data);

            $this->assertSame(self::SESSION_ID, $result['id'], $case);
            $this->assertSame(array('admidioVersion' => '6.0.0'), $result['data'], $case);
            $this->assertSame('', $result['output'], $case . ': no deprecation or warning is shown');
        }
    }
}
