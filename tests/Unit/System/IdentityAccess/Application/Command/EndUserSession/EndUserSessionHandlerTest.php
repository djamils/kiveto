<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command\EndUserSession;

use App\System\IdentityAccess\Application\Command\EndUserSession\EndUserSession;
use App\System\IdentityAccess\Application\Command\EndUserSession\EndUserSessionHandler;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;

final class EndUserSessionHandlerTest extends TestCase
{
    public function testTheSignedOutSessionIsEndedByTheDigestOfItsId(): void
    {
        $now      = new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC'));
        $sessions = $this->createMock(UserSessionStoreInterface::class);
        $sessions->expects(self::once())->method('end')->with(hash('sha256', 'php-session-id'), $now);

        new EndUserSessionHandler($sessions, new FrozenClock($now))(new EndUserSession('php-session-id'));
    }
}
