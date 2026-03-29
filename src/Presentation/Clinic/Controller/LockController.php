<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LockController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    #[Route(path: '/lock', name: 'clinic_lock', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('security/lock.html.twig', [
            'clinicName' => null,
        ]);
    }
}
