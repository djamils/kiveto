<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\Clinic\Application\Command\ChangeClinicLocale\ChangeClinicLocale;
use App\Clinic\Application\Command\ChangeClinicSlug\ChangeClinicSlug;
use App\Clinic\Application\Command\ChangeClinicStatus\ChangeClinicStatus;
use App\Clinic\Application\Command\ChangeClinicTimeZone\ChangeClinicTimeZone;
use App\Clinic\Application\Command\CreateClinic\CreateClinic;
use App\Clinic\Application\Command\RenameClinic\RenameClinic;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Clinic\Application\Query\ListClinicGroups\ClinicGroupsCollection;
use App\Clinic\Application\Query\ListClinicGroups\ListClinicGroups;
use App\Clinic\Application\Query\ListClinics\ClinicsCollection;
use App\Clinic\Application\Query\ListClinics\ListClinics;
use App\Clinic\Domain\ValueObject\ClinicStatus;
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
final class ClinicController extends AbstractController
{
    private const string CSRF_ID = 'backoffice_clinic';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/clinics', name: 'backoffice_clinics', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->get('status');
        $status       = $statusFilter ? ClinicStatus::from($statusFilter) : null;
        $search       = trim((string) $request->query->get('search'));
        $groupId      = $request->query->get('group_id');

        /** @var ClinicsCollection $collection */
        $collection = $this->queryBus->ask(new ListClinics(
            status: $status,
            clinicGroupId: $groupId,
            search: '' !== $search ? $search : null,
        ));

        /** @var ClinicGroupsCollection $groups */
        $groups = $this->queryBus->ask(new ListClinicGroups());

        return $this->render(
            'backoffice/clinics/index.html.twig',
            [
                'collection' => $collection,
                'groups'     => $groups,
                'filters'    => [
                    'status'   => $statusFilter,
                    'group_id' => $groupId,
                    'search'   => $search,
                ],
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinics/new', name: 'backoffice_clinics_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        /** @var ClinicGroupsCollection $groups */
        $groups = $this->queryBus->ask(new ListClinicGroups());

        return $this->render(
            'backoffice/clinics/new.html.twig',
            [
                'groups'     => $groups,
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinics/create', name: 'backoffice_clinics_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $name          = trim((string) $request->request->get('name'));
        $slug          = trim((string) $request->request->get('slug'));
        $timeZone      = trim((string) $request->request->get('time_zone'));
        $locale        = trim((string) $request->request->get('locale'));
        $clinicGroupId = $request->request->get('clinic_group_id');

        if ('' === $name || '' === $slug || '' === $timeZone || '' === $locale) {
            $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');

            return $this->redirectToRoute('backoffice_clinics_new');
        }

        try {
            $this->commandBus->dispatch(new CreateClinic(
                name: $name,
                slug: $slug,
                timeZone: $timeZone,
                locale: $locale,
                clinicGroupId: \is_string($clinicGroupId) && '' !== $clinicGroupId ? $clinicGroupId : null,
            ));

            $this->addFlash('success', 'Clinique créée avec succès.');

            return $this->redirectToRoute('backoffice_clinics');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());

            return $this->redirectToRoute('backoffice_clinics_new');
        }
    }

    #[Route(path: '/clinics/{id}/edit', name: 'backoffice_clinics_edit', methods: ['GET'])]
    public function edit(string $id, Request $request): Response
    {
        /** @var ClinicDto|null $clinic */
        $clinic = $this->queryBus->ask(new GetClinic($id));

        if (null === $clinic) {
            $this->addFlash('error', 'Clinique introuvable.');

            return $this->redirectToRoute('backoffice_clinics');
        }

        /** @var ClinicGroupsCollection $groups */
        $groups = $this->queryBus->ask(new ListClinicGroups());

        return $this->render(
            'backoffice/clinics/edit.html.twig',
            [
                'clinic'     => $clinic,
                'groups'     => $groups,
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinics/{id}/update', name: 'backoffice_clinics_update', methods: ['POST'])]
    public function update(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $name     = trim((string) $request->request->get('name'));
        $slug     = trim((string) $request->request->get('slug'));
        $timeZone = trim((string) $request->request->get('time_zone'));
        $locale   = trim((string) $request->request->get('locale'));
        $status   = (string) $request->request->get('status');

        if ('' === $name || '' === $slug || '' === $timeZone || '' === $locale || '' === $status) {
            $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');

            return $this->redirectToRoute('backoffice_clinics_edit', ['id' => $id]);
        }

        try {
            $this->commandBus->dispatch(new RenameClinic($id, $name));
            $this->commandBus->dispatch(new ChangeClinicSlug($id, $slug));
            $this->commandBus->dispatch(new ChangeClinicTimeZone($id, $timeZone));
            $this->commandBus->dispatch(new ChangeClinicLocale($id, $locale));
            $this->commandBus->dispatch(new ChangeClinicStatus($id, ClinicStatus::from($status)));

            $this->addFlash('success', 'Clinique mise à jour avec succès.');

            return $this->redirectToRoute('backoffice_clinics');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());

            return $this->redirectToRoute('backoffice_clinics_edit', ['id' => $id]);
        }
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
