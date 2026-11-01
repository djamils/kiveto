<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\LinkRequestFile;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;

/**
 * The « Rattachement » requests of a clinic, built from its pending links
 * grouped per account: the Portal profile name next to each file's name
 * and animals, a mismatch flag when the last names differ (case and
 * accents ignored) and a flag when another account already has a confirmed
 * link to the file. Staff decide; nothing is excluded (D17).
 */
final readonly class ClinicLinkRequests
{
    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private OwnerProfileRepositoryInterface $profiles,
        private ClientDirectoryInterface $clients,
        private PetNamesInterface $pets,
        private ClinicProfileInterface $clinics,
    ) {
    }

    /**
     * Oldest request first.
     *
     * @return list<ClinicLinkRequest>
     */
    public function ofClinic(string $clinicId): array
    {
        /** @var array<string, non-empty-list<ClientLink>> $byUser */
        $byUser = [];

        foreach ($this->links->findPendingByClinic($clinicId) as $link) {
            $byUser[$link->userId()][] = $link;
        }

        if ([] === $byUser) {
            return [];
        }

        $zone = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);

        return array_values(array_map(fn (array $links): ClinicLinkRequest => $this->request($clinicId, $links, $zone), $byUser));
    }

    public function ofAccount(string $clinicId, string $userId): ?ClinicLinkRequest
    {
        $pending = array_values(array_filter(
            $this->links->findByUser($userId),
            static fn (ClientLink $link): bool => $link->clinicId() === $clinicId && $link->isPending(),
        ));

        if ([] === $pending) {
            return null;
        }

        return $this->request($clinicId, $pending, new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone));
    }

    /**
     * @param non-empty-list<ClientLink> $links the account's pending links in the clinic
     */
    private function request(string $clinicId, array $links, \DateTimeZone $zone): ClinicLinkRequest
    {
        usort($links, static fn (ClientLink $a, ClientLink $b): int => [$a->requestedAt(), $a->id()->toString()] <=> [$b->requestedAt(), $b->id()->toString()]);

        $userId  = $links[0]->userId();
        $profile = $this->profiles->findByUserId($userId);
        $files   = [];

        foreach ($links as $link) {
            $snapshot = $this->clients->snapshotOf($clinicId, $link->clientId());
            $files[]  = new LinkRequestFile(
                $link->clientId(),
                $snapshot?->firstName,
                $snapshot?->lastName,
                $this->pets->petNamesOf($clinicId, $link->clientId()),
                null === $profile || null === $snapshot || !PersonName::sameLastName($profile->lastName(), $snapshot->lastName),
                [] !== array_filter(
                    $this->links->findByClient($clinicId, $link->clientId()),
                    static fn (ClientLink $other): bool => $other->isConfirmed() && $other->userId() !== $userId,
                ),
            );
        }

        $requestedAt = $links[0]->requestedAt() ?? throw new \LogicException('A pending link has a request date.');

        return new ClinicLinkRequest($userId, $profile?->firstName(), $profile?->lastName(), $requestedAt->setTimezone($zone), $files);
    }
}
