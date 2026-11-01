<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\UpdateOwnerProfile;

use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Domain\ValueObject\PostalAddress;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @throws OwnerProfileNotFoundException before any acceptance
 * @throws \InvalidArgumentException     for a name or an address the profile refuses
 */
#[AsMessageHandler]
final readonly class UpdateOwnerProfileHandler
{
    public const int NAME_MAX_LENGTH = OwnerProfile::NAME_MAX_LENGTH;

    public function __construct(private OwnerProfileRepositoryInterface $profiles)
    {
    }

    public function __invoke(UpdateOwnerProfile $command): void
    {
        $profile = $this->profiles->findByUserId($command->userId) ?? throw new OwnerProfileNotFoundException($command->userId);

        if (null !== $command->firstName || null !== $command->lastName) {
            $profile->rename($command->firstName ?? $profile->firstName(), $command->lastName ?? $profile->lastName());
        }

        $address = $command->address;

        if (null !== $address) {
            // The second street line is not part of the edit form: an edited address drops it.
            $profile->changeAddress(PostalAddress::create($address->streetLine1, $address->city, $address->countryCode, null, $address->postalCode));
        }

        $this->profiles->updateIdentity($profile);
    }
}
