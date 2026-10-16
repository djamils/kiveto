<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SuggestPostalAddresses;

use App\Context\Client\Application\Port\AddressDirectoryInterface;
use App\Context\Client\Application\Port\AddressSuggestion;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SuggestPostalAddressesHandler
{
    public function __construct(
        private AddressDirectoryInterface $addressDirectory,
    ) {
    }

    /**
     * @return list<AddressSuggestion>
     */
    public function __invoke(SuggestPostalAddresses $query): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $query->query));

        // Fewer letters would match half the country.
        if (mb_strlen($text) < SuggestPostalAddresses::MIN_LENGTH) {
            return [];
        }

        $near = null !== $query->nearPostalCode && 1 === preg_match('/^\d{5}$/', $query->nearPostalCode)
            ? $query->nearPostalCode
            : null;

        return $this->addressDirectory->suggest(
            $text,
            max(1, min(SuggestPostalAddresses::MAX_LIMIT, $query->limit)),
            $near,
        );
    }
}
