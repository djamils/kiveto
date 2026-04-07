<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Application\Query;

use App\Animal\Application\Query\GetAnimalById\GetAnimalById;
use App\Shared\Application\Bus\QueryInterface;
use PHPUnit\Framework\TestCase;

final class GetAnimalByIdTest extends TestCase
{
    public function testConstruct(): void
    {
        $query = new GetAnimalById(
            clinicId: '12345678-9abc-def0-1234-56789abcdef0',
            animalId: '01234567-89ab-cdef-0123-456789abcdef',
        );

        self::assertInstanceOf(QueryInterface::class, $query);
        self::assertSame('12345678-9abc-def0-1234-56789abcdef0', $query->clinicId);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $query->animalId);
    }
}
