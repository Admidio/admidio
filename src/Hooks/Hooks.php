<?php
// src/Hooks/Hooks.php
namespace Admidio\Hooks;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Admidio\Hooks\Events;

/**
 * Developer-facing facade: add_action, add_filter, do_action, apply_filters, remove_*, has_*.
 */
final class Hooks
{
    private static ?self $instance = null;

    private function __construct(
        private HookListenerProvider $provider,
        private EventDispatcherInterface $dispatcher,
        private ?LoggerInterface $logger = null
    ) {}

    public static function boot(?LoggerInterface $logger = null): void
    {
        // Idempotent boot
        if (self::$instance instanceof self) {
            return;
        }
        $provider = new HookListenerProvider();
        $dispatcher = new EventDispatcher($provider);
        self::$instance = new self($provider, $dispatcher, $logger);
    }

    /** Allow DI if you have a container */
    public static function with(HookListenerProvider $provider, EventDispatcherInterface $dispatcher, ?LoggerInterface $logger = null): void
    {
        self::$instance = new self($provider, $dispatcher, $logger);
    }

    private static function i(): self
    {
        if (!self::$instance) {
            self::boot();
        }
        return self::$instance;
    }

    // ---------- Registration ----------

    /**
     * WordPress-like signature.
     * @param callable $callback function(...$args): void
     */
    public static function add_action(string $name, callable $callback, int $priority = 10, ?int $accepted_args = null): void
    {
        $i = self::i();
        $listener = function (GenericActionEvent $e) use ($callback, $accepted_args, $i): void {
            try {
                $args = $e->args();
                if ($accepted_args !== null) {
                    $args = array_slice($args, 0, max(0, $accepted_args));
                }
                $callback(...$args);
            } catch (\Throwable $t) {
                $i->logger?->error('Action listener error', [
                    'hook' => $e->name(),
                    'exception' => $t,
                ]);
                // Actions swallow exceptions by default; mark as stopped only if desired:
                // $e->stopPropagation();
            }
        };
        $i->provider->addAction($name, $listener, $priority);
    }

    /**
     * @param callable $callback function($value, ...$args): mixed
     */
    public static function add_filter(string $name, callable $callback, int $priority = 10, ?int $accepted_args = null): void
    {
        $i = self::i();
        $listener = function (GenericFilterEvent $e) use ($callback, $accepted_args, $i): void {
            try {
                $args = [$e->get(), ...$e->args()];
                if ($accepted_args !== null) {
                    $args = array_slice($args, 0, max(0, $accepted_args));
                }
                $result = $callback(...$args);
                $e->set($result);
            } catch (\Throwable $t) {
                $i->logger?->error('Filter listener error', [
                    'hook' => $e->name(),
                    'exception' => $t,
                ]);
                // On failure, keep current value and continue.
            }
        };
        $i->provider->addFilter($name, $listener, $priority);
    }

    public static function remove_action(string $name, callable $callback, ?int $priority = null): bool
    {
        return self::i()->provider->removeAction($name, $callback, $priority);
    }

    public static function remove_filter(string $name, callable $callback, ?int $priority = null): bool
    {
        return self::i()->provider->removeFilter($name, $callback, $priority);
    }

    public static function has_action(string $name): bool
    {
        return self::i()->provider->hasAction($name);
    }

    public static function has_filter(string $name): bool
    {
        return self::i()->provider->hasFilter($name);
    }

    // ---------- Dispatch ----------

    /**
     * do_action('user_created', $user, $actorId)
     */
    public static function do_action(string $name, mixed ...$args): void
    {
        $i = self::i();
        $event = new GenericActionEvent($name, $args);
        $i->dispatcher->dispatch($event);
    }

    /**
     * $subject = apply_filters('mail_subject', $subject, $context);
     */
    public static function apply_filters(string $name, mixed $value, mixed ...$args): mixed
    {
        $i = self::i();
        $event = new GenericFilterEvent($name, $value, $args);
        /** @var GenericFilterEvent $out */
        $out = $i->dispatcher->dispatch($event);
        return $out->get();
    }

    // ---------- Optional: short-circuit helpers ----------

    /**
     * Stop remaining listeners for an ongoing action/filter by name.
     * Only meaningful when called inside a listener; provided for convenience.
     */
    public static function stop_propagation(GenericActionEvent|GenericFilterEvent $event): void
    {
        $event->stopPropagation();
    }
}
