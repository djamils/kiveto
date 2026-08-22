<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Document;

use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Presentation\Clinic\ViewModel\Consultation\CockpitPatientBuilder;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Gathers what the consultation report prints: the record itself, the animal and
 * its owner, and the practitioner who signs it.
 *
 * Both the report route and the bundle route need it, hence a builder rather
 * than two controllers assembling the same thing.
 */
final readonly class ConsultationReportContext
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CockpitPatientBuilder $patientBuilder,
    ) {
    }

    /**
     * @throws \DomainException when the consultation is unknown to this clinic
     *
     * @return array{
     *     context: array<string, mixed>,
     *     consultation: ConsultationDetailsDTO,
     *     subject: string,
     *     filename: string
     * }
     */
    public function build(string $consultationId, string $clinicId): array
    {
        $consultation = $this->queryBus->ask(new GetConsultationDetails($consultationId, $clinicId));
        \assert($consultation instanceof ConsultationDetailsDTO);

        $patient      = $this->patientBuilder->build($consultation->patientId, $clinicId, $consultationId);
        $practitioner = $this->practitioner($clinicId, $consultation->practitionerUserId);

        $patientName = $patient['patient']['name'] ?? null;
        $patientName = \is_string($patientName) ? $patientName : 'Patient';
        $ownerName   = $patient['owner']['fullName'] ?? null;

        return [
            'context' => [
                'consultation' => $consultation,
                'patient'      => $patient['patient'],
                'owner'        => $patient['owner'],
                'practitioner' => $practitioner,
                'startedAt'    => new \DateTimeImmutable($consultation->startedAtUtc, new \DateTimeZone('UTC')),
                'closedAt'     => null !== $consultation->closedAtUtc
                    ? new \DateTimeImmutable($consultation->closedAtUtc, new \DateTimeZone('UTC'))
                    : null,
            ],
            'consultation' => $consultation,
            'subject'      => \is_string($ownerName)
                ? \sprintf('%s / %s', $patientName, $ownerName)
                : $patientName,
            'filename' => \sprintf(
                'compte-rendu-%s-%s',
                $patientName,
                (new \DateTimeImmutable($consultation->startedAtUtc, new \DateTimeZone('UTC')))->format('Y-m-d'),
            ),
        ];
    }

    private function practitioner(string $clinicId, string $userId): ?ClinicVeterinarianItem
    {
        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians($clinicId));

        if (!\is_array($veterinarians)) {
            return null;
        }

        foreach ($veterinarians as $veterinarian) {
            if ($veterinarian instanceof ClinicVeterinarianItem && $veterinarian->userId === $userId) {
                return $veterinarian;
            }
        }

        return null;
    }
}
