<?php
/**
 * An installation of 5.0.14 in a temporary directory and the workspace that phase A leaves for an
 * update to 5.1.0, for the tests of phase B and of the command line.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use AdmidioCoreUpdater as U;
use ZipArchive;

trait PreparedUpdate
{
    private static \OpenSSLAsymmetricKey $key;

    private string $root = '';

    private static function loadUpdater(): void
    {
        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
        self::$key = openssl_pkey_new(array('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
    }

    /**
     * An installation of 5.0.14: a changed core file, an unchanged one, a file the new release no
     * longer has, an unknown file in a managed directory, a custom root file, an empty directory of
     * the release and the files that are never touched.
     */
    private function createInstallation(): void
    {
        $this->root = sys_get_temp_dir() . '/adm_phase_b_' . uniqid();
        $this->write(array(
            'adm_my_files/config.php' => '<?php $g_adm_db = "club"; $g_root_path = "https://club.example.org";',
            'index.php' => 'OLD INDEX',
            'system/bootstrap/bootstrap.php' => 'OLD BOOTSTRAP',
            'src/Same.php' => 'SAME',
            'src/Old/Gone.php' => 'REMOVED BY THE RELEASE',
            'src/Unknown.php' => 'UNKNOWN',
            'install/core-update/index.php' => 'NEW UPDATER',
            'custom_logo.png' => 'MY LOGO',
            'my-notes.txt' => 'MY NOTES',
            'old_plugins/birthday/index.php' => 'OLD PLUGIN'
        ));
        mkdir($this->root . '/src/Empty', 0777, true);
    }

    private function removeInstallation(): void
    {
        U\releaseLock();
        U\deleteDirectory($this->root);
    }

    /**
     * @param array<string,string> $files
     */
    private function write(array $files): void
    {
        foreach ($files as $path => $content) {
            @mkdir(dirname($this->root . '/' . $path), 0777, true);
            file_put_contents($this->root . '/' . $path, $content);
        }
    }

    private function read(string $path): string
    {
        return (string)@file_get_contents($this->root . '/' . $path);
    }

    /**
     * The workspace as phase A leaves it: a verified package of 5.1.0, its manifest and the signed
     * update.txt, in state prepared.
     * @param array<string,string> $tamper path => content packaged instead of what the manifest names
     * @return array{0: array<string,mixed>, 1: string} context and update ID
     */
    private function prepared(array $tamper = array()): array
    {
        $files = array(
            'index.php' => 'NEW INDEX',
            'system/bootstrap/bootstrap.php' => 'NEW BOOTSTRAP',
            'src/Same.php' => 'SAME',
            'src/New/Fresh.php' => 'NEW FILE',
            'install/core-update/index.php' => 'NEW UPDATER',
            'adm_my_files/config_example.php' => 'EXAMPLE'
        );
        $manifest = json_encode(array(
            'version' => '5.1.0',
            'managedDirectories' => array('src', 'system', 'install'),
            'managedRootFiles' => array('index.php'),
            'preservePaths' => array('adm_my_files', 'install/core-update', 'custom_logo.png'),
            'migrationPaths' => array('old_plugins'),
            'requirements' => array('php' => '>=8.2', 'extensions' => array()),
            'totalSize' => 100,
            'files' => array_map(static fn(string $content): string => hash('sha256', $content), $files)
        ));

        $state = U\createUpdate($this->root, array('installedVersion' => '5.0.14'));
        $workspace = U\workspace($this->root, $state['updateId']);
        $zip = new ZipArchive();
        $zip->open($workspace . '/package.zip', ZipArchive::CREATE);
        foreach (array_merge($files, $tamper) as $path => $content) {
            $zip->addFromString('admidio-5.1.0/' . $path, $content);
        }
        $zip->addFromString('admidio-5.1.0/install/update-manifest.json', $manifest);
        $zip->close();

        $text = "Version=5.1.0\nPackage-URL=https://example.org/admidio-5.1.0.zip\nPackage-SHA256=" . hash_file('sha256', $workspace . '/package.zip')
            . "\nPackage-Size=" . filesize($workspace . '/package.zip') . "\nMinimum-Source-Version=5.0.0\nMinimum-PHP-Version=8.2\n";
        file_put_contents($workspace . '/update.txt', $text);
        file_put_contents($workspace . '/update.txt.sig', U\signData($text, self::$key));
        file_put_contents($workspace . '/release-key.pem', openssl_pkey_get_details(self::$key)['key']);
        file_put_contents($workspace . '/update-manifest.json', $manifest);
        U\updateState($this->root, $state['updateId'], static fn(array $s): array => array_merge($s, array(
            'state' => 'prepared', 'channel' => 'stable', 'targetVersion' => '5.1.0', 'targetVersionText' => '5.1.0')));

        $context = array(
            'config' => U\loadConfig($this->root),
            'pdo' => null,
            'texts' => array('language' => 'en', 'texts' => array()),
            'updaterPath' => $this->root . '/install/core-update',
            'http' => array(),
            'chunkSize' => U\CHUNK_SIZE
        );

        return array($context, $state['updateId']);
    }
}
