<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Factory;

use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Entity\PortalInvitationEntity;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * Portal invitations of a clinic client. Dates are UTC (the repository reads
 * them so); the token is given in clear and stored as its sha256, as
 * OpaqueTokenHasher does, so that a demo link can be written down.
 *
 * @extends PersistentProxyObjectFactory<PortalInvitationEntity>
 */
final class PortalInvitationEntityFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return PortalInvitationEntity::class;
    }

    public function forClient(string $clinicId, string $clientId, string $phone, ?string $email): self
    {
        return $this->with([
            'clinicId' => Uuid::fromString($clinicId),
            'clientId' => Uuid::fromString($clientId),
            'phone'    => $phone,
            'email'    => $email,
        ]);
    }

    /** Sent at that time, valid 7 days. */
    public function sentAt(\DateTimeImmutable $sentAt): self
    {
        $utc = $sentAt->setTimezone(new \DateTimeZone('UTC'));

        return $this->with(['sentAt' => $utc, 'expiresAt' => $utc->modify('+7 days')]);
    }

    public function withToken(string $token): self
    {
        return $this->with(['tokenHash' => hash('sha256', $token)]);
    }

    public function byEmail(): self
    {
        return $this->with(['channel' => 'email']);
    }

    public function acceptedBy(string $userId, \DateTimeImmutable $acceptedAt): self
    {
        return $this->with([
            'status'           => 'accepted',
            'acceptedAt'       => $acceptedAt->setTimezone(new \DateTimeZone('UTC')),
            'acceptedByUserId' => Uuid::fromString($userId),
        ]);
    }

    protected function defaults(): array|callable
    {
        $sentAt = new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC'));

        return [
            'id'               => Uuid::v7(),
            'clinicId'         => Uuid::v7(),
            'clientId'         => Uuid::v7(),
            'phone'            => self::faker()->numerify('+336########'),
            'email'            => null,
            'channel'          => 'sms',
            'tokenHash'        => hash('sha256', random_bytes(32)),
            'status'           => 'pending',
            'sentAt'           => $sentAt,
            'expiresAt'        => $sentAt->modify('+7 days'),
            'acceptedAt'       => null,
            'acceptedByUserId' => null,
            'revokedAt'        => null,
        ];
    }
}
