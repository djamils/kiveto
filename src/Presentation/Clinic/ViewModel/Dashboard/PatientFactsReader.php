<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\ListAnimalWeightRecords\WeightRecordView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimal\MedicalAlertView;
use App\Context\Animal\Application\Query\ListMedicalAlertsForAnimals\ListMedicalAlertsForAnimals;
use App\Context\Animal\Application\Query\ListWeightRecordsForAnimals\ListWeightRecordsForAnimals;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\ListClientsByIds\ListClientsByIds;
use App\Context\Consultation\Application\Query\ListLastClosedConsultations\LastClosedConsultation;
use App\Context\Consultation\Application\Query\ListLastClosedConsultations\ListLastClosedConsultations;
use App\Presentation\Clinic\ViewModel\Shared\SheetText;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * Reads, for each identified patient of the dashboard, the facts its quick
 * look shows: the primary owner (Client), the medical alerts and the last
 * weighing (Animal), the last closed consultation (Consultation). Each fact
 * is read for all the patients at once, whatever their number.
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
        if ([] === $animals) {
            return [];
        }

        $animalIds = array_map(strval(...), array_keys($animals));
        $ownerIds  = array_values(array_unique(array_filter(array_map(static fn (AnimalView $animal): ?string => $animal->primaryOwnerClientId(), $animals))));

        $clients = $this->queryBus->ask(new ListClientsByIds($clinicId, $ownerIds));
        $alerts  = $this->queryBus->ask(new ListMedicalAlertsForAnimals($clinicId, $animalIds));
        $weights = $this->queryBus->ask(new ListWeightRecordsForAnimals($clinicId, $animalIds));
        $visits  = $this->queryBus->ask(new ListLastClosedConsultations($clinicId, $animalIds));
        \assert(\is_array($clients) && \is_array($alerts) && \is_array($weights) && \is_array($visits));
        /** @var array<string, ClientView> $clients */
        /** @var array<string, list<MedicalAlertView>> $alerts */
        /** @var array<string, list<WeightRecordView>> $weights */
        /** @var array<string, LastClosedConsultation> $visits */
        $facts = [];

        foreach ($animals as $animalId => $animal) {
            $owner = $clients[$animal->primaryOwnerClientId() ?? ''] ?? null;

            [$lastVisit, $todayConsultationId] = self::lastVisit($visits[$animalId] ?? null, $practitioners, $now);

            $facts[$animalId] = new PatientFacts(
                owner: null === $owner ? null : self::ownerName($owner),
                phone: null === $owner ? null : self::phone($owner),
                age: self::age($animal, $now),
                vigilance: self::vigilance($alerts[$animalId] ?? []),
                weight: self::lastWeight($weights[$animalId] ?? [], $now),
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
     * @param list<MedicalAlertView> $alerts
     *
     * @return list<array{label: string, tone: 'a'|'r'}>
     */
    private static function vigilance(array $alerts): array
    {
        $out = [];

        foreach ($alerts as $alert) {
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

    /**
     * « 12 mars · vaccin · Dr Perrin », and the consultation closed today if
     * the last visit is today's.
     *
     * @param array<string, string> $practitioners
     *
     * @return array{string, ?string}
     */
    private static function lastVisit(?LastClosedConsultation $last, array $practitioners, \DateTimeImmutable $now): array
    {
        if (null === $last) {
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
