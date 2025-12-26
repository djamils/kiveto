<?php

declare(strict_types=1);

namespace App\Controller;

use App\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetails;
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

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Demo: dispatch a command then query
        /** @var string $userId */
        $userId = $this->commandBus->dispatch(new RegisterUser(
            email: 'demo@example.com',
            passwordHash: '$argon2id$dummy-hash',
        ));

        $user = $this->queryBus->ask(new GetUserDetails($userId));

        return $this->render('clinic/index.html.twig', [
            'demo_user_id' => $userId,
            'demo_user'    => $user,
        ]);
    }

    #[Route('/dashboard-layout14', name: 'app_dashboard_layout14')]
    public function dashboardLayout14(): Response
    {
        return $this->render('clinic/dashboard-layout14.html.twig');
    }
}
