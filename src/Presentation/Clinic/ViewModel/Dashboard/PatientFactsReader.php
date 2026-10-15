<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\ListAnimalWeightRecords;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\ListMedicalAlertsForAnimal;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Consultation\Application\Query\SearchConsultations\ConsultationListItemView;
use App\Context\Consultation\Application\Query\SearchConsultations\SearchConsultations;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Reads, for each identified patient of the dashboard, the facts its quick
 * look shows: the primary owner (Client), the medical alerts and the last
 * weighing (Animal), the last closed consultation (Consultation).
 */
final readonly class PatientFactsReader
{
    public function __construct(
        private QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @param array<string, AnimalView> $animals       by animal id
     * @param array<string, string>     $practitioners « Dr Lambert » by user id
     *
     * @return array<string, PatientFacts> by animal id
     */
    public function read(string $clinicId, array $animals, array $practitioners, \DateTimeImmutable $now): array
    {
        $clients = [];
        $facts   = [];

        foreach ($animals as $animalId => $animal) {
            $owner   = null;
            $ownerId = $animal->primaryOwnerClientId();

            if (null !== $ownerId) {
                if (!\array_key_exists($ownerId, $clients)) {
                    $client            = $this->queryBus->ask(new GetClientById($clinicId, $ownerId));
                    $clients[$ownerId] = $client instanceof ClientView ? $client : null;
                }

                $owner = $clients[$ownerId];
            }

            [$lastVisit, $todayConsultationId] = $this->lastVisit($clinicId, $animalId, $practitioners, $now);

            $facts[$animalId] = new PatientFacts(
                owner: null === $owner ? null : self::ownerName($owner),
                phone: null === $owner ? null : self::phone($owner),
                age: self::age($animal, $now),
                vigilance: $this->vigilance($clinicId, $animalId),
                weight: $this->weight($clinicId, $animalId, $now),
                lastVisit: $lastVisit,
                todayConsultationId: $todayConsultationId,
            );
        }

        return $facts;
    }

    /** « Mme Nguyen », « M. Costa », or the name as the client gave it. */
    public static function ownerName(ClientView $client): string
    {
        return match ($client->civility) {
            'madam'  => 'Mme ' . $client->lastName,
            'mister' => 'M. ' . $client->lastName,
            default  => $client->fullName(),
        };
    }

    /** The primary phone (or the first one) read « 06 12 34 56 78 ». */
    public static function phone(ClientView $client): ?string
    {
        $phone = null;

        foreach ($client->contactMethods as $method) {
            if ('phone' === $method->type && ($method->isPrimary || null === $phone)) {
                $phone = $method->value;
            }
        }

        return null === $phone ? null : self::formatPhone($phone);
    }

    /** « +33612345678 » reads « 06 12 34 56 78 »; anything else is kept as typed. */
    public static function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '33') && 11 === \strlen($digits)) {
            $digits = '0' . substr($digits, 2);
        }

        return 10 === \strlen($digits) ? implode(' ', str_split($digits, 2)) : $phone;
    }

    public static function age(AnimalView $animal, \DateTimeImmutable $now): ?string
    {
        if (null === $animal->birthDate) {
            return null;
        }

        $diff = new \DateTimeImmutable($animal->birthDate)->diff($now);

        return $diff->y >= 1
            ? SheetText::plural($diff->y, 'an', 'ans')
            : SheetText::plural(max(1, $diff->m), 'mois', 'mois');
    }

    /**
     * « 6,8 kg · mars », « 31,2 kg · ce matin », « 8,9 kg · 2025 ».
     *
     * @param list<WeightRecordView> $records
     */
    public static function lastWeight(array $records, \DateTimeImmutable $now): ?string
    {
        $last = null;

        foreach ($records as $record) {
            if (null === $last || $record->measuredOn > $last->measuredOn) {
                $last = $record;
            }
        }

        if (null === $last) {
            return null;
        }

        $on   = $last->measuredOn->setTimezone($now->getTimezone());
        $when = match (true) {
            $on->format('Y-m-d') === $now->format('Y-m-d') => (int) $now->format('G') < 13 ? 'ce matin' : 'aujourd\'hui',
            $on->format('Y') === $now->format('Y')         => SheetText::date($on, 'MMMM'),
            default                                        => $on->format('Y'),
        };

        return self::kilograms($last->weightGrams) . ' kg · ' . $when;
    }

    /** « 6,8 », « 0,85 » (under a kilo, two decimals). */
    public static function kilograms(int $grams): string
    {
        return number_format($grams / 1000, $grams < 1000 ? 2 : 1, ',', ' ');
    }

    /**
     * @return list<array{label: string, tone: 'a'|'r'}>
     */
    private function vigilance(string $clinicId, string $animalId): array
    {
        $alerts = $this->queryBus->ask(new ListMedicalAlertsForAnimal($clinicId, $animalId));
        \assert(\is_array($alerts));

        $out = [];

        foreach ($alerts as $alert) {
            \assert($alert instanceof MedicalAlertView);

            $label = 'ALLERGY' === $alert->kind && !str_contains(mb_strtolower($alert->label), 'allerg')
                ? 'Allergie : ' . $alert->label
                : $alert->label;

            $out[] = [
                'label' => $label,
                'tone'  => \in_array($alert->kind, ['BEHAVIOR', 'LONG_TERM_TREATMENT'], true) ? 'a' : 'r',
            ];
        }

        return $out;
    }

    private function weight(string $clinicId, string $animalId, \DateTimeImmutable $now): ?string
    {
        $records = $this->queryBus->ask(new ListAnimalWeightRecords($clinicId, $animalId));
        \assert(\is_array($records));

        return self::lastWeight(array_values(array_filter(
            $records,
            static fn (mixed $record): bool => $record instanceof WeightRecordView,
        )), $now);
    }

    /**
     * « 12 mars · vaccin · Dr Perrin », and the consultation closed today if
     * the last visit is today's.
     *
     * @param array<string, string> $practitioners
     *
     * @return array{string, ?string}
     */
    private function lastVisit(string $clinicId, string $animalId, array $practitioners, \DateTimeImmutable $now): array
    {
        $result = $this->queryBus->ask(new SearchConsultations(
            $clinicId,
            restrictToAnimalIds: [$animalId],
            statuses: ['CLOSED'],
            sort: 'datetime',
            direction: 'desc',
            limit: 1,
        ));
        \assert(\is_array($result) && \is_array($result['items'] ?? null));

        $last = $result['items'][0] ?? null;

        if (!$last instanceof ConsultationListItemView) {
            return ['Première visite', null];
        }

        $started = new \DateTimeImmutable($last->startedAtUtc)->setTimezone($now->getTimezone());
        $isToday = $started->format('Y-m-d') === $now->format('Y-m-d');
        $day     = match (true) {
            $isToday                                    => 'Aujourd\'hui',
            $started->format('Y') === $now->format('Y') => SheetText::date($started, 'd MMM'),
            default                                     => SheetText::date($started, 'd MMM yyyy'),
        };
        $motif = $last->motifs[0] ?? $last->chiefComplaint;
        $parts = [
            $day,
            null === $motif || '' === trim($motif) ? 'consultation' : mb_strtolower(trim($motif)),
            $practitioners[$last->practitionerUserId] ?? null,
        ];

        return [
            implode(' · ', array_filter($parts, static fn (?string $part): bool => null !== $part)),
            $isToday ? $last->consultationId : null,
        ];
    }
}
