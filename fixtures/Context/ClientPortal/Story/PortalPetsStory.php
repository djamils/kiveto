<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\ClientPortal\Application\Command\StorePublishedDocumentFile\StorePublishedDocumentFileHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Context\ClientPortal\Domain\Document\PublishedDocument;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentId;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalDocumentReadRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPublishedDocumentRepository;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Context\Patient\Domain\ValueObject\DisplayLabelKind;
use App\Fixtures\Context\Animal\Story\PortalPetsAnimalStory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Client\Story\ClientSheetStatesStory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\PortalInvitationEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use App\Fixtures\Context\Patient\Factory\PatientEntityFactory;
use App\Fixtures\Context\Scheduling\Story\PortalPetsAppointmentsStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * The Portal pets history (slice 3): published visits on the timeline,
 * built on PortalDocumentsStory (Paris clinic). Every report row carries its
 * `consultation_id`, its `pet_ids` and the `practitioner_name` snapshot, and
 * a small real PDF under its storage key.
 *
 * | Account (phone)                 | State                                                                         |
 * |---------------------------------|-------------------------------------------------------------------------------|
 * | Sophie Garnier (+33612345679)   | 4 alive pets + Filou (deceased); Oscar has more than 20 published visits      |
 * |                                 | (closed consultations, several by Dr Hélène Morel, who left the clinic four   |
 * |                                 | months ago); Coco's referent vet is Dr Morel (her latest report)              |
 * | Thierry Marchand (+33612345680) | linked to two client files of Paris (Sésame and the sold Brio on the second); |
 * |                                 | his reports published; Gaston has a reconciled patient (two patient rows):     |
 * |                                 | his latest visit, and his latest weight, sit on the archived one              |
 * | Sophie Rey (+33612345681)       | also linked in Lyon, to a file with no animal (0 pets)                        |
 * | Julien Girard (+33612345678)    | 1 pet per clinic: Oslo (Paris), Pixel (Lyon, no visit, no vaccination)        |
 */
final class PortalPetsStory extends Story
{
    /** Sophie Rey's file in Lyon: no animal. */
    public const string REY_LYON_ID = '019a3d00-0000-7000-8000-0000000000c2';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;
    private const string TZ    = 'Europe/Paris';

    private \DateTimeImmutable $today;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly DbalPublishedDocumentRepository $documents,
        private readonly DbalDocumentReadRepository $reads,
        private readonly FileStorageInterface $files,
        private readonly ClockInterface $clock,
    ) {
    }

    public function build(): void
    {
        PortalPetsAppointmentsStory::load();

        $this->today = $this->clock->now()->setTimezone(new \DateTimeZone(self::TZ))->setTime(0, 0);

        $this->garnierHistory($this->departedVet());
        $this->gastonReconciled();

        $marchand = $this->portalUserId(PortalDocumentsStory::MARCHAND_PHONE);
        $this->link($marchand, self::PARIS, PortalPetsAnimalStory::MARCHAND_SECOND_FILE_ID, '-20 days');
        $this->reyInLyon();

        $this->publishReports($this->portalUserId(PortalUserStory::DEMO_PHONE_WITH_EMAIL), ClientSheetStatesStory::CLIENT_BUSY_ID, unread: 0);
        $this->publishReports($marchand, ClientSheetStatesStory::CLIENT_NORMAL_ID, unread: 1);
    }

    /** Dr Hélène Morel, a veterinarian who left the clinic four months ago. */
    private function departedVet(): string
    {
        $user = ClinicUserFactory::new()->withEmail('helene.morel@kiveto.local')->withPlainPassword('kiveto2026')->create();
        $id   = $user->getId()->toRfc4122();

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: self::PARIS,
            userId: $id,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Hélène',
            lastName: 'Morel',
            displayName: 'Morel',
            agendaColor: '#b07d3c',
            sortOrder: 10,
            isVisibleInAgenda: false,
            validFrom: $this->at('-6 years')->setTimezone(new \DateTimeZone('UTC')),
            validUntil: $this->at('-4 months 23:59:59')->setTimezone(new \DateTimeZone('UTC')),
            registrationNumber: '27154',
            professionalTitle: ProfessionalTitle::DR,
        ));

        return $id;
    }

    /** Oscar's long history (more than 20 published visits) and Coco's latest visit, by Dr Morel. */
    private function garnierHistory(string $morel): void
    {
        $client   = ClientSheetStatesStory::CLIENT_BUSY_ID;
        $oscar    = $this->animalOf($client, 'Oscar');
        $rousseau = $this->user('vet@kiveto.local');
        $bernard  = $this->user('vet2@kiveto.local');

        $visits = [
            ['-1080 days 10:00', $morel, ['Vaccination', 'Rappel annuel'], 'Rappel annuel.', 36.4, 'CHPPiL et rage faits.'],
            ['-1020 days 09:30', $morel, ['Otite'], 'Secoue la tête depuis trois jours.', 36.8, 'Nettoyage auriculaire, traitement local 10 jours.'],
            ['-1008 days 09:30', $morel, ['Contrôle otite'], 'Contrôle à dix jours.', 36.7, 'Guérison, arrêt du traitement.'],
            ['-960 days 11:00', $morel, ['Bilan senior'], 'Bilan annuel du chien âgé.', 37.0, 'Bilan sanguin dans les normes.'],
            ['-840 days 18:00', $bernard, ['Vomissements'], 'Vomissements après avoir avalé un os.', 37.3, 'Diète 24 h, pansement digestif.'],
            ['-780 days 17:30', $morel, ['Dermatite'], 'Se gratte le dos depuis une semaine.', 37.1, 'Antiparasitaire externe, revoir si cela persiste.'],
            ['-720 days 10:30', $morel, ['Vaccination', 'Rappel annuel'], 'Rappel annuel.', 37.2, 'CHPPiL et rage faits.'],
            ['-680 days 12:00', $rousseau, ['Boiterie'], 'Boite de l’antérieur gauche après une course.', 37.4, 'Repos une semaine.'],
            ['-640 days 08:30', $morel, ['Détartrage'], 'Mauvaise haleine, tartre important.', 37.0, 'Détartrage sous anesthésie, deux extractions.'],
            ['-628 days 18:15', $morel, ['Contrôle post-opératoire'], 'Contrôle après le détartrage.', 36.9, 'Gencives cicatrisées.'],
            ['-560 days 17:00', $bernard, ['Toux'], 'Toux depuis le retour de pension.', 36.9, 'Toux de chenil, antitussif 7 jours.'],
            ['-530 days 10:00', $morel, ['Bilan cardiaque'], 'Souffle entendu au rappel de vaccin.', 36.6, 'Souffle 2/6, à réévaluer.'],
            ['-460 days 18:30', $rousseau, ['Contrôle cicatrice'], 'Contrôle de la cicatrice du lipome.', 33.9, 'Cicatrice propre.'],
            ['-420 days 11:30', $morel, ['Vaccination', 'Rappel annuel'], 'Rappel annuel.', 34.1, 'CHPPiL et rage faits.'],
            ['-380 days 17:45', $morel, ['Suivi du poids'], 'Suivi de l’amaigrissement.', 34.0, 'Ration augmentée de 10 %.'],
            ['-280 days 09:15', $morel, ['Conjonctivite'], 'Œil droit rouge et larmoyant.', 34.3, 'Collyre 7 jours.'],
            ['-150 days 18:00', $morel, ['Contrôle arthrose'], 'Raideur au lever, moins marquée.', 34.6, 'Poursuite de l’anti-inflammatoire.'],
        ];

        foreach ($visits as [$when, $vet, $motifs, $complaint, $weight, $summary]) {
            $this->visit($oscar, $vet, $when, $motifs, $complaint, $weight, $summary);
        }

        $this->visit($this->animalOf($client, 'Coco'), $morel, '-140 days 16:30', ['Contrôle dentaire'], 'Contrôle des incisives.', 2.4, 'Incisives limées, revoir dans 3 mois.');
    }

    /**
     * Gaston was brought in by a neighbour while Thierry Marchand was away,
     * on a record of his own that the clinic then attached to Gaston: an
     * archived patient row linked to the animal next to the active one. His
     * latest visit and latest weight sit on it.
     */
    private function gastonReconciled(): void
    {
        $gaston  = $this->animalOf(ClientSheetStatesStory::CLIENT_NORMAL_ID, 'Gaston');
        $patient = Uuid::v7()->toRfc4122();
        $start   = $this->at('-45 days 19:20')->setTimezone(new \DateTimeZone('UTC'));
        $end     = $start->modify('+30 minutes');

        PatientEntityFactory::new()
            ->withId($patient)
            ->withClinicId(self::PARIS)
            ->withAnimalLinkId($gaston)
            ->archived()
            ->create(['displayLabelKind' => DisplayLabelKind::FromAnimal, 'displayLabelValue' => 'Gaston', 'createdAt' => $start, 'updatedAt' => $end->modify('+1 day')])
        ;

        ConsultationEntityFactory::new()
            ->withClinicId(self::PARIS)
            ->withPatientId($patient)
            ->withPractitionerUserId($this->user('vet@kiveto.local'))
            ->withStatus(ConsultationStatus::CLOSED)
            ->create([
                'chiefComplaint' => 'Amené par une voisine : a mangé une tablette de chocolat.',
                'summary'        => 'Vomissements provoqués, charbon activé. Surveillance 24 h à la maison.',
                'weightKg'       => '13.90',
                'startedAtUtc'   => $start,
                'closedAtUtc'    => $end,
                'createdAtUtc'   => $start,
                'updatedAtUtc'   => $end,
            ])
        ;
    }

    /** Sophie Rey is also a client in Lyon, on a file with no animal yet. */
    private function reyInLyon(): void
    {
        $since = $this->at('-10 days 10:00')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        ClientEntityFactory::new()
            ->withId(self::REY_LYON_ID)
            ->withClinicId(self::LYON)
            ->withName('Sophie', 'Rey')
            ->withCivility('madam')
            ->since($since)
            ->withPostalAddress(streetLine1: '8 rue Mercière', city: 'Lyon', countryCode: 'FR', postalCode: '69002')
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $since)
            ->active()
            ->create()
        ;
        ContactMethodEntityFactory::new()->forClient(self::REY_LYON_ID)->mobile()->phone(PortalDocumentsStory::REY_PHONE)->primary()->create();

        $this->link($this->portalUserId(PortalDocumentsStory::REY_PHONE), self::LYON, self::REY_LYON_ID, '-6 days');
    }

    /** An accepted invitation and the confirmed link it made (the owner profile already exists). */
    private function link(string $userId, string $clinicId, string $clientId, string $since): void
    {
        $linkedAt = $this->clock->now()->modify($since);
        $phone    = $this->connection->fetchOne('SELECT phone FROM identity_access__users WHERE id = ?', [Uuid::fromString($userId)->toBinary()]);
        \assert(\is_string($phone));

        PortalInvitationEntityFactory::new()
            ->forClient($clinicId, $clientId, $phone, null)
            ->sentAt($linkedAt->modify('-1 day'))
            ->acceptedBy($userId, $linkedAt)
            ->create()
        ;
        ClientLinkEntityFactory::new()->between($userId, $clinicId, $clientId)->linkedAt($linkedAt)->create();
    }

    /**
     * A closed visit at $when, signed by $vet. Written with the consultation
     * factory (Oscar is in the consultation room today, so the admission
     * commands would refuse a second walk-in), its motifs next to it.
     *
     * @param list<string> $motifs
     */
    private function visit(string $animalId, string $vet, string $when, array $motifs, string $complaint, float $weightKg, string $summary): void
    {
        $id    = Uuid::v7()->toRfc4122();
        $start = $this->at($when)->setTimezone(new \DateTimeZone('UTC'));
        $end   = $start->modify('+30 minutes');

        ConsultationEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::PARIS)
            ->withPatientId($this->activePatientOf($animalId))
            ->withPractitionerUserId($vet)
            ->withStatus(ConsultationStatus::CLOSED)
            ->create([
                'chiefComplaint' => $complaint,
                'summary'        => $summary,
                'weightKg'       => number_format($weightKg, 2, '.', ''),
                'temperatureC'   => '38.5',
                'startedAtUtc'   => $start,
                'closedAtUtc'    => $end,
                'createdAtUtc'   => $start,
                'updatedAtUtc'   => $end,
            ])
        ;

        foreach ($motifs as $position => $label) {
            $this->connection->insert('consultation__motifs', [
                'id'              => Uuid::v7()->toBinary(),
                'consultation_id' => $this->binary($id),
                'label'           => $label,
                'position'        => $position,
            ]);
        }
    }

    private function activePatientOf(string $animalId): string
    {
        $id = $this->connection->fetchOne(
            "SELECT BIN_TO_UUID(id) FROM patient__patients WHERE clinic_id = ? AND animal_link_id = ? AND status = 'active' LIMIT 1",
            [$this->binary(self::PARIS), $this->binary($animalId)],
        );
        \assert(\is_string($id), 'The visits of the clinic dataset created the patient of the animal.');

        return $id;
    }

    /**
     * Shares every closed visit of the client's animals not yet on the
     * Portal (a report row and its PDF), as « Partager sur le Portal » does.
     * The account has read all of them but the `$unread` newest.
     */
    private function publishReports(string $userId, string $clientId, int $unread): void
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT BIN_TO_UUID(c.id) AS id, c.started_at_utc, c.closed_at_utc, BIN_TO_UUID(p.animal_link_id) AS animal, BIN_TO_UUID(p.id) AS patient, a.name AS animal_name,
                    (SELECT CONCAT('Dr ', s.first_name, ' ', s.last_name) FROM clinic__clinic_memberships m
                       JOIN clinic__staff_profiles s ON s.membership_id = m.id
                      WHERE m.user_id = c.practitioner_user_id AND m.clinic_id = c.clinic_id LIMIT 1) AS practitioner
             FROM consultation__consultations c
             JOIN patient__patients p ON p.id = c.patient_id
             JOIN animal__animals a ON a.id = p.animal_link_id
             JOIN animal__ownerships o ON o.animal_id = a.id AND o.role = 'primary' AND o.status = 'active'
             WHERE c.clinic_id = :clinic AND o.client_id = :client AND c.status = 'CLOSED' AND c.closed_at_utc IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM " . DbalPublishedDocumentRepository::TABLE . " d WHERE d.clinic_id = c.clinic_id AND d.type = 'report' AND d.source_ref = c.id)
             ORDER BY c.closed_at_utc ASC, c.id ASC",
            ['clinic' => $this->binary(self::PARIS), 'client' => $this->binary($clientId)],
        );
        $tz        = new \DateTimeZone(self::TZ);
        $published = [];

        foreach ($rows as $row) {
            $id           = Uuid::v7()->toRfc4122();
            $consultation = RowAccessor::string($row, 'id');
            $startedAt    = self::utc(RowAccessor::string($row, 'started_at_utc'));
            $closedAt     = self::utc(RowAccessor::string($row, 'closed_at_utc'));
            $practitioner = RowAccessor::nullableString($row, 'practitioner');
            $title        = 'Compte rendu du ' . $startedAt->setTimezone($tz)->format('d/m/Y');
            $publishedAt  = min($this->clock->now(), $closedAt->modify('+1 hour'));
            $key          = StorePublishedDocumentFileHandler::storageKey($id, 1);

            $this->documents->save(PublishedDocument::reconstitute(
                id: PublishedDocumentId::fromString($id),
                clinicId: self::PARIS,
                clientId: $clientId,
                type: DocumentType::REPORT,
                sourceRef: $consultation,
                consultationId: $consultation,
                patientId: RowAccessor::string($row, 'patient'),
                petIds: [RowAccessor::string($row, 'animal')],
                title: $title,
                issuedAt: $closedAt,
                practitionerName: $practitioner,
                storageKey: $key,
                fileVersion: 1,
                pendingFileVersion: null,
                renderAttempts: 0,
                status: PublicationStatus::AVAILABLE,
                publishedAt: $publishedAt,
                updatedAt: $publishedAt,
            ));
            $this->files->storeAt($key, PortalDocumentsStory::pdf($title, [
                'Clinique vétérinaire de Paris',
                'Consultation de ' . RowAccessor::string($row, 'animal_name') . ' le ' . $startedAt->setTimezone($tz)->format('d/m/Y à H:i'),
                null === $practitioner ? '' : 'Praticien : ' . $practitioner,
                'Document de démonstration Kiveto',
            ]));
            $published[] = ['id' => $id, 'at' => $publishedAt];
        }

        foreach (\array_slice($published, 0, max(0, \count($published) - $unread)) as $document) {
            $this->reads->markRead($document['id'], $userId, min($this->clock->now(), $document['at']->modify('+1 day')));
        }
    }

    /** A moment relative to today in clinic time: '-3 days 17:30'. */
    private function at(string $relative): \DateTimeImmutable
    {
        return $this->today->modify($relative);
    }

    private function user(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s user not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
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

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), 'The slice 2 stories create the demo owner accounts.');

        return $id;
    }

    private function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
