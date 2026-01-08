<?php
// src/Admidio/Hooks/Events.php
namespace Admidio\Hooks;

use Psr\EventDispatcher\StoppableEventInterface;

final class GenericActionEvent implements StoppableEventInterface
{
    private bool $stopped = false;

    public function __construct(
        private string $name,
        /** @var list<mixed> */
        private array $args = []
    ) {}

    public function name(): string { return $this->name; }
    /** @return list<mixed> */
    public function args(): array { return $this->args; }

    public function stopPropagation(): void { $this->stopped = true; }
    public function isPropagationStopped(): bool { return $this->stopped; }
}
