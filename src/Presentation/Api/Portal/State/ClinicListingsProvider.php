<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingPage;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListings;
use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSearchException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\ClinicListings;
use App\Presentation\Api\Portal\Support\PublicListingRateLimit;
use App\Presentation\Api\Portal\Support\QueryString;
use App\Presentation\Api\Portal\View\ClinicListingSummaryView;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The search rules are ClinicListing's: a refusal is `validation_failed` on
 * its field (a coordinate that is not a plain decimal included), a cursor it cannot read (or issued for other coordinates)
 * `cursor_invalid`.
 *
 * @implements ProviderInterface<ClinicListings>
 */
final readonly class ClinicListingsProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PublicListingRateLimit $rateLimit,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ClinicListings
    {
        $this->rateLimit->consume();

        $query = QueryString::of($context);
        $lat   = self::coordinate($query, 'lat', 'La latitude doit être un nombre.');
        $lng   = self::coordinate($query, 'lng', 'La longitude doit être un nombre.');

        try {
            $page = $this->queryBus->ask(new SearchClinicListings(
                $query->string('q'),
                $lat,
                $lng,
                $query->string('cursor'),
                $query->int('limit'),
            ));
        } catch (InvalidListingSearchException $exception) {
            throw PortalProblemMap::violation($exception->field, $exception->getMessage(), $query->string($exception->field));
        } catch (InvalidCursorException) {
            throw PortalProblemMap::problem(PortalProblemMap::CURSOR_INVALID);
        }
        \assert($page instanceof ClinicListingPage);

        return new ClinicListings(array_map(ClinicListingSummaryView::of(...), $page->items), $page->nextCursor);
    }

    /**
     * A plain decimal, or absent; anything else (a trailing line break the
     * operation's pattern lets through included) is refused on its field,
     * never cast to 0.
     */
    private static function coordinate(QueryString $query, string $field, string $message): ?float
    {
        $value = $query->string($field);

        if (null === $value) {
            return null;
        }

        if (1 !== preg_match('/^-?\d{1,3}(\.\d{1,12})?$/D', $value)) {
            throw PortalProblemMap::violation($field, $message, $value);
        }

        return (float) $value;
    }
}
