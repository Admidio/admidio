<?php

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\InstallationStatistics;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginSettingsDouble;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use ReflectionMethod;

final class InstallationStatisticsPluginsTest extends PluginTestCase
{
    public function testStatisticsIncludeDirectoryPluginsRegardlessOfInstallationAndEnabledState(): void
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

        $method = new ReflectionMethod(InstallationStatistics::class, 'getAdmidioPlugins');
        $this->assertSame(array(
            'dependent' => '1.0.0',
            'hello' => '1.2.0',
            'needs-module' => '1.0.0'
        ), $method->invoke(null));
    }

    public function testFreshInstallationDoesNotReadPluginTables(): void
    {
        unset($GLOBALS['gSettingsManager']);
        PluginRegistry::setInstallations(null);

        $method = new ReflectionMethod(InstallationStatistics::class, 'getAdmidioPlugins');
        $this->assertSame(array(
            'dependent' => '1.0.0',
            'hello' => '1.2.0',
            'needs-module' => '1.0.0'
        ), $method->invoke(null));
    }
}
