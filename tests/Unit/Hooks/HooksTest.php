<?php
/**
 * The Hooks engine itself: registration, removal, priority order, the dispatch snapshot, the veto
 * by exception and the three primitives (actions, filters, resolvers). No database or entity is
 * involved, only Admidio\Hooks\Hooks.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use InvalidArgumentException;
use RuntimeException;

require_once __DIR__ . '/Support/functions.php';

class HooksTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    public static function probeStaticListener(): void
    {
        $GLOBALS['adm_hooks_test_calls'][] = 'ProbeC::m';
    }

    private function calls(): array
    {
        return $GLOBALS['adm_hooks_test_calls'] ?? array();
    }

    private function resetCalls(): void
    {
        $GLOBALS['adm_hooks_test_calls'] = array();
    }

    /**
     * @testdox removeAction() by the function name removes a registration made by that name
     */
    public function testRemoveActionByFunctionName(): void
    {
        $this->resetCalls();
        Hooks::addAction('t1', 'admHooksTestProbeListener');
        $removed = Hooks::removeAction('t1', 'admHooksTestProbeListener');
        Hooks::doAction('t1');

        $this->assertTrue($removed);
        $this->assertSame(array(), $this->calls());
    }

    /**
     * @testdox removeAction() by an explicit id equal to the function name still removes it
     */
    public function testRemoveActionByExplicitIdEqualToFunctionName(): void
    {
        $this->resetCalls();
        Hooks::addAction('t1b', 'admHooksTestProbeListener', 10, null, 'admHooksTestProbeListener');
        $removed = Hooks::removeAction('t1b', 'admHooksTestProbeListener');
        Hooks::doAction('t1b');

        $this->assertTrue($removed);
        $this->assertSame(array(), $this->calls());
    }

    /**
     * removal by an explicit id that is not also the name of a function. This is the ordinary shape
     * of an id, and the case above cannot catch a regression in it: the listener is a real function,
     * so it is removed by the callback key even when the id key is never reached.
     *
     * @testdox removeAction() by an explicit id that names no function removes it
     */
    public function testRemoveActionByExplicitIdThatNamesNoFunction(): void
    {
        $this->resetCalls();
        Hooks::addAction('t1c', 'admHooksTestProbeListener', 10, null, 'my-scanner');
        $removed = Hooks::removeAction('t1c', 'my-scanner');
        Hooks::doAction('t1c');

        $this->assertTrue($removed);
        $this->assertSame(array(), $this->calls());
    }

    /**
     * @testdox removeAction() accepts the string form of a static method
     */
    public function testRemoveActionByStringFormOfStaticMethod(): void
    {
        $this->resetCalls();
        Hooks::addAction('t2', array(self::class, 'probeStaticListener'));
        $removed = Hooks::removeAction('t2', self::class . '::probeStaticListener');
        Hooks::doAction('t2');

        $this->assertTrue($removed);
        $this->assertSame(array(), $this->calls());
    }

    /**
     * @testdox re-registering an explicit id replaces it, even across priorities
     */
    public function testExplicitIdReplacesAcrossPriorities(): void
    {
        $this->resetCalls();
        Hooks::addAction('t3', function () { $this->recordCall('A'); }, 10, null, 'myid');
        Hooks::addAction('t3', function () { $this->recordCall('B'); }, 5, null, 'myid');
        Hooks::doAction('t3');

        $this->assertSame(array('B'), $this->calls());
    }

    private function recordCall(string $value): void
    {
        $GLOBALS['adm_hooks_test_calls'][] = $value;
    }

    /**
     * @testdox a filter can veto an operation by throwing
     */
    public function testFilterCanVetoByThrowing(): void
    {
        Hooks::addFilter('t4', function () {
            throw new RuntimeException('veto');
        });

        $this->expectException(RuntimeException::class);
        Hooks::applyFilters('t4', 'orig');
    }

    /**
     * @testdox an action can veto an operation by throwing
     */
    public function testActionCanVetoByThrowing(): void
    {
        Hooks::addAction('t5', function () {
            throw new RuntimeException('veto');
        });

        $this->expectException(RuntimeException::class);
        Hooks::doAction('t5');
    }

    /**
     * @testdox doActionCatchErrors() swallows a failing callback and still runs the rest
     */
    public function testDoActionCatchErrorsSwallowsAndContinues(): void
    {
        $this->resetCalls();
        Hooks::addAction('t5b', function () {
            throw new RuntimeException('boom');
        }, 5);
        Hooks::addAction('t5b', function () { $this->recordCall('cleanup'); }, 10);

        Hooks::doActionCatchErrors('t5b');

        $this->assertSame(array('cleanup'), $this->calls());
    }

    /**
     * @testdox registrations run in priority order, stable within one priority
     */
    public function testPriorityOrderIsStableWithinOnePriority(): void
    {
        $this->resetCalls();
        Hooks::addAction('t7', function () { $this->recordCall('1'); }, 10);
        Hooks::addAction('t7', function () { $this->recordCall('2'); }, 10);
        Hooks::addAction('t7', function () { $this->recordCall('0'); }, 1);

        Hooks::doAction('t7');

        $this->assertSame(array('0', '1', '2'), $this->calls());
    }

    /**
     * @testdox acceptedArgs truncates the arguments a callback receives
     */
    public function testAcceptedArgsTruncatesExtraArguments(): void
    {
        Hooks::addFilter('t8', function ($v) { return $v . '!'; }, 10, 1);

        $this->assertSame('a!', Hooks::applyFilters('t8', 'a', 'x', 'y'));
    }

    /**
     * @testdox acceptedArgs=0 is rejected for a filter, which must accept at least the value
     */
    public function testAcceptedArgsZeroIsRejectedForAFilter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Hooks::addFilter('t8z', fn($v) => $v, 10, 0);
    }

    /**
     * @testdox a dispatch uses a snapshot of the registrations, not the live list
     */
    public function testDispatchUsesASnapshot(): void
    {
        $this->resetCalls();
        Hooks::addAction('t9', function () {
            $this->recordCall('first');
            Hooks::addAction('t9', function () { $this->recordCall('late'); }, 20);
        }, 10);

        Hooks::doAction('t9');
        $this->assertSame(array('first'), $this->calls());

        $this->resetCalls();
        Hooks::doAction('t9');
        $this->assertSame(array('first', 'late'), $this->calls());
    }

    /**
     * @testdox a resolver returns the first non-null answer
     */
    public function testResolverReturnsFirstNonNullAnswer(): void
    {
        Hooks::addResolver('r1', fn() => null, 5);
        Hooks::addResolver('r1', fn() => 'answer', 10);
        Hooks::addResolver('r1', fn() => 'never', 20);

        $this->assertSame('answer', Hooks::resolve('r1', 'default'));
    }

    /**
     * @testdox a resolver falls back to the default when nobody answers
     */
    public function testResolverFallsBackToDefault(): void
    {
        $this->assertSame('default', Hooks::resolve('r2', 'default'));
    }

    /**
     * @testdox false, 0, an empty string and an empty array are answers, not "no answer"
     */
    public function testResolverAcceptsFalsyAnswers(): void
    {
        foreach (array(false, 0, '', array()) as $falsy) {
            Hooks::reset('r3');
            Hooks::addResolver('r3', fn() => $falsy, 5);
            Hooks::addResolver('r3', fn() => 'later', 10);

            $this->assertSame($falsy, Hooks::resolve('r3', 'default'), var_export($falsy, true));
        }
    }

    /**
     * @testdox a resolver receives the dispatch arguments
     */
    public function testResolverReceivesDispatchArguments(): void
    {
        Hooks::addResolver('r4', fn($a, $b) => $a . $b, 10, 2);

        $this->assertSame('xy', Hooks::resolve('r4', 'default', 'x', 'y', 'z'));
    }

    /**
     * @testdox hasAction() reports whether a hook has a registration, before and after removal
     */
    public function testHasActionReflectsRegistrationState(): void
    {
        $this->assertFalse(Hooks::hasAction('nope'));

        Hooks::addAction('t10', 'admHooksTestProbeListener');
        $this->assertTrue(Hooks::hasAction('t10'));

        Hooks::removeAction('t10', 'admHooksTestProbeListener');
        $this->assertFalse(Hooks::hasAction('t10'));
    }

    /**
     * @testdox reset() without a name clears the whole registry
     */
    public function testResetWithoutNameClearsWholeRegistry(): void
    {
        Hooks::addFilter('t11', fn($v) => $v);
        Hooks::addAction('t3', 'admHooksTestProbeListener');

        Hooks::reset();

        $this->assertFalse(Hooks::hasFilter('t11'));
        $this->assertFalse(Hooks::hasAction('t3'));
    }
}
