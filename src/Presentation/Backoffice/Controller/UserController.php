<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\IdentityAccess\Application\Query\ListUsers\ListUsers;
use App\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '', host: 'backoffice.kiveto.local')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route(path: '/users', name: 'backoffice_users', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->query->get('search', ''),
            'type'   => $request->query->get('type', ''),
            'status' => $request->query->get('status', ''),
        ];

        /** @var UserCollection $collection */
        $collection = $this->queryBus->ask(
            new ListUsers(
                search: '' !== $filters['search'] ? $filters['search'] : null,
                type: '' !== $filters['type'] ? $filters['type'] : null,
                status: '' !== $filters['status'] ? $filters['status'] : null,
            )
        );

        return $this->render('backoffice/users/index.html.twig', [
            'collection' => $collection,
            'filters'    => $filters,
        ]);
    }
}
