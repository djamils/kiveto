<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Context\ClientPortal\Application\Query\ListDataExports\DataExportSummary;
use App\Context\ClientPortal\Application\Query\ListDataExports\ListDataExports;
use App\Presentation\Api\Portal\Security\PortalApiUser;
use App\Presentation\Api\Portal\View\DataExportView;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<DataExportView>
 */
final readonly class DataExportListProvider implements ProviderInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private Security $security,
    ) {
    }

    /**
     * @return list<DataExportView>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        \assert($user instanceof PortalApiUser);

        $exports = $this->queryBus->ask(new ListDataExports($user->id()));
        \assert(\is_array($exports));

        return array_values(array_map(static function (mixed $export): DataExportView {
            \assert($export instanceof DataExportSummary);

            return DataExportView::of($export);
        }, $exports));
    }
}
