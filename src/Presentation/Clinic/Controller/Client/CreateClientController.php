<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Client;

use App\Client\Application\Command\CreateClient\ContactMethodDto;
use App\Client\Application\Command\CreateClient\CreateClient;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/clients/create', name: 'clinic_clients_create', methods: ['POST'])]
final class CreateClientController extends AbstractController
{
    private const string CSRF_ID = 'clinic_client_form';

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(Request $request): Response
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

    /**
     * @return list<ContactMethodDto>
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

            $contactMethods[] = new ContactMethodDto(
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
