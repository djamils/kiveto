<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Security\ActorContext;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\ActorContextListener;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ActorContextListenerTest extends TestCase
{
    private const string USER = '01960000-0000-7000-8000-000000000a01';

    public function testTheSignedInAccountBecomesTheActor(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new SecurityUser(self::USER, 'admin@kiveto.local', UserType::BACKOFFICE), 'backoffice'));
        $context = new ActorContext();

        new ActorContextListener($storage, $context)($this->event(HttpKernelInterface::MAIN_REQUEST));

        self::assertSame(self::USER, $context->get());
    }

    public function testNoTokenOrAnotherUserClassLeavesNoActor(): void
    {
        $context = new ActorContext();
        $context->set('stale');

        new ActorContextListener(new TokenStorage(), $context)($this->event(HttpKernelInterface::MAIN_REQUEST));
        self::assertNull($context->get());

        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('x', null), 'dev'));
        new ActorContextListener($storage, $context)($this->event(HttpKernelInterface::MAIN_REQUEST));
        self::assertNull($context->get());
    }

    public function testASubRequestKeepsTheActorOfItsMainRequest(): void
    {
        $context = new ActorContext();
        $context->set(self::USER);

        new ActorContextListener(new TokenStorage(), $context)($this->event(HttpKernelInterface::SUB_REQUEST));

        self::assertSame(self::USER, $context->get());
    }

    private function event(int $type): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(), $type);
    }
}
