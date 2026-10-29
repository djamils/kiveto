<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Factory;

use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\ClientLinkEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * Links between an owner account and a clinic client: confirmed by default,
 * `revoked()` once the access was withdrawn. Dates are UTC.
 *
 * @extends PersistentProxyObjectFactory<ClientLinkEntity>
 */
final class ClientLinkEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ClientLinkEntity::class;
    }

    public function between(string $userId, string $clinicId, string $clientId): self
    {
        return $this->with([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId),
            'clientId' => Uuid::fromString($clientId),
        ]);
    }

    public function linkedAt(\DateTimeImmutable $linkedAt): self
    {
        return $this->with(['linkedAt' => $linkedAt->setTimezone(new \DateTimeZone('UTC'))]);
    }

    public function revoked(\DateTimeImmutable $revokedAt): self
    {
        return $this->with(['status' => 'revoked', 'revokedAt' => $revokedAt->setTimezone(new \DateTimeZone('UTC'))]);
    }

    protected function defaults(): array|callable
    {
        return [
            'id'        => Uuid::v7(),
            'userId'    => Uuid::v7(),
            'clinicId'  => Uuid::v7(),
            'clientId'  => Uuid::v7(),
            'status'    => 'confirmed',
            'linkedAt'  => new \DateTimeImmutable('-1 month', new \DateTimeZone('UTC')),
            'revokedAt' => null,
        ];
    }
}
