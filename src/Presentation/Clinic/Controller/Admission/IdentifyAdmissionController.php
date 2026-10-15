<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\ReconcileAdmissionPatient\ReconcileAdmissionPatient;
use App\Context\Admission\Application\Command\UpdateAnonymousAdmission\UpdateAnonymousAdmission;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Presentation\Clinic\ViewModel\Flux\DeskRegistration;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * « Identifier le patient » of an emergency admitted unidentified: attaches
 * the admission to the clinic's file found by its chip or searched by name
 * (`owner`), or creates the file on the spot (`new`) — for a known owner, or
 * for an animal found and brought in, attached to whoever declared it — with
 * the animal described in a few closed choices (species, sex, estimated age).
 */
#[Route('/admission/identify', name: 'clinic_admission_identify', methods: ['POST'])]
final class IdentifyAdmissionController extends AbstractController
{
    private const array SEX = ['M' => 'male', 'F' => 'female'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly DeskRegistration $deskRegistration,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_identify', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinicId    = $currentClinicId->toString();
        $admissionId = $request->request->getString('admissionId');
        $tab         = $request->request->getString('tab');

        try {
            if ('owner' === $tab) {
                $animalId   = $request->request->getString('animalId');
                $animalName = $request->request->getString('animalName');

                $this->commandBus->dispatch(new ReconcileAdmissionPatient(
                    clinicId: $clinicId,
                    admissionId: $admissionId,
                    animalId: $animalId,
                    animalName: $animalName,
                ));

                $this->addFlash('success', \sprintf('%s identifié · rattaché à sa fiche.', '' === $animalName ? 'Patient' : $animalName));
            } elseif ('new' === $tab) {
                $this->createAndAttach($request, $clinicId, $admissionId);
            } elseif ('anon' === $tab) {
                $physicalDescription = $request->request->getString('physicalDescription') ?: null;
                $triageNotes         = $request->request->getString('triageNotes') ?: null;

                $this->commandBus->dispatch(new UpdateAnonymousAdmission(
                    clinicId: $clinicId,
                    admissionId: $admissionId,
                    physicalDescription: $physicalDescription,
                    triageNotes: $triageNotes,
                ));

                $this->addFlash('success', 'Informations mises à jour.');
            }
        } catch (AdmissionNotFoundException) {
            $this->addFlash('error', 'Admission introuvable.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }

    /**
     * « Nouvelle fiche »: the owner (or the declarant of a found animal) and
     * the animal are created, then the admission is attached to the animal.
     */
    private function createAndAttach(Request $request, string $clinicId, string $admissionId): void
    {
        $found    = 'trouve' === $request->request->getString('owner');
        $civility = ['M.' => 'mister', 'Mme' => 'madam'][$request->request->getString('civility')] ?? null;

        $ownerId = $this->deskRegistration->createOwner(
            $clinicId,
            $request->request->getString('firstName'),
            $request->request->getString('lastName'),
            $request->request->getString('phone'),
            $civility,
        );

        $name = trim($request->request->getString('animalName'));
        $name = '' === $name ? 'Sans nom' : mb_substr($name, 0, 60);
        $chip = preg_replace('/\D/', '', $request->request->getString('microchip')) ?? '';

        $animalId = $this->deskRegistration->createAnimal(
            clinicId: $clinicId,
            ownerId: $ownerId,
            name: $name,
            species: $request->request->getString('species'),
            sex: self::SEX[$request->request->getString('sex')] ?? 'unknown',
            birthDate: DeskRegistration::estimatedBirthDate($request->request->getString('age'), $this->clock->now()),
            microchip: 15 === \strlen($chip) ? $chip : null,
        );

        $this->commandBus->dispatch(new ReconcileAdmissionPatient(
            clinicId: $clinicId,
            admissionId: $admissionId,
            animalId: $animalId,
            animalName: $name,
        ));

        $this->addFlash('success', $found
            ? \sprintf('Fiche « animal trouvé » créée · %s, rattaché au déclarant.', $name)
            : \sprintf('Fiche créée · %s rattaché à son propriétaire.', $name));
    }
}
