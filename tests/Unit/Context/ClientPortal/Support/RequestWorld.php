<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubjectHandler;
use App\Context\ClientPortal\Application\Command\LockFrozenQuestionnaires\LockFrozenQuestionnairesHandler;
use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequestHandler;
use App\Context\ClientPortal\Application\Command\SubmitPetChangeRequest\SubmitPetChangeRequestHandler;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireHandler;
use App\Context\ClientPortal\Application\Command\SubmitRenewalRequest\SubmitRenewalRequestHandler;
use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Port\PetState;
use App\Context\ClientPortal\Application\Port\PortalClientContact;
use App\Context\ClientPortal\Application\Port\PrescriptionPortalState;
use App\Context\ClientPortal\Application\Port\PrescriptionPublication;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\GetPortalRequestHandler;
use App\Context\ClientPortal\Application\Query\GetQuestionnaire\GetQuestionnaireHandler;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ListClinicPortalRequestsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests\ListOwnerPortalRequestsHandler;
use App\Context\ClientPortal\Application\Query\ListQuestionnaireSummaries\ListQuestionnaireSummariesHandler;
use App\Context\ClientPortal\Application\Service\PortalRequestCloser;
use App\Context\ClientPortal\Application\Service\PortalRequestPets;
use App\Context\ClientPortal\Application\Service\QuestionnaireAccess;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use Symfony\Component\ErrorHandler\BufferingLogger;

/**
 * The Pet world (account linked to CLIENT of the Lyon clinic, Europe/Paris,
 * clock at 2026-10-29 09:00 UTC, pets Moka and Rex of CLIENT, Filou of
 * CLIENT2) with the request repositories, ports and handlers.
 */
final class RequestWorld
{
    public const string PRESCRIPTION = '01960000-0000-7000-8000-0000000b0001';
    public const string RESULTING    = '01960000-0000-7000-8000-0000000b0002';
    public const string APPOINTMENT  = '01960000-0000-7000-8000-0000000b0003';
    public const string VET          = '01960000-0000-7000-8000-00000000d101';
    public const string OTHER_VET    = '01960000-0000-7000-8000-00000000d102';
    public const string ASV          = '01960000-0000-7000-8000-00000000d103';

    public PetWorld $pets;
    public InMemoryQuestionnaires $questionnaires;
    public InMemoryChangeRequests $changes;
    public InMemoryRenewalRequests $renewals;
    public RequestFakes $fakes;
    public BufferingLogger $logger;
    public RecordingPortalLock $locks;

    public function __construct()
    {
        $this->pets           = new PetWorld();
        $this->questionnaires = new InMemoryQuestionnaires();
        $this->changes        = new InMemoryChangeRequests();
        $this->renewals       = new InMemoryRenewalRequests();
        $this->fakes          = new RequestFakes();
        $this->logger         = new BufferingLogger();
        $this->locks          = new RecordingPortalLock();
        $this->pets->now('2026-10-29 09:00:00');
        $this->pets->pet();
        $this->pets->pet(PetWorld::PET2, 'Rex', chip: '250269604123456');
        $this->pets->pet(PetWorld::PET3, 'Filou', 'alive', PetWorld::CLIENT2);
        $this->pets->documents->fakes->clinics[PetWorld::CLINIC] = new ClinicLabel('Clinique des Lilas', 'Lyon', 'Europe/Paris');
        $this->fakes->veterinarians                              = [self::VET, self::OTHER_VET];
        $this->fakes->animalManagers                             = [self::ASV, self::VET];
    }

    public function contact(string $clientId = PetWorld::CLIENT, ?string $channel = 'email', ?string $email = 'sophie@example.com', ?string $mobile = '+33612345678'): void
    {
        $this->pets->documents->fakes->contacts[PetWorld::CLINIC . '/' . $clientId] = new PortalClientContact($clientId, 'Sophie Martin', 'Sophie', $email, $mobile, false, $channel);
    }

    public function petState(string $petId, string $lifeStatus = 'alive', bool $archived = false, ?string $owner = PetWorld::CLIENT, string $name = 'Moka'): void
    {
        $this->pets->fakes->states[$petId] = new PetState($petId, $name, $owner, 'neutered', null, $lifeStatus, $archived);
    }

    public function appointment(
        string $id = self::APPOINTMENT,
        ?string $ownerId = PetWorld::CLIENT,
        ?string $animalId = PetWorld::PET,
        string $startsAt = '2026-11-05 09:00:00',
        string $status = 'confirmed',
        bool $occupies = true,
    ): void {
        $this->pets->fakes->appointments[] = new OwnerAppointment(
            $id,
            $ownerId,
            $animalId,
            self::VET,
            Requests::utc($startsAt),
            30,
            $status,
            $occupies,
            false,
            'portal',
            null,
            null,
            null,
            Requests::utc('2026-10-01 08:00:00'),
            null,
        );
    }

    /** A listed prescription of the account, renewable unless said otherwise. */
    public function prescription(string $id = self::PRESCRIPTION, bool $renewable = true, ?string $animalId = PetWorld::PET, string $issuedAt = '2026-09-01 08:00:00', ?string $prescriber = self::VET): void
    {
        $this->pets->model->bySource[DocumentType::PRESCRIPTION->value . '/' . $id] = PetWorld::report($id, $id, $issuedAt, type: 'prescription');
        $this->pets->documents->prescriptions->states[$id]                          = new PrescriptionPortalState(true, null, $renewable);
        $this->pets->documents->prescriptions->publications[$id]                    = new PrescriptionPublication($id, PetWorld::CLIENT, 'ORD-1', Requests::utc($issuedAt), 'consultation-1', $animalId, 'Dr Claire Lambert', $prescriber);
    }

    public function access(): QuestionnaireAccess
    {
        return new QuestionnaireAccess($this->pets->ownerPets(), $this->pets->fakes, $this->fakes, $this->pets->documents->clock);
    }

    public function submitQuestionnaire(): SubmitQuestionnaireHandler
    {
        return new SubmitQuestionnaireHandler($this->access(), $this->questionnaires, $this->pets->documents->fakes, $this->pets->documents->clock);
    }

    public function submitChange(): SubmitPetChangeRequestHandler
    {
        return new SubmitPetChangeRequestHandler(
            new PortalRequestPets($this->pets->ownerPets(), $this->pets->fakes),
            $this->changes,
            $this->pets->fakes,
            $this->pets->documents->fakes,
            $this->pets->documents->fakes,
            $this->fakes,
            $this->fakes,
            $this->pets->documents->fakes,
            $this->pets->documents->clock,
            $this->locks,
        );
    }

    public function submitRenewal(): SubmitRenewalRequestHandler
    {
        return new SubmitRenewalRequestHandler(
            $this->pets->model,
            $this->pets->documents->prescriptions,
            $this->renewals,
            $this->pets->fakes,
            $this->pets->documents->fakes,
            $this->fakes,
            $this->fakes,
            $this->pets->documents->fakes,
            $this->pets->documents->clock,
        );
    }

    public function resolve(): ResolvePortalRequestHandler
    {
        return new ResolvePortalRequestHandler(
            $this->changes,
            $this->renewals,
            $this->pets->fakes,
            $this->pets->documents->prescriptions,
            $this->pets->documents->fakes,
            $this->pets->documents->fakes,
            $this->pets->documents->clock,
            $this->logger,
        );
    }

    public function closer(): PortalRequestCloser
    {
        return new PortalRequestCloser($this->questionnaires, $this->changes, $this->renewals, $this->pets->documents->clock);
    }

    public function close(): ClosePortalRequestsForSubjectHandler
    {
        return new ClosePortalRequestsForSubjectHandler($this->closer());
    }

    public function getQuestionnaire(): GetQuestionnaireHandler
    {
        return new GetQuestionnaireHandler($this->access(), $this->questionnaires, $this->pets->documents->fakes, $this->pets->documents->clock);
    }

    public function lockFrozen(): LockFrozenQuestionnairesHandler
    {
        return new LockFrozenQuestionnairesHandler($this->questionnaires, $this->access(), $this->pets->documents->clock);
    }

    public function summaries(): ListQuestionnaireSummariesHandler
    {
        return new ListQuestionnaireSummariesHandler($this->questionnaires, $this->pets->documents->fakes);
    }

    public function ownerRequests(): ListOwnerPortalRequestsHandler
    {
        return new ListOwnerPortalRequestsHandler($this->fakes, $this->pets->fakes, $this->pets->documents->fakes);
    }

    public function clinicRequests(): ListClinicPortalRequestsHandler
    {
        return new ListClinicPortalRequestsHandler($this->fakes, $this->pets->fakes, $this->pets->documents->fakes, $this->pets->documents->fakes);
    }

    public function getRequest(): GetPortalRequestHandler
    {
        return new GetPortalRequestHandler($this->changes, $this->renewals, $this->pets->fakes, $this->pets->fakes, $this->pets->documents->fakes, $this->pets->documents->fakes);
    }
}
