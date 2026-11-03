<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;

/**
 * The API is stateless: an authenticated call never reaches for the session.
 * The access token authenticates every request, so every listener of a
 * successful sign-in runs on every call. A listener that used the session
 * made the dev stack, whose kernel runs in debug mode, answer 500 (« Session
 * was used while the request was declared stateless. »); without debug, as in
 * this suite, Symfony only logs a warning — hence the check on the session.
 */
final class StatelessApiTest extends PortalApiTestCase
{
    public function testAnAuthenticatedCallLeavesTheSessionAlone(): void
    {
        $owner  = PortalUserFactory::new()->withPhone('+33612000090')->create();
        $tokens = $this->signIn('+33612000090');
        $bearer = ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($tokens['accessToken'])];

        $me = self::json($this->get('/me', $bearer), 200);

        self::assertIsArray($me['account']);
        self::assertSame($owner->getId()->toRfc4122(), $me['account']['id']);
        self::assertFalse($this->browser->getRequest()->hasSession(true), 'nothing opened the session of the call');
    }
}
