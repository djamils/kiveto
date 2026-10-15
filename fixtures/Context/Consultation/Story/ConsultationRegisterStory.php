<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Consultation\Story;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Consultation\Application\Command\AddDiagnosis\AddDiagnosis;
use App\Context\Consultation\Application\Command\AddPlanAction\AddPlanAction;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\RecordVitals\RecordVitals;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Paris register of consultations
 * (docs/frontend-theme/kiveto-theme/consultations-v-topbar.html) in every
 * state its rows and sheet draw, on top of the visits the other stories leave:
 *
 * - « À clôturer »: consultations left open from previous days, the patient
 *   long gone (the header counter and its filter);
 * - emergencies (« Urgence » chip), surgeries (« Chirurgie ») and laboratory
 *   analyses (« Analyses »), each reachable through the « Type » filter;
 * - the three practitioners, a weight, a diagnosis and a follow-up
 *   appointment on the sheet.
 *
 * Every visit goes through the command bus — admission, consultation, acts —
 * then is moved back to its day. Loaded before the billing register, which
 * invoices the closed ones like any other visit.
 */
final class ConsultationRegisterStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    /** Paris animals with a fixed id and nothing on the board today. */
    private const array ANIMALS = [
        'falco'  => ['01936e19-d2d2-7000-8000-000008000002', 'Falco'],
        'gaia'   => ['01936e19-d2d2-7000-8000-000010000001', 'Gaïa'],
        'sushi'  => ['01936e19-d2d2-7000-8000-00000e000001', 'Sushi'],
        'plume'  => ['01936e19-d2d2-7000-8000-000007000001', 'Plume'],
        'nori'   => ['01936e19-d2d2-7000-8000-00000a000001', 'Nori'],
        'tao'    => ['01936e19-d2d2-7000-8000-00000f000002', 'Tao'],
        'hermes' => ['01936e19-d2d2-7000-8000-000017000001', 'Hermès'],
        'kiwi'   => ['01936e19-d2d2-7000-8000-00000d000001', 'Kiwi'],
        'perle'  => ['01936e19-d2d2-7000-8000-000008000001', 'Perle'],
    ];

    /** @var array<string, string> e-mail => user id */
    private array $users = [];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        $today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $r = 'vet@kiveto.local';
        $b = 'vet2@kiveto.local';
        $d = 'contractor@kiveto.local';

        // [animal, vet, when, triage, motifs, acts, diagnosis, weight, follow-up days, closed]
        $visits = [
            ['hermes', $r, '-4 days 09:40', 'vital_emergency', ['Intoxication au chocolat'], ['CONS_STD', 'BILAN_SANG'], 'Intoxication à la théobromine, prise en charge précoce', 12.4, 2, true],
            ['kiwi', $b, '-5 days 15:15', 'standard', ['Vaccination annuelle'], ['VACC_CHPPIL', 'CONS_STD'], 'Examen clinique normal, vaccins à jour', 6.5, 365, true],
            ['tao', $d, '-3 days 11:00', 'standard', ['Détartrage sous AG'], ['DETARTRAGE'], 'Tartre important, deux extractions', 22.0, null, true],
            ['sushi', $d, '-2 days 10:20', 'standard', ['Bilan annuel'], ['CONS_STD', 'BILAN_SANG'], 'Bilan sanguin dans les normes', 4.1, null, true],
            ['plume', $r, '-2 days 16:30', 'standard', ['Insuffisance rénale chronique', 'Suivi'], ['CONS_SUIVI', 'BILAN_RENAL'], 'IRC stade IRIS 2, stable', 3.9, 90, false],
            ['falco', $r, '-1 day 08:30', 'emergency', ['Trauma / AVP'], ['CONS_STD', 'RADIO_MEMBRE'], 'Fracture fermée du radius gauche', 31.0, 7, true],
            ['gaia', $b, '-1 day 14:00', 'standard', ['Stérilisation'], ['STERIL_FEMELLE'], 'Ovariectomie sans complication', 3.2, 10, true],
            ['nori', $b, '-1 day 17:45', 'priority', ['Boiterie postérieure'], ['CONS_STD'], null, null, null, false],
            ['perle', $d, '-1 day 18:20', 'emergency', ['Dyspnée'], ['CONS_STD', 'RADIO_MEMBRE'], 'Épanchement pleural, ponction réalisée', 4.8, 3, true],
        ];

        foreach ($visits as [$animal, $vet, $when, $triage, $motifs, $acts, $diagnosis, $weight, $followUp, $closed]) {
            $this->visit($animal, $this->user($vet), $today->modify($when), $triage, $motifs, $acts, $diagnosis, $weight, $followUp, $closed);
        }

        $this->entityManager->clear();
    }

    /**
     * @param list<string> $motifs
     * @param list<string> $acts   catalogue codes
     */
    private function visit(
        string $animal,
        string $vetUserId,
        \DateTimeImmutable $at,
        string $triage,
        array $motifs,
        array $acts,
        ?string $diagnosis,
        ?float $weight,
        ?int $followUpDays,
        bool $closed,
    ): void {
        [$animalId, $animalName] = self::ANIMALS[$animal];

        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::PARIS,
            triageLevel: $triage,
            knownAnimalId: $animalId,
            animalName: $animalName,
            provisionalLabel: null,
            triageNotes: $motifs[0],
        ));
        \assert(\is_string($admissionId));

        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission(admissionId: $admissionId, startedByUserId: $vetUserId));
        \assert(\is_string($consultationId));

        $this->commandBus->dispatch(new RecordChiefComplaint(consultationId: $consultationId, clinicId: self::PARIS, chiefComplaint: $motifs[0]));
        $this->commandBus->dispatch(new UpdateConsultationMotifs(consultationId: $consultationId, clinicId: self::PARIS, labels: $motifs));

        if (null !== $weight) {
            $this->commandBus->dispatch(new RecordVitals(consultationId: $consultationId, clinicId: self::PARIS, weightKg: $weight, temperatureC: 38.6));
        }

        foreach ($acts as $code) {
            [$actId, $label] = $this->act($code);
            $this->commandBus->dispatch(new AddPlanAction(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                kind: 'PERFORMED_ACT',
                description: $label,
                catalogItemId: $actId,
                catalogCode: null,
                posology: null,
                durationDays: null,
                followUpDays: null,
                quantity: 1.0,
                createdByUserId: $vetUserId,
            ));
        }

        if (null !== $followUpDays) {
            $this->commandBus->dispatch(new AddPlanAction(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                kind: 'FOLLOW_UP_APPOINTMENT',
                description: $followUpDays >= 300 ? 'Rappel vaccinal' : 'Contrôle',
                catalogItemId: null,
                catalogCode: null,
                posology: null,
                durationDays: null,
                followUpDays: $followUpDays,
                quantity: 1.0,
                createdByUserId: $vetUserId,
            ));
        }

        if (null !== $diagnosis) {
            $this->commandBus->dispatch(new AddDiagnosis(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                code: null,
                label: $diagnosis,
                certainty: 'CERTAIN',
                note: null,
                isPrimary: true,
                source: 'MANUAL',
                createdByUserId: $vetUserId,
            ));
        }

        if ($closed) {
            $this->commandBus->dispatch(new CloseConsultation(consultationId: $consultationId, closedByUserId: $vetUserId, summary: $diagnosis));
        }

        // The patient went home either way: an open consultation is then « à clôturer ».
        $this->commandBus->dispatch(new CloseAdmission(clinicId: self::PARIS, admissionId: $admissionId, closureReason: 'released_to_owner'));

        $this->backdate($consultationId, $admissionId, $at, $closed, 15 + (\strlen($animal) * 7) % 30);
    }

    private function backdate(string $consultationId, string $admissionId, \DateTimeImmutable $at, bool $closed, int $minutes): void
    {
        $start = $this->utc($at);
        $end   = $this->utc($at->modify(\sprintf('+%d minutes', $minutes)));
        $id    = static fn (string $uuid): string => Uuid::fromString($uuid)->toBinary();

        $this->connection->executeStatement(
            'UPDATE consultation__consultations SET started_at_utc = :start, created_at_utc = :start, closed_at_utc = IF(:closed, :end, NULL), updated_at_utc = :end WHERE id = :id',
            ['start' => $start, 'end' => $end, 'closed' => $closed ? 1 : 0, 'id' => $id($consultationId)],
        );
        $this->connection->executeStatement(
            'UPDATE admission__admissions SET opened_at = :start, created_at = :start, location_status_entered_at = :end, closed_at = :end, updated_at = :end WHERE id = :id',
            ['start' => $this->utc($at->modify('-10 minutes')), 'end' => $end, 'id' => $id($admissionId)],
        );
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function user(string $email): string
    {
        if (!isset($this->users[$email])) {
            $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

            if (null === $user) {
                throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
            }

            $this->users[$email] = $user->getId()->toRfc4122();
        }

        return $this->users[$email];
    }

    /**
     * @return array{0: string, 1: string} act id and name
     */
    private function act(string $code): array
    {
        $act = $this->entityManager->getRepository(ActEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::PARIS),
        ]);

        if (null === $act) {
            throw new \RuntimeException(\sprintf('Catalog act "%s" not found.', $code));
        }

        return [$act->getId()->toRfc4122(), $act->getName()];
    }
}
