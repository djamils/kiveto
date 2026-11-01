<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Shared\Domain\ValueObject\PostalAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 5: `PATCH /me` edits the Portal profile only (AC 60).
 */
final class MeUpdateTest extends PetsApiTestCase
{
    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.91';
        parent::setUp();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Sophie', 'Martin')->active()->create();
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::createFromClient(
            $this->userId,
            'Sophie',
            'Martin',
            PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', 'Bât. B', '69003'),
            'v1',
            new \DateTimeImmutable('2026-10-01'),
        ));
    }

    public function testTheProfileChangesAndTheClinicClientFileDoesNot(): void
    {
        $account = self::json($this->patch(['firstName' => '  Sophia ', 'address' => ['streetLine1' => '3 quai Rambaud', 'postalCode' => '69002', 'city' => 'Lyon', 'countryCode' => 'FR']]), 200);

        self::assertSame([$this->userId, 'Sophia', 'Martin'], [$account['id'], $account['firstName'], $account['lastName']]);
        self::assertSame(['line1' => '3 quai Rambaud', 'line2' => null, 'postalCode' => '69002', 'city' => 'Lyon', 'country' => 'FR'], $account['address']);
        self::assertSame(['Sophia', '3 quai Rambaud'], [$this->profile()->firstName(), $this->profile()->address()?->streetLine1]);

        $me = self::json($this->authGet('/me'), 200)['account'] ?? null;
        self::assertIsArray($me);
        self::assertSame('Sophia', $me['firstName']);

        self::assertSame('Sophie', $this->connection()->fetchOne('SELECT first_name FROM client__clients WHERE id = ?', [Uuid::fromString(self::CLIENT)->toBinary()]));
    }

    public function testAnEmptyBodyChangesNothing(): void
    {
        $account = self::json($this->patch([]), 200);

        self::assertSame(['Sophie', 'Martin', '12 rue des Lilas'], [$account['firstName'], $account['lastName'], $this->profile()->address()?->streetLine1]);
    }

    public function testAMergePatchBodyIsAccepted(): void
    {
        $account = self::json($this->patch(['lastName' => 'Morel'], 'application/merge-patch+json'), 200);

        self::assertSame('Morel', $account['lastName']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideAnInvalidFieldIsRefusedCases(): iterable
    {
        yield 'empty first name' => [['firstName' => ''], 'firstName'];
        yield 'blank last name' => [['lastName' => '   '], 'lastName'];
        yield '61-character first name' => [['firstName' => str_repeat('a', 61)], 'firstName'];
        yield 'address without city' => [['address' => ['streetLine1' => '3 quai Rambaud', 'city' => ' ', 'countryCode' => 'FR']], 'address.city'];
        yield 'unknown country' => [['address' => ['streetLine1' => '3 quai Rambaud', 'city' => 'Lyon', 'countryCode' => 'XX']], 'address.countryCode'];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('provideAnInvalidFieldIsRefusedCases')]
    public function testAnInvalidFieldIsRefused(array $body, string $path): void
    {
        $response = $this->patch($body);

        self::assertSame('validation_failed', self::problemType($response, 422));
        self::assertSame($path, self::firstViolationPath(self::json($response, 422)));
        self::assertSame(['Sophie', 'Martin'], [$this->profile()->firstName(), $this->profile()->lastName()]);
    }

    public function testSixtyCharactersAreAccepted(): void
    {
        self::assertSame(str_repeat('a', 60), self::json($this->patch(['firstName' => str_repeat('a', 60)]), 200)['firstName']);
    }

    public function testAnAccountWithoutProfileAnswersNotFound(): void
    {
        $this->connection()->executeStatement('DELETE FROM client_portal__owner_profiles WHERE user_id = ?', [Uuid::fromString($this->userId)->toBinary()]);

        self::assertSame('not_found', self::problemType($this->patch(['firstName' => 'Paul']), 404));
    }

    public function testAnAnonymousCallIsRefused(): void
    {
        $this->browser->request('PATCH', self::BASE . '/me', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $this->clientIp], content: '{}');

        self::assertSame(401, $this->browser->getResponse()->getStatusCode());
    }

    protected function phone(): string
    {
        return '+33615000341';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function patch(array $body, string $contentType = 'application/json'): Response
    {
        $this->browser->request('PATCH', self::BASE . '/me', server: [
            'CONTENT_TYPE'       => $contentType,
            'HTTP_ACCEPT'        => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR'        => $this->clientIp,
        ], content: json_encode((object) $body, \JSON_THROW_ON_ERROR));

        return $this->browser->getResponse();
    }

    private function profile(): OwnerProfile
    {
        $profile = $this->service(OwnerProfileRepositoryInterface::class)->findByUserId($this->userId);
        self::assertNotNull($profile);

        return $profile;
    }
}
