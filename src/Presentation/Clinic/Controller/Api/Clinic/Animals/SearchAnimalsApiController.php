<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Api\Clinic\Animals;

use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimals;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/animals/search', name: 'api_clinic_animals_search', methods: ['GET'])]
final class SearchAnimalsApiController extends AbstractController
{
    private const int MIN_QUERY_LENGTH = 2;
    private const int DEFAULT_LIMIT    = 10;
    private const int MAX_LIMIT        = 20;

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly RateLimiterFactoryInterface $apiClinicSearchLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $accept = (string) $request->headers->get('Accept', '');
        if ('' !== $accept && '*/*' !== $accept && false === stripos($accept, 'application/json')) {
            return new JsonResponse(['error' => 'not_acceptable'], Response::HTTP_NOT_ACCEPTABLE);
        }

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $limit = $this->apiClinicSearchLimiter->create($user->id())->consume(1);
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return new JsonResponse(
                ['error' => 'too_many_requests'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter],
            );
        }

        $ownerId = trim((string) $request->query->get('ownerId', ''));
        if ('' === $ownerId) {
            return new JsonResponse(
                ['error' => 'missing_owner_id', 'message' => 'Le paramètre ownerId est obligatoire.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return $this->envelope([]);
        }

        $requestedLimit = (int) $request->query->get('limit', (string) self::DEFAULT_LIMIT);
        $cappedLimit    = max(1, min(self::MAX_LIMIT, $requestedLimit));

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $result = $this->queryBus->ask(new SearchAnimals(
            clinicId: $currentClinicId->toString(),
            searchTerm: $query,
            ownerClientId: $ownerId,
            page: 1,
            limit: $cappedLimit,
        ));

        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));

        $items = [];
        foreach (array_values($result['items']) as $row) {
            \assert($row instanceof AnimalListItemView);
            $items[] = [
                'id'        => $row->id,
                'name'      => $row->name,
                'species'   => $row->species,
                'breedName' => $row->breedName,
            ];
        }

        return $this->envelope($items);
    }

    /**
     * @param list<array<string, string|null>> $items
     */
    private function envelope(array $items): JsonResponse
    {
        return new JsonResponse([
            'data' => $items,
            'meta' => ['count' => \count($items)],
        ]);
    }
}
