<?php
/**
 * Discovery and the state of a plugin. The installation state is supplied instead of read from the
 * components table - it is one query whose result is a plain array, and every rule that is worth
 * testing is applied to that array, not to the query.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginRegistryTest extends PluginTestCase
{

    /**
     * @testdox The component record of an installed plugin has a UUID the changelog can relate to
     */
    public function testComponentUuidIsRead(): void
    {
        PluginRegistry::setInstallations(array(
            'hello' => array('comId' => 2, 'uuid' => 'b784156e-0461-417d-bf67-affd3b9e2f14', 'version' => '1.2.0')
        ));

        $this->assertSame('b784156e-0461-417d-bf67-affd3b9e2f14', PluginRegistry::getComponentUuid('hello'));
    }

    /**
     * @testdox A plugin with no component record has no UUID, and is not linked to
     */
    public function testUninstalledPluginHasNoComponentUuid(): void
    {
        PluginRegistry::setInstallations(array());

        $this->assertSame('', PluginRegistry::getComponentUuid('hello'));
    }

    /**
     * @testdox A preference a plugin declares is traced back to that plugin
     *
     * This is what lets a changelog entry name the plugin it belongs to, and the plugin
     * administration link to the history of one plugin rather than of every setting there is.
     */
    public function testSettingIsTracedToItsPlugin(): void
    {
        $this->assertSame('hello', PluginRegistry::getOwnerOfSetting('hello_greeting')?->id);
    }

    /**
     * @testdox The flag that enables a plugin belongs to it too
     */
    public function testEnabledFlagIsTracedToItsPlugin(): void
    {
        $this->assertSame('hello', PluginRegistry::getOwnerOfSetting('plugin_hello_enabled')?->id);
    }

    /**
     * @testdox A preference no plugin declares belongs to no plugin
     */
    public function testCorePreferenceHasNoPluginOwner(): void
    {
        $this->assertNull(PluginRegistry::getOwnerOfSetting('documents_files_module_enabled'));
    }
    /**
     * @testdox Every directory is discovered, sorted and without executing any plugin code
     */
    public function testDiscovery(): void
    {
        $GLOBALS['helloPluginEntryFileRuns'] = 0;

        $plugins = PluginRegistry::all();

        $this->assertSame(
            array('broken-json', 'core-namespace', 'dependent', 'escaping', 'hello', 'needs-module', 'no-entry'),
            array_keys($plugins)
        );
        $this->assertSame(0, $GLOBALS['helloPluginEntryFileRuns']);
    }

    /**
     * @testdox A broken plugin is listed instead of breaking the discovery
     */
    public function testBrokenPluginIsListed(): void
    {
        $plugins = PluginRegistry::all();

        $this->assertFalse($plugins['broken-json']->isValid());
        $this->assertSame(PluginRegistry::STATE_BROKEN, PluginRegistry::getState($plugins['broken-json']));
    }

    /**
     * @testdox A plugin that is not installed is available
     */
    public function testAvailableState(): void
    {
        $this->assertSame(PluginRegistry::STATE_AVAILABLE, PluginRegistry::getState('hello'));
        $this->assertFalse(PluginRegistry::isInstalled('hello'));
        $this->assertFalse(PluginRegistry::isEnabled('hello'));
    }

    /**
     * @testdox An installed plugin is enabled unless the organization decided otherwise
     */
    public function testInstalledPluginIsEnabledByDefault(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));

        $this->assertTrue(PluginRegistry::isInstalled('hello'));
        $this->assertSame(7, PluginRegistry::getComponentId('hello'));
        $this->assertSame('1.2.0', PluginRegistry::getInstalledVersion('hello'));
        $this->assertTrue(PluginRegistry::isEnabled('hello'));
        $this->assertSame(PluginRegistry::STATE_ENABLED, PluginRegistry::getState('hello'));
    }

    /**
     * @testdox Files that are newer than the database put the plugin into the update state
     */
    public function testUpdateState(): void
    {
        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.1.0')));

        $this->assertSame(PluginRegistry::STATE_UPDATE, PluginRegistry::getState('hello'));
        $this->assertSame(array(), PluginRegistry::getLoadable(), 'a plugin waiting for its update must not be loaded');
    }

    /**
     * @testdox A plugin that only exists in the database is orphaned
     */
    public function testOrphanedState(): void
    {
        PluginRegistry::setInstallations(array('gone' => array('comId' => 9, 'version' => '1.0.0')));

        $this->assertSame(PluginRegistry::STATE_ORPHANED, PluginRegistry::getState('gone'));
    }

    /**
     * @testdox An unsatisfied requirement keeps a plugin out of the available state
     */
    public function testUnsatisfiedRequirement(): void
    {
        $this->assertSame(PluginRegistry::STATE_BROKEN, PluginRegistry::getState('dependent'),
            'dependent requires hello, which is not installed');

        PluginRegistry::setInstallations(array('hello' => array('comId' => 7, 'version' => '1.2.0')));

        $this->assertSame(PluginRegistry::STATE_AVAILABLE, PluginRegistry::getState('dependent'));
    }

    /**
     * @testdox Only installed, enabled and up to date plugins are loadable
     */
    public function testLoadableSelection(): void
    {
        PluginRegistry::setInstallations(array(
            'hello' => array('comId' => 7, 'version' => '1.2.0'),
            'broken-json' => array('comId' => 8, 'version' => '1.0.0'),
            'no-entry' => array('comId' => 9, 'version' => '1.0.0')
        ));

        $this->assertSame(array('hello'), array_map(static fn($plugin) => $plugin->id, PluginRegistry::getLoadable()));
    }

    /**
     * @testdox A plugin is ordered behind the plugins it requires
     */
    public function testDependencyOrder(): void
    {
        PluginRegistry::setInstallations(array(
            'dependent' => array('comId' => 1, 'version' => '1.0.0'),
            'hello' => array('comId' => 2, 'version' => '1.2.0')
        ));

        $this->assertSame(
            array('hello', 'dependent'),
            array_map(static fn($plugin) => $plugin->id, PluginRegistry::getLoadable()),
            'alphabetically dependent comes first, but it requires hello'
        );
    }
}
