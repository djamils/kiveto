<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Admission\Application\Command\CloseAdmission\CloseAdmission;
use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Command\OpenAdmissionFromAppointment\OpenAdmissionFromAppointment;
use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;
use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequest;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaire;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireOutcome;
use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequest;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaire;
use App\Context\ClientPortal\Domain\Request\PreVisitQuestionnaireRepositoryInterface;
use App\Context\ClientPortal\Domain\Request\QuestionnaireAnswers;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ClinicVeterinarianItem;
use App\Context\Clinic\Application\Query\Staff\ListClinicVeterinarians\ListClinicVeterinarians;
use App\Context\Consultation\Application\Command\AddPrescriptionLine\AddPrescriptionLine;
use App\Context\Consultation\Application\Command\CloseConsultation\CloseConsultation;
use App\Context\Consultation\Application\Command\RecordChiefComplaint\RecordChiefComplaint;
use App\Context\Consultation\Application\Command\StartConsultationFromAdmission\StartConsultationFromAdmission;
use App\Context\Consultation\Application\Command\StartConsultationFromAppointment\StartConsultationFromAppointment;
use App\Context\Prescription\Application\Command\CancelPrescription\CancelPrescription;
use App\Context\Prescription\Application\Command\IssuePrescription\IssuePrescription;
use App\Context\Prescription\Application\Command\PreparePrescriptionForConsultation\PreparePrescriptionForConsultation;
use App\Context\Prescription\Domain\Event\PrescriptionCancelledIntegrationEvent;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\Client\Story\ConsentsStory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Portal requests (slice 6), all in Paris, on the accounts of slices
 * 2–5: what the owners asked through the ClientPortal commands (bells
 * rung), what the team answered (`ResolvePortalRequest`, owner notified).
 * Lyon, the Calanques (Cassis) and the Vercors have none: an empty queue.
 *
 * | Account (phone)                 | Request                                                                     | State                                                    |
 * |---------------------------------|-----------------------------------------------------------------------------|----------------------------------------------------------|
 * | Sophie Garnier (+33612345679)   | renewal of Oscar's meloxicam, « effets indésirables »                       | **submitted**, urgent                                    |
 * |                                 | renewal of Oscar's diet                                                     | done, no message                                         |
 * |                                 | renewal of Nala's Cerenia                                                   | rejected, with message (blocks a new one for 7 days)     |
 * |                                 | renewal of Pepito's meloxicam                                               | done, with message, linked to the ordonnance written     |
 * |                                 |                                                                             | today at Pepito's visit (`resultingPrescriptionId`)       |
 * |                                 | change: Pepito's chip number                                                | **submitted**                                            |
 * |                                 | change: Coco, `other`, long note (truncation)                               | **submitted**                                            |
 * |                                 | change: Oscar's weight                                                      | rejected, with message                                   |
 * |                                 | questionnaire on Oscar's appointment in 3 days: `may_bite`, long notes     | submitted, editable                                      |
 * |                                 | questionnaire on Coco's visit 12 days ago                                   | locked (written the eve of the visit, edited after it)   |
 * | Thierry Marchand (+33612345680) | renewal of Minette's expired ordonnance                                     | **submitted**                                            |
 * |                                 | renewal of Minette's current ordonnance                                     | done, with message                                       |
 * |                                 | change: Gaston's sterilization                                              | done, with message                                       |
 * | Sophie Rey (+33612345681)       | renewal of Falco's antibiotic                                               | **submitted**                                            |
 * |                                 | renewal of Perle's ordonnance, then the ordonnance cancelled at the clinic  | closed (`prescription_cancelled`)                        |
 * |                                 | change: Falco's name                                                        | **submitted**                                            |
 * |                                 | questionnaire on Falco's appointment today, consultation started from it    | submitted, frozen (cockpit)                              |
 * | Julien Girard (+33612345678)    | change: Oslo's weight                                                       | **submitted**                                            |
 * |                                 | questionnaire on Oslo's vaccination in 9 days: calm, no symptom             | submitted, editable                                      |
 *
 * Seven open requests in Paris: the dashboard shows « N autres demandes
 * Portal ». Everything is dated by the clock of the load, except Coco's
 * questionnaire, written through the domain two days before her visit (the
 * owner's later edit attempt, through the command, records the lock).
 */
final class PortalRequestsStory extends Story
{
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    private const string OTHER_NOTE = 'Coco a fait une réaction après la dernière vermifugation : les yeux ont gonflé dans '
        . 'l’heure, puis elle a refusé ses granulés pendant deux jours. Nous pensons qu’il s’agit d’une allergie au '
        . 'produit, mais nous ne connaissons pas son nom exact (le flacon a été jeté). Pourriez-vous l’indiquer dans '
        . 'son dossier, pour que personne ne lui en redonne, et nous dire quel produit utiliser la prochaine fois ? '
        . 'Elle vit maintenant en intérieur avec un autre lapin, Caramel, qui n’est pas suivi chez vous.';

    private const string MAY_BITE_NOTES = 'Oscar ne supporte pas qu’on lui manipule les pattes arrière depuis son opération : '
        . 'il a déjà pincé un vétérinaire. Il se calme si je reste près de sa tête et qu’on lui parle doucement. '
        . 'Merci de prévoir une muselière souple taille 3, la sienne est cassée. Il boit beaucoup plus depuis deux '
        . 'semaines (environ 2 litres par jour) et se relève la nuit pour uriner, je ne sais pas si c’est lié au '
        . 'traitement. Il a encore mangé normalement ce matin. Je peux venir à jeun si une prise de sang est prévue, '
        . 'dites-le-moi la veille par SMS. Il faut arriver par la porte de côté : il a peur des chats de la salle d’attente.';

    private \DateTimeImmutable $now;

    /** @var array<string, string> veterinarian user id => display name */
    private array $veterinarians = [];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly IntegrationEventBusInterface $integrationEvents,
        private readonly PreVisitQuestionnaireRepositoryInterface $questionnaires,
        private readonly UuidGeneratorInterface $uuids,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalBookingStory::load();
        ConsentsStory::load();

        $this->now = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ));

        $veterinarians = $this->queryBus->ask(new ListClinicVeterinarians(self::PARIS));
        \assert(\is_array($veterinarians));

        foreach ($veterinarians as $vet) {
            \assert($vet instanceof ClinicVeterinarianItem);
            $this->veterinarians[$vet->userId] = $vet->displayName ?? $vet->userId;
        }

        $garnier  = $this->portalUserId(PortalUserStory::DEMO_PHONE_WITH_EMAIL);
        $marchand = $this->portalUserId(PortalDocumentsStory::MARCHAND_PHONE);
        $rey      = $this->portalUserId(PortalDocumentsStory::REY_PHONE);
        $girard   = $this->portalUserId(PortalUserStory::DEMO_PHONE);

        $this->garnier($garnier);
        $this->marchand($marchand);
        $this->rey($rey);
        $this->girard($girard);
    }

    /** Sophie Garnier: every renewal outcome, two open changes, a refused one, two questionnaires. */
    private function garnier(string $userId): void
    {
        $client   = ClientSheetStatesStory::CLIENT_BUSY_ID;
        $ordos    = $this->ordonnances($client);
        $rousseau = $this->user('vet@kiveto.local');
        $bernard  = $this->user('vet2@kiveto.local');

        // Oscar's meloxicam: side effects, the urgent one of the queue.
        $this->renewal($userId, $ordos['Oscar'][0], 'adverse_effects', 'Selles noires depuis deux jours et il vomit après le repas. Faut-il arrêter le Meloxidyl en attendant ?');

        // Oscar's diet: renewed without a word.
        $diet = $this->renewal($userId, $ordos['Oscar'][1], 'improved', 'Plus que quelques jours de croquettes.');
        $this->resolve($diet, 'done', $bernard);

        // Nala's Cerenia is given at the clinic: refused.
        $cerenia = $this->renewal($userId, $ordos['Nala'][0], 'no_improvement', 'Elle vomit de nouveau depuis hier soir.');
        $this->resolve($cerenia, 'rejected', $this->user('contractor@kiveto.local'), 'Le Cerenia injectable se fait à la clinique : prenez rendez-vous si les vomissements continuent demain.');

        // Pepito's meloxicam: renewed by the ordonnance written at his visit today.
        $pepito    = $ordos['Pepito'][0];
        $renewal   = $this->renewal($userId, $pepito, 'improved', 'Il boite beaucoup moins, mais encore un peu le matin.');
        $submitted = $this->clock->now();
        $written   = $this->visitWithOrdonnance($pepito, $rousseau, $submitted);
        $this->resolve($renewal, 'done', $rousseau, 'Nouvelle ordonnance pour 10 jours, disponible dans vos documents. Revenez nous voir s’il boite encore à la fin du traitement.', $written);

        // Changes: the chip set at his primo-vaccination, a long free note, a weight the clinic contests.
        $this->change($userId, $this->animalOf($client, 'Pepito'), 'chip_number', '250268731000453', note: 'Puce posée lors de la primo-vaccination, le numéro est sur le certificat I-CAD.');
        $this->change($userId, $this->animalOf($client, 'Coco'), 'other', 'Allergie au vermifuge', note: self::OTHER_NOTE);
        $weight = $this->change($userId, $this->animalOf($client, 'Oscar'), 'weight', '41,5', $this->now->modify('-2 days'));
        $this->resolve($weight, 'rejected', $rousseau, 'C’est bien plus que son poids relevé à la clinique : nous le repèserons à son prochain rendez-vous.');

        // Oscar's appointment in 3 days: he may bite.
        $this->questionnaire($userId, $this->nextAppointment($client, 'Oscar'), ['none'], true, 'Meloxidyl 0,1 mg/kg le matin', 'may_bite', self::MAY_BITE_NOTES);

        $this->lockedQuestionnaire($userId, $client);
    }

    /** Thierry Marchand: Minette's expired ordonnance asked again, her current one renewed, Gaston neutered. */
    private function marchand(string $userId): void
    {
        $client = ClientSheetStatesStory::CLIENT_NORMAL_ID;
        $ordos  = $this->ordonnances($client);
        \assert($ordos['Minette'][0]['validUntil'] < $this->now, 'Minette\'s first ordonnance (PortalDocumentsStory) is expired.');

        $this->renewal($userId, $ordos['Minette'][0], 'improved', 'Il nous reste un sac de k/d, elle le mange bien.');

        $current = $this->renewal($userId, $ordos['Minette'][1], 'improved', null);
        $this->resolve($current, 'done', $this->user('vet2@kiveto.local'), 'C’est fait : l’ordonnance renouvelée vous attend à l’accueil avec le sac de k/d.');

        $gaston = $this->change($userId, $this->animalOf($client, 'Gaston'), 'sterilization', 'neutered', note: 'Castré à la clinique de Cassis cet été.');
        $this->resolve($gaston, 'done', $this->user('contractor@kiveto.local'), 'Dossier de Gaston mis à jour, merci !');
    }

    /** Sophie Rey: Falco's antibiotic, Perle's ordonnance cancelled, a new name, and today's visit (cockpit). */
    private function rey(string $userId): void
    {
        $client = $this->clientOf($userId);
        $ordos  = $this->ordonnances($client);

        $this->renewal($userId, $ordos['Falco'][0], 'no_improvement', 'Il tousse encore un peu la nuit.');

        // Perle's ordonnance is cancelled at the clinic: its request closes, never notified.
        $perle = $ordos['Perle'][0];
        $this->renewal($userId, $perle, 'improved', null);
        $this->commandBus->dispatch(new CancelPrescription(self::PARIS, $perle['id'], 'Erreur de posologie, réécrite'));
        // The integration event waits on `async` for a worker: handled here at once.
        $this->integrationEvents->publish([new TransportNamesStamp(['sync'])], new PrescriptionCancelledIntegrationEvent($perle['id'], self::PARIS));

        $falco = $this->animalOf($client, 'Falco');
        $this->change($userId, $falco, 'name', 'Falko', note: 'Son nom s’écrit avec un k sur son passeport.');

        $this->cockpit($userId, $client, $falco);
    }

    /** Julien Girard, the demo account: Oslo's weight, and a calm questionnaire. */
    private function girard(string $userId): void
    {
        $client = $this->clientOf($userId);
        $oslo   = $this->animalOf($client, 'Oslo');

        $this->change($userId, $oslo, 'weight', '25,1', $this->now->modify('-1 day'), 'Pesé sur la balance de la pharmacie.');
        $this->questionnaire($userId, $this->nextAppointment($client, 'Oslo'), ['none'], false, null, 'calm', null);
    }

    /**
     * Pepito seen this morning (walk-in): a new meloxicam ordonnance, issued
     * after the renewal request (the link requires a later issue time).
     *
     * @param array{id: string, animal: string, validUntil: \DateTimeImmutable} $ordonnance
     */
    private function visitWithOrdonnance(array $ordonnance, string $vet, \DateTimeImmutable $requestedAt): string
    {
        $admissionId = $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
            clinicId: self::PARIS,
            knownAnimalId: $ordonnance['animal'],
            animalName: 'Pepito',
            triageNotes: 'Boiterie antérieure gauche, contrôle.',
        ));
        \assert(\is_string($admissionId));

        $consultationId = $this->commandBus->dispatch(new StartConsultationFromAdmission($admissionId, $vet));
        \assert(\is_string($consultationId));

        $this->commandBus->dispatch(new RecordChiefComplaint($consultationId, self::PARIS, 'Boiterie antérieure gauche, en amélioration.'));
        $this->commandBus->dispatch(new AddPrescriptionLine(
            consultationId: $consultationId,
            clinicId: self::PARIS,
            articleId: $this->articleOf($ordonnance['id']),
            dose: '0,1 mg/kg',
            frequency: '1×/j',
            durationDays: 10,
            route: 'per os',
            quantity: 1.0,
            createdByUserId: $vet,
        ));
        $this->commandBus->dispatch(new CloseConsultation($consultationId, $vet, 'Boiterie en nette amélioration. Meloxidyl prolongé 10 jours.'));
        $this->commandBus->dispatch(new CloseAdmission(self::PARIS, $admissionId, 'released_to_owner'));

        $prescriptionId = $this->commandBus->dispatch(new PreparePrescriptionForConsultation(self::PARIS, $consultationId, $vet));
        \assert(\is_string($prescriptionId));

        // Issue times are kept to the second: wait for the next one.
        while ($this->clock->now()->getTimestamp() <= $requestedAt->getTimestamp()) {
            usleep(100_000);
        }

        $this->commandBus->dispatch(new IssuePrescription(self::PARIS, $prescriptionId));

        return $prescriptionId;
    }

    /**
     * Falco's appointment in a few minutes: the questionnaire answered, then
     * Falco checked in early and the consultation started from the
     * appointment (it opens in the cockpit with the answers).
     */
    private function cockpit(string $userId, string $clientId, string $falco): void
    {
        $vet   = $this->user('vet@kiveto.local');
        $start = $this->now->modify('+30 minutes');
        $start = $start->setTime((int) $start->format('G'), intdiv((int) $start->format('i'), 15) * 15);

        $appointmentId = AppointmentEntityFactory::new()
            ->withClinicId(self::PARIS)
            ->withPractitionerUserId($vet)
            ->startingAt($start->setTimezone(new \DateTimeZone('UTC')), 20)
            ->withStatus(AppointmentStatus::PLANNED)
            ->create([
                'reason'   => 'Contrôle toux',
                'ownerId'  => Uuid::fromString($clientId),
                'animalId' => Uuid::fromString($falco),
            ])
            ->getId()
            ->toRfc4122()
        ;

        $this->questionnaire($userId, $appointmentId, ['cough', 'appetite_loss'], true, 'Synulox 500 mg, 1 comprimé matin et soir depuis 5 jours.', 'stressed', 'Il tousse surtout la nuit et après l’effort.');

        $admissionId = $this->commandBus->dispatch(new OpenAdmissionFromAppointment(self::PARIS, $appointmentId, $falco, 'Falco', 'Contrôle toux'));
        \assert(\is_string($admissionId));
        $this->commandBus->dispatch(new CheckInAppointment(self::PARIS, $appointmentId, $admissionId));
        $this->commandBus->dispatch(new StartConsultationFromAppointment($appointmentId, $vet));
    }

    /**
     * Coco's visit 12 days ago: the questionnaire written the eve before
     * (through the domain, at that date), then an edit attempt today that
     * the command answers LOCKED, recording the lock.
     */
    private function lockedQuestionnaire(string $userId, string $clientId): void
    {
        $appointment = $this->connection->fetchAssociative(
            "SELECT BIN_TO_UUID(ap.id) AS id, ap.starts_at_utc FROM scheduling__appointments ap
             JOIN animal__animals a ON a.id = ap.animal_id
             WHERE ap.clinic_id = ? AND ap.owner_id = ? AND a.name = 'Coco' AND ap.status = 'COMPLETED' AND ap.starts_at_utc < ?
             ORDER BY ap.starts_at_utc DESC LIMIT 1",
            [$this->binary(self::PARIS), $this->binary($clientId), $this->utc($this->now)],
        );
        \assert(\is_array($appointment), 'PortalBookingStory gives Coco a past visit.');

        $appointmentId = RowAccessor::string($appointment, 'id');
        $eve           = new \DateTimeImmutable(RowAccessor::string($appointment, 'starts_at_utc'), new \DateTimeZone('UTC'))->modify('-1 day')->setTime(19, 30);

        $this->questionnaires->save(PreVisitQuestionnaire::submit(
            PortalRequestId::fromString($this->uuids->generate()),
            self::PARIS,
            $clientId,
            $userId,
            $appointmentId,
            PreVisitQuestionnaire::VERSION,
            QuestionnaireAnswers::of(['appetite_loss', 'diarrhea'], false, null, 'stressed', 'Elle mange moins de foin depuis trois jours.'),
            $eve,
        ));

        $outcome = $this->submitQuestionnaire($userId, $appointmentId, ['none'], false, null, 'calm', 'Tout va mieux depuis la visite.');
        \assert(SubmitQuestionnaireOutcome::LOCKED === $outcome);
    }

    /**
     * @param list<string> $symptoms
     */
    private function questionnaire(string $userId, string $appointmentId, array $symptoms, bool $treated, ?string $treatment, string $temperament, ?string $notes): void
    {
        $outcome = $this->submitQuestionnaire($userId, $appointmentId, $symptoms, $treated, $treatment, $temperament, $notes);
        \assert(SubmitQuestionnaireOutcome::SUBMITTED === $outcome);
    }

    /**
     * @param list<string> $symptoms
     */
    private function submitQuestionnaire(string $userId, string $appointmentId, array $symptoms, bool $treated, ?string $treatment, string $temperament, ?string $notes): SubmitQuestionnaireOutcome
    {
        $outcome = $this->commandBus->dispatch(new SubmitQuestionnaire(
            userId: $userId,
            clinicId: self::PARIS,
            appointmentId: $appointmentId,
            version: PreVisitQuestionnaire::VERSION,
            symptoms: $symptoms,
            hasOngoingTreatment: $treated,
            ongoingTreatmentDetails: $treatment,
            temperament: $temperament,
            notes: $notes,
        ));
        \assert($outcome instanceof SubmitQuestionnaireOutcome);

        return $outcome;
    }

    /**
     * @param array{id: string, animal: string, validUntil: \DateTimeImmutable} $ordonnance
     */
    private function renewal(string $userId, array $ordonnance, string $condition, ?string $note): string
    {
        $id = $this->commandBus->dispatch(new SubmitRenewalRequest($userId, self::PARIS, $ordonnance['id'], $condition, $note));
        \assert(\is_string($id));

        return $id;
    }

    private function change(string $userId, string $petId, string $field, string $value, ?\DateTimeImmutable $measuredOn = null, ?string $note = null): string
    {
        $id = $this->commandBus->dispatch(new SubmitPetChangeRequest($userId, self::PARIS, $petId, $field, $value, $measuredOn?->setTime(0, 0), $note));
        \assert(\is_string($id));

        return $id;
    }

    private function resolve(string $requestId, string $outcome, string $vet, ?string $message = null, ?string $resultingPrescriptionId = null): void
    {
        $status = $this->commandBus->dispatch(new ResolvePortalRequest(
            clinicId: self::PARIS,
            requestId: $requestId,
            outcome: $outcome,
            actorUserId: $vet,
            actorName: $this->veterinarians[$vet],
            message: $message,
            resultingPrescriptionId: $resultingPrescriptionId,
        ));
        \assert($outcome === $status);
    }

    /**
     * The ordonnances listed on the client's Portal, by pet, oldest first.
     *
     * @return array<string, list<array{id: string, animal: string, validUntil: \DateTimeImmutable}>>
     */
    private function ordonnances(string $clientId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(p.id) AS id, BIN_TO_UUID(p.patient_animal_id) AS animal, a.name, p.valid_until
             FROM client_portal__published_documents d
             JOIN prescription__prescriptions p ON p.id = d.source_ref AND p.clinic_id = d.clinic_id
             JOIN animal__animals a ON a.id = p.patient_animal_id
             WHERE d.clinic_id = ? AND d.client_id = ? AND d.type = 'prescription' AND d.status = 'available' AND p.status = 'ISSUED'
             ORDER BY p.issued_at, p.id",
            [$this->binary(self::PARIS), $this->binary($clientId)],
        );

        $byPet = [];

        foreach ($rows as $row) {
            $byPet[RowAccessor::string($row, 'name')][] = [
                'id'         => RowAccessor::string($row, 'id'),
                'animal'     => RowAccessor::string($row, 'animal'),
                'validUntil' => new \DateTimeImmutable(RowAccessor::string($row, 'valid_until'), new \DateTimeZone('UTC')),
            ];
        }

        return $byPet;
    }

    /** The article of the ordonnance's first line. */
    private function articleOf(string $prescriptionId): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(article_id) FROM prescription__prescription_lines WHERE prescription_id = ? AND article_id IS NOT NULL LIMIT 1',
            [$this->binary($prescriptionId)],
        );
        \assert(\is_string($id));

        return $id;
    }

    /** The pet's first upcoming appointment that holds its slot. */
    private function nextAppointment(string $clientId, string $petName): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(ap.id) FROM scheduling__appointments ap JOIN animal__animals a ON a.id = ap.animal_id
             WHERE ap.clinic_id = ? AND ap.owner_id = ? AND a.name = ? AND ap.status IN ('PLANNED', 'PENDING_CLINIC_CONFIRMATION') AND ap.starts_at_utc > ?
             ORDER BY ap.starts_at_utc LIMIT 1",
            [$this->binary(self::PARIS), $this->binary($clientId), $petName, $this->utc($this->now)],
        );
        \assert(\is_string($id), \sprintf('%s has an upcoming appointment.', $petName));

        return $id;
    }

    private function animalOf(string $clientId, string $name): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(a.id) FROM animal__animals a JOIN animal__ownerships o ON o.animal_id = a.id AND o.role = 'primary'
             WHERE o.client_id = ? AND a.name = ? LIMIT 1",
            [$this->binary($clientId), $name],
        );
        \assert(\is_string($id), \sprintf('The dataset has no animal "%s" for client %s.', $name, $clientId));

        return $id;
    }

    /** The Paris client file the account is linked to (the oldest link). */
    private function clientOf(string $userId): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(client_id) FROM client_portal__client_links
             WHERE user_id = ? AND clinic_id = ? AND revoked_at IS NULL ORDER BY linked_at, id LIMIT 1',
            [$this->binary($userId), $this->binary(self::PARIS)],
        );
        \assert(\is_string($id));

        return $id;
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), \sprintf('The Portal dataset creates the owner account %s.', $phone));

        return $id;
    }

    private function user(string $email): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'CLINIC' AND email = ?", [$email]);
        \assert(\is_string($id), \sprintf('The clinic dataset creates the staff member %s.', $email));
        \assert(isset($this->veterinarians[$id]), \sprintf('%s is a veterinarian of Paris.', $email));

        return $id;
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
