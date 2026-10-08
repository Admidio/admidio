<?php
/**
 * How the standalone updater reads adm_my_files/config.php.
 */

namespace Admidio\Tests\Unit\CoreUpdater;

use Admidio\Tests\Support\AdmidioTestCase;
use AdmidioCoreUpdater\Config;
use AdmidioCoreUpdater\UpdaterException;

final class ConfigTest extends AdmidioTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once dirname(__DIR__, 3) . '/install/core-update/lib/bootstrap.php';
    }

    /**
     * @testdox A configuration without optional settings gets the defaults of init_globals.php
     */
    public function testDefaults(): void
    {
        $config = Config::fromValues('C:\\admidio\\', array('g_adm_db' => 'club'));

        $this->assertSame('C:/admidio', $config->rootPath);
        $this->assertSame('mysql', $config->dbType);
        $this->assertNull($config->dbPort);
        $this->assertSame('adm', $config->tablePrefix);
        $this->assertSame('Europe/Berlin', $config->timezone);
        $this->assertFalse($config->debug);
    }

    /**
     * @testdox The test endpoint and the test key are ignored unless debug mode is on
     */
    public function testDebugOverridesNeedDebugMode(): void
    {
        $values = array(
            'gCoreUpdateServerUrl' => 'https://updates.test/',
            'gCoreUpdateRootKeyFile' => 'keys/test-root.pem'
        );

        $production = Config::fromValues('/srv/admidio', $values);
        $this->assertSame('', $production->serverUrl);
        $this->assertSame('', $production->rootKeyFile);

        $development = Config::fromValues('/srv/admidio', $values + array('gDebug' => true));
        $this->assertSame('https://updates.test/', $development->serverUrl);
        $this->assertSame('keys/test-root.pem', $development->rootKeyFile);
    }

    /**
     * @testdox The cookie prefix is the one constants.php derives
     *
     * init_globals.php always sets $gDebug before constants.php asks isset($gDebug), so every
     * installation uses the database name and the table prefix; characters that are no word
     * characters become underscores.
     */
    public function testCookiePrefix(): void
    {
        $config = Config::fromValues('/srv/admidio', array('g_adm_db' => 'my-club.db', 'g_tbl_praefix' => 'xy'));

        $this->assertSame('ADMIDIO_my_club_db_xy', $config->getCookiePrefix());
    }

    /**
     * @testdox The base path and the scheme come from the URL of the installation
     */
    public function testUrl(): void
    {
        $config = Config::fromValues('/srv/admidio', array('g_root_path' => 'https://example.org/club/'));
        $this->assertSame('https://example.org/club', $config->baseUrl);
        $this->assertSame('/club', $config->getBasePath());
        $this->assertTrue($config->isHttps());

        $root = Config::fromValues('/srv/admidio', array('g_root_path' => 'http://example.org'));
        $this->assertSame('', $root->getBasePath());
        $this->assertFalse($root->isHttps());
    }

    /**
     * @testdox The configuration file is read in a scope of its own
     */
    public function testLoad(): void
    {
        $root = sys_get_temp_dir() . '/adm_updater_config_' . uniqid();
        mkdir($root . '/adm_my_files', 0777, true);
        file_put_contents($root . '/adm_my_files/config.php', '<?php
            $gDbType = "pgsql";
            $g_adm_srv = "db.local";
            $g_adm_port = "5433";
            $g_adm_db = "club";
            $g_adm_usr = "admidio";
            $g_adm_pw = "secret";
            $g_tbl_praefix = "club";
            $g_root_path = "https://club.example.org";
            $configFile = "must not leak into the loader";
        ');

        try {
            $config = Config::load($root);
        } finally {
            unlink($root . '/adm_my_files/config.php');
            rmdir($root . '/adm_my_files');
            rmdir($root);
        }

        $this->assertSame('pgsql', $config->dbType);
        $this->assertSame('db.local', $config->dbHost);
        $this->assertSame(5433, $config->dbPort);
        $this->assertSame('club', $config->dbName);
        $this->assertSame('admidio', $config->dbUser);
        $this->assertSame('secret', $config->dbPassword);
        $this->assertSame('club', $config->tablePrefix);
        $this->assertSame('https://club.example.org', $config->baseUrl);
    }

    /**
     * @testdox Without a configuration there is no installation to update
     */
    public function testMissingConfiguration(): void
    {
        $this->expectException(UpdaterException::class);
        Config::load(sys_get_temp_dir() . '/adm_updater_nothing_' . uniqid());
    }
}
