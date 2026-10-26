<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformCode;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnection;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionId;
use App\System\ElectronicInvoicing\Domain\Connection\PlatformConnectionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Exception\PlatformConnectionNotFoundException;

final class InMemoryConnections implements PlatformConnectionRepositoryInterface
{
    /** @var array<string, PlatformConnection> */
    public array $items = [];

    public static function verified(string $siren, string $id = '01960000-0000-7000-8000-0000000ec001'): self
    {
        $repository = new self();
        $connection = PlatformConnection::open(PlatformConnectionId::fromString($id), Siren::fromInput($siren), PlatformCode::fromString('simulator'), new \DateTimeImmutable('2026-10-21 09:00'));
        $connection->markVerified(new \DateTimeImmutable('2026-10-21 09:00'));
        $repository->save($connection);

        return $repository;
    }

    public function save(PlatformConnection $connection): void
    {
        $this->items[$connection->id()->toString()] = $connection;
    }

    public function get(PlatformConnectionId $id): PlatformConnection
    {
        return $this->items[$id->toString()] ?? throw new PlatformConnectionNotFoundException($id->toString());
    }

    public function byOrganisation(Siren $organisationId): ?PlatformConnection
    {
        foreach ($this->items as $item) {
            if ($item->organisationId()->equals($organisationId)) {
                return $item;
            }
        }

        return null;
    }

    public function polled(): array
    {
        return array_values(array_filter($this->items, static fn (PlatformConnection $c): bool => $c->isPolled()));
    }
}
