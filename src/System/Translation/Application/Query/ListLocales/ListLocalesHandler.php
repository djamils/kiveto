<?php

declare(strict_types=1);

namespace App\System\Translation\Application\Query\ListLocales;

use App\System\Translation\Domain\Repository\TranslationSearchRepository;
use App\System\Translation\Domain\ValueObject\AppScope;
use App\System\Translation\Domain\ValueObject\TranslationDomain;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListLocalesHandler
{
    public function __construct(private TranslationSearchRepository $repository)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListLocales $query): array
    {
        $scope  = null !== $query->scope ? AppScope::fromString($query->scope) : null;
        $domain = null !== $query->domain ? TranslationDomain::fromString($query->domain) : null;

        return $this->repository->listLocales($scope, $domain);
    }
}
