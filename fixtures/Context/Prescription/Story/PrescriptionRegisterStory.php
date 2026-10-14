<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Prescription\Story;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Command\UpdateConsultationMotifs\UpdateConsultationMotifs;
use App\Context\Prescription\Application\Command\AllowPrescriptionRenewals\AllowPrescriptionRenewals;
use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescription;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Context\Prescription\Application\Command\RenewPrescription\RenewPrescription;
use App\Context\Prescription\Application\Command\SetPrescriptionNotes\SetPrescriptionNotes;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Paris register of ordonnances at a realistic size
 * (docs/frontend-theme/kiveto-theme/ordonnances-v-topbar_3.html): two pages
 * over « Tout », about twenty over the default « 30 jours », and every state
 * the register and its sheet draw:
 *
 * - signed today, this week and over the past months, by the three
 *   practitioners (each its own avatar colour);
 * - a long ordonnance of seven medicines (the « +5 » of the column);
 * - renewable ones, some already renewed (history of the sheet);
 * - a controlled substance (28 days) still valid and a narcotic one expired;
 * - ordinary ones expired after their year;
 * - cancelled ones, one of them rewritten the same morning;
 * - a draft waiting in a consultation still in progress (« 1 brouillon en
 *   consultation » under the title).
 *
 * Each ordonnance comes from a real visit — admission, consultation, its
 * medicines — through the command bus, then moved back to its day, so the
 * consultation it links to, the animal's history and the numbering agree.
 * Loaded last, after the stories whose visits share these animals.
 */
final class PrescriptionRegisterStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    /** Paris animals with a fixed id and nothing on the board today. */
    private const array ANIMALS = [
        'falco'    => ['01936e19-d2d2-7000-8000-000008000002', 'Falco'],
        'sushi'    => ['01936e19-d2d2-7000-8000-00000e000001', 'Sushi'],
        'gaia'     => ['01936e19-d2d2-7000-8000-000010000001', 'Gaïa'],
        'plume'    => ['01936e19-d2d2-7000-8000-000007000001', 'Plume'],
        'nori'     => ['01936e19-d2d2-7000-8000-00000a000001', 'Nori'],
        'rocky'    => ['01936e19-d2d2-7000-8000-000013000001', 'Rocky'],
        'milo'     => ['01936e19-d2d2-7000-8000-000014000001', 'Milo'],
        'perle'    => ['01936e19-d2d2-7000-8000-000008000001', 'Perle'],
        'mochi'    => ['01936e19-d2d2-7000-8000-000003000001', 'Mochi'],
        'cookie'   => ['01936e19-d2d2-7000-8000-00001a000001', 'Cookie'],
        'nala'     => ['01936e19-d2d2-7000-8000-000004000001', 'Nala'],
        'rio'      => ['01936e19-d2d2-7000-8000-000006000001', 'Rio'],
        'pixel'    => ['01936e19-d2d2-7000-8000-00000c000001', 'Pixel'],
        'balto'    => ['01936e19-d2d2-7000-8000-000009000001', 'Balto'],
        'tao'      => ['01936e19-d2d2-7000-8000-00000f000002', 'Tao'],
        'pacha'    => ['01936e19-d2d2-7000-8000-000001000001', 'Pacha'],
        'biscotte' => ['01936e19-d2d2-7000-8000-000016000001', 'Biscotte'],
        'hermes'   => ['01936e19-d2d2-7000-8000-000017000001', 'Hermès'],
        'tornade'  => ['01936e19-d2d2-7000-8000-000018000001', 'Tornade'],
        'lila'     => ['01936e19-d2d2-7000-8000-000014000002', 'Lila'],
        'choco'    => ['01936e19-d2d2-7000-8000-00000a000002', 'Choco'],
        'kiwi'     => ['01936e19-d2d2-7000-8000-00000d000001', 'Kiwi'],
    ];

    private const string NOTE = "Administrer au cours du repas.\nEn cas de vomissements ou de diarrhée, interrompre et appeler la clinique.";

    /** @var array<string, string> e-mail => user id */
    private array $users = [];

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $r = 'vet@kiveto.local';
        $b = 'vet2@kiveto.local';
        $d = 'contractor@kiveto.local';

        // [animal, vet, when, motifs, medicines, outcome, renewals]
        // medicine: [catalog code, dose, frequency, days, route, quantity]
        // outcome: issued | renewed | cancelled:<reason>
        $visits = [
            ['rocky', $r, '-485 days 10:15', ['Plaie de morsure'], [['SYNULOX-500', '1 cp', '2×/j', 7, 'per os', 14.0]], 'cancelled:Traitement modifié', 0],
            ['milo', $r, '-430 days 14:30', ['Antiparasitaire'], [['STRONGHOLD-45', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'issued', 0],
            ['cookie', $d, '-400 days 09:20', ['Prurit saisonnier'], [['APOQUEL-36', '1 cp', '2×/j', 14, 'per os', 28.0]], 'issued', 0],
            ['falco', $b, '-100 days 10:30', ['Toux de chenil'], [['SYNULOX-500', '1 cp', '2×/j', 7, 'per os', 14.0]], 'issued', 0],
            ['choco', $d, '-75 days 09:00', ['Abcès dentaire'], [['BAYTRIL-10', '0,25 mL', '1×/j', 7, 'per os', 1.0]], 'issued', 0],
            ['lila', $r, '-60 days 16:00', ['Vomissements'], [['CERENIA-10', '1 mg/kg', '1×/j', 3, 'SC', 1.0]], 'issued', 0],
            ['sushi', $b, '-45 days 14:20', ['Sédation pour soins'], [['KETAMINE-100', '5 mg/kg', '1×', null, 'IM', 1.0]], 'issued', 0],
            ['kiwi', $r, '-35 days 10:40', ['Boiterie'], [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 5, 'per os', 1.0]], 'issued', 0],
            ['nori', $d, '-27 days 11:30', ['Cystite idiopathique'], [['CYSTAID-PLUS', '1 gélule', '1×/j', 30, 'per os', 1.0]], 'issued', 1],
            ['hermes', $r, '-24 days 15:00', ['Antiparasitaire'], [['STRONGHOLD-45', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'renewed', 3],
            ['pacha', $b, '-21 days 17:10', ['Pyodermite'], [['SYNULOX-500', '1 cp', '2×/j', 21, 'per os', 42.0]], 'issued', 0],
            ['mochi', $r, '-18 days 09:20', ['Gastrite'], [['CERENIA-10', '1 mg/kg', '1×/j', 4, 'SC', 1.0]], 'issued', 0],
            ['gaia', $d, '-15 days 10:00', ['Antiparasitaire'], [['FRONTLINE-CB', '1 pipette', '1×/mois', null, 'spot-on', 6.0]], 'issued', 2],
            ['rio', $b, '-12 days 16:30', ['Dermatite atopique'], [['APOQUEL-36', '1 cp', '2×/j', 14, 'per os', 60.0], ['FRONTLINE-CB', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'renewed', 2],
            ['tornade', $r, '-10 days 11:45', ['Examen sous sédation'], [['MEDETO-1', '10 µg/kg', '1×', null, 'IM', 1.0]], 'issued', 0],
            ['plume', $d, '-9 days 14:00', ['Antiparasitaire'], [['STRONGHOLD-45', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'issued', 0],
            ['biscotte', $b, '-7 days 09:30', ['Boiterie postérieure'], [['MELOXIDYL-1', '0,2 mg/kg', '1×/j', 5, 'per os', 1.0]], 'cancelled:Erreur de saisie — refaite', 0],
            ['biscotte', $b, '-7 days 09:50', ['Boiterie postérieure'], [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 10, 'per os', 1.0], ['CERENIA-10', '1 mg/kg', '1×/j', 2, 'SC', 1.0]], 'issued', 0],
            ['tao', $r, '-4 days 18:20', ['Plaie de coussinet'], [['SYNULOX-500', '1 cp ½', '2×/j', 6, 'per os', 18.0], ['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 5, 'per os', 1.0]], 'issued', 0],
            ['perle', $d, '-3 days 10:10', ['Cystite idiopathique'], [['CYSTAID-PLUS', '1 gélule', '1×/j', 30, 'per os', 1.0], ['FOOD-HILLS-KD', '55 g', '1×/j', 30, 'per os', 1.0]], 'issued', 0],
            ['nala', $r, '-2 days 15:40', ['Dermatite atopique', 'Suivi'], [['APOQUEL-36', '1 cp', '1×/j', 30, 'per os', 30.0], ['FRONTLINE-CB', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'issued', 5],
            ['balto', $r, '-2 days 16:40', ['Insuffisance cardiaque', 'Arthrose', 'Dermatite'], [
                ['VETMEDIN-125', '1 cp ½', '2×/j', null, 'per os', 100.0],
                ['MELOXIDYL-1', '0,8 mL', '1×/j', 10, 'per os', 2.0],
                ['APOQUEL-36', '½ cp', '2×/j', 14, 'per os', 20.0],
                ['FRONTLINE-CB', '1 pipette', '1×/mois', null, 'spot-on', 3.0],
                ['CERENIA-10', '1 mg/kg', '1×/j', 3, 'SC', 1.0],
                ['SYNULOX-500', '1 cp', '2×/j', 7, 'per os', 14.0],
                ['FOOD-RC', '100 g', '2×/j', 30, 'per os', 2.0],
            ], 'issued', 3],
            ['milo', $b, '-1 day 11:20', ['Dermatite allergique'], [['STRONGHOLD-45', '1 pipette', '1×/mois', null, 'spot-on', 3.0]], 'issued', 2],
            ['pixel', $r, '-1 day 16:05', ['Gastro-entérite'], [['CERENIA-10', '1 mg/kg', '1×/j', 3, 'SC', 1.0]], 'issued', 0],
            ['rocky', $d, '-1 day 17:50', ['Plaie de morsure'], [['CONVENIA-80', '8 mg/kg', '1×', null, 'SC', 1.0], ['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 4, 'per os', 1.0]], 'issued', 0],
            ['cookie', $b, '08:40', ['Boiterie antérieure gauche'], [['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 10, 'per os', 2.0]], 'issued', 0],
            ['falco', $r, '09:15', ['Otite externe droite'], [['SYNULOX-500', '1 cp', '2×/j', 7, 'per os', 14.0], ['MELOXIDYL-1', '0,1 mg/kg', '1×/j', 5, 'per os', 1.0]], 'issued', 0],
        ];

        foreach ($visits as [$animal, $vet, $when, $motifs, $medicines, $outcome, $renewals]) {
            $this->visit($animal, $this->user($vet), $when, $motifs, $medicines, $outcome, $renewals);
        }

        $this->draftsInProgress();
    }

    /**
     * @param list<string>                                                               $motifs
     * @param list<array{0: string, 1: string, 2: string, 3: ?int, 4: string, 5: float}> $medicines
     */
    private function visit(string $animal, string $vetUserId, string $when, array $motifs, array $medicines, string $outcome, int $renewals): void
    {
        [$animalId, $animalName] = self::ANIMALS[$animal];
        $at                      = $this->today->modify($when);

        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::PARIS,
            triageLevel: 'standard',
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

        foreach ($medicines as [$code, $dose, $frequency, $days, $route, $quantity]) {
            $this->commandBus->dispatch(new AddPrescriptionLine(
                consultationId: $consultationId,
                clinicId: self::PARIS,
                articleId: $this->articleId($code),
                dose: $dose,
                frequency: $frequency,
                durationDays: $days,
                route: $route,
                quantity: $quantity,
                createdByUserId: $vetUserId,
            ));
        }

        $this->commandBus->dispatch(new CloseConsultation(consultationId: $consultationId, closedByUserId: $vetUserId, summary: 'Traitement prescrit, conseils donnés au propriétaire.'));
        $this->commandBus->dispatch(new CloseAdmission(clinicId: self::PARIS, admissionId: $admissionId, closureReason: 'released_to_owner'));

        $prescriptionId = $this->commandBus->dispatch(new PreparePrescriptionForConsultation(clinicId: self::PARIS, consultationId: $consultationId, prescriberUserId: $vetUserId));
        \assert(\is_string($prescriptionId));

        if ($renewals > 0) {
            $this->commandBus->dispatch(new AllowPrescriptionRenewals(self::PARIS, $prescriptionId, $renewals));
        }

        if (\count($medicines) > 1) {
            $this->commandBus->dispatch(new SetPrescriptionNotes(self::PARIS, $prescriptionId, self::NOTE));
        }

        $this->commandBus->dispatch(new IssuePrescription(self::PARIS, $prescriptionId));
        $this->backdate($consultationId, $admissionId, $prescriptionId, $at);
        // The backdating went around the ORM: forget what it holds, or the next
        // save would write the old dates back.
        $this->entityManager->clear();

        if ('renewed' === $outcome) {
            $this->commandBus->dispatch(new RenewPrescription(self::PARIS, $prescriptionId, $vetUserId));
        }

        if (str_starts_with($outcome, 'cancelled:')) {
            $this->commandBus->dispatch(new CancelPrescription(self::PARIS, $prescriptionId, mb_substr($outcome, 10)));
            $this->connection->executeStatement(
                'UPDATE prescription__prescriptions SET cancelled_at = :at, updated_at = :at WHERE id = :id',
                ['at' => $this->utc($at->modify('+15 minutes')), 'id' => Uuid::fromString($prescriptionId)->toBinary()],
            );
        }
    }

    /**
     * Consultations still in progress that prescribed something but have no
     * ordonnance yet: their draft waits there, as a practitioner leaves it
     * before signing at the end of the visit.
     */
    private function draftsInProgress(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS consultation_id, BIN_TO_UUID(c.practitioner_user_id) AS practitioner_user_id
             FROM consultation__consultations c
             WHERE c.clinic_id = :clinicId AND c.status = 'OPEN'
               AND EXISTS (SELECT 1 FROM consultation__prescription_lines pl WHERE pl.consultation_id = c.id)
               AND NOT EXISTS (SELECT 1 FROM prescription__prescriptions p WHERE p.consultation_id = c.id)",
            ['clinicId' => Uuid::fromString(self::PARIS)->toBinary()],
        );

        foreach ($rows as $row) {
            $this->commandBus->dispatch(new PreparePrescriptionForConsultation(
                clinicId: self::PARIS,
                consultationId: RowAccessor::string($row, 'consultation_id'),
                prescriberUserId: RowAccessor::string($row, 'practitioner_user_id'),
            ));
        }
    }

    private function backdate(string $consultationId, string $admissionId, string $prescriptionId, \DateTimeImmutable $at): void
    {
        $start = $this->utc($at);
        $end   = $this->utc($at->modify('+25 minutes'));
        $id    = static fn (string $uuid): string => Uuid::fromString($uuid)->toBinary();

        $this->connection->executeStatement(
            'UPDATE consultation__consultations SET started_at_utc = :start, created_at_utc = :start, closed_at_utc = :end, updated_at_utc = :end WHERE id = :id',
            ['start' => $start, 'end' => $end, 'id' => $id($consultationId)],
        );
        $this->connection->executeStatement(
            'UPDATE admission__admissions SET opened_at = :start, created_at = :start, location_status_entered_at = :end, closed_at = :end, updated_at = :end WHERE id = :id',
            ['start' => $this->utc($at->modify('-10 minutes')), 'end' => $end, 'id' => $id($admissionId)],
        );
        $this->connection->executeStatement(
            'UPDATE prescription__prescriptions SET issued_at = :at, created_at = :at, updated_at = :at,
                    valid_until = DATE_ADD(:at, INTERVAL validity_span_days DAY)
             WHERE id = :id',
            ['at' => $end, 'id' => $id($prescriptionId)],
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

    private function articleId(string $code): string
    {
        $article = $this->entityManager->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code,
            'tenantId' => Uuid::fromString(self::PARIS),
        ]);

        if (null === $article) {
            throw new \RuntimeException(\sprintf('Catalog article "%s" not found.', $code));
        }

        return $article->getId()->toRfc4122();
    }
}
