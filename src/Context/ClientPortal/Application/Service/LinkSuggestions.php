<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\ClientDirectoryInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\MobileFileMatch;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Context\ClientPortal\Application\Port\SuggestionIdSignerInterface;
use App\Context\ClientPortal\Domain\Link\ClientLinkRepositoryInterface;

/**
 * S7b link suggestions, computed on read: the client files of active
 * clinics whose `mobile` is the account's phone, one suggestion per clinic
 * (family files grouped). A clinic is left out once the account has a link
 * of any status to every matching file there — so a link the clinic
 * rejected (revoked) never brings its suggestion back. A file confirmed-
 * linked to another account stays suggested (families share files).
 */
final readonly class LinkSuggestions
{
    public function __construct(
        private OwnerAccountsInterface $accounts,
        private ClientDirectoryInterface $clients,
        private ClientLinkRepositoryInterface $links,
        private ClinicProfileInterface $clinics,
        private SuggestionIdSignerInterface $signer,
    ) {
    }

    /**
     * By clinic name.
     *
     * @return list<LinkSuggestionCandidate>
     */
    public function of(string $userId): array
    {
        $account = $this->accounts->accountOf($userId);

        if (null === $account) {
            return [];
        }

        $linked = [];

        foreach ($this->links->findByUser($userId) as $link) {
            $linked[$link->clinicId() . '/' . $link->clientId()] = true;
        }

        /** @var array<string, list<MobileFileMatch>> $byClinic */
        $byClinic = [];

        foreach ($this->clients->mobileMatches($account->phone) as $match) {
            if (!isset($linked[$match->clinicId . '/' . $match->clientId])) {
                $byClinic[$match->clinicId][] = $match;
            }
        }

        $candidates = [];

        foreach ($byClinic as $clinicId => $files) {
            $clinicId     = (string) $clinicId;
            $clinic       = $this->clinics->clinicOf($clinicId);
            $candidates[] = new LinkSuggestionCandidate($this->signer->idOf($userId, $clinicId), $clinicId, $clinic->name, $clinic->city, $files);
        }

        usort($candidates, static fn (LinkSuggestionCandidate $a, LinkSuggestionCandidate $b): int => [mb_strtolower($a->clinicName), $a->clinicId] <=> [mb_strtolower($b->clinicName), $b->clinicId]);

        return $candidates;
    }
}
