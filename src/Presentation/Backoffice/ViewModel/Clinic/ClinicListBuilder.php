<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Clinic;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;

/**
 * B1 / B2 — the clinics page: the « À traiter » list, the four counters,
 * the status tabs and the table (search, sort, pages of 20).
 */
final readonly class ClinicListBuilder
{
    public const int PER_PAGE = 20;

    public const array SORTS = ['activity' => 'Dernière activité', 'name' => 'Nom', 'created' => 'Création récente'];

    /** An invitation still waiting after that many days is to be chased. */
    private const int INVITATION_CHASE_DAYS = 2;

    /** An onboarding clinic nobody signed in to after that many days. */
    private const int ACTIVATION_DAYS = 5;

    public function __construct(
        private ConsoleDirectory $directory,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     rows: list<ClinicListRow>, total: int, page: int, pageCount: int, perPage: int,
     *     counts: array<string, int>, stats: array<string, array{value: int, hint: string}>,
     *     alerts: list<array{kind: string, label: string, text: string, clinic: ConsoleClinicRow, action: string}>,
     *     people: int
     * }
     */
    public function build(string $status, string $search, string $sort, int $page): array
    {
        $now     = $this->clock->now();
        $members = $this->directory->members();
        $people  = array_values(array_unique(array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $members)));
        $logins  = $this->directory->accounts($people);

        /** @var array<string, list<ConsoleMemberRow>> $byClinic */
        $byClinic = [];
        foreach ($members as $member) {
            $byClinic[$member->clinicId][] = $member;
        }

        $rows   = [];
        $alerts = [];
        foreach ($this->directory->clinics() as $clinic) {
            $clinicMembers = array_values(array_filter($byClinic[$clinic->id] ?? [], static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));
            $invitations   = $this->directory->invitations($clinic->id, array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $clinicMembers));
            $pending       = array_values(array_filter(
                $clinicMembers,
                static fn (ConsoleMemberRow $m): bool => \in_array($invitations[$m->userId] ?? null, [GetInvitationStatuses::PENDING_VALID, GetInvitationStatuses::PENDING_EXPIRED], true),
            ));

            $lastLogin = null;
            foreach ($clinicMembers as $member) {
                $at = ($logins[$member->userId] ?? null)?->lastLoginAt;
                if (null !== $at && (null === $lastLogin || $at > $lastLogin)) {
                    $lastLogin = $at;
                }
            }

            $row = new ClinicListRow(
                clinic: $clinic,
                place: ClinicLifecycle::place($clinic->city, $clinic->postalCode),
                lifecycle: ClinicLifecycle::of($clinic->isSuspended(), null !== $clinic->onboardedAt, $clinic->plan),
                members: \count($clinicMembers),
                pending: \count($pending),
                lastLogin: $lastLogin,
            );
            $rows[] = $row;
            $alerts = [...$alerts, ...$this->alerts($row, $pending, $now)];
        }

        $counts = ['all' => \count($rows)];
        foreach ([ClinicLifecycle::ACTIVE, ClinicLifecycle::ONBOARDING, ClinicLifecycle::SUSPENDED] as $key) {
            $counts[$key] = \count(self::withLifecycle($rows, $key));
        }

        $visible = array_values(array_filter(
            $rows,
            fn (ClinicListRow $row): bool => ('all' === $status || $row->lifecycle->key === $status)
                && $this->matches($row->clinic, $byClinic[$row->clinic->id] ?? [], $logins, $search),
        ));
        usort($visible, self::sorter($sort));

        $total    = \count($visible);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page     = min(max(1, $page), $lastPage);

        return [
            'rows'      => \array_slice($visible, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total'     => $total,
            'page'      => $page,
            'pageCount' => $lastPage,
            'perPage'   => self::PER_PAGE,
            'counts'    => $counts,
            'stats'     => $this->stats($rows, $members, $logins, $now),
            'alerts'    => $alerts,
            'people'    => \count($people),
        ];
    }

    /**
     * @param list<ConsoleMemberRow> $pending
     *
     * @return list<array{kind: string, label: string, text: string, clinic: ConsoleClinicRow, action: string}>
     */
    private function alerts(ClinicListRow $row, array $pending, \DateTimeImmutable $now): array
    {
        if (ClinicLifecycle::SUSPENDED === $row->lifecycle->key) {
            return [];
        }

        $alerts = [];
        $limit  = $now->modify(\sprintf('-%d days', self::INVITATION_CHASE_DAYS));
        $stale  = array_values(array_filter($pending, static fn (ConsoleMemberRow $m): bool => $m->since <= $limit));

        if ([] !== $stale) {
            $oldest   = min(array_map(static fn (ConsoleMemberRow $m): int => $m->since->getTimestamp(), $stale));
            $days     = intdiv($now->getTimestamp() - $oldest, 86400);
            $many     = \count($stale) > 1 ? 's' : '';
            $alerts[] = [
                'kind'   => 'invitations',
                'label'  => 'Invitations',
                'text'   => \sprintf('%d invitation%s non acceptée%s depuis %d jours', \count($stale), $many, $many, $days),
                'clinic' => $row->clinic,
                'action' => 'resend',
            ];
        }

        $age = intdiv($now->getTimestamp() - $row->clinic->createdAt->getTimestamp(), 86400);

        if (ClinicLifecycle::ONBOARDING === $row->lifecycle->key && null === $row->lastLogin && $age >= self::ACTIVATION_DAYS) {
            $alerts[] = [
                'kind'   => 'activation',
                'label'  => 'Activation',
                'text'   => \sprintf('Aucune connexion depuis la création il y a %d jours', $age),
                'clinic' => $row->clinic,
                'action' => 'open',
            ];
        }

        return $alerts;
    }

    /**
     * @param list<ClinicListRow>           $rows
     * @param list<ConsoleMemberRow>        $members
     * @param array<string, AccountSummary> $logins
     *
     * @return array<string, array{value: int, hint: string}>
     */
    private function stats(array $rows, array $members, array $logins, \DateTimeImmutable $now): array
    {
        $active     = self::withLifecycle($rows, ClinicLifecycle::ACTIVE);
        $onboarding = self::withLifecycle($rows, ClinicLifecycle::ONBOARDING);
        $suspended  = self::withLifecycle($rows, ClinicLifecycle::SUSPENDED);

        $accounts = [];
        foreach ($members as $member) {
            if (!$member->isDisabled()) {
                $accounts[$member->userId] = true;
            }
        }

        $weekAgo = $now->modify('-7 days');
        $recent  = 0;
        foreach (array_keys($accounts) as $userId) {
            $at = ($logins[$userId] ?? null)?->lastLoginAt;
            if (null !== $at && $at >= $weekAgo) {
                ++$recent;
            }
        }

        $vets    = array_sum(array_map(static fn (ClinicListRow $r): int => $r->clinic->veterinarians, $active));
        $pending = array_sum(array_map(static fn (ClinicListRow $r): int => $r->pending, $onboarding));
        $names   = array_map(static fn (ClinicListRow $r): string => $r->clinic->name, $suspended);

        return [
            'active'     => ['value' => \count($active), 'hint' => \sprintf('%d véto%s facturable%s', $vets, $vets > 1 ? 's' : '', $vets > 1 ? 's' : '')],
            'onboarding' => ['value' => \count($onboarding), 'hint' => \sprintf('%d invitation%s en attente', $pending, $pending > 1 ? 's' : '')],
            'suspended'  => ['value' => \count($suspended), 'hint' => [] === $names ? '—' : implode(', ', \array_slice($names, 0, 2)) . (\count($names) > 2 ? '…' : '')],
            'users'      => ['value' => $recent, 'hint' => \sprintf('sur %d compte%s', \count($accounts), \count($accounts) > 1 ? 's' : '')],
        ];
    }

    /**
     * @param list<ClinicListRow> $rows
     *
     * @return list<ClinicListRow>
     */
    private static function withLifecycle(array $rows, string $key): array
    {
        return array_values(array_filter($rows, static fn (ClinicListRow $r): bool => $key === $r->lifecycle->key));
    }

    /**
     * @param list<ConsoleMemberRow>        $members
     * @param array<string, AccountSummary> $logins
     */
    private function matches(ConsoleClinicRow $clinic, array $members, array $logins, string $search): bool
    {
        $search = mb_strtolower(trim($search));

        if ('' === $search) {
            return true;
        }

        $haystacks = [$clinic->name, $clinic->slug, (string) $clinic->siret, (string) $clinic->legalName, (string) $clinic->city];
        foreach ($members as $member) {
            $haystacks[] = $member->printedName();
            $haystacks[] = (string) ($logins[$member->userId] ?? null)?->email;
        }

        $compact = (string) preg_replace('/\s+/', '', $search);

        foreach ($haystacks as $haystack) {
            $haystack = mb_strtolower($haystack);
            if (str_contains($haystack, $search) || ('' !== $compact && str_contains((string) preg_replace('/\s+/', '', $haystack), $compact))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return \Closure(ClinicListRow, ClinicListRow): int
     */
    private static function sorter(string $sort): \Closure
    {
        return match ($sort) {
            'name'    => static fn (ClinicListRow $a, ClinicListRow $b): int => strcasecmp($a->clinic->name, $b->clinic->name),
            'created' => static fn (ClinicListRow $a, ClinicListRow $b): int => $b->clinic->createdAt <=> $a->clinic->createdAt,
            default   => static fn (ClinicListRow $a, ClinicListRow $b): int => ($b->lastLogin?->getTimestamp() ?? 0) <=> ($a->lastLogin?->getTimestamp() ?? 0)
                ?: strcasecmp($a->clinic->name, $b->clinic->name),
        };
    }
}
