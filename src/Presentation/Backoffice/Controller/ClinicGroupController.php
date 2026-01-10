<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\Clinic\Application\Command\ActivateClinicGroup\ActivateClinicGroup;
use App\Clinic\Application\Command\CreateClinicGroup\CreateClinicGroup;
use App\Clinic\Application\Command\RenameClinicGroup\RenameClinicGroup;
use App\Clinic\Application\Command\SuspendClinicGroup\SuspendClinicGroup;
use App\Clinic\Application\Query\ListClinicGroups\ClinicGroupCollection;
use App\Clinic\Application\Query\ListClinicGroups\ListClinicGroups;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '', host: 'backoffice.kiveto.local')]
final class ClinicGroupController extends AbstractController
{
    private const string CSRF_ID = 'backoffice_clinic_group';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/clinic-groups', name: 'backoffice_clinic_groups', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $status       = $statusFilter ? ClinicGroupStatus::from($statusFilter) : null;

        /** @var ClinicGroupCollection $collection */
        $collection = $this->queryBus->ask(new ListClinicGroups($status));

        return $this->render(
            'backoffice/clinic-groups/index.html.twig',
            [
                'collection' => $collection,
                'filters'    => ['status' => $statusFilter],
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinic-groups/create', name: 'backoffice_clinic_groups_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $name = trim((string) $request->request->get('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom du groupe est obligatoire.');

            return $this->redirectToRoute('backoffice_clinic_groups');
        }

        try {
            $this->commandBus->dispatch(new CreateClinicGroup($name));
            $this->addFlash('success', 'Groupe de cliniques créé avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_groups');
    }

    #[Route(path: '/clinic-groups/{id}/rename', name: 'backoffice_clinic_groups_rename', methods: ['POST'])]
    public function rename(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $name = trim((string) $request->request->get('name'));

        if ('' === $name) {
            $this->addFlash('error', 'Le nom du groupe est obligatoire.');

            return $this->redirectToRoute('backoffice_clinic_groups');
        }

        try {
            $this->commandBus->dispatch(new RenameClinicGroup($id, $name));
            $this->addFlash('success', 'Groupe renommé avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_groups');
    }

    #[Route(path: '/clinic-groups/{id}/suspend', name: 'backoffice_clinic_groups_suspend', methods: ['POST'])]
    public function suspend(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        try {
            $this->commandBus->dispatch(new SuspendClinicGroup($id));
            $this->addFlash('success', 'Groupe suspendu.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_groups');
    }

    #[Route(path: '/clinic-groups/{id}/activate', name: 'backoffice_clinic_groups_activate', methods: ['POST'])]
    public function activate(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        try {
            $this->commandBus->dispatch(new ActivateClinicGroup($id));
            $this->addFlash('success', 'Groupe activé.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_groups');
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
