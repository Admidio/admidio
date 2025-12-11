<?php
namespace Admidio\Hooks;

use Psr\EventDispatcher\StoppableEventInterface;

final class GenericFilterEvent implements StoppableEventInterface
{
    private bool $stopped = false;

    /**
     * @param list<mixed> $args Additional context args after $value
     */
    public function __construct(
        private string $name,
        private mixed $value,
        private array $args = []
    ) {}

    public function name(): string { return $this->name; }
    public function get(): mixed { return $this->value; }
    public function set(mixed $value): void { $this->value = $value; }
    /** @return list<mixed> */
    public function args(): array { return $this->args; }

    public function stopPropagation(): void { $this->stopped = true; }
    public function isPropagationStopped(): bool { return $this->stopped; }
}
