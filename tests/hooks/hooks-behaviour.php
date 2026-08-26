<?php
require __DIR__ . '/../../src/Hooks/Hooks.php';
use Admidio\Hooks\Hooks;

$fails = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $fails; if (!$ok) { $fails++; }
    printf("%-6s %s%s\n", $ok ? '  ok' : 'FAIL', $name, $detail !== '' ? '  -> ' . $detail : '');
}
function calls(): array { return $GLOBALS['c'] ?? []; }
function reset_calls(): void { $GLOBALS['c'] = []; }

function probeListener(...$a) { $GLOBALS['c'][] = 'probeListener'; }
class ProbeC { public static function m(...$a) { $GLOBALS['c'][] = 'ProbeC::m'; } }

// 1 removal by function name
reset_calls();
Hooks::addAction('t1', 'probeListener');
$r = Hooks::removeAction('t1', 'probeListener');
Hooks::doAction('t1');
check('removeAction by function name', $r === true && calls() === []);

// 1b removal by explicit id
reset_calls();
Hooks::addAction('t1b', 'probeListener', 10, null, 'probeListener');
$r = Hooks::removeAction('t1b', 'probeListener');
Hooks::doAction('t1b');
check('removeAction by an explicit id equal to the function name', $r === true && calls() === []);

// 2 removal by [Class,method] and by its string form
reset_calls();
Hooks::addAction('t2', ['ProbeC', 'm']);
$r = Hooks::removeAction('t2', 'ProbeC::m');
Hooks::doAction('t2');
check('removeAction by the string form of a static method', $r === true && calls() === []);

// 3 explicit id replaces across priorities
reset_calls();
Hooks::addAction('t3', function () { $GLOBALS['c'][] = 'A'; }, 10, null, 'myid');
Hooks::addAction('t3', function () { $GLOBALS['c'][] = 'B'; }, 5, null, 'myid');
Hooks::doAction('t3');
check('re-registering an explicit id replaces it across priorities', calls() === ['B'], implode(',', calls()));

// 4 filter veto
Hooks::addFilter('t4', function ($v) { throw new RuntimeException('veto'); });
$thrown = false; $out = null;
try { $out = Hooks::applyFilters('t4', 'orig'); } catch (Throwable $e) { $thrown = true; }
check('a filter can veto by throwing', $thrown, var_export($out, true));

// 5 action veto
Hooks::addAction('t5', function () { throw new RuntimeException('veto'); });
$thrown = false;
try { Hooks::doAction('t5'); } catch (Throwable $e) { $thrown = true; }
check('an action can veto by throwing', $thrown);

// 5b failure dispatch does not propagate and still runs the rest
reset_calls();
Hooks::addAction('t5b', function () { throw new RuntimeException('boom'); }, 5);
Hooks::addAction('t5b', function () { $GLOBALS['c'][] = 'cleanup'; }, 10);
$thrown = false;
try { Hooks::doActionCatchErrors('t5b'); } catch (Throwable $e) { $thrown = true; }
check('doActionCatchErrors swallows and continues', !$thrown && calls() === ['cleanup'], implode(',', calls()));

// 6 order
reset_calls();
Hooks::addAction('t7', function () { $GLOBALS['c'][] = '1'; }, 10);
Hooks::addAction('t7', function () { $GLOBALS['c'][] = '2'; }, 10);
Hooks::addAction('t7', function () { $GLOBALS['c'][] = '0'; }, 1);
Hooks::doAction('t7');
check('priority order, stable within a priority', calls() === ['0', '1', '2'], implode(',', calls()));

// 7 acceptedArgs
Hooks::addFilter('t8', function ($v) { return $v . '!'; }, 10, 1);
check('acceptedArgs=1 truncates the extra arguments', Hooks::applyFilters('t8', 'a', 'x', 'y') === 'a!');
$thrown = false;
try { Hooks::addFilter('t8z', fn($v) => $v, 10, 0); } catch (InvalidArgumentException $e) { $thrown = true; }
check('acceptedArgs=0 on a filter is rejected', $thrown);

// 8 snapshot
reset_calls();
Hooks::addAction('t9', function () {
    $GLOBALS['c'][] = 'first';
    Hooks::addAction('t9', function () { $GLOBALS['c'][] = 'late'; }, 20);
}, 10);
Hooks::doAction('t9');
check('dispatch uses a snapshot', calls() === ['first'], implode(',', calls()));
reset_calls();
Hooks::doAction('t9');
check('the added listener runs on the next dispatch', calls() === ['first', 'late'], implode(',', calls()));

// 9 resolver
Hooks::addResolver('r1', fn() => null, 5);
Hooks::addResolver('r1', fn() => 'answer', 10);
Hooks::addResolver('r1', fn() => 'never', 20);
check('resolver returns the first non-null answer', Hooks::resolve('r1', 'default') === 'answer');
check('resolver falls back to the default', Hooks::resolve('r2', 'default') === 'default');
foreach ([false, 0, '', []] as $falsy) {
    Hooks::reset('r3');
    Hooks::addResolver('r3', fn() => $falsy, 5);
    Hooks::addResolver('r3', fn() => 'later', 10);
    check('resolver accepts ' . var_export($falsy, true) . ' as an answer', Hooks::resolve('r3', 'default') === $falsy);
}
Hooks::reset('r4');
Hooks::addResolver('r4', fn($a, $b) => $a . $b, 10, 2);
check('resolver receives the dispatch arguments', Hooks::resolve('r4', 'default', 'x', 'y', 'z') === 'xy');

// 10 has*/reset
check('hasAction is false for an unknown hook', Hooks::hasAction('nope') === false);
Hooks::addAction('t10', 'probeListener');
check('hasAction is true after a registration', Hooks::hasAction('t10') === true);
Hooks::removeAction('t10', 'probeListener');
check('hasAction is false after the last removal', Hooks::hasAction('t10') === false);
Hooks::addFilter('t11', fn($v) => $v);
Hooks::reset();
check('reset clears the whole registry', Hooks::hasFilter('t11') === false && Hooks::hasAction('t3') === false);

echo $fails === 0 ? "\nall checks passed\n" : "\n$fails checks failed\n";
exit($fails === 0 ? 0 : 1);
