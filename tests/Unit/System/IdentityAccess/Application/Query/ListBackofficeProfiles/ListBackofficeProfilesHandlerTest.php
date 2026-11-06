<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\ListBackofficeProfiles;

use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\ListBackofficeProfiles\ListBackofficeProfiles;
use App\System\IdentityAccess\Application\Query\ListBackofficeProfiles\ListBackofficeProfilesHandler;
use PHPUnit\Framework\TestCase;

final class ListBackofficeProfilesHandlerTest extends TestCase
{
    public function testAnswersEveryActiveMemberOfTheTeam(): void
    {
        $views = [
            new BackofficeProfileView('01960000-0000-7000-8000-000000005b01', 'alice@kiveto.fr', 'Alice', 'Europe/Paris', true, true, new \DateTimeImmutable('2026-01-15 09:30:00')),
            new BackofficeProfileView('01960000-0000-7000-8000-000000005b02', 'bob@kiveto.fr', 'Bob', 'America/Martinique', false, true, new \DateTimeImmutable('2026-02-15 09:30:00')),
        ];
        $profiles = $this->createMock(BackofficeProfileStoreInterface::class);
        $profiles->expects(self::once())->method('all')->willReturn($views);

        self::assertSame($views, new ListBackofficeProfilesHandler($profiles)(new ListBackofficeProfiles()));
    }
}
