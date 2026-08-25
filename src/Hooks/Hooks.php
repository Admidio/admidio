<?php
namespace Admidio\Hooks;

use InvalidArgumentException;

/**
 * Generic extension points of Admidio. Three primitives are offered:
 *
 * - **Actions** notify that something is happening or has happened. Every callback is called, the
 *   return values are ignored. A callback may throw to abort the operation, if the hook documents
 *   that it may.
 * - **Filters** transform a value. Every callback receives the result of the previous one, so the
 *   value passes through the whole chain.
 * - **Resolvers** ask for one answer. The callbacks are asked in priority order until one returns
 *   something other than **null**; if none does, the default of the caller is returned. **false**,
 *   **0**, empty string and empty array are answers and end the resolution.
 *
 * A lower priority runs earlier, registrations of the same priority run in registration order.
 *
 * A callback may be registered with an explicit ID, which is unique within one hook regardless of
 * the priority: registering the same ID again replaces the previous registration. Without an ID the
 * callback identifies itself, so that it can be removed again without remembering an ID.
 *
 * Exceptions of a callback are logged and rethrown. A hook is an extension point of the operation
 * that dispatches it, so a failing extension has to fail that operation instead of leaving it half
 * done. Only the dispatch sites of failure and diagnostic hooks use doActionCatchErrors(), so that
 * a failing diagnostic cannot mask the failure it reports.
 *
 * **Code example**
 * ```
 * // transform a value
 * Hooks::addFilter('entity_value', function (mixed $value, string $column) {
 *     return ($column === 'usd_value') ? trim($value) : $value;
 * });
 * $value = Hooks::applyFilters('entity_value', $value, $columnName);
 *
 * // observe an event
 * Hooks::addAction('user_created', array($myPlugin, 'onUserCreated'), 20);
 * Hooks::doAction('user_created', $changeSet);
 *
 * // ask for one answer
 * Hooks::addResolver('translation_missing', 'myTranslationProvider');
 * $text = Hooks::resolve('translation_missing', $referenceText, $textId, $language);
 * ```
 */
final class Hooks
{
    public const TYPE_ACTION = 'action';
    public const TYPE_FILTER = 'filter';
    public const TYPE_RESOLVER = 'resolver';

    public const DEFAULT_PRIORITY = 10;

    /**
     * All registrations, as $registrations[$type][$hookName][$priority][$key], where $key is the
     * explicit ID or the identity of the callback. One registration holds the callback, the number
     * of arguments it accepts and the sequence number that keeps the registration order of one
     * priority.
     * @var array<string, array<string, array<int, array<string, array>>>>
     */
    private static array $registrations = array(
        self::TYPE_ACTION => array(),
        self::TYPE_FILTER => array(),
        self::TYPE_RESOLVER => array()
    );

    /**
     * Counter that gives every registration its sequence number.
     * @var int
     */
    private static int $sequence = 0;

    /**
     * The class only offers static methods and must not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Register a callback that is notified when the action is dispatched. The return value of the
     * callback is ignored.
     * @param string $name Name of the hook, e.g. **user_created**.
     * @param callable $callback The callback that should be called.
     * @param int $priority Lower priorities run earlier.
     * @param int|null $acceptedArgs Number of arguments the callback should receive. If **null**,
     *                               it receives all arguments of the dispatch.
     * @param string|null $id Explicit registration ID. Registering the same ID again replaces the
     *                        previous registration of this hook.
     * @return void
     */
    public static function addAction(string $name, callable $callback, int $priority = self::DEFAULT_PRIORITY, ?int $acceptedArgs = null, ?string $id = null): void
    {
        self::register(self::TYPE_ACTION, $name, $callback, $priority, $acceptedArgs, $id);
    }

    /**
     * Register a callback that transforms the filtered value. It receives the value as its first
     * argument and has to return the value that the next callback receives.
     * @param string $name Name of the hook, e.g. **entity_value**.
     * @param callable $callback The callback that should be called.
     * @param int $priority Lower priorities run earlier.
     * @param int|null $acceptedArgs Number of arguments the callback should receive, the filtered
     *                               value included. If **null**, it receives all of them.
     * @param string|null $id Explicit registration ID.
     * @return void
     */
    public static function addFilter(string $name, callable $callback, int $priority = self::DEFAULT_PRIORITY, ?int $acceptedArgs = null, ?string $id = null): void
    {
        if ($acceptedArgs !== null && $acceptedArgs < 1) {
            throw new InvalidArgumentException('A filter callback of the hook "' . $name . '" must accept at least the filtered value.');
        }

        self::register(self::TYPE_FILTER, $name, $callback, $priority, $acceptedArgs, $id);
    }

    /**
     * Register a callback that is asked for the answer of the hook. It returns **null** if it has
     * no answer, so that the next callback is asked, or the answer otherwise.
     * @param string $name Name of the hook, e.g. **translation_missing**.
     * @param callable $callback The callback that should be called.
     * @param int $priority Lower priorities are asked earlier.
     * @param int|null $acceptedArgs Number of arguments the callback should receive. If **null**,
     *                               it receives all arguments of the dispatch.
     * @param string|null $id Explicit registration ID.
     * @return void
     */
    public static function addResolver(string $name, callable $callback, int $priority = self::DEFAULT_PRIORITY, ?int $acceptedArgs = null, ?string $id = null): void
    {
        self::register(self::TYPE_RESOLVER, $name, $callback, $priority, $acceptedArgs, $id);
    }

    /**
     * Remove a registration of an action, by its explicit ID or by the callback that was registered.
     * @param string $name Name of the hook.
     * @param string|callable $idOrCallback The explicit registration ID or the registered callback.
     * @return bool Returns **true** if a registration was removed.
     */
    public static function removeAction(string $name, string|callable $idOrCallback): bool
    {
        return self::remove(self::TYPE_ACTION, $name, $idOrCallback);
    }

    /**
     * Remove a registration of a filter, by its explicit ID or by the callback that was registered.
     * @param string $name Name of the hook.
     * @param string|callable $idOrCallback The explicit registration ID or the registered callback.
     * @return bool Returns **true** if a registration was removed.
     */
    public static function removeFilter(string $name, string|callable $idOrCallback): bool
    {
        return self::remove(self::TYPE_FILTER, $name, $idOrCallback);
    }

    /**
     * Remove a registration of a resolver, by its explicit ID or by the callback that was registered.
     * @param string $name Name of the hook.
     * @param string|callable $idOrCallback The explicit registration ID or the registered callback.
     * @return bool Returns **true** if a registration was removed.
     */
    public static function removeResolver(string $name, string|callable $idOrCallback): bool
    {
        return self::remove(self::TYPE_RESOLVER, $name, $idOrCallback);
    }

    /**
     * Check whether at least one callback is registered for the action. A dispatch site uses this
     * before it builds a context that it would not build otherwise.
     * @param string $name Name of the hook.
     * @return bool Returns **true** if at least one callback is registered.
     */
    public static function hasAction(string $name): bool
    {
        return !empty(self::$registrations[self::TYPE_ACTION][$name]);
    }

    /**
     * Check whether at least one callback is registered for the filter.
     * @param string $name Name of the hook.
     * @return bool Returns **true** if at least one callback is registered.
     */
    public static function hasFilter(string $name): bool
    {
        return !empty(self::$registrations[self::TYPE_FILTER][$name]);
    }

    /**
     * Check whether at least one callback is registered for the resolver.
     * @param string $name Name of the hook.
     * @return bool Returns **true** if at least one callback is registered.
     */
    public static function hasResolver(string $name): bool
    {
        return !empty(self::$registrations[self::TYPE_RESOLVER][$name]);
    }

    /**
     * Dispatch an action to all registered callbacks. The return values are ignored, an exception
     * of a callback is logged and rethrown, so that it aborts the operation that dispatched it.
     * @param string $name Name of the hook.
     * @param mixed ...$args The arguments that the callbacks receive.
     * @return void
     */
    public static function doAction(string $name, mixed ...$args): void
    {
        foreach (self::getRegistrations(self::TYPE_ACTION, $name) as $registration) {
            self::call($registration, $args);
        }
    }

    /**
     * Dispatch an action to all registered callbacks and log the exception of a callback instead of
     * rethrowing it. Only the dispatch sites of failure and diagnostic hooks use this, where the
     * original failure has to stay the one that Admidio reports and the remaining callbacks still
     * have to get their chance to clean up.
     * @param string $name Name of the hook.
     * @param mixed ...$args The arguments that the callbacks receive.
     * @return void
     */
    public static function doActionCatchErrors(string $name, mixed ...$args): void
    {
        foreach (self::getRegistrations(self::TYPE_ACTION, $name) as $registration) {
            try {
                self::call($registration, $args);
            } catch (\Throwable) {
                // already logged in call(), the dispatch site must not be disturbed any further
            }
        }
    }

    /**
     * Pass a value through all registered callbacks of the filter. Every callback receives the
     * result of the previous one as its first argument.
     * @param string $name Name of the hook.
     * @param mixed $value The value that should be filtered.
     * @param mixed ...$args Further arguments that the callbacks receive after the value.
     * @return mixed Returns the value after the last callback.
     */
    public static function applyFilters(string $name, mixed $value, mixed ...$args): mixed
    {
        foreach (self::getRegistrations(self::TYPE_FILTER, $name) as $registration) {
            $value = self::call($registration, array($value, ...$args));
        }

        return $value;
    }

    /**
     * Ask the registered callbacks of the resolver for an answer until one of them has one.
     * @param string $name Name of the hook.
     * @param mixed $default The value that is returned if no callback has an answer.
     * @param mixed ...$args The arguments that the callbacks receive.
     * @return mixed Returns the first answer that is not **null**, otherwise the default.
     */
    public static function resolve(string $name, mixed $default = null, mixed ...$args): mixed
    {
        foreach (self::getRegistrations(self::TYPE_RESOLVER, $name) as $registration) {
            $answer = self::call($registration, $args);

            if ($answer !== null) {
                return $answer;
            }
        }

        return $default;
    }

    /**
     * Remove all registrations. The registry is static and therefore outlives one test case or one
     * iteration of a long-running CLI process.
     * @param string $name Name of the hook that should be cleared. If empty, everything is cleared.
     * @return void
     */
    public static function reset(string $name = ''): void
    {
        foreach (array_keys(self::$registrations) as $type) {
            if ($name === '') {
                self::$registrations[$type] = array();
            } else {
                unset(self::$registrations[$type][$name]);
            }
        }
    }

    /**
     * Store a registration and drop a previous registration of the same key, also if that one was
     * registered with another priority.
     * @param string $type One of the TYPE_... constants.
     * @param string $name Name of the hook.
     * @param callable $callback The callback that should be called.
     * @param int $priority Lower priorities run earlier.
     * @param int|null $acceptedArgs Number of arguments the callback should receive.
     * @param string|null $id Explicit registration ID.
     * @return void
     */
    private static function register(string $type, string $name, callable $callback, int $priority, ?int $acceptedArgs, ?string $id): void
    {
        if ($acceptedArgs !== null && $acceptedArgs < 0) {
            throw new InvalidArgumentException('The number of accepted arguments must not be negative.');
        }

        $key = ($id === null) ? self::callableId($callback) : 'id:' . $id;

        // An ID is unique within the hook and not within one priority, so a registration that moves
        // to another priority must not leave its previous registration behind.
        self::removeKey($type, $name, $key);

        self::$registrations[$type][$name][$priority][$key] = array(
            'callback' => $callback,
            'acceptedArgs' => $acceptedArgs,
            'sequence' => ++self::$sequence,
            'name' => $name,
            'key' => $key
        );
    }

    /**
     * Remove a registration by its explicit ID or by the callback that was registered. A string is
     * ambiguous, because the name of a function is both a callback and a possible ID, so both are
     * tried.
     * @param string $type One of the TYPE_... constants.
     * @param string $name Name of the hook.
     * @param string|callable $idOrCallback The explicit registration ID or the registered callback.
     * @return bool Returns **true** if a registration was removed.
     */
    private static function remove(string $type, string $name, string|callable $idOrCallback): bool
    {
        if (is_string($idOrCallback)) {
            $keys = array('id:' . $idOrCallback, self::callableId($idOrCallback));
        } else {
            $keys = array(self::callableId($idOrCallback));
        }

        $removed = false;
        foreach ($keys as $key) {
            $removed = self::removeKey($type, $name, $key) || $removed;
        }

        return $removed;
    }

    /**
     * Remove the registration of one key from every priority of the hook.
     * @param string $type One of the TYPE_... constants.
     * @param string $name Name of the hook.
     * @param string $key The registration key.
     * @return bool Returns **true** if a registration was removed.
     */
    private static function removeKey(string $type, string $name, string $key): bool
    {
        if (!isset(self::$registrations[$type][$name])) {
            return false;
        }

        $removed = false;

        foreach (self::$registrations[$type][$name] as $priority => $registrations) {
            if (array_key_exists($key, $registrations)) {
                unset(self::$registrations[$type][$name][$priority][$key]);
                $removed = true;
            }

            if (empty(self::$registrations[$type][$name][$priority])) {
                unset(self::$registrations[$type][$name][$priority]);
            }
        }

        if (empty(self::$registrations[$type][$name])) {
            unset(self::$registrations[$type][$name]);
        }

        return $removed;
    }

    /**
     * All registrations of a hook in the order in which they have to be called. The returned array
     * is a snapshot: a callback that registers or removes a callback of the same hook changes the
     * following dispatches and not the one that is running.
     * @param string $type One of the TYPE_... constants.
     * @param string $name Name of the hook.
     * @return array<int,array> Returns the registrations, ordered by priority and registration order.
     */
    private static function getRegistrations(string $type, string $name): array
    {
        if (empty(self::$registrations[$type][$name])) {
            return array();
        }

        $byPriority = self::$registrations[$type][$name];
        ksort($byPriority, SORT_NUMERIC);

        $ordered = array();
        foreach ($byPriority as $registrations) {
            uasort($registrations, function (array $left, array $right) {
                return $left['sequence'] <=> $right['sequence'];
            });

            foreach ($registrations as $registration) {
                $ordered[] = $registration;
            }
        }

        return $ordered;
    }

    /**
     * Call one registered callback. An exception is logged with the hook it happened in and
     * rethrown, so that the operation that dispatched the hook fails instead of continuing with a
     * half-applied extension.
     * @param array $registration The registration.
     * @param array $args The arguments of the dispatch.
     * @return mixed Returns the return value of the callback.
     */
    private static function call(array $registration, array $args): mixed
    {
        global $gLogger;

        if ($registration['acceptedArgs'] !== null) {
            $args = array_slice($args, 0, $registration['acceptedArgs']);
        }

        try {
            return ($registration['callback'])(...$args);
        } catch (\Throwable $exception) {
            if ($gLogger instanceof \Psr\Log\LoggerInterface) {
                $gLogger->error('Hook listener error', array(
                    'hook' => $registration['name'],
                    'listener' => $registration['key'],
                    'exception' => $exception
                ));
            }
            throw $exception;
        }
    }

    /**
     * The identity of a callback, so that it can be removed again without an explicit registration
     * ID. It is stable as long as the callback is registered, because the registry holds the object
     * or the closure itself and it can therefore not be freed and its handle not be reused.
     * @param callable $callback The callback.
     * @return string Returns the identity of the callback.
     */
    private static function callableId(callable $callback): string
    {
        if ($callback instanceof \Closure) {
            return 'closure:' . spl_object_hash($callback);
        }

        if (is_array($callback) && count($callback) === 2) {
            list($target, $method) = $callback;
            if (is_object($target)) {
                return 'obj:' . spl_object_hash($target) . '::' . (string)$method;
            }
            return 'cls:' . (string)$target . '::' . (string)$method;
        }

        if (is_string($callback)) {
            // the string form of a static method is the same callback as its array form
            if (str_contains($callback, '::')) {
                return 'cls:' . $callback;
            }
            return 'fn:' . $callback;
        }

        if (is_object($callback)) {
            return 'inv:' . spl_object_hash($callback);
        }

        return 'cb:' . md5(serialize($callback));
    }
}
