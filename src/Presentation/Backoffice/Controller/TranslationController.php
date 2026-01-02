<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Controller;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Translation\Application\Command\DeleteTranslation\DeleteTranslation;
use App\Translation\Application\Command\UpsertTranslation\UpsertTranslation;
use App\Translation\Application\Query\SearchTranslations\SearchTranslations;
use App\Translation\Application\Query\SearchTranslations\TranslationSearchResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '', host: 'backoffice.kiveto.local')]
final class TranslationController extends AbstractController
{
    private const string CSRF_ID = 'backoffice_translation';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/translations', name: 'backoffice_translations', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $criteria = new SearchTranslations(
            scope: $request->query->get('scope'),
            locale: $request->query->get('locale'),
            domain: $request->query->get('domain'),
            keyContains: $request->query->get('key'),
            valueContains: $request->query->get('value'),
            updatedBy: null,
            updatedAfter: null,
            page: max(1, (int) $request->query->get('page', 1)),
            perPage: 50,
        );

        /** @var TranslationSearchResult $result */
        $result = $this->queryBus->ask($criteria);

        return $this->render('backoffice/translations/index.html.twig', [
            'result' => $result,
            'filters' => [
                'scope' => (string) $request->query->get('scope', ''),
                'locale' => (string) $request->query->get('locale', ''),
                'domain' => (string) $request->query->get('domain', ''),
                'key' => (string) $request->query->get('key', ''),
                'value' => (string) $request->query->get('value', ''),
                'page' => (int) $request->query->get('page', 1),
            ],
            'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
        ]);
    }

    #[Route(path: '/translations/upsert', name: 'backoffice_translations_upsert', methods: ['POST'])]
    public function upsert(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $scope  = (string) $request->request->get('scope');
        $locale = (string) $request->request->get('locale');
        $domain = (string) $request->request->get('domain');
        $key    = (string) $request->request->get('key');
        $value  = (string) $request->request->get('value');
        $description = $request->request->get('description');

        if ('' === trim($scope) || '' === trim($locale) || '' === trim($domain) || '' === trim($key) || '' === $value) {
            $this->addFlash('error', 'Tous les champs sont obligatoires.');

            return $this->redirectToRoute('backoffice_translations', $this->redirectParams($request));
        }

        $this->commandBus->dispatch(new UpsertTranslation(
            scope: $scope,
            locale: $locale,
            domain: $domain,
            key: $key,
            value: $value,
            description: \is_string($description) ? $description : null,
            actorId: null,
        ));

        $this->addFlash('success', 'Traduction enregistrée.');

        return $this->redirectToRoute('backoffice_translations', $this->redirectParams($request, $scope, $locale, $domain));
    }

    #[Route(path: '/translations/delete', name: 'backoffice_translations_delete', methods: ['POST'])]
    public function delete(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $scope  = (string) $request->request->get('scope');
        $locale = (string) $request->request->get('locale');
        $domain = (string) $request->request->get('domain');
        $key    = (string) $request->request->get('key');

        if ('' === trim($scope) || '' === trim($locale) || '' === trim($domain) || '' === trim($key)) {
            $this->addFlash('error', 'Paramètres manquants pour la suppression.');

            return $this->redirectToRoute('backoffice_translations', $this->redirectParams($request));
        }

        $this->commandBus->dispatch(new DeleteTranslation(
            scope: $scope,
            locale: $locale,
            domain: $domain,
            key: $key,
            actorId: null,
        ));

        $this->addFlash('success', 'Traduction supprimée (si elle existait).');

        return $this->redirectToRoute('backoffice_translations', $this->redirectParams($request, $scope, $locale, $domain));
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function redirectParams(Request $request, ?string $scope = null, ?string $locale = null, ?string $domain = null): array
    {
        return [
            'scope' => $scope ?? (string) $request->request->get('scope', ''),
            'locale' => $locale ?? (string) $request->request->get('locale', ''),
            'domain' => $domain ?? (string) $request->request->get('domain', ''),
            'key' => (string) $request->request->get('key', $request->query->get('key', '')),
            'value' => (string) $request->request->get('value', $request->query->get('value', '')),
            'description' => (string) $request->request->get('description', $request->query->get('description', '')),
            'page' => (int) $request->query->get('page', 1),
        ];
    }
}

