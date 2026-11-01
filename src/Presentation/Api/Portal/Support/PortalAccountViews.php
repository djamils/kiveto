<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Support;

use App\Context\ClientPortal\Application\Query\GetOwnerProfile\GetOwnerProfile;
use App\Context\ClientPortal\Application\Query\GetOwnerProfile\OwnerProfileDetails;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\View\AccountView;
use App\Presentation\Api\Portal\View\AddressView;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\PortalAccount;

/** Assembles the `account` of /me and of the acceptance answers. */
final readonly class PortalAccountViews
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function of(string $userId): AccountView
    {
        $account = $this->queryBus->ask(new GetPortalAccount($userId));
        if (!$account instanceof PortalAccount) {
            throw PortalProblemMap::problem(PortalProblemMap::UNAUTHORIZED);
        }

        $profile = $this->queryBus->ask(new GetOwnerProfile($userId));
        $profile = $profile instanceof OwnerProfileDetails ? $profile : null;
        $address = $profile?->address;

        return new AccountView(
            id: $account->userId,
            firstName: $profile?->firstName,
            lastName: $profile?->lastName,
            email: $account->email,
            emailVerified: $account->emailVerified,
            maskedPhone: PhoneMasker::mask($account->phone),
            address: null === $address ? null : new AddressView($address->line1, $address->line2, $address->postalCode, $address->city, $address->country),
        );
    }
}
