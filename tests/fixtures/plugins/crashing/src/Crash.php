<?php
/**
 * The code of a plugin that goes wrong. Every Throwable is created here, so that its file is a file
 * of the plugin, and call() puts a stack frame of the plugin between Admidio and a failure.
 */

namespace AdmidioPlugin\Crashing;

final class Crash
{
    /** A PHP Error, as a plugin that uses an Admidio API the wrong way produces it. */
    public static function error(): \Error
    {
        return new \TypeError('A plugin passed the wrong type.');
    }

    /** An application exception, which is the hook policy's business and not a crash. */
    public static function exception(): \Exception
    {
        return new \RuntimeException('The plugin refused the operation.');
    }

    /** Run a callback from inside the plugin, like a hook callback that calls back into Admidio. */
    public static function call(callable $callback): mixed
    {
        return $callback();
    }

    /** Throw a TypeError that nothing catches. */
    public static function failUncaught(): void
    {
        $strict = static function (int $number): int {
            return $number;
        };
        $strict('not a number');
    }
}
