<?php
/**
 * Maintenance jobs of core, modules and plugins
 *
 * `admidio maintenance:run` runs every registered job, so that one crontab entry covers the
 * recurring work of an installation. Modules and plugins register their jobs like their commands,
 * in their own namespace.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Cli\CliTaskRegistry;
use Admidio\Infrastructure\Cli\CoreTasks;
use Admidio\Tests\Support\AdmidioTestCase;
use InvalidArgumentException;

class MaintenanceJobRegistryTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (CliTaskRegistry::get('user:show') === null) {
            CoreTasks::register();
        }
    }

    /**
     * @testdox The core maintenance jobs are registered in their order of execution
     */
    public function testCoreJobsAreRegistered(): void
    {
        $jobs = array_filter(CliTaskRegistry::getMaintenanceJobs(), static fn (array $job): bool => $job['core']);

        $this->assertSame(
            array('categories', 'documents', 'sessions', 'auto-logins', 'sso-tokens', 'saml-metadata', 'update-check', 'memberships'),
            array_keys($jobs)
        );
        foreach ($jobs as $job) {
            $this->assertNotSame('', $job['description']);
            $this->assertTrue(is_callable($job['callback']));
        }
    }

    /**
     * @testdox maintenance:run and maintenance:jobs are commands, and --job is checked against the registered jobs
     */
    public function testMaintenanceCommandsExist(): void
    {
        $run = CliTaskRegistry::get('maintenance:run');
        $this->assertNotNull($run);
        $this->assertNotNull(CliTaskRegistry::get('maintenance:jobs'));

        $jobOption = array_values(array_filter($run['options'], static fn (array $option): bool => $option['name'] === 'job'))[0];
        $this->assertArrayNotHasKey('values', $jobOption, 'Extension jobs are registered after the command, so the option cannot list them.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--job expects one of: categories');
        CoreTasks::maintenanceRun(array(), array('job' => array('no-such-job')));
    }

    /**
     * @testdox A plugin registers a maintenance job in its own namespace
     */
    public function testAPluginRegistersAJobInItsNamespace(): void
    {
        CliTaskRegistry::setPluginContext('job-fixture');
        try {
            if (!isset(CliTaskRegistry::getMaintenanceJobs()['job-fixture:cleanup'])) {
                CliTaskRegistry::registerMaintenanceJob('job-fixture:cleanup', static fn (): string => 'done', 'Fixture job.');
            }
        } finally {
            CliTaskRegistry::setPluginContext(null);
        }

        $job = CliTaskRegistry::getMaintenanceJobs()['job-fixture:cleanup'];
        $this->assertFalse($job['core']);
        $this->assertSame('done', ($job['callback'])());

        // core jobs run first
        $names = array_keys(CliTaskRegistry::getMaintenanceJobs());
        $this->assertLessThan(array_search('job-fixture:cleanup', $names, true), array_search('memberships', $names, true));
    }

    /**
     * @testdox A plugin cannot register a job outside of its namespace
     */
    public function testAPluginCannotUseAnotherNamespace(): void
    {
        CliTaskRegistry::setPluginContext('job-fixture');
        try {
            $this->expectException(InvalidArgumentException::class);
            CliTaskRegistry::registerMaintenanceJob('other:cleanup', static fn (): null => null, 'Foreign job.');
        } finally {
            CliTaskRegistry::setPluginContext(null);
        }
    }

    /**
     * @testdox An extension job needs a namespace and must not take a core namespace
     */
    public function testExtensionJobNamesAreChecked(): void
    {
        try {
            CliTaskRegistry::registerMaintenanceJob('cleanup', static fn (): null => null, 'No namespace.');
            $this->fail('A job without namespace was accepted.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        CliTaskRegistry::registerMaintenanceJob('maintenance:cleanup', static fn (): null => null, 'Core namespace.');
    }

    /**
     * @testdox A job name can be registered only once
     */
    public function testAJobCannotBeRegisteredTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CliTaskRegistry::registerCoreMaintenanceJob('categories', static fn (): null => null, 'Duplicate.');
    }
}
