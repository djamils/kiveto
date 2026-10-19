<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Test-only decorator of the external-call bus: forwards every command (so the
 * outbox row is still written) and keeps it, so a test can assert what was queued.
 */
final class RecordingExternalApiCommandBus implements ExternalApiCommandBusInterface
{
    /** @var list<ExternalApiCommandInterface> */
    private array $sent = [];

    public function __construct(private readonly ExternalApiCommandBusInterface $inner)
    {
    }

    public function send(ExternalApiCommandInterface $command, object ...$stamps): void
    {
        $this->sent[] = $command;

        $this->inner->send($command, ...$stamps);
    }

    public function reset(): void
    {
        $this->sent = [];
    }

    /**
     * @template T of ExternalApiCommandInterface
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    public function recorded(string $class): array
    {
        return array_values(array_filter($this->sent, static fn (ExternalApiCommandInterface $command): bool => $command instanceof $class));
    }
}
