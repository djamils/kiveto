<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Command\OpenEmergencyAdmission\OpenEmergencyAdmission;
use App\Context\Admission\Domain\ValueObject\IntakeChannel;
use App\Context\Admission\Domain\ValueObject\TriageLevel;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/admission/emergency', name: 'clinic_admission_emergency', methods: ['GET', 'POST'])]
final class EmergencyAdmissionController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->render('clinic/admission/emergency_form.html.twig', [
                'intakeChannels' => IntakeChannel::cases(),
                'triageLevels'   => TriageLevel::cases(),
                'closureReasons' => [],
            ]);
        }

        $token = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('admission_emergency', $token))) {
            $this->addFlash('error', 'Token de sécurité invalide. Veuillez réessayer.');

            return $this->redirectToRoute('clinic_admission_emergency');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $intakeChannel = $request->request->getString('intake_channel');
        $triageLevel   = $request->request->getString('triage_level');

        if ('' === $intakeChannel || '' === $triageLevel) {
            $this->addFlash('error', 'Le canal d\'admission et le niveau de triage sont obligatoires.');

            return $this->redirectToRoute('clinic_admission_emergency');
        }

        $provisionalLabel = $request->request->getString('provisional_label') ?: null;
        $knownAnimalId    = $request->request->getString('known_animal_id') ?: null;
        $animalName       = $request->request->getString('animal_name') ?: null;
        $observedSpecies  = $request->request->getString('observed_species') ?: null;
        $observedColor    = $request->request->getString('observed_color') ?: null;
        $presenterName    = $request->request->getString('presenter_name') ?: null;
        $presenterPhone   = $request->request->getString('presenter_phone') ?: null;
        $presenterRole    = $request->request->getString('presenter_role') ?: null;
        $physicalDesc     = $request->request->getString('physical_description') ?: null;
        $triageNotes      = $request->request->getString('triage_notes') ?: null;

        try {
            $this->commandBus->dispatch(new OpenEmergencyAdmission(
                clinicId: $currentClinicId->toString(),
                intakeChannel: $intakeChannel,
                triageLevel: $triageLevel,
                provisionalLabel: $provisionalLabel,
                knownAnimalId: $knownAnimalId,
                animalName: $animalName,
                observedSpecies: $observedSpecies,
                observedColor: $observedColor,
                presenterName: $presenterName,
                presenterPhone: $presenterPhone,
                presenterRole: $presenterRole,
                physicalDescription: $physicalDesc,
                triageNotes: $triageNotes,
            ));

            $this->addFlash('success', 'Admission urgente créée avec succès.');

            return $this->redirectToRoute('clinic_admission_queue');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_admission_emergency');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('clinic_admission_emergency');
        }
    }
}
