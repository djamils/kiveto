<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Context\ClientPortal\Application\Command\RequestDataExport\RequestDataExport;
use App\Context\ClientPortal\Domain\Exception\DataExportRateLimitedException;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\View\DataExportRequestView;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<null, DataExportRequestView>
 */
final readonly class RequestDataExportProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DataExportRequestView
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        try {
            $id = $this->commandBus->dispatch(new RequestDataExport($user->id()));
        } catch (DataExportRateLimitedException $limited) {
            throw PortalProblemMap::problem(PortalProblemMap::EXPORT_RATE_LIMITED, ['Retry-After' => (string) $limited->retryAfterSeconds]);
        }
        \assert(\is_string($id));

        return new DataExportRequestView($id);
    }
}
