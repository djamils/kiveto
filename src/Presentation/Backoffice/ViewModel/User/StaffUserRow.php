<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\User;

use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;

/**
 * One person of the clinic staff as the users list prints them: their
 * account, their clinics (the first one named, « +N » for the others) and
 * their state across them.
 */
final readonly class StaffUserRow
{
    /**
     * @param list<ConsoleMemberRow>                           $memberships
     * @param array{key: string, label: string, badge: string} $state
     */
    public function __construct(
        public AccountSummary $account,
        public array $memberships,
        public string $name,
        public array $state,
    ) {
    }

    /**
     * @param list<ConsoleMemberRow>               $memberships
     * @param array<string, array<string, string>> $invitations clinicId → userId → PENDING_VALID | PENDING_EXPIRED
     */
    public static function of(AccountSummary $account, array $memberships, array $invitations): self
    {
        usort($memberships, static fn (ConsoleMemberRow $a, ConsoleMemberRow $b): int => $a->isDisabled() <=> $b->isDisabled() ?: $a->since <=> $b->since ?: strcmp($a->clinicName, $b->clinicName));
        $first = $memberships[0] ?? null;

        return new self(
            account: $account,
            memberships: $memberships,
            name: null !== $first ? $first->printedName() : (string) ($account->email ?? $account->phone ?? '—'),
            state: self::state($account, $memberships, $invitations),
        );
    }

    public function first(): ?ConsoleMemberRow
    {
        return $this->memberships[0] ?? null;
    }

    /** The other clinics, for « +N ». */
    public function others(): int
    {
        return max(0, \count($this->memberships) - 1);
    }

    public function role(): ?string
    {
        $first = $this->first();

        return null !== $first ? ConsoleDirectory::roleLabel($first->role) : null;
    }

    public function hasStatus(string $status): bool
    {
        return match ($status) {
            'active'   => 'active' === $this->state['key'],
            'invited'  => \in_array($this->state['key'], ['invited', 'expired'], true),
            'disabled' => 'disabled' === $this->state['key'],
            default    => true,
        };
    }

    /** The text typed matches the name, the e-mail or a phone number (spaces ignored). */
    public function matches(string $needle): bool
    {
        $digits = preg_replace('/\D/', '', $needle) ?? '';
        $phones = array_filter([$this->account->phone, ...array_map(static fn (ConsoleMemberRow $m): ?string => $m->phone, $this->memberships)]);

        if (str_contains(mb_strtolower($this->name), $needle) || str_contains(mb_strtolower((string) $this->account->email), $needle)) {
            return true;
        }

        foreach ($phones as $phone) {
            $national = str_starts_with($phone, '+33') ? '0' . substr($phone, 3) : $phone;

            if (\strlen($digits) >= 4 && (str_contains(preg_replace('/\D/', '', $phone) ?? '', $digits) || str_contains($national, $digits))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ConsoleMemberRow>               $memberships
     * @param array<string, array<string, string>> $invitations
     *
     * @return array{key: string, label: string, badge: string}
     */
    private static function state(AccountSummary $account, array $memberships, array $invitations): array
    {
        $live = array_values(array_filter($memberships, static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));

        if ($account->isDisabled() || ([] !== $memberships && [] === $live)) {
            return ['key' => 'disabled', 'label' => 'Désactivé', 'badge' => 'is-outline'];
        }

        if ($account->isPending()) {
            foreach ($live as $member) {
                if (GetInvitationStatuses::PENDING_VALID === ($invitations[$member->clinicId][$account->id] ?? null)) {
                    return ['key' => 'invited', 'label' => 'Invitation envoyée', 'badge' => 'is-orange'];
                }
            }

            return ['key' => 'expired', 'label' => 'Invitation expirée', 'badge' => 'is-red'];
        }

        return ['key' => 'active', 'label' => 'Actif', 'badge' => 'is-green'];
    }
}
