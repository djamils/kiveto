<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView as SchedulingMotif;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Presentation\Api\Portal\Support\PublicListingRateLimit;
use App\Presentation\Api\Portal\Support\PublicListingResolver;
use App\Presentation\Api\Portal\View\BookingMotifView;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * @implements ProviderInterface<BookingMotifView>
 */
final readonly class ListingMotifsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PublicListingResolver $listings,
        private PublicListingRateLimit $rateLimit,
    ) {
    }

    /**
     * @return list<BookingMotifView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $this->rateLimit->consume();
        $listingId = $uriVariables['listingId'] ?? null;
        \assert(\is_string($listingId));

        $clinicId = $this->listings->bookableClinicId($listingId);

        $motifs = $this->queryBus->ask(new ListBookingMotifs($clinicId, newClientsOnly: true));
        \assert(\is_array($motifs));

        return array_values(array_map(static function (mixed $motif): BookingMotifView {
            \assert($motif instanceof SchedulingMotif);

            return BookingMotifView::forNewClient($motif);
        }, $motifs));
    }
}
