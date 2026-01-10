<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\ClinicAccess\Application\Command\AddUserToClinic\AddUserToClinic;
use App\ClinicAccess\Application\Command\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\ClinicAccess\Application\Command\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\ClinicAccess\Application\Command\ChangeClinicMembershipValidity\ChangeClinicMembershipValidity;
use App\ClinicAccess\Application\Command\DisableClinicMembership\DisableClinicMembership;
use App\ClinicAccess\Application\Command\EnableClinicMembership\EnableClinicMembership;
use App\ClinicAccess\Application\Query\ListAllMemberships\ListAllMemberships;
use App\ClinicAccess\Application\Query\ListAllMemberships\MembershipCollection;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;
use App\Clinic\Application\Query\ListClinics\ClinicCollection;
use App\Clinic\Application\Query\ListClinics\ListClinics;
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
final class ClinicMembershipController extends AbstractController
{
    private const string CSRF_ID = 'backoffice_clinic_membership';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/clinic-memberships', name: 'backoffice_clinic_memberships', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $clinicIdFilter    = $request->query->get('clinic_id');
        $userIdFilter      = $request->query->get('user_id');
        $statusFilter      = $request->query->get('status');
        $roleFilter        = $request->query->get('role');
        $engagementFilter  = $request->query->get('engagement');

        $status     = $statusFilter ? ClinicMembershipStatus::from($statusFilter) : null;
        $role       = $roleFilter ? ClinicMemberRole::from($roleFilter) : null;
        $engagement = $engagementFilter ? ClinicMembershipEngagement::from($engagementFilter) : null;

        /** @var MembershipCollection $collection */
        $collection = $this->queryBus->ask(new ListAllMemberships(
            clinicId: $clinicIdFilter ?: null,
            userId: $userIdFilter ?: null,
            status: $status,
            role: $role,
            engagement: $engagement,
        ));

        return $this->render(
            'backoffice/clinic-memberships/index.html.twig',
            [
                'collection' => $collection,
                'filters'    => [
                    'clinic_id'  => $clinicIdFilter,
                    'user_id'    => $userIdFilter,
                    'status'     => $statusFilter,
                    'role'       => $roleFilter,
                    'engagement' => $engagementFilter,
                ],
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinic-memberships/new', name: 'backoffice_clinic_memberships_new', methods: ['GET'])]
    public function new(): Response
    {
        /** @var ClinicCollection $clinics */
        $clinics = $this->queryBus->ask(new ListClinics());

        return $this->render(
            'backoffice/clinic-memberships/new.html.twig',
            [
                'clinics'    => $clinics,
                'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            ],
        );
    }

    #[Route(path: '/clinic-memberships/create', name: 'backoffice_clinic_memberships_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $clinicId       = trim((string) $request->request->get('clinic_id'));
        $userId         = trim((string) $request->request->get('user_id'));
        $roleStr        = trim((string) $request->request->get('role'));
        $engagementStr  = trim((string) $request->request->get('engagement'));
        $validFromStr   = trim((string) $request->request->get('valid_from'));
        $validUntilStr  = trim((string) $request->request->get('valid_until'));

        if ('' === $clinicId || '' === $userId || '' === $roleStr || '' === $engagementStr) {
            $this->addFlash('error', 'Tous les champs obligatoires doivent être remplis.');

            return $this->redirectToRoute('backoffice_clinic_memberships_new');
        }

        try {
            $role       = ClinicMemberRole::from($roleStr);
            $engagement = ClinicMembershipEngagement::from($engagementStr);

            $validFrom  = '' !== $validFromStr ? new \DateTimeImmutable($validFromStr) : null;
            $validUntil = '' !== $validUntilStr ? new \DateTimeImmutable($validUntilStr) : null;

            $this->commandBus->dispatch(new AddUserToClinic(
                clinicId: $clinicId,
                userId: $userId,
                role: $role,
                engagement: $engagement,
                validFrom: $validFrom,
                validUntil: $validUntil,
            ));

            $this->addFlash('success', 'Membership créée avec succès.');

            return $this->redirectToRoute('backoffice_clinic_memberships');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());

            return $this->redirectToRoute('backoffice_clinic_memberships_new');
        }
    }

    #[Route(path: '/clinic-memberships/{id}/disable', name: 'backoffice_clinic_memberships_disable', methods: ['POST'])]
    public function disable(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        try {
            $this->commandBus->dispatch(new DisableClinicMembership($id));

            $this->addFlash('success', 'Membership désactivée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_memberships');
    }

    #[Route(path: '/clinic-memberships/{id}/enable', name: 'backoffice_clinic_memberships_enable', methods: ['POST'])]
    public function enable(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        try {
            $this->commandBus->dispatch(new EnableClinicMembership($id));

            $this->addFlash('success', 'Membership activée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_memberships');
    }

    #[Route(path: '/clinic-memberships/{id}/role', name: 'backoffice_clinic_memberships_change_role', methods: ['POST'])]
    public function changeRole(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $roleStr = trim((string) $request->request->get('role'));

        if ('' === $roleStr) {
            $this->addFlash('error', 'Le rôle est obligatoire.');

            return $this->redirectToRoute('backoffice_clinic_memberships');
        }

        try {
            $role = ClinicMemberRole::from($roleStr);
            $this->commandBus->dispatch(new ChangeClinicMembershipRole($id, $role));

            $this->addFlash('success', 'Rôle modifié avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_memberships');
    }

    #[Route(path: '/clinic-memberships/{id}/engagement', name: 'backoffice_clinic_memberships_change_engagement', methods: ['POST'])]
    public function changeEngagement(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $engagementStr = trim((string) $request->request->get('engagement'));

        if ('' === $engagementStr) {
            $this->addFlash('error', 'L\'engagement est obligatoire.');

            return $this->redirectToRoute('backoffice_clinic_memberships');
        }

        try {
            $engagement = ClinicMembershipEngagement::from($engagementStr);
            $this->commandBus->dispatch(new ChangeClinicMembershipEngagement($id, $engagement));

            $this->addFlash('success', 'Engagement modifié avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_memberships');
    }

    #[Route(path: '/clinic-memberships/{id}/validity', name: 'backoffice_clinic_memberships_change_validity', methods: ['POST'])]
    public function changeValidity(string $id, Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $validFromStr  = trim((string) $request->request->get('valid_from'));
        $validUntilStr = trim((string) $request->request->get('valid_until'));

        if ('' === $validFromStr) {
            $this->addFlash('error', 'La date de début de validité est obligatoire.');

            return $this->redirectToRoute('backoffice_clinic_memberships');
        }

        try {
            $validFrom  = new \DateTimeImmutable($validFromStr);
            $validUntil = '' !== $validUntilStr ? new \DateTimeImmutable($validUntilStr) : null;

            $this->commandBus->dispatch(new ChangeClinicMembershipValidity($id, $validFrom, $validUntil));

            $this->addFlash('success', 'Période de validité modifiée avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('backoffice_clinic_memberships');
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
