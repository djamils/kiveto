<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Invitation\PortalInvitation;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationId;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationRepositoryInterface;
use App\Context\ClientPortal\Domain\Invitation\PortalInvitationStatus;
use App\Shared\Domain\ValueObject\PhoneNumber;

final class InMemoryPortalInvitations implements PortalInvitationRepositoryInterface
{
    /** @var array<string, PortalInvitation> by id */
    public array $invitations = [];

    /** @var list<string> what was read with a lock, in order (`id:…`, `token`, `client`, `phone`) */
    public array $locks = [];

    public int $saves = 0;

    public function save(PortalInvitation $invitation): void
    {
        $this->invitations[$invitation->id()->toString()] = $invitation;
        ++$this->saves;
    }

    public function findByIdForUpdate(PortalInvitationId $id): ?PortalInvitation
    {
        $this->locks[] = 'id:' . $id->toString();

        return $this->invitations[$id->toString()] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?PortalInvitation
    {
        foreach ($this->invitations as $invitation) {
            if ($invitation->tokenHash() === $tokenHash) {
                return $invitation;
            }
        }

        return null;
    }

    public function findByTokenHashForUpdate(string $tokenHash): ?PortalInvitation
    {
        $this->locks[] = 'token';

        return $this->findByTokenHash($tokenHash);
    }

    public function findPendingForClientForUpdate(string $clinicId, string $clientId): array
    {
        $this->locks[] = 'client';

        $found = array_values(array_filter(
            $this->invitations,
            static fn (PortalInvitation $i): bool => $i->clinicId() === $clinicId && $i->clientId() === $clientId && PortalInvitationStatus::PENDING === $i->status(),
        ));
        usort($found, static fn (PortalInvitation $a, PortalInvitation $b): int => $b->sentAt() <=> $a->sentAt());

        return $found;
    }

    public function findLatestForClient(string $clinicId, string $clientId): ?PortalInvitation
    {
        $found = array_values(array_filter(
            $this->invitations,
            static fn (PortalInvitation $i): bool => $i->clinicId() === $clinicId && $i->clientId() === $clientId,
        ));
        usort($found, static fn (PortalInvitation $a, PortalInvitation $b): int => $b->sentAt() <=> $a->sentAt());

        return $found[0] ?? null;
    }

    public function findPendingByPhone(PhoneNumber $phone, \DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $this->invitations,
            static fn (PortalInvitation $i): bool => $i->phone()->equals($phone) && $i->isOpen($now),
        ));
    }

    public function findPendingByPhoneForUpdate(PhoneNumber $phone): array
    {
        $this->locks[] = 'phone';

        return array_values(array_filter(
            $this->invitations,
            static fn (PortalInvitation $i): bool => $i->phone()->equals($phone) && PortalInvitationStatus::PENDING === $i->status(),
        ));
    }
}
