<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\SearchClinicListings;

use App\Context\ClinicListing\Application\Port\ClinicListingReadModelInterface;
use App\Context\ClinicListing\Application\Port\ListingRow;
use App\Context\ClinicListing\Application\Port\OnlineBookingStatusInterface;
use App\Context\ClinicListing\Application\Query\Shared\ClinicListingAddressView;
use App\Context\ClinicListing\Application\Query\Shared\OnlineBookingResolver;
use App\Context\ClinicListing\Domain\Exception\InvalidCursorException;
use App\Context\ClinicListing\Domain\Exception\InvalidListingSearchException;
use App\Shared\Application\Pagination\Cursor;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchClinicListingsHandler
{
    public function __construct(
        private ClinicListingReadModelInterface $listings,
        private OnlineBookingStatusInterface $onlineBooking,
    ) {
    }

    /**
     * @throws InvalidListingSearchException
     * @throws InvalidCursorException
     */
    public function __invoke(SearchClinicListings $query): ClinicListingPage
    {
        $q = self::text($query->q);
        self::validate($q, $query->lat, $query->lng);
        $limit = Cursor::clampLimit($query->limit);

        $around = null !== $query->lat && null !== $query->lng;
        $after  = null === $query->cursor ? null : ListingCursor::decode($query->cursor, $query->lat, $query->lng);

        $rows = $around
            ? $this->listings->searchByDistance($q, (float) $query->lat, (float) $query->lng, SearchClinicListings::RADIUS_METRES, $after?->distanceM, $after?->id, $limit + 1)
            : $this->listings->searchByName($q, $after?->name, $after?->id, $limit + 1);

        $nextCursor = null;

        if (\count($rows) > $limit) {
            $rows       = \array_slice($rows, 0, $limit);
            $last       = $rows[\count($rows) - 1];
            $nextCursor = $around
                ? ListingCursor::encodeDistance((int) $last->distanceM, $last->id, (float) $query->lat, (float) $query->lng)
                : ListingCursor::encodeName($last->name, $last->id);
        }

        $open = OnlineBookingResolver::openAmong($this->onlineBooking, $rows);

        return new ClinicListingPage(
            items: array_map(static fn (ListingRow $row): ClinicListingSummary => new ClinicListingSummary(
                id: $row->id,
                name: $row->name,
                address: ClinicListingAddressView::of($row->addressLine, $row->postalCode, $row->city),
                phone: $row->phone,
                distanceM: $row->distanceM,
                onlineBooking: \in_array($row->clinicId, $open, true),
                tags: $row->tags,
            ), $rows),
            nextCursor: $nextCursor,
        );
    }

    private static function text(?string $q): ?string
    {
        $q = mb_trim((string) preg_replace('/\s+/u', ' ', $q ?? ''));

        return '' === $q ? null : $q;
    }

    private static function validate(?string $q, ?float $lat, ?float $lng): void
    {
        if (null !== $q && mb_strlen($q) < SearchClinicListings::MIN_QUERY) {
            throw new InvalidListingSearchException('q', \sprintf('Saisissez au moins %d caractères.', SearchClinicListings::MIN_QUERY));
        }

        if (null === $lat && null !== $lng) {
            throw new InvalidListingSearchException('lat', 'Indiquez la latitude avec la longitude.');
        }

        if (null !== $lat && null === $lng) {
            throw new InvalidListingSearchException('lng', 'Indiquez la longitude avec la latitude.');
        }

        if (null !== $lat && ($lat < -90.0 || $lat > 90.0)) {
            throw new InvalidListingSearchException('lat', 'La latitude est comprise entre -90 et 90.');
        }

        if (null !== $lng && ($lng < -180.0 || $lng > 180.0)) {
            throw new InvalidListingSearchException('lng', 'La longitude est comprise entre -180 et 180.');
        }
    }
}
