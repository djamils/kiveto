<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\Client\Application\Command\ArchiveClient\ArchiveClient;
use App\Client\Application\Command\CreateClient\ContactMethodDto as CreateContactMethodDto;
use App\Client\Application\Command\CreateClient\CreateClient;
use App\Client\Application\Command\ReplaceClientContactMethods\ContactMethodDto as ReplaceContactMethodDto;
use App\Client\Application\Command\ReplaceClientContactMethods\ReplaceClientContactMethods;
use App\Client\Application\Command\UpdateClientIdentity\UpdateClientIdentity;
use App\Client\Application\Query\GetClientById\GetClientById;
use App\Client\Application\Query\SearchClients\SearchClients;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/clients', name: 'clinic_clients_')]
final class ClientController extends AbstractController
{
    private const string CSRF_ID = 'clinic_client_form';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $search = trim((string) $request->query->get('search', ''));
        $page   = max(1, (int) $request->query->get('page', 1));
        $limit  = 20;

        $result = $this->queryBus->ask(new SearchClients(
            clinicId: $currentClinicId->toString(),
            searchTerm: '' !== $search ? $search : null,
            page: $page,
            limit: $limit,
        ));

        \assert(\is_array($result));
        \assert(isset($result['items']));
        \assert(isset($result['total']));
        \assert(\is_array($result['items']));
        \assert(\is_int($result['total']));

        return $this->render('clinic/clients/list.html.twig', [
            'clients'         => $result['items'],
            'total'           => $result['total'],
            'page'            => $page,
            'limit'           => $limit,
            'search'          => $search,
            'totalPages'      => (int) ceil($result['total'] / $limit),
            'currentClinicId' => $currentClinicId->toString(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        return $this->render('clinic/clients/form.html.twig', [
            'client'          => null,
            'csrf_token'      => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            'currentClinicId' => $currentClinicId->toString(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->assertCsrf($request);

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $firstName = trim((string) $request->request->get('first_name'));
        $lastName  = trim((string) $request->request->get('last_name'));

        $contactMethods = $this->extractContactMethods($request);

        if ('' === $firstName || '' === $lastName) {
            throw new \InvalidArgumentException('Le prénom et le nom sont obligatoires.');
        }

        if (0 === \count($contactMethods)) {
            throw new \InvalidArgumentException('Au moins un moyen de contact est obligatoire.');
        }

        $clientId = $this->commandBus->dispatch(new CreateClient(
            clinicId: $currentClinicId->toString(),
            firstName: $firstName,
            lastName: $lastName,
            contactMethods: $contactMethods,
        ));

        $this->addFlash('success', \sprintf('Client "%s %s" créé avec succès.', $firstName, $lastName));

        return $this->redirectToRoute('clinic_clients_view', ['id' => $clientId]);
    }

    #[Route('/{id}', name: 'view', methods: ['GET'])]
    public function view(string $id): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $client = $this->queryBus->ask(new GetClientById(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        if (null === $client) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        return $this->render('clinic/clients/view.html.twig', [
            'client'          => $client,
            'currentClinicId' => $currentClinicId->toString(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET'])]
    public function edit(string $id): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $client = $this->queryBus->ask(new GetClientById(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        if (null === $client) {
            throw $this->createNotFoundException('Client introuvable.');
        }

        return $this->render('clinic/clients/form.html.twig', [
            'client'          => $client,
            'csrf_token'      => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
            'currentClinicId' => $currentClinicId->toString(),
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['POST'])]
    public function update(string $id, Request $request): Response
    {
        $this->assertCsrf($request);

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $firstName = trim((string) $request->request->get('first_name'));
        $lastName  = trim((string) $request->request->get('last_name'));

        $contactMethods = $this->extractContactMethodsForReplace($request);

        if ('' === $firstName || '' === $lastName) {
            throw new \InvalidArgumentException('Le prénom et le nom sont obligatoires.');
        }

        if (0 === \count($contactMethods)) {
            throw new \InvalidArgumentException('Au moins un moyen de contact est obligatoire.');
        }

        // Update identity
        $this->commandBus->dispatch(new UpdateClientIdentity(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
            firstName: $firstName,
            lastName: $lastName,
        ));

        // Replace contact methods
        $this->commandBus->dispatch(new ReplaceClientContactMethods(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
            contactMethods: $contactMethods,
        ));

        $this->addFlash('success', \sprintf('Client "%s %s" modifié avec succès.', $firstName, $lastName));

        return $this->redirectToRoute('clinic_clients_view', ['id' => $id]);
    }

    #[Route('/{id}/archive', name: 'archive', methods: ['POST'])]
    public function archive(string $id, Request $request): Response
    {
        $this->assertCsrf($request);

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $this->commandBus->dispatch(new ArchiveClient(
            clinicId: $currentClinicId->toString(),
            clientId: $id,
        ));

        $this->addFlash('success', 'Client archivé avec succès.');

        return $this->redirectToRoute('clinic_clients_list');
    }

    /**
     * @return list<CreateContactMethodDto>
     */
    private function extractContactMethods(Request $request): array
    {
        $contactMethods = [];

        /** @var array<int|string, mixed> $types */
        $types = $request->request->all('contact_type');
        /** @var array<int|string, mixed> $labels */
        $labels = $request->request->all('contact_label');
        /** @var array<int|string, mixed> $values */
        $values = $request->request->all('contact_value');
        /** @var array<int|string, mixed> $primary */
        $primary = $request->request->all('contact_primary');

        foreach ($types as $index => $type) {
            $typeValue  = $labels[$index] ?? '';
            $labelValue = $labels[$index] ?? '';
            $valueValue = $values[$index] ?? '';

            \assert(\is_scalar($type));
            \assert(\is_scalar($labelValue));
            \assert(\is_scalar($valueValue));

            $type      = trim((string) $type);
            $label     = trim((string) $labelValue);
            $value     = trim((string) $valueValue);
            $isPrimary = isset($primary[$index]);

            if ('' === $type || '' === $label || '' === $value) {
                continue;
            }

            $contactMethods[] = new CreateContactMethodDto(
                type: $type,
                label: $label,
                value: $value,
                isPrimary: $isPrimary,
            );
        }

        return $contactMethods;
    }

    /**
     * @return list<ReplaceContactMethodDto>
     */
    private function extractContactMethodsForReplace(Request $request): array
    {
        $contactMethods = [];

        /** @var array<int|string, mixed> $types */
        $types = $request->request->all('contact_type');
        /** @var array<int|string, mixed> $labels */
        $labels = $request->request->all('contact_label');
        /** @var array<int|string, mixed> $values */
        $values = $request->request->all('contact_value');
        /** @var array<int|string, mixed> $primary */
        $primary = $request->request->all('contact_primary');

        foreach ($types as $index => $type) {
            $typeValue  = $labels[$index] ?? '';
            $labelValue = $labels[$index] ?? '';
            $valueValue = $values[$index] ?? '';

            \assert(\is_scalar($type));
            \assert(\is_scalar($labelValue));
            \assert(\is_scalar($valueValue));

            $type      = trim((string) $type);
            $label     = trim((string) $labelValue);
            $value     = trim((string) $valueValue);
            $isPrimary = isset($primary[$index]);

            if ('' === $type || '' === $label || '' === $value) {
                continue;
            }

            $contactMethods[] = new ReplaceContactMethodDto(
                type: $type,
                label: $label,
                value: $value,
                isPrimary: $isPrimary,
            );
        }

        return $contactMethods;
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
