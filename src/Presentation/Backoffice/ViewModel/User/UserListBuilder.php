<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\User;

use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;
use App\System\IdentityAccess\Application\Query\SearchAccounts\AccountPage;
use App\System\IdentityAccess\Application\Query\SearchAccounts\SearchAccounts;

/**
 * E1 — « Utilisateurs »: the clinic staff (every clinic account, with its
 * clinics) and the owners of the Portal.
 */
final readonly class UserListBuilder
{
    public const int PER_PAGE = 10;

    public const array STATUSES = ['all', 'active', 'invited', 'disabled'];

    /** Every clinic account is read at once: a pre-alpha has dozens. */
    private const int STAFF_LIMIT = 5000;

    private const array STATE_ORDER = ['active' => 0, 'invited' => 1, 'expired' => 2, 'disabled' => 3];

    public function __construct(
        private ConsoleDirectory $directory,
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @return array{staff: int, portal: int}
     */
    public function totals(): array
    {
        return ['staff' => $this->page('CLINIC', null, 1, 1)->total, 'portal' => $this->page('PORTAL', null, 1, 1)->total];
    }

    /**
     * Every clinic account matching the clinic and the text, whatever its
     * state, in the console's order (active, invited, expired, disabled; the
     * most recent sign-in first).
     *
     * @return list<StaffUserRow>
     */
    public function staffRows(?string $clinicId, string $search): array
    {
        $members = $this->directory->members();
        /** @var array<string, list<ConsoleMemberRow>> $byUser */
        $byUser = [];
        foreach ($members as $member) {
            $byUser[$member->userId][] = $member;
        }

        $invitations = $this->invitations($members);
        $needle      = mb_strtolower(trim($search));
        $rows        = [];

        foreach ($this->page('CLINIC', null, 1, self::STAFF_LIMIT)->items as $account) {
            $memberships = $byUser[$account->id] ?? [];

            if (null !== $clinicId && [] === array_filter($memberships, static fn (ConsoleMemberRow $m): bool => $m->clinicId === $clinicId)) {
                continue;
            }

            $row = StaffUserRow::of($account, $memberships, $invitations);

            if ('' === $needle || $row->matches($needle)) {
                $rows[] = $row;
            }
        }

        usort($rows, static fn (StaffUserRow $a, StaffUserRow $b): int => self::STATE_ORDER[$a->state['key']] <=> self::STATE_ORDER[$b->state['key']]
            ?: ($b->account->lastLoginAt?->getTimestamp() ?? 0) <=> ($a->account->lastLoginAt?->getTimestamp() ?? 0)
            ?: strcasecmp($a->name, $b->name));

        return $rows;
    }

    /**
     * @return array{rows: list<StaffUserRow>, counts: array<string, int>, total: int, page: int, pageCount: int, perPage: int}
     */
    public function staff(string $status, ?string $clinicId, string $search, int $page): array
    {
        $all     = $this->staffRows($clinicId, $search);
        $visible = array_values(array_filter($all, static fn (StaffUserRow $row): bool => $row->hasStatus($status)));
        $counts  = [];
        foreach (self::STATUSES as $key) {
            $counts[$key] = \count(array_filter($all, static fn (StaffUserRow $row): bool => $row->hasStatus($key)));
        }

        $total    = \count($visible);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min(max(1, $page), $lastPage);

        return [
            'rows'      => \array_slice($visible, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'counts'    => $counts,
            'total'     => $total,
            'page'      => $page,
            'pageCount' => $lastPage,
            'perPage'   => self::PER_PAGE,
        ];
    }

    public function portal(string $search, int $page, int $perPage = self::PER_PAGE): AccountPage
    {
        return $this->page('PORTAL', '' !== trim($search) ? trim($search) : null, $page, $perPage);
    }

    /**
     * clinicId → userId → invitation status, for the clinics with someone
     * whose account waits for an invitation.
     *
     * @param list<ConsoleMemberRow> $members
     *
     * @return array<string, array<string, string>>
     */
    private function invitations(array $members): array
    {
        $byClinic = [];
        foreach ($members as $member) {
            $byClinic[$member->clinicId][] = $member->userId;
        }

        $statuses = [];
        foreach ($byClinic as $clinicId => $userIds) {
            $statuses[$clinicId] = array_filter(
                $this->directory->invitations($clinicId, $userIds),
                static fn (string $status): bool => \in_array($status, [GetInvitationStatuses::PENDING_VALID, GetInvitationStatuses::PENDING_EXPIRED], true),
            );
        }

        return $statuses;
    }

    private function page(string $type, ?string $text, int $page, int $perPage): AccountPage
    {
        $answer = $this->queryBus->ask(new SearchAccounts($type, $text, $page, $perPage));
        \assert($answer instanceof AccountPage);

        return $answer;
    }
}
