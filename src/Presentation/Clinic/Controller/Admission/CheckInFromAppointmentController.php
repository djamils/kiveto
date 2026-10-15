<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Command\OpenAdmissionFromAppointment\OpenAdmissionFromAppointment;
use App\Context\Admission\Application\Command\RecordArrivalCheckIn\RecordArrivalCheckIn;
use App\Context\Admission\Application\Command\UpdateAdmissionTriage\UpdateAdmissionTriage;
use App\Context\Admission\Domain\Exception\PatientAlreadyAdmittedException;
use App\Context\Animal\Application\Command\RecordAnimalWeight\RecordAnimalWeight;
use App\Context\Client\Application\Command\ReplaceClientContactMethods\ReplaceClientContactMethods;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\GetClientById;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Presentation\Clinic\ViewModel\Client\ClientContactSlots;
use App\Presentation\Clinic\ViewModel\Client\StaffNameResolver;
use App\Presentation\Clinic\ViewModel\Dashboard\PatientFactsReader;
use App\Presentation\Clinic\ViewModel\Flux\DeskRegistration;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Enregistrer une arrivée »: honours today's booking of the patient when
 * there is one, otherwise opens a walk-in admission — for a known animal or,
 * when the search found nobody, for a client and animal created on the spot
 * (the record is completed later).
 *
 * The arrival window of a booking also brings what the desk noted: the
 * weighing (on the animal's record and the card), the four flags and the
 * note for the practitioner, a corrected phone number, and whether the
 * balance left from a previous visit is cashed right away.
 */
#[Route('/admission/checkin', name: 'clinic_admission_checkin', methods: ['POST'])]
final class CheckInFromAppointmentController extends AbstractController
{
    private const array FLAGS = ['jeun', 'sympt', 'docs', 'trait'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly DeskRegistration $deskRegistration,
        private readonly StaffNameResolver $staffNameResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->request->getString('_token');

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('waiting_room_action', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);
        $clinicId = $currentClinicId->toString();

        $appointmentId = $request->request->getString('appointmentId') ?: null;
        $animalId      = $request->request->getString('animalId') ?: null;
        $animalName    = $request->request->getString('animalName') ?: null;
        $triageNotes   = trim($request->request->getString('triageNotes')) ?: null;
        $isPriority    = '1' === $request->request->getString('priority', '0');

        try {
            if (null !== $appointmentId) {
                $weightGrams = $this->weightGrams($request);
                $phone       = $this->confirmedPhone($request);

                $admissionId = $this->commandBus->dispatch(new OpenAdmissionFromAppointment(
                    clinicId: $clinicId,
                    appointmentId: $appointmentId,
                    knownAnimalId: $animalId,
                    animalName: $animalName,
                    triageNotes: $triageNotes,
                ));

                \assert(\is_string($admissionId));

                $this->commandBus->dispatch(new CheckInAppointment(
                    clinicId: $clinicId,
                    appointmentId: $appointmentId,
                    admissionId: $admissionId,
                ));

                if ($isPriority) {
                    $this->commandBus->dispatch(new UpdateAdmissionTriage(
                        clinicId: $clinicId,
                        admissionId: $admissionId,
                        newTriageLevel: 'priority',
                    ));
                }

                $this->recordArrival($request, $clinicId, $admissionId, $animalId, $weightGrams, $phone);

                $this->addFlash('success', \sprintf(
                    '%s en salle d\'attente%s · rendez-vous honoré.',
                    $animalName ?? 'Patient',
                    null === $weightGrams ? '' : ' · ' . PatientFactsReader::kilograms($weightGrams) . ' kg',
                ));

                return $this->settleNow($request) ?? $this->redirectToRoute('clinic_admission_queue');
            }

            $newAnimal = trim($request->request->getString('newAnimalName'));
            $created   = false;

            if (null === $animalId && '' !== $newAnimal) {
                $animalId   = $this->createClientAndAnimal($request, $clinicId, $newAnimal);
                $animalName = $newAnimal;
                $created    = true;
            }

            $this->commandBus->dispatch(new OpenAdmissionForWalkIn(
                clinicId: $clinicId,
                triageLevel: $isPriority ? 'priority' : 'standard',
                knownAnimalId: $animalId,
                animalName: $animalName,
                provisionalLabel: $request->request->getString('foundAnimalDescription') ?: null,
                triageNotes: $triageNotes,
            ));

            $this->addFlash('success', \sprintf(
                'Arrivée enregistrée — %s · sans rendez-vous · file %s%s.',
                $animalName ?? 'patient',
                $isPriority ? 'prioritaire' : 'normale',
                $created ? ' · fiche client créée' : '',
            ));
        } catch (PatientAlreadyAdmittedException) {
            $this->addFlash('error', \sprintf(
                '%s est déjà sur place — son admission est en cours.',
                $animalName ?? 'Ce patient',
            ));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }

    /** « 4,2 » kg typed at the desk, checked before anything is written. */
    private function weightGrams(Request $request): ?int
    {
        $raw = str_replace(',', '.', trim($request->request->getString('weight')));

        if ('' === $raw) {
            return null;
        }

        if (1 !== preg_match('/^\d{1,3}(\.\d)?$/', $raw) || (float) $raw <= 0 || (float) $raw > 120) {
            throw new \InvalidArgumentException('Le poids doit être compris entre 0 et 120 kg (ex. 4,2).');
        }

        return (int) round((float) $raw * 1000);
    }

    /**
     * What the desk noted: the flags and note on the admission, the weighing
     * on the animal's record too, a corrected phone on the client.
     */
    private function recordArrival(Request $request, string $clinicId, string $admissionId, ?string $animalId, ?int $weightGrams, ?string $phone): void
    {
        /** @var list<string> $flags */
        $flags = array_values(array_intersect(self::FLAGS, array_map(
            static fn (mixed $flag): string => \is_string($flag) ? $flag : '',
            $request->request->all('checks'),
        )));
        $note = trim($request->request->getString('note'));

        if (null !== $weightGrams || [] !== $flags || '' !== $note) {
            $this->commandBus->dispatch(new RecordArrivalCheckIn(
                clinicId: $clinicId,
                admissionId: $admissionId,
                fasting: \in_array('jeun', $flags, true),
                newSymptom: \in_array('sympt', $flags, true),
                documentsBrought: \in_array('docs', $flags, true),
                ongoingTreatment: \in_array('trait', $flags, true),
                weightGrams: $weightGrams,
                note: '' === $note ? null : $note,
            ));
        }

        if (null !== $weightGrams && null !== $animalId) {
            $this->commandBus->dispatch(new RecordAnimalWeight(
                clinicId: $clinicId,
                animalId: $animalId,
                weightGrams: $weightGrams,
                measuredOn: $this->clock->now()->setTimezone($this->timeZone($clinicId))->format('Y-m-d'),
                bodyConditionScore: null,
                authorName: $this->staffNameResolver->resolve($clinicId, $this->getUser()),
            ));
        }

        $ownerId = $request->request->getString('ownerId');

        if (null !== $phone && '' !== $ownerId) {
            $this->updatePhone($clinicId, $ownerId, $phone);
        }
    }

    /** A phone corrected in the arrival window, checked before anything is written. */
    private function confirmedPhone(Request $request): ?string
    {
        $raw = trim($request->request->getString('phone'));

        if ('' === $raw) {
            return null;
        }

        $phone = DeskRegistration::e164($raw);

        if (1 !== preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            throw new \InvalidArgumentException('Le numéro de téléphone est invalide.');
        }

        return $phone;
    }

    /** The number confirmed with the client replaces the one the clinic calls first. */
    private function updatePhone(string $clinicId, string $ownerId, string $phone): void
    {
        $client = $this->queryBus->ask(new GetClientById($clinicId, $ownerId));

        if (!$client instanceof ClientView) {
            return;
        }

        $slots = ClientContactSlots::fromContactMethods($client->contactMethods);

        if ($slots->mobile?->value === $phone) {
            return;
        }

        $this->commandBus->dispatch(new ReplaceClientContactMethods(
            $clinicId,
            $ownerId,
            $slots->withValues($phone, $slots->landline?->value, $slots->email?->value),
        ));
    }

    /** « Encaisser maintenant »: the invoice left unpaid, or the register of what the client owes. */
    private function settleNow(Request $request): ?Response
    {
        if ('now' !== $request->request->getString('settle')) {
            return null;
        }

        $invoiceId = $request->request->getString('settleInvoiceId');

        if (1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invoiceId)) {
            return $this->redirectToRoute('clinic_invoice_details', ['invoiceId' => $invoiceId, 'encaisser' => 1]);
        }

        return $this->redirectToRoute('clinic_invoices', [
            'pay' => 'du',
            'per' => 'tout',
            'q'   => mb_substr(trim($request->request->getString('settleOwner')), 0, 100),
        ]);
    }

    private function timeZone(string $clinicId): \DateTimeZone
    {
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));

        return new \DateTimeZone($settings instanceof ClinicSettingsView ? $settings->timeZone : 'Europe/Paris');
    }

    /**
     * Creates the owner (« Prénom Nom » + phone) and the animal typed in the
     * compact new-client form, and returns the animal id.
     */
    private function createClientAndAnimal(Request $request, string $clinicId, string $animalName): string
    {
        $owner = trim($request->request->getString('newOwnerName'));
        $phone = trim($request->request->getString('newOwnerPhone'));

        if ('' === $owner || '' === $phone) {
            throw new \InvalidArgumentException('Le propriétaire et son téléphone sont obligatoires.');
        }

        $words = preg_split('/\s+/', $owner) ?: [];

        if (\count($words) < 2) {
            throw new \InvalidArgumentException('Indiquez le prénom et le nom du propriétaire.');
        }

        $lastName  = (string) array_pop($words);
        $firstName = implode(' ', $words);

        $clientId = $this->deskRegistration->createOwner($clinicId, $firstName, $lastName, $phone);

        return $this->deskRegistration->createAnimal(
            $clinicId,
            $clientId,
            $animalName,
            $request->request->getString('newAnimalSpecies'),
        );
    }
}
