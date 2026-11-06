<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The console opens on its clinics.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'backoffice_home', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('backoffice_clinics');
    }
}
