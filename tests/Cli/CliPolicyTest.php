<?php
/**
 * Tests of the command-line configuration, adm_my_files/cli-config.php.
 *
 * The file decides whether the command line may be used at all, by which system account, for which
 * command and with which option defaults. Every one of those answers is a refusal or a value that
 * nobody typed, so they are asserted here rather than left to the commands that observe them.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Cli\CliPolicy;
use Admidio\Infrastructure\Cli\CliSystemAccount;
use Admidio\Tests\Support\AdmidioTestCase;

class CliPolicyTest extends AdmidioTestCase
{
    /**
     * The configuration file of the checkout must not decide what these tests observe.
     */
    protected function tearDown(): void
    {
        CliPolicy::useForTests(null);
        CliSystemAccount::useForTests(null);

        foreach (array('ADMIDIO_FORMAT', 'ADMIDIO_AS', 'ADMIDIO_ORGANIZATION', 'ADMIDIO_NO_DEFAULTS') as $variable) {
            putenv($variable);
        }

        parent::tearDown();
    }

    /**
     * A policy as the configuration file defines one.
     *
     * @param array<string,mixed> $values
     */
    private function policy(array $values = array()): CliPolicy
    {
        return CliPolicy::fromValues($values, '/opt/admidio/adm_my_files/cli-config.php');
    }

    /**
     * The registration of a command, as far as the defaults are concerned.
     *
     * @param array<int,array<string,mixed>> $options
     * @return array<string,mixed>
     */
    private function task(bool $actorRequired = false, array $options = array(), bool $supportsDryRun = false): array
    {
        return array(
            'name' => 'user:edit',
            'actorRequired' => $actorRequired,
            'supportsDryRun' => $supportsDryRun,
            'options' => $options
        );
    }

    /**
     * @testdox Without a configuration file every command except the ones describing the CLI is refused
     */
    public function testWithoutConfigurationFileEveryRestrictedCommandIsRefused(): void
    {
        $policy = CliPolicy::read(__DIR__ . '/does-not-exist');

        $this->assertFalse($policy->exists());
        $this->assertFalse($policy->isEnabled());
        $this->assertStringContainsString('is disabled', (string) $policy->denialReason('user:edit'));

        foreach (array('', 'help', 'list', 'version', 'completion', 'cli:defaultconfig') as $command) {
            $this->assertNull(
                $policy->denialReason($command),
                'The command "' . $command . '" only describes the command line and must stay available.'
            );
        }
    }

    /**
     * @testdox A configuration file that does not enable the command line refuses a command
     */
    public function testDisabledConfigurationRefusesACommand(): void
    {
        $reason = $this->policy(array('enabled' => false))->denialReason('user:edit');

        $this->assertStringContainsString('disabled', (string) $reason);
        $this->assertStringContainsString('cli-config.php', (string) $reason);
    }

    /**
     * @testdox The installation commands run while the installation has no configuration file
     */
    public function testInstallationCommandsRunWithoutAnInstallation(): void
    {
        $policy = $this->policy(array('enabled' => false));

        $this->assertNull($policy->denialReason('install:run', __DIR__ . '/no-config.php'));
        // as soon as the installation exists, its commands are restricted like every other one
        $this->assertNotNull($policy->denialReason('install:run', __FILE__));
    }

    /**
     * @testdox A system account is addressed by name, by id, by group name and by group id
     */
    public function testSystemAccountIsAddressedByNameIdAndGroup(): void
    {
        $account = CliSystemAccount::fromValues('reinhold', 1001, array(), array('admidio-cli', 'staff'), array(2000, 50));

        $this->assertTrue($account->matches('reinhold'));
        $this->assertTrue($account->matches('REINHOLD'), 'Account names are compared without regard to case.');
        $this->assertTrue($account->matches(' reinhold '), 'Surrounding whitespace of an entry is irrelevant.');
        $this->assertTrue($account->matches('1001'));
        $this->assertTrue($account->matches('%admidio-cli'));
        $this->assertTrue($account->matches('% staff'));
        $this->assertTrue($account->matches('%2000'));

        $this->assertFalse($account->matches('www-data'));
        $this->assertFalse($account->matches('1002'));
        $this->assertFalse($account->matches('%wheel'));
        $this->assertFalse($account->matches('%1001'), 'A user id is no group id.');
        $this->assertFalse($account->matches('2000'), 'A group id is no user id.');
        $this->assertFalse($account->matches(''));
        $this->assertFalse($account->matches('%'));
    }

    /**
     * @testdox An account that is not listed may not use the command line
     */
    public function testAccountThatIsNotListedIsRefused(): void
    {
        CliSystemAccount::useForTests(CliSystemAccount::fromValues('www-data', 33));
        $policy = $this->policy(array('enabled' => true, 'allowedUsers' => 'reinhold, %admidio-cli'));

        $reason = (string) $policy->denialReason('user:edit');
        $this->assertStringContainsString('www-data', $reason);
        $this->assertStringContainsString('$gCliAllowedUsers', $reason);

        CliSystemAccount::useForTests(CliSystemAccount::fromValues('reinhold', 1001));
        $this->assertNull($policy->denialReason('user:edit'));
    }

    /**
     * @testdox An unknown system account is refused with its own explanation
     */
    public function testUnknownSystemAccountIsRefusedWithItsOwnExplanation(): void
    {
        CliSystemAccount::useForTests(CliSystemAccount::fromValues('', null, array(), array(), array(), ''));

        $policy = $this->policy(array('enabled' => true, 'allowedUsers' => 'reinhold'));
        $this->assertStringContainsString('could not be determined', (string) $policy->denialReason('user:edit'));

        // without a restriction the account is never needed, so it is never missed either
        $this->assertNull($this->policy(array('enabled' => true))->denialReason('user:edit'));
    }

    /**
     * @testdox Commands are allowed and denied by shell pattern, and the denial wins
     */
    public function testCommandsAreAllowedAndDeniedByPattern(): void
    {
        $policy = $this->policy(array(
            'enabled' => true,
            'allowedCommands' => 'maintenance:*, user:list',
            'deniedCommands' => 'maintenance:mode'
        ));

        $this->assertNull($policy->denialReason('maintenance:run'));
        $this->assertNull($policy->denialReason('user:list'));
        $this->assertNotNull($policy->denialReason('user:edit'));
        $this->assertNotNull(
            $policy->denialReason('maintenance:mode'),
            'The deny list is checked first, so it wins over a pattern that allows the command.'
        );

        $withoutAllowList = $this->policy(array('enabled' => true, 'deniedCommands' => 'database:backup'));
        $this->assertNull($withoutAllowList->denialReason('user:edit'));
        $this->assertNotNull($withoutAllowList->denialReason('database:backup'));
    }

    /**
     * @testdox Only the configured accounts may be assumed with --as
     */
    public function testOnlyConfiguredAccountsMayBeAssumed(): void
    {
        $policy = $this->policy(array('enabled' => true, 'allowedActors' => 'cronjob, 42'));

        $this->assertTrue($policy->allowsActor(array('cronjob', 'uuid-1', '7')));
        $this->assertTrue($policy->allowsActor(array('other', 'uuid-2', '42')), 'The id addresses the account as well.');
        $this->assertFalse($policy->allowsActor(array('administrator', 'uuid-3', '7')));

        $this->assertTrue(
            $this->policy(array('enabled' => true))->allowsActor(array('administrator', 'uuid-3', '7')),
            'Without the setting every valid account may be used.'
        );
    }

    /**
     * @testdox An option that was not given is filled in from the configuration file
     */
    public function testOptionIsFilledInFromTheConfigurationFile(): void
    {
        $policy = $this->policy(array(
            'enabled' => true,
            'defaults' => array('format' => 'json', 'organization' => 'example')
        ));

        $options = $policy->applyDefaults('user:list', $this->task(), array('organization' => 'other'));

        $this->assertSame('json', $options['format']);
        $this->assertSame('other', $options['organization'], 'The command line wins over the configuration file.');
        $this->assertSame(array('format'), $policy->defaultedOptions());
    }

    /**
     * @testdox The defaults of a command win over the global ones, and the environment wins over both
     */
    public function testCommandDefaultsAndEnvironmentPrecedence(): void
    {
        $policy = $this->policy(array(
            'enabled' => true,
            'defaults' => array(
                'format' => 'json',
                'user:list' => array('format' => 'csv')
            )
        ));

        $this->assertSame('csv', $policy->applyDefaults('user:list', $this->task(), array())['format']);
        $this->assertSame('json', $policy->applyDefaults('group:list', $this->task(), array())['format']);

        putenv('ADMIDIO_FORMAT=table');
        $this->assertSame(
            'table',
            $policy->applyDefaults('user:list', $this->task(), array())['format'],
            'A single invocation overrides the configuration file through the environment.'
        );
    }

    /**
     * @testdox A confirmation of a destructive operation cannot be given by the configuration file
     */
    public function testConfirmationCannotBeDefaulted(): void
    {
        $policy = $this->policy(array(
            'enabled' => true,
            'defaults' => array('yes' => true, 'overwrite' => true, 'quiet' => true)
        ));

        $options = $policy->applyDefaults(
            'user:delete',
            $this->task(false, array(array('name' => 'yes', 'flag' => true))),
            array()
        );

        $this->assertArrayNotHasKey('yes', $options);
        $this->assertArrayNotHasKey('overwrite', $options);
        $this->assertTrue($options['quiet'], 'A flag that only affects the output may be configured.');
    }

    /**
     * @testdox A default acting user only reaches the commands that use one
     */
    public function testDefaultActingUserOnlyReachesCommandsThatUseOne(): void
    {
        $policy = $this->policy(array('enabled' => true, 'defaults' => array('as' => 'reinhold')));

        $this->assertSame('reinhold', $policy->applyDefaults('user:edit', $this->task(true), array())['as']);
        $this->assertArrayNotHasKey(
            'as',
            $policy->applyDefaults('version', $this->task(false), array()),
            'A command without an acting user rejects --as, so it must not be given one.'
        );
    }

    /**
     * @testdox A rejected value names the configuration file it came from
     */
    public function testRejectedValueNamesTheConfigurationFile(): void
    {
        $policy = $this->policy(array('enabled' => true, 'defaults' => array('format' => 'yaml')));
        $policy->applyDefaults('user:list', $this->task(), array());

        $this->assertStringContainsString(
            'cli-config.php',
            $policy->defaultsHint('--format expects one of: text, json.')
        );
        $this->assertSame(
            '',
            $policy->defaultsHint('Missing required argument USER.'),
            'An error about something else must not point at the configuration file.'
        );
    }

    /**
     * @testdox One invocation can be run without the defaults of the configuration file
     */
    public function testOneInvocationCanBeRunWithoutTheConfiguredDefaults(): void
    {
        $policy = $this->policy(array(
            'enabled' => true,
            'defaults' => array('as' => 'reinhold', 'format' => 'json')
        ));

        putenv('ADMIDIO_NO_DEFAULTS=1');
        $options = $policy->applyDefaults('user:edit', $this->task(true), array());
        $this->assertSame(array(), $options);

        putenv('ADMIDIO_AS=cronjob');
        $this->assertSame(
            'cronjob',
            $policy->applyDefaults('user:edit', $this->task(true), array())['as'],
            'The environment is not a default of the file and still applies.'
        );
    }

    /**
     * @testdox A relative log file belongs to the data directory of the installation
     */
    public function testRelativeLogFileBelongsToTheDataDirectory(): void
    {
        $relative = CliPolicy::fromValues(
            array('enabled' => true, 'logFile' => 'logs/admidio-cli.log'),
            '/opt/admidio/adm_my_files/cli-config.php',
            '/opt/admidio/adm_my_files'
        );
        $this->assertSame('/opt/admidio/adm_my_files/logs/admidio-cli.log', $relative->logFilePath());

        $absolute = CliPolicy::fromValues(
            array('enabled' => true, 'logFile' => '/var/log/admidio/cli.log'),
            '/opt/admidio/adm_my_files/cli-config.php',
            '/opt/admidio/adm_my_files'
        );
        $this->assertSame('/var/log/admidio/cli.log', $absolute->logFilePath());

        $this->assertSame('', $this->policy(array('enabled' => true))->logFilePath());
    }
}
