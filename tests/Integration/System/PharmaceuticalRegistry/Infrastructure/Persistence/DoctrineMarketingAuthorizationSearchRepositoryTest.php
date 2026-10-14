<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\PharmaceuticalRegistry\Infrastructure\Persistence;

use App\Fixtures\System\PharmaceuticalRegistry\Factory\AuthorizationEntityFactory;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository\DoctrineMarketingAuthorizationSearchRepository;
use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

#[CoversMethod(DoctrineMarketingAuthorizationSearchRepository::class, 'search')]
final class DoctrineMarketingAuthorizationSearchRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testEveryWordTypedMustStartAWordOfTheName(): void
    {
        AuthorizationEntityFactory::createOne(['commercialName' => 'Zzquel 3,6 mg', 'holderLaboratory' => 'Zoetis', 'atcVetCode' => 'QD11AH91']);
        AuthorizationEntityFactory::createOne(['commercialName' => 'Zzquel 16 mg', 'holderLaboratory' => 'Zoetis']);
        AuthorizationEntityFactory::createOne(['commercialName' => 'Prozzquel', 'holderLaboratory' => 'Ceva']);

        self::assertSame(['Zzquel 16 mg', 'Zzquel 3,6 mg'], self::names($this->repository()->search('zzq', 10)));
        self::assertSame(['Zzquel 3,6 mg'], self::names($this->repository()->search('ZZQUEL 3', 10)));
        self::assertSame(['Zzquel 16 mg'], self::names($this->repository()->search('zzquel', 1)));

        $first = $this->repository()->search('zzquel 3', 10)[0];
        self::assertSame('Zoetis', $first->holderLaboratory);
        self::assertSame('QD11AH91', $first->atcVetCode);
        self::assertSame('ACTIVE', $first->status);
    }

    public function testNothingTypedFindsNothing(): void
    {
        self::assertSame([], $this->repository()->search('  -  ', 10));
    }

    /**
     * @param array<MarketingAuthorizationSearchResult> $results
     *
     * @return list<string>
     */
    private static function names(array $results): array
    {
        return array_values(array_map(static fn (MarketingAuthorizationSearchResult $r): string => $r->commercialName, $results));
    }

    private function repository(): DoctrineMarketingAuthorizationSearchRepository
    {
        $repository = self::getContainer()->get(DoctrineMarketingAuthorizationSearchRepository::class);
        \assert($repository instanceof DoctrineMarketingAuthorizationSearchRepository);

        return $repository;
    }
}
