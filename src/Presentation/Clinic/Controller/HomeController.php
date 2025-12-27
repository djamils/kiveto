<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetails;
use App\IdentityAccess\Application\Query\GetUserDetails\UserDetails;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('/', name: 'clinic_home')]
    public function index(): Response
    {
        // Demo: dispatch a command then query
        /** @var string $userId */
        /*$userId = $this->commandBus->dispatch(new RegisterUser(
            email: 'demo@example.com',
            plainPassword: 'demo-password',
        ));*/

        /** @var UserDetails $user */
        $user = $this->queryBus->ask(new GetUserDetails('019b5fc7-0b1d-7336-ba8c-150ecef42832'));

        return $this->render('clinic/index.html.twig', [
            'demo_user_id' => $user->id,
            'demo_user'    => $user,
        ]);
    }

    #[Route('/dashboard-layout14', name: 'clinic_dashboard_layout14')]
    public function dashboardLayout14(): Response
    {
        return $this->render('clinic/dashboard-layout14.html.twig');
    }
}
