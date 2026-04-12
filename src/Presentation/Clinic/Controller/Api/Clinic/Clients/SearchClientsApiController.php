<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Api\Clinic\Clients;

use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClients;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/clients/search', name: 'api_clinic_clients_search', methods: ['GET'])]
final class SearchClientsApiController extends AbstractController
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
        if (!$this->isJsonAcceptable($request)) {
            return new JsonResponse(
                ['error' => 'not_acceptable'],
                Response::HTTP_NOT_ACCEPTABLE,
            );
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

        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return $this->envelope([]);
        }

        $requestedLimit = (int) $request->query->get('limit', (string) self::DEFAULT_LIMIT);
        $cappedLimit    = max(1, min(self::MAX_LIMIT, $requestedLimit));

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $result = $this->queryBus->ask(new SearchClients(
            clinicId: $currentClinicId->toString(),
            searchTerm: $query,
            page: 1,
            limit: $cappedLimit,
        ));

        \assert(\is_array($result));
        \assert(isset($result['items']) && \is_array($result['items']));

        $items = [];
        foreach (array_values($result['items']) as $row) {
            \assert($row instanceof ClientListItemView);
            $items[] = [
                'id'           => $row->id,
                'firstName'    => $row->firstName,
                'lastName'     => $row->lastName,
                'fullName'     => $row->fullName(),
                'primaryEmail' => $row->primaryEmail,
                'primaryPhone' => $row->primaryPhone,
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

    private function isJsonAcceptable(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');

        if ('' === $accept || '*/*' === $accept) {
            return true;
        }

        return false !== stripos($accept, 'application/json');
    }
}
