<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Port\ClinicAppointmentsInterface;
use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\ClinicOnboardingView;
use App\Context\Clinic\Application\Query\Account\GetClinicOnboarding\OnboardingStepView;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;

/**
 * « Mise en route »: the clinic is an active pilot once every counted step
 * is done. The import of the legacy data is not handled in pre-alpha: it is
 * shown, not counted.
 */
final readonly class ClinicOnboardingChecklist
{
    public function __construct(
        private ClinicAccounts $accounts,
        private ClinicConsoleReadRepositoryInterface $console,
        private InvitationStatusReaderInterface $invitations,
        private ClinicAppointmentsInterface $appointments,
    ) {
    }

    public function of(string $clinicId): ClinicOnboardingView
    {
        $clinic  = $this->accounts->clinic($clinicId);
        $account = $this->accounts->of($clinicId);
        $members = array_values(array_filter($this->console->members(clinicId: $clinicId), static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));

        $statuses = [] !== $members ? $this->invitations->statuses($clinicId, array_map(static fn (ConsoleMemberRow $m): string => $m->userId, $members)) : [];
        $waiting  = array_values(array_filter($members, static fn (ConsoleMemberRow $m): bool => InvitationStatusReaderInterface::ACTIVE !== ($statuses[$m->userId] ?? InvitationStatusReaderInterface::ACTIVE)));
        $accepted = \count($members) - \count($waiting);
        $booked   = $this->appointments->hasAny($clinicId);

        return new ClinicOnboardingView(
            steps: [
                new OnboardingStepView('created', 'Clinique créée', true, ''),
                new OnboardingStepView(
                    'invited',
                    [] !== $members ? \sprintf('%d invitation%s envoyée%s', \count($members), \count($members) > 1 ? 's' : '', \count($members) > 1 ? 's' : '') : 'Invitations envoyées',
                    [] !== $members,
                    [] !== $members ? 'Liens valables 7 jours' : 'Aucune personne invitée',
                ),
                new OnboardingStepView(
                    'accepted',
                    'Toutes les invitations acceptées',
                    [] !== $members && [] === $waiting,
                    [] === $members ? '—' : ([] === $waiting
                        ? 'Toute l’équipe s’est connectée'
                        : \sprintf('%d sur %d · %s en attente', $accepted, \count($members), self::names($waiting))),
                ),
                new OnboardingStepView('imported', 'Données de l’ancien logiciel importées', false, 'Migration non gérée en pré-alpha', counted: false),
                new OnboardingStepView(
                    'hours',
                    'Horaires d’ouverture configurés',
                    !$clinic->openingHours()->isEmpty(),
                    'Fait par l’admin clinique dans la VetApp',
                ),
                new OnboardingStepView(
                    'appointment',
                    'Premier rendez-vous créé',
                    $booked,
                    $booked ? 'L’agenda est utilisé' : '—',
                ),
            ],
            onboardedAt: $account->onboardedAt(),
        );
    }

    /**
     * @param list<ConsoleMemberRow> $members
     */
    private static function names(array $members): string
    {
        $names = array_map(static fn (ConsoleMemberRow $m): string => trim($m->firstName . ' ' . $m->lastName) ?: $m->displayName, $members);

        if (\count($names) <= 1) {
            return implode('', $names);
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' et ' . $last;
    }
}
