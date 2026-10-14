<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Context\Admission\Domain\Exception\PatientAlreadyAdmittedException;
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
 * « Admission d'urgence »: one screen where the motif comes first and the
 * identity can wait. A known animal is attached at once; otherwise the
 * patient is admitted under a provisional « Urgence HH:MM » label, to be
 * identified once stabilised.
 */
#[Route('/admission/emergency', name: 'clinic_admission_emergency', methods: ['POST'])]
final class EmergencyAdmissionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_emergency', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $triageNotes = trim($request->request->getString('triage_notes'));

        if ('' === $triageNotes) {
            $this->addFlash('error', 'Décrivez ce qui se passe pour admettre l\'urgence.');

            return $this->redirectToRoute('clinic_admission_queue');
        }

        $knownAnimalId = $request->request->getString('known_animal_id') ?: null;
        $animalName    = $request->request->getString('animal_name') ?: null;
        $label         = null;

        if (null === $knownAnimalId) {
            $zone     = $request->request->getString('time_zone');
            $known    = \in_array($zone, \DateTimeZone::listIdentifiers(), true);
            $timeZone = new \DateTimeZone($known ? $zone : 'Europe/Paris');
            $label    = 'Urgence ' . $this->clock->now()->setTimezone($timeZone)->format('H:i');
        }

        try {
            $this->commandBus->dispatch(new OpenEmergencyAdmission(
                clinicId: $currentClinicId->toString(),
                intakeChannel: $request->request->getString('intake_channel') ?: 'emergency',
                triageLevel: $request->request->getString('triage_level') ?: 'emergency',
                provisionalLabel: $label,
                knownAnimalId: $knownAnimalId,
                animalName: $animalName,
                observedSpecies: $request->request->getString('observed_species') ?: null,
                observedColor: null,
                presenterName: null,
                presenterPhone: null,
                presenterRole: null,
                physicalDescription: null,
                triageNotes: $triageNotes,
            ));

            $this->addFlash('success', \sprintf(
                'Urgence admise — %s en tête de file%s.',
                $animalName ?? $label,
                null === $knownAnimalId ? ' · identification à régulariser' : '',
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
        } catch (\ValueError) {
            $this->addFlash('error', 'Canal d\'admission ou niveau de triage invalide.');
        }

        return $this->redirectToRoute('clinic_admission_queue');
    }
}
