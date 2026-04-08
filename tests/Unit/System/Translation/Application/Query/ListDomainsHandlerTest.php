<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Translation\Application\Query;

use App\System\Translation\Application\Query\ListDomains\ListDomains;
use App\System\Translation\Application\Query\ListDomains\ListDomainsHandler;
use App\System\Translation\Domain\Repository\TranslationSearchRepository;
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

        $result = $handler(new ListDomains(scope: 'clinic', locale: 'fr-FR'));

        self::assertSame(['messages', 'auth'], $result);
    }
}
