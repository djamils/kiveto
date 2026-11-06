<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\Clinic;

use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * B6 — « … est créée »: the invitations that left, shown once after the
 * creation; afterwards the address leads to the sheet.
 */
final class ClinicCreatedController extends AbstractController
{
    public function __construct(
        private readonly ConsoleDirectory $directory,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(path: '/clinics/{id}/created', name: 'backoffice_clinic_created', requirements: ['id' => ShowClinicController::UUID], methods: ['GET'])]
    public function __invoke(Request $request, string $id): Response
    {
        $created = $request->getSession()->get(NewClinicController::CREATED_KEY);
        $request->getSession()->remove(NewClinicController::CREATED_KEY);
        $clinic = $this->directory->clinic($id);

        if (null === $clinic || !\is_array($created) || ($created['clinicId'] ?? null) !== $id) {
            return $this->redirectToRoute('backoffice_clinic_show', ['id' => $id]);
        }

        return $this->render('backoffice/console/clinics/created.html.twig', [
            'clinic'     => $clinic,
            'invited'    => \is_array($created['invited'] ?? null) ? $created['invited'] : [],
            'expires_on' => $this->clock->now()->modify('+7 days'),
        ]);
    }
}
