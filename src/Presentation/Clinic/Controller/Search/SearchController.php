<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Search;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use App\Shared\Search\Query\SearchQuery;
use App\Shared\Search\SearchEngine;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/search', name: 'clinic_search', methods: ['GET'])]
final class SearchController extends AbstractController
{
    private const array VALID_MODES = ['navigate', 'pick'];

    /**
     * @param array<string, string> $bucketIcons
     * @param array<string, string> $routeMap
     */
    public function __construct(
        private readonly SearchEngine $searchEngine,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly SearchTermNormalizer $normalizer,
        private readonly RateLimiterFactoryInterface $apiClinicSearchLimiter,
        #[Autowire(param: 'vetsaas.search.bucket_icons')]
        private readonly array $bucketIcons,
        #[Autowire(param: 'vetsaas.search.route_map')]
        private readonly array $routeMap,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $limit = $this->apiClinicSearchLimiter->create($user->id())->consume(1);

        if (!$limit->isAccepted()) {
            return new Response('Too Many Requests', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $mode = (string) $request->query->get('mode', 'navigate');

        if (!\in_array($mode, self::VALID_MODES, true)) {
            return new Response('Invalid mode parameter.', Response::HTTP_BAD_REQUEST);
        }

        $q        = trim((string) $request->query->get('q', ''));
        $type     = $request->query->has('type') ? (string) $request->query->get('type') : null;
        $pickerId = $request->query->has('pickerId') ? (string) $request->query->get('pickerId') : null;

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        if (!$this->normalizer->isExecutable($q)) {
            return $this->render('clinic/search/_results.html.twig', [
                'buckets'     => [],
                'mode'        => $mode,
                'pickerId'    => $pickerId,
                'bucketIcons' => $this->bucketIcons,
                'routeMap'    => $this->routeMap,
            ]);
        }

        $query = new SearchQuery(
            term: $q,
            clinicId: $currentClinicId->toString(),
            userId: $user->id(),
        );

        if (null !== $type) {
            $single  = $this->searchEngine->searchOne($query, $type);
            $buckets = null !== $single ? [$single] : [];
        } else {
            $buckets = $this->searchEngine->searchAll($query);
        }

        return $this->render('clinic/search/_results.html.twig', [
            'buckets'     => $buckets,
            'mode'        => $mode,
            'pickerId'    => $pickerId,
            'bucketIcons' => $this->bucketIcons,
            'routeMap'    => $this->routeMap,
        ]);
    }
}
