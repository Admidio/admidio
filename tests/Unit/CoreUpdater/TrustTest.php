<?php
/**
 * The chain of trust of an update: root key -> release-keys.json -> key of the channel ->
 * release.json -> manifest. The keys are generated for the test and never stored.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\Descriptor;
use AdmidioCoreUpdater\KeyStatement;
use AdmidioCoreUpdater\Manifest;
use AdmidioCoreUpdater\Signature;
use AdmidioCoreUpdater\UpdaterException;
use OpenSSLAsymmetricKey;

final class TrustTest extends AdmidioTestCase
{
    /** @var array<string,OpenSSLAsymmetricKey> */
    private static array $private = array();
    /** @var array<string,string> PEM of the public keys */
    private static array $public = array();

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';

        foreach (array('root', 'release', 'nightly', 'stranger') as $name) {
            $key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
            self::$private[$name] = $key;
            self::$public[$name] = openssl_pkey_get_details($key)['key'];
        }
    }

    /**
     * A signed key statement: release key for stable and beta, nightly key for master.
     * @return array{0: string, 1: string} json and signature
     */
    private static function statement(int $sequence, array $revoked = array(), string $signer = 'root', ?array $keys = null): array
    {
        $json = json_encode(array(
            'schema' => 1,
            'sequence' => $sequence,
            'keys' => $keys ?? array(
                array('id' => 'release-1', 'channels' => array('stable', 'beta'), 'publicKey' => self::$public['release']),
                array('id' => 'nightly-1', 'channels' => array('master'), 'publicKey' => self::$public['nightly'])
            ),
            'revoked' => $revoked
        ));

        return array($json, Signature::sign($json, self::$private[$signer]));
    }

    private static function keys(int $sequence = 1, array $revoked = array()): KeyStatement
    {
        [$json, $signature] = self::statement($sequence, $revoked);

        return KeyStatement::fromSigned($json, $signature, self::$public['root']);
    }

    /**
     * A signed descriptor of version 5.1.0 on the stable channel.
     * @return array{0: string, 1: string}
     */
    private static function descriptor(array $changes = array(), string $signer = 'release'): array
    {
        $json = json_encode(array_merge(array(
            'schema' => 1,
            'channel' => 'stable',
            'version' => '5.1.0',
            'versionText' => '5.1.0',
            'packageUrl' => 'https://downloads.example.org/admidio-5.1.0.zip',
            'packageSha256' => str_repeat('a', 64),
            'packageSize' => 12345678,
            'manifestSha256' => str_repeat('b', 64),
            'minimumSourceVersion' => '5.0.0',
            'minimumPhpVersion' => '8.2',
            'releasedAt' => '2026-10-08T12:00:00Z'
        ), $changes));

        return array($json, Signature::sign($json, self::$private[$signer]));
    }

    /**
     * Assert that a call fails with a text key.
     */
    private function assertRefused(string $key, callable $call, string $message = ''): void
    {
        try {
            $call();
            $this->fail('not refused: ' . $message);
        } catch (UpdaterException $exception) {
            $this->assertSame($key, $exception->getMessage(), $message . ' - ' . $exception->getDetail());
        }
    }

    /**
     * @testdox A signature covers the exact bytes
     */
    public function testSignature(): void
    {
        $signature = Signature::sign('{"a":1}', self::$private['release']);

        $this->assertTrue(Signature::verify('{"a":1}', $signature, self::$public['release']));
        $this->assertFalse(Signature::verify('{"a": 1}', $signature, self::$public['release']), 'one byte more');
        $this->assertFalse(Signature::verify('{"a":1}', $signature, self::$public['stranger']), 'another key');
        $this->assertFalse(Signature::verify('{"a":1}', 'not base64!', self::$public['release']));
        $this->assertFalse(Signature::verify('{"a":1}', $signature, 'not a key'));
    }

    /**
     * @testdox The key statement must be signed by the root key
     */
    public function testStatementNeedsTheRootKey(): void
    {
        [$json, $signature] = self::statement(1, array(), 'release');

        $this->assertRefused('SYS_CORE_UPDATE_KEYS_INVALID', fn() => KeyStatement::fromSigned($json, $signature, self::$public['root']));
    }

    /**
     * @testdox Each key signs only the channels the statement lists for it
     */
    public function testChannelScope(): void
    {
        $keys = self::keys();

        $this->assertSame(array('release-1'), array_keys($keys->keysFor('stable')));
        $this->assertSame(array('release-1'), array_keys($keys->keysFor('beta')));
        $this->assertSame(array('nightly-1'), array_keys($keys->keysFor('master')));
        $this->assertSame(array(), $keys->keysFor('unknown'));

        [$json, $signature] = self::descriptor(array(), 'nightly');
        $this->assertRefused('SYS_CORE_UPDATE_SIGNATURE_INVALID',
            fn() => Descriptor::fromSigned($json, $signature, $keys, 'stable'), 'the nightly key signs a stable release');
    }

    /**
     * @testdox A revoked key is refused even though its signature is valid
     */
    public function testRevokedKey(): void
    {
        $keys = self::keys(2, array('release-1'));
        [$json, $signature] = self::descriptor();

        $this->assertSame(array(), $keys->keysFor('stable'));
        $this->assertRefused('SYS_CORE_UPDATE_KEYS_INVALID', fn() => Descriptor::fromSigned($json, $signature, $keys, 'stable'));
    }

    /**
     * @testdox The statement with the highest sequence wins, wherever it comes from
     *
     * An old package or a stale mirror can only offer an older statement, which loses against the
     * one this installation has seen before - so a revoked key does not come back.
     */
    public function testHighestSequenceWins(): void
    {
        $shipped = self::keys(1);
        $seen = self::keys(3, array('release-1'));
        $fetched = self::keys(2);

        $this->assertSame(3, KeyStatement::newest(array($shipped, $fetched, $seen))->sequence);
        $this->assertSame(2, KeyStatement::newest(array($shipped, $fetched, null))->sequence, 'nothing seen yet, offline');
        $this->assertRefused('SYS_CORE_UPDATE_KEYS_INVALID', fn() => KeyStatement::newest(array(null, null)));
    }

    /**
     * @testdox The highest statement seen is kept with its signature and verified again when read
     */
    public function testSeenStatementIsStored(): void
    {
        $file = sys_get_temp_dir() . '/adm_keys_seen_' . uniqid() . '.json';
        try {
            self::keys(4)->save($file);
            $this->assertSame(4, KeyStatement::fromFile($file, self::$public['root'])->sequence);

            file_put_contents($file, str_replace('"sequence":4', '"sequence":9', (string)file_get_contents($file)));
            $this->assertNull(KeyStatement::fromFile($file, self::$public['root']), 'a statement changed on disk');
            $this->assertNull(KeyStatement::fromFile($file . '.missing', self::$public['root']));
        } finally {
            @unlink($file);
            @unlink($file . '.sig');
        }
    }

    /**
     * @testdox A statement that is not in the expected form is refused
     */
    public function testMalformedStatement(): void
    {
        foreach (array(
            'sequence 0' => array('sequence' => 0),
            'no keys' => array('keys' => 'none'),
            'invalid key' => array('keys' => array(array('id' => 'k', 'channels' => array('stable'), 'publicKey' => 'garbage'))),
            'invalid id' => array('keys' => array(array('id' => '../k', 'channels' => array('stable'), 'publicKey' => self::$public['release'])))
        ) as $case => $change) {
            $json = json_encode(array_merge(array('schema' => 1, 'sequence' => 1, 'keys' => array(), 'revoked' => array()), $change));
            $signature = Signature::sign($json, self::$private['root']);
            $this->assertRefused('SYS_CORE_UPDATE_KEYS_INVALID', fn() => KeyStatement::fromSigned($json, $signature, self::$public['root']), $case);
        }
    }

    /**
     * @testdox A valid descriptor is read with all its fields
     */
    public function testDescriptor(): void
    {
        [$json, $signature] = self::descriptor(array('packageUrl' => array('https://a.example.org/p.zip', 'https://b.example.org/p.zip')));

        $descriptor = Descriptor::fromSigned($json, $signature, self::keys(), 'stable');

        $this->assertSame('5.1.0', $descriptor->version);
        $this->assertSame(array('https://a.example.org/p.zip', 'https://b.example.org/p.zip'), $descriptor->packageUrls);
        $this->assertSame(12345678, $descriptor->packageSize);
        $this->assertSame(str_repeat('b', 64), $descriptor->manifestSha256);
    }

    /**
     * @testdox A descriptor must name the channel that was asked for
     *
     * Otherwise a signed beta descriptor could be served where stable was requested.
     */
    public function testDescriptorChannel(): void
    {
        [$json, $signature] = self::descriptor(array('channel' => 'beta'));

        $this->assertRefused('SYS_CORE_UPDATE_INFORMATION_INVALID', fn() => Descriptor::fromSigned($json, $signature, self::keys(), 'stable'));
    }

    /**
     * @testdox A descriptor with a missing or invalid field is refused
     */
    public function testInvalidDescriptor(): void
    {
        foreach (array(
            'version' => array('version' => '5.1'),
            'beta version' => array('version' => '5.1.0-beta'),
            'packageUrl' => array('packageUrl' => array()),
            'packageSha256' => array('packageSha256' => 'abc'),
            'packageSize' => array('packageSize' => '12345678'),
            'manifestSha256' => array('manifestSha256' => strtoupper(str_repeat('b', 64))),
            'minimumSourceVersion' => array('minimumSourceVersion' => 'old'),
            'minimumPhpVersion' => array('minimumPhpVersion' => 'eight')
        ) as $field => $change) {
            [$json, $signature] = self::descriptor($change);
            $this->assertRefused('SYS_CORE_UPDATE_INFORMATION_INVALID', fn() => Descriptor::fromSigned($json, $signature, self::keys(), 'stable'), $field);
        }

        [$json, $signature] = self::descriptor();
        $this->assertRefused('SYS_CORE_UPDATE_SIGNATURE_INVALID',
            fn() => Descriptor::fromSigned($json . ' ', $signature, self::keys(), 'stable'), 'changed after signing');
    }

    /**
     * @testdox An installation that is too old is refused before anything is downloaded
     */
    public function testCompatibility(): void
    {
        [$json, $signature] = self::descriptor(array('minimumSourceVersion' => '5.0.10', 'minimumPhpVersion' => '8.3'));
        $descriptor = Descriptor::fromSigned($json, $signature, self::keys(), 'stable');

        $descriptor->checkCompatibility('5.0.10', '8.3.0');
        $this->assertRefused('SYS_CORE_UPDATE_SOURCE_TOO_OLD', fn() => $descriptor->checkCompatibility('5.0.9', '8.4.0'));
        $this->assertRefused('SYS_CORE_UPDATE_PHP_TOO_OLD', fn() => $descriptor->checkCompatibility('5.0.14', '8.2.29'));
    }

    /**
     * A manifest of version 5.1.0, and a descriptor that names its hash.
     * @return array{0: string, 1: string, 2: Descriptor}
     */
    private static function manifest(array $changes = array(), string $signer = 'release'): array
    {
        $json = json_encode(array_merge(array(
            'version' => '5.1.0',
            'managedDirectories' => array('src', 'system', 'themes/simple'),
            'managedRootFiles' => array('index.php', 'composer.json'),
            'preservePaths' => array('adm_my_files', 'install/core-update'),
            'requirements' => array('php' => '>=8.2', 'extensions' => array('pdo', 'zip')),
            'totalSize' => 1000,
            'files' => array('index.php' => str_repeat('c', 64), 'src/Foo.php' => str_repeat('d', 64))
        ), $changes));

        [$descriptorJson, $descriptorSignature] = self::descriptor(array('manifestSha256' => hash('sha256', $json)));

        return array($json, Signature::sign($json, self::$private[$signer]),
            Descriptor::fromSigned($descriptorJson, $descriptorSignature, self::keys(), 'stable'));
    }

    /**
     * @testdox A manifest is accepted when its hash, signature and version match the descriptor
     */
    public function testManifest(): void
    {
        [$json, $signature, $descriptor] = self::manifest();

        $manifest = Manifest::fromSigned($json, $signature, self::keys(), $descriptor);

        $this->assertSame(array('index.php' => str_repeat('c', 64), 'src/Foo.php' => str_repeat('d', 64)), $manifest->files);
        $this->assertSame(array('index.php', 'composer.json'), $manifest->managedRootFiles);
        $this->assertSame('>=8.2', $manifest->requirements['php']);
    }

    /**
     * @testdox A manifest that is not the one the descriptor names is refused
     */
    public function testManifestMismatch(): void
    {
        [$json, $signature, $descriptor] = self::manifest();
        $keys = self::keys();

        $other = str_replace('"totalSize":1000', '"totalSize":1001', $json);
        $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID',
            fn() => Manifest::fromSigned($other, Signature::sign($other, self::$private['release']), $keys, $descriptor), 'another hash');

        [$json, $signature, $descriptor] = self::manifest(array(), 'stranger');
        $this->assertRefused('SYS_CORE_UPDATE_SIGNATURE_INVALID', fn() => Manifest::fromSigned($json, $signature, $keys, $descriptor));

        [$json, $signature, $descriptor] = self::manifest(array('version' => '5.0.14'));
        $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID', fn() => Manifest::fromSigned($json, $signature, $keys, $descriptor), 'another version');
    }

    /**
     * @testdox A manifest with an unsafe path or names that differ only in case is refused
     */
    public function testUnsafeManifest(): void
    {
        $keys = self::keys();
        foreach (array(
            'parent directory' => array('files' => array('../index.php' => str_repeat('c', 64))),
            'absolute path' => array('files' => array('/etc/passwd' => str_repeat('c', 64))),
            'drive prefix' => array('files' => array('C:/index.php' => str_repeat('c', 64))),
            'backslash' => array('files' => array('src\\Foo.php' => str_repeat('c', 64))),
            'case collision' => array('files' => array('src/Foo.php' => str_repeat('c', 64), 'src/foo.php' => str_repeat('d', 64))),
            'invalid hash' => array('files' => array('index.php' => 'abc')),
            'root file in a directory' => array('managedRootFiles' => array('src/index.php')),
            'unsafe managed directory' => array('managedDirectories' => array('../../')),
            'unsafe preserved path' => array('preservePaths' => array('/'))
        ) as $case => $change) {
            [$json, $signature, $descriptor] = self::manifest($change);
            $this->assertRefused('SYS_CORE_UPDATE_MANIFEST_INVALID', fn() => Manifest::fromSigned($json, $signature, $keys, $descriptor), $case);
        }
    }
}
