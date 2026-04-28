<?php
namespace Admidio\Hooks;

/**
 *  WordPress-like hooks system for Admidio.
 *
 * Features:
 * - add_action / do_action
 * - add_filter / apply_filters
 * - remove_action / remove_filter
 * - has_action / has_filter
 *
 * Notes:
 * - Supports optional $id to overwrite/remove by ID later.
 * - Lower $priority runs earlier (WordPress semantics).
 */
final class Hooks
{
    private static ?self $instance = null;

    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];
    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    private function __construct() {}

    private static function i(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ---------- Registration ----------

    /**
     * WordPress-like signature.
     * @param callable $callback function(...$args): void
     */
    public static function add_action(
        string $name,
        callable $callback,
        int $priority = 10,
        ?int $accepted_args = null,
        ?string $id = null
    ): void {
        $i = self::i();

        $wrapper = function (array $args) use ($callback, $accepted_args, $name): void {
            try {
                if ($accepted_args !== null) {
                    $args = array_slice($args, 0, max(0, $accepted_args));
                }
                $callback(...$args);
            } catch (\Throwable $t) {
                global $gLogger;
                $gLogger?->error('Action listener error', [
                    'hook' => $name,
                    'exception' => $t,
                ]);
            }
        };

        $key = $id ?? self::callableId($callback);
        $i->actions[$name][$priority][$key] = $wrapper;
    }


    /**
     * @param callable $callback function($value, ...$args): mixed
     */
    public static function add_filter(
        string $name,
        callable $callback,
        int $priority = 10,
        ?int $accepted_args = null,
        ?string $id = null
    ): void {
        $i = self::i();

        $wrapper = function (mixed $value, array $args) use ($callback, $accepted_args, $name): mixed {
            try {
                $callArgs = [$value, ...$args];
                if ($accepted_args !== null) {
                    $callArgs = array_slice($callArgs, 0, max(0, $accepted_args));
                }
                return $callback(...$callArgs);
            } catch (\Throwable $t) {
                global $gLogger;
                $gLogger?->error('Filter listener error', [
                    'hook' => $name,
                    'exception' => $t,
                ]);
                return $value; // keep current value on failure
            }
        };

        $key = $id ?? self::callableId($callback);
        $i->filters[$name][$priority][$key] = $wrapper;
    }

        /**
     * Remove by $id (preferred) or by $callback identity.
     * If $priority is null, scans all priorities.
     */
    public static function remove_action(string $name, string|callable $idOrCallback, ?int $priority = null): bool
    {
        $i = self::i();
        return self::removeFrom($i->actions, $name, $idOrCallback, $priority);
    }
        
    /**
     * Remove by $id (preferred) or by $callback identity.
     * If $priority is null, scans all priorities.
     */
    public static function remove_filter(string $name, string|callable $idOrCallback, ?int $priority = null): bool
    {
        $i = self::i();
        return self::removeFrom($i->filters, $name, $idOrCallback, $priority);
    }

    /**
     * @param array<string, array<int, array<string, callable>>> $bucket
     */
    private static function removeFrom(array &$bucket, string $name, string|callable $idOrCallback, ?int $priority = null): bool
    {
        if (!isset($bucket[$name])) {
            return false;
        }

        $needleId = is_string($idOrCallback) ? $idOrCallback : self::callableId($idOrCallback);
        $found = false;

        $priorities = $priority !== null ? [$priority] : array_keys($bucket[$name]);

        foreach ($priorities as $prio) {
            if (!isset($bucket[$name][$prio])) {
                continue;
            }

            if (isset($bucket[$name][$prio][$needleId])) {
                unset($bucket[$name][$prio][$needleId]);
                $found = true;
            }

            if (empty($bucket[$name][$prio])) {
                unset($bucket[$name][$prio]);
            }
        }

        if (empty($bucket[$name])) {
            unset($bucket[$name]);
        }

        return $found;
    }


    public static function has_action(string $name): bool
    {
        return !empty(self::i()->actions[$name]);
    }

    public static function has_filter(string $name): bool
    {
        return !empty(self::i()->filters[$name]);
    }

    // ---------- Dispatch ----------

    /**
     * do_action('user_created', $user, $actorId)
     */
    public static function do_action(string $name, mixed ...$args): void
    {
        $i = self::i();
        foreach ($i->getListeners($i->actions, $name) as $listener) {
            // $listener is the wrapper created in add_action
            $listener($args);
        }
    }

    /**
     * $subject = apply_filters('mail_subject', $subject, $context);
     */
    public static function apply_filters(string $name, mixed $value, mixed ...$args): mixed
    {
        $i = self::i();
        foreach ($i->getListeners($i->filters, $name) as $filter) {
            // $filter is the wrapper created in add_filter
            $value = $filter($value, $args);
        }
        return $value;
    }


    /**
     * @param array<string, array<int, array<string, callable>>> $bucket
     * @return iterable<callable>
     */
    private function getListeners(array $bucket, string $name): iterable
    {
        $byPriority = $bucket[$name] ?? [];
        if (!$byPriority) {
            return;
        }

        // WordPress semantics: lower priority runs earlier.
        ksort($byPriority, SORT_NUMERIC);

        foreach ($byPriority as $listenersById) {
            foreach ($listenersById as $listener) {
                yield $listener;
            }
        }
    }

    
    // ---------- Helpers ----------

    /**
     * Creates a mostly-stable ID for a callable for overwrite/remove without explicitly passing $id.
     * Not guaranteed unique across processes; good enough for request-lifetime hooks.
     */
    private static function callableId(callable $cb): string
    {
        // Closure
        if ($cb instanceof \Closure) {
            return 'closure:' . spl_object_hash($cb);
        }

        // ['Class', 'method'] or [$object, 'method']
        if (is_array($cb) && count($cb) === 2) {
            [$t, $m] = $cb;
            if (is_object($t)) {
                return 'obj:' . spl_object_hash($t) . '::' . (string)$m;
            }
            return 'cls:' . (string)$t . '::' . (string)$m;
        }

        // 'function_name'
        if (is_string($cb)) {
            return 'fn:' . $cb;
        }

        // invokable object
        if (is_object($cb) && method_exists($cb, '__invoke')) {
            return 'inv:' . spl_object_hash($cb);
        }

        // last resort
        return 'cb:' . md5(serialize($cb));
    }
}