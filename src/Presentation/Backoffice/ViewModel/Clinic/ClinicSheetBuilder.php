<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Clinic;

use App\Context\Clinic\Application\Query\Account\GetClinicAccount\ClinicAccountView;
use App\Context\Clinic\Application\Query\Account\GetClinicAccount\GetClinicAccount;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\ClinicOnboardingView;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\GetClinicOnboarding;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Application\Query\ListClinicActivity\ListClinicActivity;
use App\System\Audit\Application\Query\SearchAuditLog\AuditLogPage;
use App\System\Audit\Application\Query\SearchAuditLog\SearchAuditLog;
use App\System\FeatureCatalog\Application\Query\IsFeatureEnabled\IsFeatureEnabled;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;
use App\System\IdentityAccess\Application\Query\ListClinicSupportSessions\ListClinicSupportSessions;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\Notification\Application\Query\GetSmsUsage\GetSmsUsage;
use App\System\Notification\Application\Query\GetSmsUsage\SmsUsageView;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * C1 / C2 / C3 — everything the clinic's sheet shows.
 */
final readonly class ClinicSheetBuilder
{
    private const array MONTHS = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];

    public function __construct(
        private ConsoleDirectory $directory,
        private QueryBusInterface $queryBus,
        private RouterInterface $router,
        #[Autowire('%app.console.price_per_vet_cents%')]
        private int $pricePerVetCents,
    ) {
    }

    /**
     * @return array<string, mixed>|null null for an unknown clinic
     */
    public function build(string $clinicId): ?array
    {
        $clinic = $this->directory->clinic($clinicId);

        if (null === $clinic) {
            return null;
        }

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);
        $account = $this->queryBus->ask(new GetClinicAccount($clinicId));
        \assert($account instanceof ClinicAccountView);
        $onboarding = $this->queryBus->ask(new GetClinicOnboarding($clinicId));
        \assert($onboarding instanceof ClinicOnboardingView);

        $members     = $this->directory->members(clinicId: $clinicId);
        $userIds     = array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $members);
        $accounts    = $this->directory->accounts($userIds);
        $invitations = $this->directory->invitations($clinicId, $userIds);

        $team    = [];
        $pending = 0;
        foreach ($members as $member) {
            $state = ConsoleDirectory::memberState($member, $accounts[$member->userId] ?? null, $invitations[$member->userId] ?? null);
            $pending += \in_array($state['key'], ['invited', 'expired'], true) ? 1 : 0;
            $team[] = [
                'userId'    => $member->userId,
                'name'      => $member->printedName(),
                'firstName' => $member->firstName,
                'email'     => ($accounts[$member->userId] ?? null)?->email,
                'role'      => ConsoleDirectory::roleLabel($member->role),
                'roleCode'  => $member->role,
                'admin'     => $member->isAdministrator,
                'state'     => $state,
                'lastLogin' => ($accounts[$member->userId] ?? null)?->lastLoginAt,
                'url'       => $this->url('backoffice_user_show', ['id' => $member->userId]),
            ];
        }
        usort($team, static fn (array $a, array $b): int => ('disabled' === $a['state']['key']) <=> ('disabled' === $b['state']['key']));

        $activity = $this->queryBus->ask(new ListClinicActivity($clinicId, 5));
        \assert(\is_array($activity));
        $created = $this->queryBus->ask(new SearchAuditLog(perPage: 1, clinicId: $clinicId, text: 'clinic.created'));
        \assert($created instanceof AuditLogPage);

        return [
            'clinic'     => $clinic,
            'place'      => ClinicLifecycle::place($clinic->city, $clinic->postalCode),
            'lifecycle'  => ClinicLifecycle::of($clinic->isSuspended(), $onboarding->isComplete(), $account->plan),
            'account'    => $account,
            'onboarding' => $onboarding,
            'identity'   => $this->identity($settings),
            'team'       => $team,
            'pending'    => $pending,
            'billing'    => [
                'vets'    => $clinic->veterinarians,
                'monthly' => 'internal' === $account->plan ? '0 €' : ConsoleFormat::euros($clinic->veterinarians * $this->pricePerVetCents) . ' HT / mois',
            ],
            'sms'         => $this->sms($clinicId),
            'activity'    => $activity,
            'created_by'  => ($created->items[0] ?? null)?->actorName,
            'users_url'   => $this->url('backoffice_users', ['clinic' => $clinicId]),
            'support_url' => $this->url('backoffice_support_start', ['id' => $clinicId]),
            'support'     => $this->support($clinicId),
        ];
    }

    /**
     * D1 — who a support session can sign in as (active accounts of live
     * memberships), whether writing is allowed, the latest sessions.
     *
     * @return array{clinicName: string, suspended: bool, can_write: bool, members: array<string, array{userId: string, name: string, role: string}>, sessions: list<array{name: string, view: SupportSessionView}>}|null
     */
    public function support(string $clinicId): ?array
    {
        $clinic = $this->directory->clinic($clinicId);

        if (null === $clinic) {
            return null;
        }

        $members  = array_values(array_filter($this->directory->members(clinicId: $clinicId), static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));
        $accounts = $this->directory->accounts(array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $members));
        $names    = [];
        $people   = [];
        foreach ($members as $member) {
            $names[$member->userId] = $member->printedName();

            if (true === ($accounts[$member->userId] ?? null)?->isActive()) {
                $people[$member->userId] = ['userId' => $member->userId, 'name' => $member->printedName(), 'role' => ConsoleDirectory::roleLabel($member->role)];
            }
        }

        $sessions = [];
        $answer   = $this->queryBus->ask(new ListClinicSupportSessions($clinicId, 3));
        foreach (\is_array($answer) ? $answer : [] as $view) {
            if ($view instanceof SupportSessionView) {
                $sessions[] = ['name' => $names[$view->userId] ?? 'Compte retiré', 'view' => $view];
            }
        }

        return [
            'clinicName' => $clinic->name,
            'suspended'  => $clinic->isSuspended(),
            'can_write'  => true === $this->queryBus->ask(new IsFeatureEnabled($clinicId, 'support.write_mode')),
            'members'    => $people,
            'sessions'   => $sessions,
        ];
    }

    /**
     * The members still waiting for their invitation, for « Relancer ».
     *
     * @return list<array{userId: string, firstName: string, role: string}>
     */
    public function waiting(string $clinicId): array
    {
        $members     = array_values(array_filter($this->directory->members(clinicId: $clinicId), static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));
        $invitations = $this->directory->invitations($clinicId, array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $members));

        $waiting = [];
        foreach ($members as $member) {
            if (\in_array($invitations[$member->userId] ?? null, [GetInvitationStatuses::PENDING_VALID, GetInvitationStatuses::PENDING_EXPIRED], true)) {
                $waiting[] = ['userId' => $member->userId, 'firstName' => $member->firstName, 'role' => $member->role];
            }
        }

        return $waiting;
    }

    public function pricePerVet(): string
    {
        return ConsoleFormat::euros($this->pricePerVetCents);
    }

    /**
     * @return array<string, string|null>
     */
    private function identity(ClinicSettingsView $settings): array
    {
        $letterhead = $settings->letterhead;
        $street     = trim(($letterhead['streetLine1'] ?? '') . ' ' . ($letterhead['streetLine2'] ?? ''));
        $place      = trim(($letterhead['postalCode'] ?? '') . ' ' . ($letterhead['city'] ?? ''));
        $siret      = $letterhead['siret'] ?? null;
        $ape        = $letterhead['apeCode'] ?? null;

        return [
            'legalName'  => $letterhead['legalName'] ?? null,
            'siret'      => null !== $siret ? trim(chunk_split($siret, 3, ' ')) : null,
            'vat'        => $letterhead['vatNumber'] ?? null,
            'naf'        => null !== $ape ? $ape . ('75.00Z' === $ape || '7500Z' === $ape ? ' · Activités vétérinaires' : '') : null,
            'address'    => '' !== $street || '' !== $place ? trim($street . ('' !== $street && '' !== $place ? ', ' : '') . $place) : null,
            'street'     => $letterhead['streetLine1'] ?? null,
            'postalCode' => $letterhead['postalCode'] ?? null,
            'city'       => $letterhead['city'] ?? null,
            'phone'      => $letterhead['phone'] ?? null,
            'email'      => $letterhead['email'] ?? null,
            'timeZone'   => $settings->timeZone,
        ];
    }

    /**
     * @return array{month: string, used: int, quota: int, percent: int}|null
     */
    private function sms(string $clinicId): ?array
    {
        try {
            $usage = $this->queryBus->ask(new GetSmsUsage($clinicId));
        } catch (\RuntimeException) {
            return null;
        }

        if (!$usage instanceof SmsUsageView) {
            return null;
        }

        return [
            'month'   => self::MONTHS[substr($usage->month, 5, 2)] ?? $usage->month,
            'used'    => $usage->used,
            'quota'   => $usage->quota,
            'percent' => $usage->percent,
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
