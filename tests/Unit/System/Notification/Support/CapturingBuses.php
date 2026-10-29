<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;

/**
 * Buses that only remember what was sent or published through them.
 */
final class CapturingBuses implements CommandBusInterface, ExternalApiCommandBusInterface, EventBusInterface, IntegrationEventBusInterface
{
    /** @var list<object> */
    public array $commands = [];

    /** @var list<array{ExternalApiCommandInterface, list<object>}> */
    public array $external = [];

    /** @var list<object> */
    public array $events = [];

    /** @var (\Closure(object): mixed)|null */
    public ?\Closure $onDispatch = null;

    public function dispatch(object $command, object ...$stamps): mixed
    {
        $this->commands[] = $command;

        return null !== $this->onDispatch ? ($this->onDispatch)($command) : null;
    }

    public function send(ExternalApiCommandInterface $command, object ...$stamps): void
    {
        $this->external[] = [$command, array_values($stamps)];
    }

    public function publish(array $stamps, object ...$events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    public function publisher(): DomainEventPublisher
    {
        return new DomainEventPublisher($this);
    }

    public function integrationPublisher(): IntegrationEventPublisher
    {
        return new IntegrationEventPublisher($this);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function eventsOf(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $e): bool => $e instanceof $class));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function commandsOf(string $class): array
    {
        return array_values(array_filter($this->commands, static fn (object $c): bool => $c instanceof $class));
    }
}
