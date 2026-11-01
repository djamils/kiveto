<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;

final class InMemoryClientLinks implements ClientLinkRepositoryInterface
{
    /** @var array<string, ClientLink> by id */
    public array $links = [];

    public int $saves = 0;

    public function save(ClientLink $link): void
    {
        $this->links[$link->id()->toString()] = $link;
        ++$this->saves;
    }

    public function findForUpdate(string $userId, string $clinicId, string $clientId): ?ClientLink
    {
        foreach ($this->links as $link) {
            if ($link->userId() === $userId && $link->clinicId() === $clinicId && $link->clientId() === $clientId) {
                return $link;
            }
        }

        return null;
    }

    public function findByClient(string $clinicId, string $clientId): array
    {
        return array_values(array_filter($this->links, static fn (ClientLink $l): bool => $l->clinicId() === $clinicId && $l->clientId() === $clientId));
    }

    public function findConfirmedByUser(string $userId): array
    {
        return array_values(array_filter($this->links, static fn (ClientLink $l): bool => $l->userId() === $userId && $l->isConfirmed()));
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->links, static fn (ClientLink $l): bool => $l->userId() === $userId));
    }

    public function hasConfirmed(string $userId, string $clinicId): bool
    {
        foreach ($this->findConfirmedByUser($userId) as $link) {
            if ($link->clinicId() === $clinicId) {
                return true;
            }
        }

        return false;
    }
}
