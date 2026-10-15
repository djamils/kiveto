<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\RecordUserLogin\RecordUserLogin;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\RecordLoginListener;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class RecordLoginListenerTest extends TestCase
{
    public function testRecordsTheSignInOfAKivetoUser(): void
    {
        $user = new SecurityUser('11111111-1111-1111-1111-111111111111', 'vet@kiveto.local', UserType::CLINIC);

        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new RecordUserLogin('11111111-1111-1111-1111-111111111111'));

        (new RecordLoginListener($bus))($this->event($user));
    }

    public function testIgnoresAnyOtherKindOfUser(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        (new RecordLoginListener($bus))($this->event($this->createStub(UserInterface::class)));
    }

    private function event(UserInterface $user): LoginSuccessEvent
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('someone', static fn (): UserInterface => $user)),
            $token,
            new Request(),
            null,
            'clinic',
        );
    }
}
