<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\User;

use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Application\Query\ListUserHistory\ListUserHistory;
use App\System\IdentityAccess\Application\Query\GetLatestInvitation\GetLatestInvitation;
use App\System\IdentityAccess\Application\Query\GetLatestInvitation\InvitationView;
use App\System\IdentityAccess\Application\Query\ListUserSessions\ListUserSessions;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * E2 / E3 — a clinic account's sheet: profile, clinics, sessions, security,
 * history and, while it waits, its invitation.
 */
final readonly class UserSheetBuilder
{
    public function __construct(
        private ConsoleDirectory $directory,
        private QueryBusInterface $queryBus,
        private RouterInterface $router,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>|null null for an unknown account or one that is not a clinic account
     */
    public function build(string $userId): ?array
    {
        $account = $this->directory->accounts([$userId])[$userId] ?? null;

        if (null === $account || 'CLINIC' !== $account->type) {
            return null;
        }

        $memberships = $this->directory->members(userId: $userId);
        $invitations = [];
        foreach ($memberships as $member) {
            $invitations[$member->clinicId] = $this->directory->invitations($member->clinicId, [$userId]);
        }

        $row     = StaffUserRow::of($account, $memberships, $invitations);
        $first   = $row->first();
        $profile = $this->profile($row->memberships);
        $live    = array_values(array_filter($row->memberships, static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));
        $taken   = array_map(static fn (ConsoleMemberRow $m): string => $m->clinicId, $row->memberships);

        $invitation = $account->isPending() ? $this->queryBus->ask(new GetLatestInvitation($userId)) : null;
        $sessions   = $account->isActive() ? $this->queryBus->ask(new ListUserSessions($userId)) : [];
        $history    = $this->queryBus->ask(new ListUserHistory($userId, 5));

        return [
            'account'     => $account,
            'row'         => $row,
            'name'        => $row->name,
            'state'       => $row->state,
            'role'        => $row->role(),
            'profile'     => $profile,
            'memberships' => array_map(fn (ConsoleMemberRow $m): array => [
                'membershipId' => $m->membershipId,
                'clinicId'     => $m->clinicId,
                'clinicName'   => $m->clinicName,
                'role'         => $m->role,
                'admin'        => $m->isAdministrator,
                'since'        => $m->since,
                'removed'      => $m->isDisabled(),
                'clinicUrl'    => $this->url('backoffice_clinic_show', ['id' => $m->clinicId]),
            ], $row->memberships),
            'live_clinics' => array_map(static fn (ConsoleMemberRow $m): string => $m->clinicName, $live),
            'is_vet'       => [] !== array_filter($live, static fn (ConsoleMemberRow $m): bool => 'VETERINARY' === $m->role),
            'clinics'      => array_values(array_filter($this->directory->clinics(), static fn (ConsoleClinicRow $c): bool => !\in_array($c->id, $taken, true) && !$c->isSuspended())),
            'invitation'   => $invitation instanceof InvitationView ? $this->invitation($invitation) : null,
            'sessions'     => \is_array($sessions) ? $sessions : [],
            'history'      => \is_array($history) ? $history : [],
            'support_url'  => null !== $first && !$first->isDisabled() && $account->isActive() ? $this->url('backoffice_support_start', ['id' => $first->clinicId]) : null,
        ];
    }

    /**
     * The identity the clinics know: the first profile with a name.
     *
     * @param list<ConsoleMemberRow> $memberships
     *
     * @return array{firstName: ?string, lastName: ?string, phone: ?string, registrationNumber: ?string}
     */
    private function profile(array $memberships): array
    {
        foreach ($memberships as $member) {
            if ('' !== $member->firstName || '' !== $member->lastName) {
                return [
                    'firstName'          => '' !== $member->firstName ? $member->firstName : null,
                    'lastName'           => '' !== $member->lastName ? $member->lastName : null,
                    'phone'              => $member->phone,
                    'registrationNumber' => $member->registrationNumber,
                ];
            }
        }

        return ['firstName' => null, 'lastName' => null, 'phone' => null, 'registrationNumber' => null];
    }

    /**
     * @return array{sentAt: \DateTimeImmutable, expiresAt: \DateTimeImmutable, expired: bool, daysLeft: int, clinicName: ?string}
     */
    private function invitation(InvitationView $invitation): array
    {
        $now  = $this->clock->now();
        $left = (int) ceil(($invitation->expiresAt->getTimestamp() - $now->getTimestamp()) / 86400);

        return [
            'sentAt'     => $invitation->sentAt,
            'expiresAt'  => $invitation->expiresAt,
            'expired'    => $invitation->expired,
            'daysLeft'   => max(0, $left),
            'clinicName' => $this->directory->clinic($invitation->clinicId)?->name,
        ];
    }

    /**
     * @param array<string, string> $parameters
     */
    private function url(string $route, array $parameters): ?string
    {
        return null !== $this->router->getRouteCollection()->get($route)
            ? $this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH)
            : null;
    }
}
