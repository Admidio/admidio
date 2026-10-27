<?php
/**
 * Removal warnings of plugins
 *
 * Before a plugin is removed, it can say what removal leaves behind - for example accounts in other
 * systems that are no longer managed. A warning informs the administrator; it never blocks removal.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Hooks\Hooks;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginInstaller;
use Admidio\Tests\Support\AdmidioTestCase;

class PluginRemovalWarningsTest extends AdmidioTestCase
{
    protected function tearDown(): void
    {
        Hooks::removeFilter('plugin_removal_warnings', 'removal-warning-test');
        parent::tearDown();
    }

    /**
     * @testdox A plugin reports what removal leaves behind, other plugins are not asked about
     */
    public function testAPluginReportsItsWarnings(): void
    {
        $plugin = Plugin::read(dirname(__DIR__, 3) . '/plugins/hello-world');
        Hooks::addFilter('plugin_removal_warnings', static function (array $warnings, Plugin $about): array {
            if ($about->id === 'hello-world') {
                $warnings[] = '  2 remote accounts remain.  ';
                $warnings[] = '';
            }
            return $warnings;
        }, 10, 2, 'removal-warning-test');

        $this->assertSame(array('2 remote accounts remain.'), PluginInstaller::getRemovalWarnings($plugin));
    }

    /**
     * @testdox Without listeners, and for a plugin whose files are gone, there is nothing to report
     */
    public function testNothingToReport(): void
    {
        $this->assertSame(array(), PluginInstaller::getRemovalWarnings(Plugin::read(dirname(__DIR__, 3) . '/plugins/hello-world')));
        $this->assertSame(array(), PluginInstaller::getRemovalWarnings('orphan'));
    }
}
