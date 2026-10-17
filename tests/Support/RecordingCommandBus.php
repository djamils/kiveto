<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Bus\CommandBusInterface;

/**
 * Test-only decorator of the command bus: delegates every command and keeps
 * it, so a test can assert what an entry point dispatched.
 */
final class RecordingCommandBus implements CommandBusInterface
{
    /** @var list<object> */
    private array $dispatched = [];

    public function __construct(private readonly CommandBusInterface $inner)
    {
    }

    public function dispatch(object $command, object ...$stamps): mixed
    {
        $this->dispatched[] = $command;

        return $this->inner->dispatch($command, ...$stamps);
    }

    public function reset(): void
    {
        $this->dispatched = [];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function recorded(string $class): array
    {
        return array_values(array_filter($this->dispatched, static fn (object $command): bool => $command instanceof $class));
    }
}
