<?php

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginSettingsDouble;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use ReflectionMethod;

final class InstallationStatisticsPluginsTest extends PluginTestCase
{
    private array $originalDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDatabase = array_key_exists('gDb', $GLOBALS) ? array($GLOBALS['gDb']) : array();
    }

    protected function tearDown(): void
    {
        if ($this->originalDatabase === array()) {
            unset($GLOBALS['gDb']);
        } else {
            $GLOBALS['gDb'] = $this->originalDatabase[0];
        }
        parent::tearDown();
    }

    public function testStatisticsOnlyIncludeInstalledPluginsEnabledInAnyOrganization(): void
    {
        PluginRegistry::setInstallations(array(
            'hello' => array('comId' => 2, 'version' => '1.1.0'),
            'dependent' => array('comId' => 3, 'version' => '1.0.0'),
            'broken-json' => array('comId' => 4, 'version' => '1.0.0'),
            'missing' => array('comId' => 5, 'version' => '1.0.0')
        ));
        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble(array(
            'plugin_hello_enabled' => '1',
            'plugin_dependent_enabled' => '0',
            'plugin_broken_json_enabled' => '1',
            'plugin_no_entry_enabled' => '1',
            'plugin_missing_enabled' => '1'
        ));

        foreach (array('TBL_PREFERENCES' => 'adm_preferences', 'TBL_ORGANIZATIONS' => 'adm_organizations') as $name => $value) {
            if (!defined($name)) {
                define($name, $value);
            }
        }
        $database = $this->getMockBuilder(\Admidio\Infrastructure\Database::class)
            ->disableOriginalConstructor()->onlyMethods(array('queryPrepared'))->getMock();
        $database->method('queryPrepared')->willReturnCallback(function (string $sql, array $params) {
            $statement = $this->createMock(\PDOStatement::class);
            // hello is enabled in a different organization; dependent is disabled everywhere.
            $statement->method('fetchAll')->willReturn($params === array('plugin_hello_enabled')
                ? array('Other organization') : array());
            return $statement;
        });
        $GLOBALS['gDb'] = $database;
        $method = new ReflectionMethod(InstallationStatistics::class, 'getAdmidioPlugins');
        $this->assertSame(array(
            'hello' => '1.2.0'
        ), $method->invoke(null));
    }

    public function testFreshInstallationDoesNotReadPluginTables(): void
    {
        unset($GLOBALS['gSettingsManager']);
        PluginRegistry::setInstallations(null);

        $method = new ReflectionMethod(InstallationStatistics::class, 'getAdmidioPlugins');
        $this->assertSame(array(), $method->invoke(null));
    }

    public function testUpdatePreviewDoesNotReadPluginRegistryBeforeMigration(): void
    {
        if (!defined('TBL_COMPONENTS')) {
            define('TBL_COMPONENTS', 'adm_components');
        }
        $GLOBALS['gSettingsManager'] = new PluginSettingsDouble(array());
        PluginRegistry::setInstallations(null);
        $database = $this->getMockBuilder(\Admidio\Infrastructure\Database::class)
            ->disableOriginalConstructor()->onlyMethods(array('getTableColumns', 'queryPrepared'))->getMock();
        $database->expects($this->once())->method('getTableColumns')->with(TBL_COMPONENTS)
            ->willReturn(array('com_id', 'com_name_intern', 'com_version'));
        $database->expects($this->never())->method('queryPrepared');
        $GLOBALS['gDb'] = $database;

        $method = new ReflectionMethod(InstallationStatistics::class, 'getAdmidioPlugins');
        $this->assertSame(array(), $method->invoke(null, '5.0.0'));
    }
}
