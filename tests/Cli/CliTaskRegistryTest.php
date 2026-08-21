<?php
/**
 * CLI Task Registry Tests
 *
 * Tests the catalogue of command line tasks. Every command Admidio offers registers itself with a
 * description, its arguments and its options, and the registry is what turns a name typed on the
 * command line into the callback that runs it.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Cli\CliTaskRegistry;
use Admidio\Infrastructure\Cli\CoreTasks;
use Admidio\Tests\Support\DatabaseTestCase;

class CliTaskRegistryTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // the registry is static, so it only has to be filled once for the whole run
        if (CliTaskRegistry::get('user:show') === null) {
            CoreTasks::register();
        }
    }

    /**
     * Test that the core commands are there
     *
     * @testdox The core commands register themselves with the registry
     */
    public function testCoreCommandsRegisterThemselves(): void
    {
        $tasks = CliTaskRegistry::getAll();

        $this->assertNotEmpty($tasks);

        // one command from each corner of the application
        foreach (array('user:show', 'group:list', 'category:list', 'event:list', 'maintenance:mode', 'cli:selfcheck') as $expected) {
            $this->assertArrayHasKey($expected, $tasks, $expected . ' should be a registered command.');
        }
    }

    /**
     * Test that a name that does not exist is answered as such
     *
     * @testdox A command that does not exist is reported as unknown
     */
    public function testCommandThatDoesNotExistIsReportedAsUnknown(): void
    {
        $this->assertNull(CliTaskRegistry::get('nothing:here'));
        $this->assertNull(CliTaskRegistry::get(''));

        // and a real one is found
        $this->assertNotNull(CliTaskRegistry::get('user:show'));
    }

    /**
     * Test what a registered command carries
     *
     * @testdox A registered command carries everything the help needs
     */
    public function testRegisteredCommandCarriesEverythingTheHelpNeeds(): void
    {
        $task = CliTaskRegistry::get('maintenance:mode');

        $this->assertNotNull($task);
        $this->assertEquals('maintenance:mode', $task['name']);
        $this->assertNotEmpty($task['description']);
        $this->assertIsCallable($task['callback']);

        // the shape the help and the completion are built from
        foreach (array('arguments', 'options', 'examples') as $key) {
            $this->assertArrayHasKey($key, $task);
            $this->assertIsArray($task[$key]);
        }

        // a core command is marked as such, so a module cannot claim the name
        $this->assertTrue($task['core']);
    }

    /**
     * Test that the commands are named consistently
     *
     * @testdox Every command is named after the area it belongs to
     */
    public function testEveryCommandIsNamedAfterTheAreaItBelongsTo(): void
    {
        $names = array_keys(CliTaskRegistry::getAll());

        // these describe the command line or the installation itself and carry no area
        $withoutArea = array('help', 'list', 'completion', 'status', 'version');

        foreach ($names as $name) {
            if (in_array($name, $withoutArea, true)) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9-]+:[a-z0-9-]+$/',
                $name,
                'The command "' . $name . '" does not follow the area:action naming.'
            );
        }
    }

    /**
     * Test that the catalogue can be described
     *
     * @testdox The registry can describe the whole catalogue for the help
     */
    public function testRegistryCanDescribeTheWholeCatalogue(): void
    {
        $documentation = CliTaskRegistry::getDocumentation();

        $this->assertNotEmpty($documentation);

        // every command that can be run is described, so no command is undocumented
        $this->assertEquals(count(CliTaskRegistry::getAll()), count($documentation));
    }
}
