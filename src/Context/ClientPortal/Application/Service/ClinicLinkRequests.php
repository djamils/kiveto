<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\PetNamesInterface;
use App\Context\ClientPortal\Application\Port\PortalClientSnapshot;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\LinkRequestFile;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
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

        $zone   = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);
        $lookup = $this->lookup($clinicId, array_map(strval(...), array_keys($byUser)), array_map(static fn (ClientLink $link): string => $link->clientId(), array_merge(...array_values($byUser))));

        return array_values(array_map(fn (array $links): ClinicLinkRequest => $this->request($links, $zone, $lookup), $byUser));
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

        return $this->request(
            $pending,
            new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone),
            $this->lookup($clinicId, [$userId], array_map(static fn (ClientLink $link): string => $link->clientId(), $pending)),
        );
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

        $zone   = new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone);
        $lookup = $this->lookup(
            $clinicId,
            array_map(static fn (LinkDecision $decision): string => $decision->userId(), $decisions),
            array_merge(...array_map(static fn (LinkDecision $decision): array => $decision->clientIds(), $decisions)),
        );

        return array_map(fn (LinkDecision $decision): ClinicLinkRequest => $this->decided($decision, $zone, $lookup), $decisions);
    }

    public function decision(string $clinicId, string $decisionId): ?ClinicLinkRequest
    {
        $decision = $this->decisions->findInClinic($clinicId, $decisionId);

        return null === $decision ? null : $this->decided(
            $decision,
            new \DateTimeZone($this->clinics->clinicOf($clinicId)->timeZone),
            $this->lookup($clinicId, [$decision->userId()], $decision->clientIds()),
        );
    }

    /**
     * What the requests show of their accounts and files, read once for all
     * of them: the profiles, the files, their animals and every link to them.
     *
     * @param list<string> $userIds
     * @param list<string> $clientIds
     *
     * @return array{profiles: array<string, OwnerProfile>, snapshots: array<string, PortalClientSnapshot>, pets: array<string, list<string>>, links: array<string, list<ClientLink>>}
     */
    private function lookup(string $clinicId, array $userIds, array $clientIds): array
    {
        $userIds   = array_values(array_unique($userIds));
        $clientIds = array_values(array_unique($clientIds));
        $links     = [];

        foreach ($this->links->findByClients($clinicId, $clientIds) as $link) {
            $links[$link->clientId()][] = $link;
        }

        return [
            'profiles'  => $this->profiles->findByUserIds($userIds),
            'snapshots' => $this->clients->snapshotsOf($clinicId, $clientIds),
            'pets'      => $this->pets->petNamesOfClients($clinicId, $clientIds),
            'links'     => $links,
        ];
    }

    /**
     * @param non-empty-list<ClientLink>                                                                                                                                              $links  the account's pending links in the clinic
     * @param array{profiles: array<string, OwnerProfile>, snapshots: array<string, PortalClientSnapshot>, pets: array<string, list<string>>, links: array<string, list<ClientLink>>} $lookup
     */
    private function request(array $links, \DateTimeZone $zone, array $lookup): ClinicLinkRequest
    {
        usort($links, static fn (ClientLink $a, ClientLink $b): int => [$a->requestedAt(), $a->id()->toString()] <=> [$b->requestedAt(), $b->id()->toString()]);

        $userId      = $links[0]->userId();
        $profile     = $lookup['profiles'][$userId] ?? null;
        $requestedAt = $links[0]->requestedAt() ?? throw new \LogicException('A pending link has a request date.');

        return new ClinicLinkRequest(
            $userId,
            $profile?->firstName(),
            $profile?->lastName(),
            $requestedAt->setTimezone($zone),
            array_map(fn (ClientLink $link): LinkRequestFile => $this->file($userId, $profile?->lastName(), $link->clientId(), $lookup), $links),
        );
    }

    /**
     * An answer as the request it closed: the files it decided, asked for
     * when their links were requested (the decision time for a link no
     * longer known).
     */
    /**
     * @param array{profiles: array<string, OwnerProfile>, snapshots: array<string, PortalClientSnapshot>, pets: array<string, list<string>>, links: array<string, list<ClientLink>>} $lookup
     */
    private function decided(LinkDecision $decision, \DateTimeZone $zone, array $lookup): ClinicLinkRequest
    {
        $clinicId  = $decision->clinicId();
        $userId    = $decision->userId();
        $profile   = $lookup['profiles'][$userId] ?? null;
        $requested = array_filter(array_map(
            static fn (ClientLink $link): ?\DateTimeImmutable => $link->clinicId() === $clinicId && \in_array($link->clientId(), $decision->clientIds(), true) ? $link->requestedAt() : null,
            $this->links->findByUser($userId),
        ));

        return new ClinicLinkRequest(
            $userId,
            $profile?->firstName(),
            $profile?->lastName(),
            ([] === $requested ? $decision->decidedAt() : min($requested))->setTimezone($zone),
            array_map(fn (string $clientId): LinkRequestFile => $this->file($userId, $profile?->lastName(), $clientId, $lookup), $decision->clientIds()),
            $decision->id()->toString(),
            $decision->outcome()->value,
            $decision->decidedAt()->setTimezone($zone),
            $decision->decidedByName(),
        );
    }

    /**
     * @param array{profiles: array<string, OwnerProfile>, snapshots: array<string, PortalClientSnapshot>, pets: array<string, list<string>>, links: array<string, list<ClientLink>>} $lookup
     */
    private function file(string $userId, ?string $profileLastName, string $clientId, array $lookup): LinkRequestFile
    {
        $snapshot = $lookup['snapshots'][$clientId] ?? null;

        return new LinkRequestFile(
            $clientId,
            $snapshot?->firstName,
            $snapshot?->lastName,
            $lookup['pets'][$clientId] ?? [],
            null === $profileLastName || null === $snapshot || !PersonName::sameLastName($profileLastName, $snapshot->lastName),
            [] !== array_filter(
                $lookup['links'][$clientId] ?? [],
                static fn (ClientLink $other): bool => $other->isConfirmed() && $other->userId() !== $userId,
            ),
        );
    }
}
