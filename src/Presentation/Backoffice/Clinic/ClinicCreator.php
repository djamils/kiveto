<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Clinic;

use App\Context\Clinic\Application\Command\Account\OpenClinicAccount\OpenClinicAccount;
use App\Context\Clinic\Application\Command\Clinic\CreateClinic\CreateClinic;
use App\Context\Clinic\Application\Command\Clinic\UpdateClinicLetterhead\UpdateClinicLetterhead;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Feature\ConsoleFeatures;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\ConsoleFormat;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use Psr\Log\LoggerInterface;

/**
 * B5 — « Créer la clinique et envoyer N invitations »: the clinic, its
 * letterhead, its account, then each invitation (the first veterinarian
 * administers the clinic). An invitation that fails does not undo the
 * clinic: it is reported and can be sent again from the sheet.
 */
final readonly class ClinicCreator
{
    /** The pilot ends at the end of the third month after the creation month. */
    private const int PILOT_MONTHS = 3;

    public function __construct(
        private CommandBusInterface $commandBus,
        private ConsoleStaffInviter $inviter,
        private ConsoleAuditTrail $auditTrail,
        private ConsoleFeatures $features,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{clinicId: string, invited: list<array{name: string, email: string, role: string, sent: bool, error: ?string}>}
     */
    public function create(ClinicDraft $draft): array
    {
        $company = $draft->company;
        $place   = $draft->place;
        $same    = '1' === ($place['sameAddress'] ?? '1');

        $clinicId = $this->commandBus->dispatch(new CreateClinic(
            name: $place['name'],
            slug: $place['slug'],
            timeZone: $place['timeZone'] ?? 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            currencyCode: 'EUR',
        ));
        \assert(\is_string($clinicId));

        $this->commandBus->dispatch(new UpdateClinicLetterhead(
            clinicId: $clinicId,
            legalName: self::text($company['legalName'] ?? null),
            siret: self::text($company['siret'] ?? null),
            vatNumber: self::text($company['vat'] ?? null),
            apeCode: self::text($company['naf'] ?? null),
            streetLine1: self::text($same ? ($company['street'] ?? null) : ($place['street'] ?? null)),
            postalCode: self::text($same ? ($company['postalCode'] ?? null) : ($place['postalCode'] ?? null)),
            city: self::text($same ? ($company['city'] ?? null) : ($place['city'] ?? null)),
            countryCode: 'FR',
            phone: ConsoleFormat::phone($place['phone'] ?? null),
            email: self::text($place['email'] ?? null),
        ));

        $pilotEnd = 'pilot' === $draft->plan
            ? $this->clock->now()->modify('first day of this month')->modify(\sprintf('+%d months', self::PILOT_MONTHS))->modify('last day of this month')->format('Y-m-d')
            : null;

        $this->commandBus->dispatch(new OpenClinicAccount($clinicId, $draft->plan, $pilotEnd, self::text($place['parallelSoftware'] ?? null)));
        $this->features->applyStarting($clinicId, $draft->features);

        $this->auditTrail->record(
            'clinic.created',
            AuditCategory::CLINICS,
            \sprintf('Clinique créée : %s', $place['name']),
            clinicId: $clinicId,
            clinicName: $place['name'],
            details: ['Tenant' => $place['slug'], 'SIRET' => $company['siret'] ?? '—', 'Formule' => 'pilot' === $draft->plan ? 'Pilote' : 'Payant'],
        );

        $invited       = [];
        $administrator = false;
        foreach ($draft->team as $person) {
            $role          = ClinicMemberRole::from($person['role']);
            $admin         = !$administrator && ClinicMemberRole::VETERINARY === $role;
            $administrator = $administrator || $admin;
            $name          = trim($person['firstName'] . ' ' . $person['lastName']);

            try {
                $sent      = $this->inviter->invite($clinicId, $person['firstName'], $person['lastName'], $person['email'], $role, $admin);
                $invited[] = ['name' => $name, 'email' => $person['email'], 'role' => ConsoleDirectory::roleLabel($role->value), 'sent' => $sent, 'error' => null];
            } catch (InvitationRefusedException $exception) {
                $this->logger->warning('A person of a new clinic could not be invited.', ['clinicId' => $clinicId, 'exception' => $exception]);
                $invited[] = ['name' => $name, 'email' => $person['email'], 'role' => ConsoleDirectory::roleLabel($role->value), 'sent' => false, 'error' => $exception->getMessage()];
            }
        }

        return ['clinicId' => $clinicId, 'invited' => $invited];
    }

    private static function text(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return '' === $value ? null : $value;
    }
}
