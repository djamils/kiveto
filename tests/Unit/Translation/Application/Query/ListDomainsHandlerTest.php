<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Query;

use App\Translation\Application\Query\ListDomains\ListDomains;
use App\Translation\Application\Query\ListDomains\ListDomainsHandler;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use PHPUnit\Framework\TestCase;

final class ListDomainsHandlerTest extends TestCase
{
    public function testReturnsDomains(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('listDomains')
            ->willReturn(['messages', 'auth'])
        ;

        $handler = new ListDomainsHandler($repo);

        $result = $handler(new ListDomains(scope: 'clinic', locale: 'fr_FR'));

        self::assertSame(['messages', 'auth'], $result);
    }
}
