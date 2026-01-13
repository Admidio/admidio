<?php
// src/Admidio/Hooks/HookListenerProvider.php
namespace Admidio\Hooks;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Stores closures keyed by hook name and priority; yields callables for matching events.
 */
final class HookListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];
    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    public function addAction(string $name, callable $listener, int $priority = 10): void
    {
        $this->actions[$name][$priority][] = $listener;
    }

    public function addFilter(string $name, callable $listener, int $priority = 10): void
    {
        $this->filters[$name][$priority][] = $listener;
    }

    public function removeAction(string $name, callable $listener, ?int $priority = null): bool
    {
        return $this->removeFrom($this->actions, $name, $listener, $priority);
    }

    public function removeFilter(string $name, callable $listener, ?int $priority = null): bool
    {
        return $this->removeFrom($this->filters, $name, $listener, $priority);
    }

    public function hasAction(string $name): bool
    {
        return !empty($this->actions[$name]);
    }

    public function hasFilter(string $name): bool
    {
        return !empty($this->filters[$name]);
    }

    public function getListenersForEvent(object $event): iterable
    {
        if ($event instanceof GenericActionEvent) {
            $name = $event->name();
            yield from $this->sorted($this->actions[$name] ?? []);
        } elseif ($event instanceof GenericFilterEvent) {
            $name = $event->name();
            yield from $this->sorted($this->filters[$name] ?? []);
        }
    }

    /** @param array<int, list<callable>> $byPriority */
    private function sorted(array $byPriority): iterable
    {
        if (!$byPriority) {
            return;
        }
        // WordPress semantics: lower priority runs earlier.
        ksort($byPriority, SORT_NUMERIC);
        foreach ($byPriority as $listeners) {
            foreach ($listeners as $l) {
                yield $l;
            }
        }
    }

    /**
     * @param array<string, array<int, list<callable>>> $bucket
     */
    private function removeFrom(array &$bucket, string $name, callable $listener, ?int $priority): bool
    {
        if (!isset($bucket[$name])) {
            return false;
        }
        $found = false;

        $scan = $priority !== null ? [$priority => ($bucket[$name][$priority] ?? [])] : $bucket[$name];

        foreach ($scan as $prio => $list) {
            foreach ($list as $idx => $l) {
                // Strict comparison won't work for all callables; fallback to string form when possible.
                if ($l === $listener || (is_object($l) && is_object($listener) && spl_object_hash($l) === spl_object_hash($listener))) {
                    unset($bucket[$name][$prio][$idx]);
                    $found = true;
                }
            }
            if (isset($bucket[$name][$prio]) && empty($bucket[$name][$prio])) {
                unset($bucket[$name][$prio]);
            }
        }
        if (empty($bucket[$name])) {
            unset($bucket[$name]);
        }
        return $found;
    }
}
