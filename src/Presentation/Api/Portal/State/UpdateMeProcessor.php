<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\ProfileAddress;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\UpdateOwnerProfile;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Resource\MeUpdate;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\PortalAccountViews;
use App\Presentation\Api\Portal\View\AccountView;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MeUpdate, AccountView>
 */
final readonly class UpdateMeProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private PortalAccountViews $accounts,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AccountView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);
        $address = $data->address;

        try {
            $this->commandBus->dispatch(new UpdateOwnerProfile(
                $user->id(),
                $data->firstName,
                $data->lastName,
                null === $address ? null : new ProfileAddress($address->streetLine1, $address->postalCode, $address->city, $address->countryCode),
            ));
        } catch (OwnerProfileNotFoundException) {
            throw PortalProblemMap::problem(PortalProblemMap::NOT_FOUND);
        }

        return $this->accounts->of($user->id());
    }
}
