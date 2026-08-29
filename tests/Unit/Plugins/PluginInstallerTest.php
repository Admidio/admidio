<?php
/**
 * The lifecycle operations.
 *
 * Almost every path of PluginInstaller needs a database and cannot run here. The refusal to remove
 * a plugin of the Admidio distribution can: it is decided before anything is touched, which is the
 * point of it - the guard has to hold even when the rest of the operation would fail.
 */

namespace Admidio\Tests\Unit\Plugins;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginInstaller;
use Admidio\Infrastructure\Plugins\PluginRegistry;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class PluginInstallerTest extends PluginTestCase
{
    /**
     * @testdox A plugin of the Admidio distribution is refused before anything is removed
     * @dataProvider builtInPlugins
     */
    public function testRemoveRefusesABuiltInPlugin(string $id): void
    {
        $this->expectExceptionMessage('SYS_PLUGIN_REMOVE_BUILT_IN');

        PluginInstaller::remove($id);
    }

    /**
     * Every plugin Admidio ships, one per case.
     * @return array<string,array<int,string>>
     */
    public static function builtInPlugins(): array
    {
        $cases = array();
        foreach (PluginRegistry::BUILT_IN as $id) {
            $cases[$id] = array($id);
        }

        return $cases;
    }

    /**
     * @testdox A plugin that Admidio does not ship gets past the guard
     *
     * It must not be refused, so the next thing it does is reach for the database - which is not
     * there. Failing on that is what proves the guard let it through.
     */
    public function testRemoveDoesNotRefuseAnAddedPlugin(): void
    {
        try {
            PluginInstaller::remove('hello-world');
        } catch (\Throwable $exception) {
            $this->assertStringNotContainsString('SYS_PLUGIN_REMOVE_BUILT_IN', $exception->getMessage());
            return;
        }

        $this->fail('removing a plugin without a database was expected to fail on the database');
    }
}
