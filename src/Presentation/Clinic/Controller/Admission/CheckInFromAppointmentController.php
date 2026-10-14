<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenAdmissionForWalkIn\OpenAdmissionForWalkIn;
use App\Context\Admission\Application\Command\OpenAdmissionFromAppointment\OpenAdmissionFromAppointment;
use App\Context\Admission\Application\Command\UpdateAdmissionTriage\UpdateAdmissionTriage;
use App\Context\Admission\Domain\Exception\PatientAlreadyAdmittedException;
use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Context\Client\Application\Command\CreateClient\CreateClient;
use App\Context\Scheduling\Application\Command\CheckInAppointment\CheckInAppointment;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
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
 */
#[Route('/admission/checkin', name: 'clinic_admission_checkin', methods: ['POST'])]
final class CheckInFromAppointmentController extends AbstractController
{
    private const array SPECIES = ['dog', 'cat', 'nac', 'other'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
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

                $this->addFlash('success', \sprintf(
                    'Arrivée enregistrée — %s · rendez-vous honoré.',
                    $animalName ?? 'patient',
                ));

                return $this->redirectToRoute('clinic_admission_queue');
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

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $clinicId,
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: [
                new ContactMethodDto(type: 'phone', label: 'mobile', value: $this->toE164($phone), isPrimary: true),
            ],
        ));
        \assert(\is_string($clientId));

        $species = $request->request->getString('newAnimalSpecies');

        $animalId = $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $animalName,
            species: \in_array($species, self::SPECIES, true) ? $species : 'other',
            sex: 'unknown',
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: null,
            birthDate: null,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
        ));
        \assert(\is_string($animalId));

        return $animalId;
    }

    /** « 06 12 34 56 78 » as typed at the desk becomes « +33612345678 »; an international number is kept. */
    private function toE164(string $phone): string
    {
        $compact = preg_replace('/[\s.\-()]/', '', $phone) ?? $phone;

        if (str_starts_with($compact, '00')) {
            return '+' . substr($compact, 2);
        }

        if (1 === preg_match('/^0\d{9}$/', $compact)) {
            return '+33' . substr($compact, 1);
        }

        return $compact;
    }
}
