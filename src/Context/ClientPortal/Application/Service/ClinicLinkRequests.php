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
use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;

/**
 * The « Rattachement » requests of a clinic, built from its pending links
 * grouped per account: the Portal profile name next to each file's name
 * and animals, a mismatch flag when the last names differ (case and
 * accents ignored) and a flag when another account already has a confirmed
 * link to the file. Staff decide; nothing is excluded (D17). Once answered,
 * each recorded decision is a request of its own: the files it decided,
 * who answered and when.
 */
final readonly class ClinicLinkRequests
{
    /** Decisions listed at most. */
    private const int HISTORY = 500;

    public function __construct(
        private ClientLinkRepositoryInterface $links,
        private LinkDecisionRepositoryInterface $decisions,
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
     * The clinic's answers, the latest first.
     *
     * @return list<ClinicLinkRequest>
     */
    public function decidedOf(string $clinicId): array
    {
        $decisions = $this->decisions->findByClinic($clinicId, self::HISTORY);

        if ([] === $decisions) {
            return [];
        }

        $zone = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);

        return array_map(fn (LinkDecision $decision): ClinicLinkRequest => $this->decided($decision, $zone), $decisions);
    }

    public function decision(string $clinicId, string $decisionId): ?ClinicLinkRequest
    {
        $decision = $this->decisions->findInClinic($clinicId, $decisionId);

        return null === $decision ? null : $this->decided($decision, new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone));
    }

    /**
     * @param non-empty-list<ClientLink> $links the account's pending links in the clinic
     */
    private function request(string $clinicId, array $links, \DateTimeZone $zone): ClinicLinkRequest
    {
        usort($links, static fn (ClientLink $a, ClientLink $b): int => [$a->requestedAt(), $a->id()->toString()] <=> [$b->requestedAt(), $b->id()->toString()]);

        $userId      = $links[0]->userId();
        $profile     = $this->profiles->findByUserId($userId);
        $requestedAt = $links[0]->requestedAt() ?? throw new \LogicException('A pending link has a request date.');

        return new ClinicLinkRequest(
            $userId,
            $profile?->firstName(),
            $profile?->lastName(),
            $requestedAt->setTimezone($zone),
            array_map(fn (ClientLink $link): LinkRequestFile => $this->file($clinicId, $userId, $profile?->lastName(), $link->clientId()), $links),
        );
    }

    /**
     * An answer as the request it closed: the files it decided, asked for
     * when their links were requested (the decision time for a link no
     * longer known).
     */
    private function decided(LinkDecision $decision, \DateTimeZone $zone): ClinicLinkRequest
    {
        $clinicId  = $decision->clinicId();
        $userId    = $decision->userId();
        $profile   = $this->profiles->findByUserId($userId);
        $requested = array_filter(array_map(
            static fn (ClientLink $link): ?\DateTimeImmutable => $link->clinicId() === $clinicId && \in_array($link->clientId(), $decision->clientIds(), true) ? $link->requestedAt() : null,
            $this->links->findByUser($userId),
        ));

        return new ClinicLinkRequest(
            $userId,
            $profile?->firstName(),
            $profile?->lastName(),
            ([] === $requested ? $decision->decidedAt() : min($requested))->setTimezone($zone),
            array_map(fn (string $clientId): LinkRequestFile => $this->file($clinicId, $userId, $profile?->lastName(), $clientId), $decision->clientIds()),
            $decision->id()->toString(),
            $decision->outcome()->value,
            $decision->decidedAt()->setTimezone($zone),
            $decision->decidedByName(),
        );
    }

    private function file(string $clinicId, string $userId, ?string $profileLastName, string $clientId): LinkRequestFile
    {
        $snapshot = $this->clients->snapshotOf($clinicId, $clientId);

        return new LinkRequestFile(
            $clientId,
            $snapshot?->firstName,
            $snapshot?->lastName,
            $this->pets->petNamesOf($clinicId, $clientId),
            null === $profileLastName || null === $snapshot || !PersonName::sameLastName($profileLastName, $snapshot->lastName),
            [] !== array_filter(
                $this->links->findByClient($clinicId, $clientId),
                static fn (ClientLink $other): bool => $other->isConfirmed() && $other->userId() !== $userId,
            ),
        );
    }
}
