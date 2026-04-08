<?php

declare(strict_types=1);

namespace App\System\Translation\Application\Query\ListDomains;

use App\Shared\Domain\Localization\Locale;
use App\System\Translation\Domain\Repository\TranslationSearchRepository;
use App\System\Translation\Domain\ValueObject\AppScope;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListDomainsHandler
{
    public function __construct(private TranslationSearchRepository $repository)
    {
    }

    /**
     * @return list<string>
     */
    public function __invoke(ListDomains $query): array
    {
        $scope  = null !== $query->scope ? AppScope::fromString($query->scope) : null;
        $locale = null !== $query->locale ? Locale::fromString($query->locale) : null;

        return $this->repository->listDomains($scope, $locale);
    }
}
