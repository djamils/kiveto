<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\BookablePractitioner;
use App\Context\Scheduling\Application\Query\ListBookablePractitioners\ListBookablePractitioners;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\PublicListingRateLimit;
use App\Presentation\Api\Portal\Support\PublicListingResolver;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\PractitionerView;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * @implements ProviderInterface<PractitionerView>
 */
final readonly class ListingPractitionersProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PublicListingResolver $listings,
        private PublicListingRateLimit $rateLimit,
    ) {
    }

    /**
     * @return list<PractitionerView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $this->rateLimit->consume();
        $listingId = $uriVariables['listingId'] ?? null;
        \assert(\is_string($listingId));

        $clinicId = $this->listings->bookableClinicId($listingId);

        $practitioners = $this->queryBus->ask(new ListBookablePractitioners($clinicId, (string) QueryString::of($context)->string('motifId'), newClient: true));

        if (!\is_array($practitioners)) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return array_values(array_map(static function (mixed $practitioner): PractitionerView {
            \assert($practitioner instanceof BookablePractitioner);

            return PractitionerView::of($practitioner);
        }, $practitioners));
    }
}
