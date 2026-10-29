<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Token pairs: rotation, logout, and the security layer's errors in the
 * API's format.
 */
final class SessionTest extends PortalApiTestCase
{
    /** AC 14: each use rotates the refresh token. */
    public function testARefreshRotatesTheToken(): void
    {
        PortalUserFactory::new()->withPhone('+33612000001')->create();
        $first = $this->signIn('+33612000001');

        $second = self::json($this->post('/auth/refresh', ['refreshToken' => $first['refreshToken']]), 200);

        self::assertSame(['accessToken', 'accessTokenExpiresAt', 'refreshToken', 'refreshTokenExpiresAt'], array_keys($second));
        self::assertNotSame($first['refreshToken'], $second['refreshToken']);
        $rotated = $this->connection()->fetchAssociative('SELECT rotated_at, rotated_to_id FROM identity_access__refresh_tokens WHERE token_hash = ?', [hash('sha256', self::string($first['refreshToken']))]);
        self::assertIsArray($rotated);
        self::assertNotNull($rotated['rotated_at']);
        self::assertSame(
            $rotated['rotated_to_id'],
            $this->connection()->fetchOne('SELECT id FROM identity_access__refresh_tokens WHERE token_hash = ?', [hash('sha256', self::string($second['refreshToken']))]),
        );
        self::json($this->post('/auth/refresh', ['refreshToken' => $second['refreshToken']]), 200);
    }

    /** AC 19, AC 50: logout revokes the caller's family only. */
    public function testLogoutRevokesOnlyTheCallersFamily(): void
    {
        PortalUserFactory::new()->withPhone('+33612000002')->create();
        PortalUserFactory::new()->withPhone('+33612000003')->create();
        $alice = $this->signIn('+33612000002');
        $bob   = $this->signIn('+33612000003');

        $this->post('/auth/logout', ['refreshToken' => $bob['refreshToken']], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($alice['accessToken'])]);
        self::assertSame(204, $this->browser->getResponse()->getStatusCode());
        self::json($this->post('/auth/refresh', ['refreshToken' => $bob['refreshToken']]), 200);

        $this->post('/auth/logout', ['refreshToken' => $alice['refreshToken']], ['HTTP_AUTHORIZATION' => 'Bearer ' . self::string($alice['accessToken'])]);
        self::assertSame(204, $this->browser->getResponse()->getStatusCode());
        self::assertSame('refresh_revoked', self::problemType($this->post('/auth/refresh', ['refreshToken' => $alice['refreshToken']]), 401));
    }

    public function testAnUnknownRefreshTokenIsRevoked(): void
    {
        self::assertSame('refresh_revoked', self::problemType($this->post('/auth/refresh', ['refreshToken' => 'not-a-token']), 401));
    }

    /** AC 51: missing, invalid and expired tokens; no other kind of account. */
    public function testAuthenticatedRoutesAnswerProblemsWithoutAValidToken(): void
    {
        self::assertSame('unauthorized', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x']), 401));
        self::assertSame('unauthorized', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x'], ['HTTP_AUTHORIZATION' => 'Bearer not.a.jwt']), 401));

        $owner = PortalUserFactory::new()->withPhone('+33612000004')->create();
        self::assertSame('token_expired', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x'], $this->bearer($owner->getId()->toRfc4122(), time() - 10)), 401));

        $vet = ClinicUserFactory::new()->withEmail('vet.api@example.com')->create();
        self::assertSame('unauthorized', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x'], $this->bearer($vet->getId()->toRfc4122(), time() + 600)), 401), 'a staff id is no owner');

        $gone = PortalUserFactory::new()->withPhone('+33612000005')->disabled()->create();
        self::assertSame('unauthorized', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x'], $this->bearer($gone->getId()->toRfc4122(), time() + 600)), 401), 'a disabled owner');

        self::assertSame('unauthorized', self::problemType($this->post('/auth/logout', ['refreshToken' => 'x'], $this->bearer(Uuid::v7()->toRfc4122(), time() + 600)), 401), 'an unknown id');
    }

    /** AC 71: the firewall is lazy — a stale bearer never blocks a public route. */
    public function testAnExpiredBearerIsIgnoredOnPublicRoutes(): void
    {
        $owner  = PortalUserFactory::new()->withPhone('+33612000006')->create();
        $tokens = $this->signIn('+33612000006');
        $stale  = $this->bearer($owner->getId()->toRfc4122(), time() - 10);

        self::json($this->post('/auth/refresh', ['refreshToken' => $tokens['refreshToken']], $stale), 200);
        self::json($this->post('/auth/otp/request', ['phone' => '+33612000007'], $stale), 202);
        self::json($this->get('/legal/terms/current', $stale), 200);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $sub, int $expiresAt): array
    {
        $encoder = $this->browser->getContainer()->get(JWTEncoderInterface::class);
        \assert($encoder instanceof JWTEncoderInterface);

        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $encoder->encode(['sub' => $sub, 'typ' => 'portal', 'iat' => $expiresAt - 900, 'exp' => $expiresAt])];
    }
}
