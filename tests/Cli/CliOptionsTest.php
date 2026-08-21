<?php
/**
 * CLI Option Tests
 *
 * Tests the helpers every command line task reads its input through. They are the CLI counterpart
 * of admFuncVariableIsValid(): a task never touches the parsed arrays itself but asks for a string,
 * an integer or a flag and gets a usable value or an error.
 */

namespace Admidio\Tests\Cli;

use Admidio\Infrastructure\Cli\CliApplication;
use Admidio\Tests\Support\DatabaseTestCase;
use InvalidArgumentException;

class CliOptionsTest extends DatabaseTestCase
{
    /**
     * A parsed command line, the way CliApplication hands it to a task.
     *
     * @return array<string,mixed>
     */
    private function options(): array
    {
        return array(
            'format' => 'json',
            'limit' => '25',
            'ratio' => '1.5',
            'role' => array('board', 'members'),
            'force' => true
        );
    }

    /**
     * Test that a text option is read with a fallback
     *
     * @testdox A text option is returned as given or replaced by its default
     */
    public function testTextOptionIsReturnedAsGivenOrReplacedByItsDefault(): void
    {
        $options = $this->options();

        $this->assertEquals('json', CliApplication::optionString($options, 'format'));
        $this->assertEquals('', CliApplication::optionString($options, 'missing'));
        $this->assertEquals('text', CliApplication::optionString($options, 'missing', 'text'));

        // a flag that was given counts as the string 1
        $this->assertEquals('1', CliApplication::optionString($options, 'force'));

        // an option that was repeated is answered with the last value
        $this->assertEquals('members', CliApplication::optionString($options, 'role'));
    }

    /**
     * Test that a repeated option is collected
     *
     * @testdox An option that may be repeated is returned as a list
     */
    public function testRepeatedOptionIsReturnedAsAList(): void
    {
        $options = $this->options();

        $this->assertEquals(array('board', 'members'), CliApplication::optionValues($options, 'role'));

        // a single value is returned as a list of one, so a task never has to check
        $this->assertEquals(array('json'), CliApplication::optionValues($options, 'format'));
        $this->assertEquals(array(), CliApplication::optionValues($options, 'missing'));
    }

    /**
     * Test that presence can be asked for
     *
     * @testdox Whether an option was given at all can be asked separately
     */
    public function testWhetherAnOptionWasGivenCanBeAskedSeparately(): void
    {
        $options = $this->options();

        $this->assertTrue(CliApplication::optionExists($options, 'limit'));
        $this->assertTrue(CliApplication::optionExists($options, 'force'));
        $this->assertFalse(CliApplication::optionExists($options, 'missing'));
    }

    /**
     * Test that numbers are typed
     *
     * @testdox A numeric option is returned as a number
     */
    public function testNumericOptionIsReturnedAsANumber(): void
    {
        $options = $this->options();

        $this->assertSame(25, CliApplication::optionInt($options, 'limit'));
        $this->assertSame(1.5, CliApplication::optionFloat($options, 'ratio'));

        // a default is only used when the option is missing
        $this->assertSame(7, CliApplication::optionInt($options, 'missing', 7));
        $this->assertNull(CliApplication::optionInt($options, 'missing'));
    }

    /**
     * Test that a number that is not one is refused
     *
     * @testdox An option that should be a number refuses text
     */
    public function testOptionThatShouldBeANumberRefusesText(): void
    {
        // unlike the request parameters of the web interface, the CLI reports the mistake
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('--limit expects an integer.');
        CliApplication::optionInt(array('limit' => 'abc'), 'limit');
    }

    /**
     * Test that a flag is read as a decision
     *
     * @testdox A flag is answered as a decision and keeps its default when it is absent
     */
    public function testFlagIsAnsweredAsADecision(): void
    {
        $options = $this->options();

        $this->assertTrue(CliApplication::optionBool($options, 'force'));
        $this->assertFalse(CliApplication::optionBool($options, 'missing', false));
        $this->assertTrue(CliApplication::optionBool($options, 'missing', true));

        // without a default an absent flag is undecided rather than false
        $this->assertNull(CliApplication::optionBool($options, 'missing'));
    }

    /**
     * Test that a missing argument is reported
     *
     * @testdox A required argument that was not given is reported by its name
     */
    public function testRequiredArgumentThatWasNotGivenIsReportedByItsName(): void
    {
        $this->assertEquals('second', CliApplication::requireArgument(array('first', 'second'), 1, 'name'));

        try {
            CliApplication::requireArgument(array('first'), 1, 'name');
            $this->fail('A missing argument must be reported.');
        } catch (InvalidArgumentException $e) {
            // the message names the argument so that the user knows what to add
            $this->assertStringContainsString('NAME', $e->getMessage());
        }
    }

    /**
     * Test that a date is checked before it is used
     *
     * @testdox A date and time option has to be given in the expected format
     */
    public function testDateAndTimeOptionHasToBeGivenInTheExpectedFormat(): void
    {
        $this->assertEquals('2030-07-01 09:00:00', CliApplication::validateDateTime('2030-07-01 09:00:00'));
        $this->assertEquals('2030-07-01 09:00:00', CliApplication::validateDateTime('2030-07-01T09:00'));

        foreach (array('not a date', '2030-07-01', '01.07.2030 09:00') as $wrong) {
            $refused = false;
            try {
                CliApplication::validateDateTime($wrong, 'start');
            } catch (InvalidArgumentException $e) {
                $refused = true;
                $this->assertStringContainsString('start', $e->getMessage());
            }
            $this->assertTrue($refused, '"' . $wrong . '" must be refused as a date and time.');
        }
    }

    /**
     * Test that the exit codes are distinct
     *
     * @testdox The command line reports its outcome through distinct exit codes
     */
    public function testCommandLineReportsItsOutcomeThroughDistinctExitCodes(): void
    {
        $codes = array(
            CliApplication::EXIT_SUCCESS,
            CliApplication::EXIT_ERROR,
            CliApplication::EXIT_USAGE,
            CliApplication::EXIT_STATE_NOT_OK,
            CliApplication::EXIT_UPDATE_AVAILABLE,
            CliApplication::EXIT_REJECTED,
            CliApplication::EXIT_FAILED
        );

        // a script that reads the exit code must be able to tell the outcomes apart
        $this->assertEquals(count($codes), count(array_unique($codes)));

        // and success is the usual zero
        $this->assertEquals(0, CliApplication::EXIT_SUCCESS);
    }

    /**
     * Test that the global options are known
     *
     * @testdox The options that every command understands are published
     */
    public function testOptionsThatEveryCommandUnderstandsArePublished(): void
    {
        $global = CliApplication::globalOptionNames();

        // the ones that decide who acts, on what and how the answer is written
        foreach (array('organization', 'as', 'format', 'output', 'help') as $expected) {
            $this->assertContains($expected, $global);
        }

        // the ones that decide which installation and which configuration file are addressed
        $this->assertContains('config', $global);
        $this->assertContains('host', $global);

        // and the ones that make a command usable from a script
        $this->assertContains('quiet', $global);
        $this->assertContains('no-interaction', $global);
        $this->assertContains('yes', $global);
    }
}
