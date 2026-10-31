<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic\CountUnreadDocumentsPerClinic;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\ListOwnerClinics;
use App\Context\ClientPortal\Application\Query\ListOwnerClinics\OwnerClinic;
use App\Presentation\Api\Portal\Resource\Me;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\Presentation\Api\Portal\Support\PortalAccountViews;
use App\Presentation\Api\Portal\View\ClinicLinkView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Clinic tags arrive with the listing (slice 7): empty for now.
 *
 * @implements ProviderInterface<Me>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private PortalAccountViews $accounts,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Me
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $clinics = $this->queryBus->ask(new ListOwnerClinics($user->id()));
        \assert(\is_array($clinics));
        $unread = $this->queryBus->ask(new CountUnreadDocumentsPerClinic($user->id()));
        \assert(\is_array($unread));

        return new Me(
            $this->accounts->of($user->id()),
            array_values(array_map(static function (mixed $clinic) use ($unread): ClinicLinkView {
                \assert($clinic instanceof OwnerClinic);

                return new ClinicLinkView($clinic->clinicId, $clinic->clinicName, $clinic->city, 'confirmed', ApiDate::format($clinic->linkedAt), $clinic->petNames, [], \is_int($unread[$clinic->clinicId] ?? null) ? $unread[$clinic->clinicId] : 0);
            }, $clinics)),
        );
    }
}
