<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Consultation\Application\Query\ListConsultationsForPatient\ConsultationHistoryRow;
use App\Context\Consultation\Application\Query\ListConsultationsForPatient\ListConsultationsForPatient;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\GetPatientAnimalLink;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Builds the patient banner and the consultation history of the cockpit.
 *
 * A patient with no linked animal (not reconciled yet) still renders: the
 * provisional label and observed species stand in for the animal record, no
 * allergy badge is shown, and the history falls back to that patient alone.
 */
final readonly class CockpitPatientBuilder
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{
     *     patient: array<string, mixed>,
     *     owner: array<string, mixed>|null,
     *     history: list<array<string, mixed>>
     * }
     */
    public function build(string $patientId, string $clinicId, string $currentConsultationId): array
    {
        $link   = $this->animalLink($patientId, $clinicId);
        $animal = null !== $link?->animalId ? $this->animal($link->animalId, $clinicId) : null;
        $owner  = null !== $animal ? $this->owner($animal, $clinicId) : null;

        return [
            'patient' => $this->patientPayload($patientId, $link, $animal),
            'owner'   => $this->ownerPayload($owner),
            'history' => $this->history($patientId, $clinicId, $link?->animalId, $currentConsultationId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patientPayload(string $patientId, ?PatientAnimalLinkDto $link, ?AnimalView $animal): array
    {
        if (null === $animal) {
            return [
                'patientId'    => $patientId,
                'animalId'     => null,
                'isIdentified' => false,
                'name'         => null !== $link ? $link->displayLabel : 'Patient non identifié',
                'speciesLabel' => $link?->observedSpecies,
                'color'        => $link?->observedColor,
                'sexLabel'     => null,
                'sterilized'   => false,
                'breed'        => null,
                'birthDate'    => null,
                'ageLabel'     => null,
                'microchip'    => null,
                'photoUrl'     => null,
            ];
        }

        return [
            'patientId'    => $patientId,
            'animalId'     => $animal->id,
            'isIdentified' => true,
            'name'         => $animal->name,
            'speciesLabel' => self::speciesLabel($animal->species),
            'color'        => $animal->color,
            'sexLabel'     => self::sexLabel($animal->sex),
            'sterilized'   => 'neutered' === $animal->reproductiveStatus,
            'breed'        => $animal->isMixedBreed ? 'Croisé' : $animal->breedName,
            'birthDate'    => $animal->birthDate,
            'ageLabel'     => $this->ageLabel($animal->birthDate),
            'microchip'    => $animal->identification->microchipNumber,
            'photoUrl'     => $animal->photoUrl,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ownerPayload(?ClientView $owner): ?array
    {
        if (null === $owner) {
            return null;
        }

        $address = $owner->postalAddress;

        return [
            'clientId' => $owner->id,
            'fullName' => $owner->fullName(),
            'phone'    => self::contactValue($owner, 'phone'),
            'email'    => self::contactValue($owner, 'email'),
            'address'  => null !== $address
                ? trim(\sprintf(
                    '%s %s %s',
                    $address->streetLine1,
                    $address->postalCode ?? '',
                    $address->city,
                ))
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function history(
        string $patientId,
        string $clinicId,
        ?string $animalId,
        string $currentConsultationId,
    ): array {
        $rows = $this->queryBus->ask(new ListConsultationsForPatient(
            clinicId: $clinicId,
            patientId: $patientId,
            animalId: $animalId,
            excludeConsultationId: $currentConsultationId,
        ));

        if (!\is_array($rows)) {
            return [];
        }

        $history = [];

        foreach ($rows as $row) {
            if (!$row instanceof ConsultationHistoryRow) {
                continue;
            }

            $history[] = [
                'consultationId' => $row->consultationId,
                'startedAtUtc'   => $row->startedAtUtc,
                'status'         => $row->status,
                'motif'          => $row->chiefComplaint,
                'summary'        => $row->summary,
                'weightKg'       => $row->weightKg,
                'url'            => $this->urlGenerator->generate(
                    'clinic_consultation_details',
                    ['id' => $row->consultationId],
                ),
            ];
        }

        return $history;
    }

    private function animalLink(string $patientId, string $clinicId): ?PatientAnimalLinkDto
    {
        $link = $this->queryBus->ask(new GetPatientAnimalLink($patientId, $clinicId));

        return $link instanceof PatientAnimalLinkDto ? $link : null;
    }

    private function animal(string $animalId, string $clinicId): ?AnimalView
    {
        try {
            $animal = $this->queryBus->ask(new GetAnimalById($clinicId, $animalId));
        } catch (\Throwable) {
            return null;
        }

        return $animal instanceof AnimalView ? $animal : null;
    }

    private function owner(AnimalView $animal, string $clinicId): ?ClientView
    {
        $ownerId = $animal->primaryOwnerClientId();

        if (null === $ownerId) {
            return null;
        }

        try {
            $client = $this->queryBus->ask(new GetClientById($clinicId, $ownerId));
        } catch (\Throwable) {
            return null;
        }

        return $client instanceof ClientView ? $client : null;
    }

    private static function contactValue(ClientView $owner, string $type): ?string
    {
        $fallback = null;

        foreach ($owner->contactMethods as $method) {
            if ($method->type !== $type) {
                continue;
            }

            if ($method->isPrimary) {
                return $method->value;
            }

            $fallback ??= $method->value;
        }

        return $fallback;
    }

    private static function speciesLabel(string $species): string
    {
        return match (mb_strtolower($species)) {
            'dog'   => 'Chien',
            'cat'   => 'Chat',
            'nac'   => 'NAC',
            default => 'Autre',
        };
    }

    private static function sexLabel(string $sex): ?string
    {
        return match (mb_strtolower($sex)) {
            'male'   => '♂',
            'female' => '♀',
            default  => null,
        };
    }

    private function ageLabel(?string $birthDate): ?string
    {
        if (null === $birthDate) {
            return null;
        }

        try {
            $birth = new \DateTimeImmutable($birthDate);
        } catch (\Exception) {
            return null;
        }

        $interval = $birth->diff($this->clock->now());

        if ($interval->y > 0) {
            return \sprintf('%d an%s', $interval->y, $interval->y > 1 ? 's' : '');
        }

        if ($interval->m > 0) {
            return \sprintf('%d mois', $interval->m);
        }

        return \sprintf('%d jour%s', $interval->d, $interval->d > 1 ? 's' : '');
    }
}
