<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\ListLocales;

use App\Translation\Domain\Model\ValueObject\AppScope;
use App\Translation\Domain\Model\ValueObject\TranslationDomain;
use App\Translation\Domain\Repository\TranslationSearchRepository;
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
