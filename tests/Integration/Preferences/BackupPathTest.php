<?php
/**
 * The directory of the backups that an administrator enters in the preferences
 * (PreferencesService::validateBackupPath()): an existing directory the web server can write to,
 * outside the program files of Admidio, which an update replaces.
 */

namespace Admidio\Tests\Integration\Preferences;

use Admidio\Infrastructure\Exception;
use Admidio\Preferences\Service\PreferencesService;
use Admidio\Tests\Support\AdministratorTestCase;
use ReflectionMethod;

final class BackupPathTest extends AdministratorTestCase
{
    private string $outside = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->outside = sys_get_temp_dir() . '/adm_backup_path_' . uniqid();
        mkdir($this->outside);
    }

    protected function tearDown(): void
    {
        @rmdir($this->outside);

        parent::tearDown();
    }

    private function validate(string $path): string
    {
        $method = new ReflectionMethod(PreferencesService::class, 'validateBackupPath');

        return $method->invoke(new PreferencesService(), $path);
    }

    private function assertRefused(string $path, string $message): void
    {
        try {
            $this->validate($path);
            $this->fail('accepted: ' . $path);
        } catch (Exception $exception) {
            $this->assertSame($message, $exception->getTranslationId(), $path);
        }
    }

    /**
     * @testdox An empty field and the default directory are stored as empty, another directory as its real path
     */
    public function testAccepted(): void
    {
        $this->assertSame('', $this->validate('  '));
        $this->assertSame('', $this->validate(ADMIDIO_PATH . FOLDER_DATA . '/backups'), 'the default directory');
        $this->assertSame(str_replace('\\', '/', (string)realpath($this->outside)), $this->validate($this->outside . '/'));
    }

    /**
     * @testdox A relative or missing path is refused
     */
    public function testInvalid(): void
    {
        $this->assertRefused('backups', 'ORG_BACKUP_PATH_INVALID');
        $this->assertRefused($this->outside . '/missing', 'ORG_BACKUP_PATH_INVALID');
    }

    /**
     * @testdox The program files of Admidio and a directory that contains the installation are refused
     */
    public function testProgramFiles(): void
    {
        $this->assertRefused(ADMIDIO_PATH . '/src', 'ORG_BACKUP_PATH_IN_INSTALLATION');
        $this->assertRefused(ADMIDIO_PATH, 'ORG_BACKUP_PATH_IN_INSTALLATION');
        $this->assertRefused(dirname(ADMIDIO_PATH), 'ORG_BACKUP_PATH_IN_INSTALLATION');
        $this->assertRefused('/', 'ORG_BACKUP_PATH_IN_INSTALLATION');
    }
}
