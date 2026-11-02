<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptLinkSuggestion;

use App\Context\ClientPortal\Application\Port\LinkRequestBellInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\PortalLockInterface;
use App\Context\ClientPortal\Application\Port\PrescriberDirectoryInterface;
use App\Context\ClientPortal\Application\Port\SuggestionIdSignerInterface;
use App\Context\ClientPortal\Application\Service\LinkSuggestionCandidate;
use App\Context\ClientPortal\Application\Service\LinkSuggestions;
use App\Context\ClientPortal\Domain\Exception\LinkSuggestionNotFoundException;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkStatus;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * One pending link per matching file of the clinic the account has no link
 * to, then the bell of the `MANAGE_ANIMALS` holders (the files' names only,
 * never owner text). A replay — the account already waits for that clinic —
 * answers `pending` again without writing.
 */
#[AsMessageHandler]
final readonly class AcceptLinkSuggestionHandler
{
    public function __construct(
        private LinkSuggestions $suggestions,
        private ClientLinkRepositoryInterface $links,
        private SuggestionIdSignerInterface $signer,
        private PrescriberDirectoryInterface $staff,
        private LinkRequestBellInterface $bell,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private PortalLockInterface $locks,
    ) {
    }

    public function __invoke(AcceptLinkSuggestion $command): string
    {
        // Serialised per account: a double accept waits, then sees the links
        // (no two inserts into the gap of `uniq_client_link`, no deadlock)
        $this->locks->lockAccount($command->userId);
        $candidate = $this->candidate($command->userId, $command->suggestionId);

        if (null === $candidate) {
            if ($this->awaitsClinic($command->userId, $command->suggestionId)) {
                return ClientLinkStatus::PENDING->value;
            }

            throw new LinkSuggestionNotFoundException();
        }

        $now     = $this->clock->now();
        $created = [];

        foreach ($candidate->files as $file) {
            if (null !== $this->links->findForUpdate($command->userId, $candidate->clinicId, $file->clientId)) {
                continue;
            }

            $link = ClientLink::requestFromSuggestion(ClientLinkId::fromString($this->uuids->generate()), $command->userId, $candidate->clinicId, $file->clientId, $now);
            $this->links->save($link);
            $created[] = $link;
        }

        if ([] !== $created) {
            $this->bell->announce(
                $candidate->clinicId,
                $this->staff->animalManagers($candidate->clinicId),
                $command->userId,
                'portal-link-request:' . $created[0]->id()->toString(),
                'Demande de rattachement Portal',
                implode(' · ', array_map(static fn (MobileFileMatch $file): string => trim($file->firstName . ' ' . $file->lastName), $candidate->files)),
            );
        }

        return ClientLinkStatus::PENDING->value;
    }

    private function candidate(string $userId, string $suggestionId): ?LinkSuggestionCandidate
    {
        foreach ($this->suggestions->of($userId) as $candidate) {
            if (hash_equals($candidate->id, $suggestionId)) {
                return $candidate;
            }
        }

        return null;
    }

    /** The id names a clinic the account already has a pending link to. */
    private function awaitsClinic(string $userId, string $suggestionId): bool
    {
        foreach ($this->links->findActiveByUser($userId) as $link) {
            if ($link->isPending() && hash_equals($this->signer->idOf($userId, $link->clinicId()), $suggestionId)) {
                return true;
            }
        }

        return false;
    }
}
