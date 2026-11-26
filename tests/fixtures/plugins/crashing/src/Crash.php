<?php
/**
 * The code of a plugin that goes wrong in a way the loader's catch does not see: the tests run it in
 * a process of their own, because it ends that process.
 */

namespace AdmidioPlugin\Crashing;

final class Crash
{
    /** Throw a TypeError that nothing catches, which PHP reports as a fatal error. */
    public static function failUncaught(): void
    {
        $strict = static function (int $number): int {
            return $number;
        };
        $strict('not a number');
    }
}
