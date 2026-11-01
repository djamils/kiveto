<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Client\Application\Command\SetClientConsent\SetClientConsent;
use App\Context\Client\Application\Command\UpdateClientCommunicationPreferences\UpdateClientCommunicationPreferences;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 5, Task 33: the owner's consents per clinic, written to every active
 * linked client file (AC 54, 55, 56, 92, 204, 205).
 */
final class ConsentsTest extends PetsApiTestCase
{
    private const string STAFF = '01930000-0000-7000-8000-00000000e301';

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.95';
        parent::setUp();

        ClientEntityFactory::new()->withId(self::CLIENT)->withClinicId(self::CLINIC)->withName('Sophie', 'Martin')->active()
            ->withCommunicationPreferences('sms', ['marketing' => ['sms', 'email'], 'billing' => ['email'], 'appointment_reminders' => ['sms']], '2026-06-01 08:00:00+00:00')
            ->create()
        ;
        $this->service(OwnerProfileRepositoryInterface::class)->save(OwnerProfile::createFromClient($this->userId, 'Sophie', 'Martin', null, 'v1', new \DateTimeImmutable('2026-10-01')));
    }

    public function testAllFiveKindsAreReturnedWithTheirEffect(): void
    {
        $consents = self::json($this->authGet('/clinics/' . self::CLINIC . '/consents'), 200);

        self::assertSame([
            'preferredChannel' => 'sms',
            'consents'         => [
                ['kind' => 'appointment_reminders', 'channels' => ['sms'], 'grantedAt' => '2026-06-01T08:00:00+00:00', 'effect' => 'send_control'],
                ['kind' => 'vaccination_reminders', 'channels' => [], 'grantedAt' => null, 'effect' => 'send_control'],
                ['kind' => 'clinical_documents', 'channels' => [], 'grantedAt' => null, 'effect' => 'channel_preference'],
                ['kind' => 'billing', 'channels' => ['email'], 'grantedAt' => '2026-06-01T08:00:00+00:00', 'effect' => 'channel_preference'],
                ['kind' => 'marketing', 'channels' => ['sms', 'email'], 'grantedAt' => '2026-06-01T08:00:00+00:00', 'effect' => 'send_control'],
            ],
        ], $consents);
    }

    public function testWithdrawingAChannelWritesOneHistoryRowAndLeavesTheOtherKinds(): void
    {
        $consents = self::json($this->put('marketing', ['sms']), 200);

        self::assertSame(['sms'], self::consent($consents, 'marketing')['channels']);
        self::assertSame(['email'], self::consent($consents, 'billing')['channels']);
        self::assertSame(['sms'], self::consent($consents, 'appointment_reminders')['channels']);
        self::assertSame([[self::CLIENT, 'marketing', 'email', 'withdrawn', 'portal', 'portal_user', $this->userId, 'Sophie Martin']], $this->historyRows());
    }

    public function testTheHistoryListsBothSourcesNewestFirstAndHidesTheStaffName(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $this->bus()->dispatch(new UpdateClientCommunicationPreferences(
            self::CLINIC,
            self::CLIENT,
            'sms',
            ['marketing' => ['sms', 'email'], 'billing' => ['email'], 'appointment_reminders' => ['sms', 'email']],
            'clinic',
            'staff',
            self::STAFF,
            'Claire Lambert',
        ));
        $this->travelTo(self::NOW);
        self::json($this->put('marketing', ['sms']), 200);

        $first = self::json($this->authGet('/clinics/' . self::CLINIC . '/consents/history?limit=1'), 200);
        self::assertSame([['kind' => 'marketing', 'channel' => 'email', 'action' => 'withdrawn', 'at' => '2026-11-02T10:00:00+00:00', 'source' => 'portal', 'author' => 'Sophie Martin', 'approximate' => false]], $first['items']);
        self::assertIsString($first['nextCursor']);

        $second = self::json($this->authGet('/clinics/' . self::CLINIC . '/consents/history?limit=1&cursor=' . $first['nextCursor']), 200);
        self::assertSame([['kind' => 'appointment_reminders', 'channel' => 'email', 'action' => 'granted', 'at' => '2026-11-01T09:00:00+00:00', 'source' => 'clinic', 'author' => 'La clinique', 'approximate' => false]], $second['items']);
        self::assertNull($second['nextCursor']);
    }

    public function testAChannelOtherThanSmsOrEmailIsRefused(): void
    {
        $response = $this->put('marketing', ['phone']);

        self::assertSame('validation_failed', self::problemType($response, 422));
        self::assertSame('channels[0]', self::firstViolationPath(self::json($response, 422)));
        self::assertSame([], $this->historyRows());
    }

    public function testAKindSentAnywayCannotLoseItsLastChannel(): void
    {
        $response = $this->put('billing', []);

        self::assertSame('validation_failed', self::problemType($response, 422));
        self::assertSame('channels', self::firstViolationPath(self::json($response, 422)));
        self::assertSame([], $this->historyRows());
    }

    public function testAMissingChannelListIsRefused(): void
    {
        self::assertSame('validation_failed', self::problemType($this->put('marketing', null), 422));
    }

    public function testTwoLinkedFilesAreReadAsOneAndWrittenTogether(): void
    {
        ClientEntityFactory::new()->withId(self::OTHER_CLIENT)->withClinicId(self::CLINIC)->withName('Sophie', 'Martin-Durand')->active()
            ->withCommunicationPreferences(null, ['marketing' => ['email'], 'vaccination_reminders' => ['email']], '2026-07-01 08:00:00+00:00')
            ->create()
        ;
        $this->link(self::OTHER_CLIENT);

        $before = self::json($this->authGet('/clinics/' . self::CLINIC . '/consents'), 200);
        self::assertSame(['sms', 'email'], self::consent($before, 'marketing')['channels']);
        self::assertSame('2026-07-01T08:00:00+00:00', self::consent($before, 'marketing')['grantedAt']);
        self::assertSame(['email'], self::consent($before, 'vaccination_reminders')['channels']);

        $after = self::json($this->put('marketing', []), 200);

        self::assertSame([], self::consent($after, 'marketing')['channels']);
        self::assertCount(2, $this->bus()->recorded(SetClientConsent::class));
        self::assertSame([
            [self::CLIENT, 'marketing', 'email', 'withdrawn', 'portal', 'portal_user', $this->userId, 'Sophie Martin'],
            [self::CLIENT, 'marketing', 'sms', 'withdrawn', 'portal', 'portal_user', $this->userId, 'Sophie Martin'],
            [self::OTHER_CLIENT, 'marketing', 'email', 'withdrawn', 'portal', 'portal_user', $this->userId, 'Sophie Martin'],
        ], $this->historyRows());

        $history = self::json($this->authGet('/clinics/' . self::CLINIC . '/consents/history'), 200);
        self::assertIsArray($history['items']);
        self::assertSame(['email', 'sms'], array_column($history['items'], 'channel'));
    }

    public function testAnArchivedFileIsSkippedWithoutError(): void
    {
        ClientEntityFactory::new()->withId(self::OTHER_CLIENT)->withClinicId(self::CLINIC)->withName('Sophie', 'Martin')->archived()
            ->withCommunicationPreferences(null, ['marketing' => ['email']], '2026-07-01 08:00:00+00:00')
            ->create()
        ;
        $this->link(self::OTHER_CLIENT);

        $consents = self::json($this->put('marketing', []), 200);

        self::assertSame([], self::consent($consents, 'marketing')['channels']);
        self::assertSame([self::CLIENT, self::CLIENT], array_column($this->historyRows(), 0));
        $archived = $this->connection()->fetchOne('SELECT communication_preferences FROM client__clients WHERE id = ?', [Uuid::fromString(self::OTHER_CLIENT)->toBinary()]);
        self::assertStringContainsString('"marketing"', self::string($archived));
    }

    public function testAnUnknownKindIsNotFound(): void
    {
        self::assertSame('not_found', self::problemType($this->put('newsletter', ['sms']), 404));
    }

    public function testAClinicWithoutActiveFileIsNotFound(): void
    {
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/consents'), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::OTHER_CLINIC . '/consents/history'), 404));
        self::assertSame('not_found', self::problemType($this->put('marketing', ['sms'], self::OTHER_CLINIC), 404));

        $this->connection()->executeStatement('UPDATE client__clients SET status = ? WHERE id = ?', ['archived', Uuid::fromString(self::CLIENT)->toBinary()]);

        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/consents'), 404));
        self::assertSame('not_found', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/consents/history'), 404));
        self::assertSame('not_found', self::problemType($this->put('marketing', ['sms']), 404));
    }

    public function testAForgedCursorIsRefused(): void
    {
        self::assertSame('cursor_invalid', self::problemType($this->authGet('/clinics/' . self::CLINIC . '/consents/history?cursor=forged'), 400));
    }

    protected function phone(): string
    {
        return '+33615000351';
    }

    /**
     * @param list<string>|null $channels
     */
    private function put(string $kind, ?array $channels, string $clinicId = self::CLINIC): Response
    {
        $this->browser->request('PUT', self::BASE . '/clinics/' . $clinicId . '/consents/' . $kind, server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_ACCEPT'        => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token,
            'REMOTE_ADDR'        => $this->clientIp,
        ], content: json_encode(null === $channels ? new \stdClass() : ['channels' => $channels], \JSON_THROW_ON_ERROR));

        return $this->browser->getResponse();
    }

    /**
     * @param array<string, mixed> $consents
     *
     * @return array<string, mixed>
     */
    private static function consent(array $consents, string $kind): array
    {
        foreach (self::rows($consents['consents'] ?? null) as $consent) {
            if ($kind === $consent['kind']) {
                return $consent;
            }
        }

        self::fail(\sprintf('No consent "%s".', $kind));
    }

    /**
     * @return list<list<mixed>>
     */
    private function historyRows(): array
    {
        return array_map('array_values', $this->connection()->fetchAllAssociative(
            'SELECT BIN_TO_UUID(client_id), kind, channel, action, source, actor_type, BIN_TO_UUID(actor_id), author_label
             FROM client__consent_history WHERE clinic_id = ? ORDER BY client_id, kind, channel',
            [Uuid::fromString(self::CLINIC)->toBinary()],
        ));
    }
}
