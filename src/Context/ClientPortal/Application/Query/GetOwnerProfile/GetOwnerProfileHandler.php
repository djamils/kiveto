<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetOwnerProfile;

use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetOwnerProfileHandler
{
    public function __construct(private OwnerProfileRepositoryInterface $profiles)
    {
    }

    public function __invoke(GetOwnerProfile $query): ?OwnerProfileDetails
    {
        $profile = $this->profiles->findByUserId($query->userId);

        if (null === $profile) {
            return null;
        }

        $address = $profile->address();

        return new OwnerProfileDetails(
            firstName: $profile->firstName(),
            lastName: $profile->lastName(),
            address: null === $address ? null : new OwnerAddress(
                line1: $address->streetLine1,
                line2: $address->streetLine2,
                postalCode: $address->postalCode,
                city: $address->city,
                country: $address->countryCode,
            ),
            termsVersion: $profile->termsVersion(),
            termsAcceptedAt: $profile->termsAcceptedAt(),
        );
    }
}
