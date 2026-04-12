<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Profile;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals', name: 'clinic_animals_list', methods: ['GET'])]
final class AnimalsListRedirectController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->redirectToRoute(
            'clinic_clients_list',
            ['tab' => 'animals'],
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
