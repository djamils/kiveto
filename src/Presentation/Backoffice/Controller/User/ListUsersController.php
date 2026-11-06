<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller\User;

use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Presentation\Backoffice\ViewModel\User\UserFilters;
use App\Presentation\Backoffice\ViewModel\User\UserListBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E1 — « Utilisateurs »: the clinic staff (states, clinic filter) and the
 * owners of the Portal, searched by name, e-mail or phone.
 */
final class ListUsersController extends AbstractController
{
    public function __construct(
        private readonly UserListBuilder $builder,
        private readonly ConsoleDirectory $directory,
    ) {
    }

    #[Route(path: '/users', name: 'backoffice_users', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $filters = UserFilters::fromRequest($request, $this->directory);
        $page    = max(1, $request->query->getInt('page', 1));

        return $this->render('backoffice/console/users/index.html.twig', [
            'filters' => $filters,
            'totals'  => $this->builder->totals(),
            'staff'   => 'staff' === $filters->tab ? $this->builder->staff($filters->status, $filters->clinicId, $filters->search, $page) : null,
            'portal'  => 'portal' === $filters->tab ? $this->builder->portal($filters->search, $page) : null,
            'clinics' => $this->directory->clinics(),
        ]);
    }
}
