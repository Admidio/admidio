<?php

use Admidio\Infrastructure\Database;
use Admidio\InstallationUpdate\Service\Installation;
use Admidio\InstallationUpdate\ValueObject\InstallationConfig;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\TestDatabaseInitializer;
use Admidio\Announcements\Entity\Announcement;
use Admidio\Users\Entity\User;

// This process uses the production bootstrap: its constants and committed fixtures must
// match the web requests, which cannot see a PHPUnit connection's open transaction.
require dirname(__DIR__) . '/environment.php';
require_once dirname(__DIR__) . '/env.php';
admidioTestLoadEnvironment();
TestEnvironment::validateTestEnvironment();
[$script, $action, $site, $prefix, $url] = $argv;
if (!in_array($action, ['install', 'drop'], true) || !preg_match('/^ht[a-f0-9]{8}$/D', $prefix)
    || realpath(dirname($site)) !== realpath(admidioTestEnv('TEST_FILES_PATH')) || basename($site) !== $prefix) {
    throw new RuntimeException('Refusing an invalid HTTP fixture directory or table prefix.');
}
$config = admidioTestDatabaseConfig();
require $site . '/vendor/autoload.php';
$cliConfigFile = $site . '/adm_my_files/config.php';
$cliTablePrefix = $prefix;
$cliRootUrl = $url;
$cliDbType = match ($config['engine']) {
    'mariadb' => Database::DB_TYPE_MARIADB,
    'postgres' => Database::DB_TYPE_PGSQL,
    default => Database::DB_TYPE_MYSQL,
};
$cliTimezone = 'UTC';
$cliLanguage = 'en';
require $site . '/system/bootstrap/cli-installation.php';
if ($action === 'drop' && is_file($cliConfigFile)
    && ($g_tbl_praefix !== $prefix || $gDbType !== $cliDbType || $g_adm_db !== $config['database']
        || $g_adm_srv !== $config['host'] || (int) $g_adm_port !== $config['port'] || $g_adm_usr !== $config['user'])) {
    throw new RuntimeException('HTTP cleanup database settings do not match the retained installation. Use the original test settings.');
}
require_once dirname(__DIR__) . '/Support/TestDatabaseInitializer.php';
require_once dirname(__DIR__) . '/Support/AdmidioTestFixture.php';
$gDb = new Database($cliDbType, $config['host'], $config['port'], $config['database'], $config['user'], $config['password']);
if ($action === 'drop') {
    TestDatabaseInitializer::dropTables($gDb, $config, $prefix);
    $schema = $config['engine'] === 'postgres' ? 'public' : $config['database'];
    $tables = $gDb->queryPrepared('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', [$schema]);
    while ($table = $tables->fetch()) {
        if (str_starts_with($table['table_name'] ?? $table['TABLE_NAME'], $prefix . '_')) {
            throw new RuntimeException('HTTP fixture tables could not be removed.');
        }
    }
    exit;
}
$installation = InstallationConfig::fromArray([
    'dbType' => $cliDbType, 'dbHost' => $config['host'], 'dbPort' => $config['port'],
    'dbName' => $config['database'], 'dbUsername' => $config['user'], 'dbPassword' => $config['password'],
    'tablePrefix' => $prefix, 'rootUrl' => $url, 'language' => 'en', 'timezone' => 'UTC',
    'organizationName' => 'HTTP Test Organization', 'organizationShortName' => 'HTTPTEST',
    'organizationEmail' => 'http@example.local', 'adminLogin' => 'admin',
    'adminFirstName' => 'HTTP', 'adminLastName' => 'Administrator',
    'adminEmail' => 'http-admin@example.local', 'adminPassword' => 'test_admin_123',
]);
Installation::writeConfigFile($installation, $cliConfigFile);
chmod($cliConfigFile, 0600);
$result = Installation::install($gDb, $installation);
$gCurrentUserId = $result['administratorId'];
$gCurrentUser = new User($gDb, $gProfileFields, $gCurrentUserId);
$gValidLogin = true;
$fixture = new AdmidioTestFixture($gDb);
$member = $fixture->createAndSaveUser('httpmember', 'http-member@example.local');
$user = new User($gDb, $gProfileFields, $member['usr_id']);
$user->setPassword('test_member_123');
$user->setValue('FIRST_NAME', 'HTTP');
$user->setValue('LAST_NAME', 'Member');
$user->save();
$role = $fixture->createAndSaveRole('HTTP members', $result['organizationId']);
$fixture->assignUserToRole($member['usr_id'], $role['rol_id']);

$editor = $fixture->createAndSaveUser('httpeditor', 'http-editor@example.local');
$user = new User($gDb, $gProfileFields, $editor['usr_id']);
$user->setPassword('test_editor_123');
$user->save();
$editorRole = $fixture->createAndSaveRoleWithRights('HTTP editors', $result['organizationId'], ['rol_announcements' => 1]);
$fixture->assignUserToRole($editor['usr_id'], $editorRole['rol_id']);
$category = $fixture->createAndSaveCategory('HTTP announcements', 'ANN', $result['organizationId']);
$announcement = new Announcement($gDb);
$announcement->setValue('ann_cat_id', $category['cat_id']);
$announcement->setValue('ann_headline', 'HTTP public announcement');
$announcement->setValue('ann_description', 'Public HTTP route fixture');
$announcement->save();
$gSettingsManager->setMulti(['system_notifications_enabled' => '0', 'announcements_module_enabled' => '1']);
