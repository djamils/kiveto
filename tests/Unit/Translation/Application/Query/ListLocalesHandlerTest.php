<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Query;

use App\Translation\Application\Query\ListLocales\ListLocales;
use App\Translation\Application\Query\ListLocales\ListLocalesHandler;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use PHPUnit\Framework\TestCase;

final class ListLocalesHandlerTest extends TestCase
{
    public function testReturnsLocales(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('listLocales')
            ->willReturn(['fr-FR', 'en-GB'])
        ;

        $handler = new ListLocalesHandler($repo);

        $result = $handler(new ListLocales(scope: 'clinic', domain: 'messages'));

        self::assertSame(['fr-FR', 'en-GB'], $result);
    }
}
