<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LockController extends AbstractController
{
    #[Route(path: '/lock', name: 'clinic_lock', methods: ['GET'])]
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('security/lock.html.twig', [
            'clinicName' => null,
        ]);
    }
}
