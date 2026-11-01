<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClinicListing\Domain\ClinicListing;
use App\Context\ClinicListing\Domain\ClinicListingId;
use App\Context\ClinicListing\Domain\ListingAddress;
use App\Context\ClinicListing\Domain\ListingProfile;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * AC 260: the directory's GET routes are public — a stale Bearer is ignored,
 * not answered 401 — while its only write, the new-client booking, needs an
 * access token.
 */
final class ListingBookingSecurityTest extends PortalApiTestCase
{
    private const string LISTING = '01930000-0000-7000-8000-0000000e8001';
    private const string CLINIC  = '01930000-0000-7000-8000-0000000e8c01';

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.180';
        parent::setUp();

        $profile = new ListingProfile('Clinique des Lilas', ListingAddress::fromParts('18 rue Lepic', '75018', 'Paris'), null, 'Europe/Paris', [], true);
        $this->service(ClinicListingRepositoryInterface::class)->save(ClinicListing::reconstitute(
            ClinicListingId::fromString(self::LISTING),
            self::CLINIC,
            $profile,
            null,
            $profile->address()?->hash(),
            new \DateTimeImmutable('2026-11-02 09:00:00'),
        ));
        $settings = ListingSettings::defaults(self::CLINIC);
        $settings->update(true, true, []);
        $this->service(ListingSettingsRepositoryInterface::class)->save($settings);
    }

    public function testTheDirectoryIsReadWithoutToken(): void
    {
        self::assertSame(['Clinique des Lilas'], array_column(self::rowsOf(self::json($this->get('/clinic-listings'), 200)['items'] ?? null), 'name'));
        self::assertSame(self::LISTING, self::json($this->get('/clinic-listings/' . self::LISTING), 200)['id']);
        self::assertSame('not_found', self::problemType($this->get('/clinic-listings/01930000-0000-7000-8000-0000000e8999'), 404));
        self::assertSame('booking_not_open', self::problemType($this->get('/clinic-listings/' . self::LISTING . '/booking/motifs'), 422));
    }

    public function testAStaleOrForgedBearerIsIgnoredOnAPublicRead(): void
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer not-a-jwt'];

        self::json($this->get('/clinic-listings', $server), 200);
        self::json($this->get('/clinic-listings/' . self::LISTING, $server), 200);
    }

    /**
     * The access map, read before routing by the authenticator: the booking
     * path is authenticated whatever the method, the rest of the directory is
     * public for GET only.
     */
    public function testOnlyTheDirectoryReadsArePublic(): void
    {
        $map = $this->browser->getContainer()->get('security.access_map');
        \assert($map instanceof AccessMapInterface);
        $roles = static fn (string $method, string $path): mixed => $map->getPatterns(Request::create(self::BASE . $path, $method))[0];

        self::assertSame([AuthenticatedVoter::PUBLIC_ACCESS], $roles('GET', '/clinic-listings'));
        self::assertSame([AuthenticatedVoter::PUBLIC_ACCESS], $roles('GET', '/clinic-listings/' . self::LISTING . '/booking/availability'));
        self::assertSame([AuthenticatedVoter::IS_AUTHENTICATED_FULLY], $roles('POST', '/clinic-listings/' . self::LISTING . '/booking/appointments'));
        self::assertSame([AuthenticatedVoter::IS_AUTHENTICATED_FULLY], $roles('GET', '/clinic-listings/' . self::LISTING . '/booking/appointments'));
        self::assertSame([AuthenticatedVoter::IS_AUTHENTICATED_FULLY], $roles('POST', '/clinic-listings/' . self::LISTING . '/notify-me'));
    }

    /**
     * Without token → 401 `unauthorized`; with one the request reaches the
     * booking (here refused on its merits: the clinic takes no new client
     * online). The 201 itself is ListingBookingTest's.
     */
    public function testTheNewClientBookingAnswers401WithoutTokenAndIsHandledWithOne(): void
    {
        $body = ['newPet' => ['name' => 'Pixel', 'species' => 'cat'], 'motifId' => '01930000-0000-7000-8000-0000000e8b01', 'practitionerId' => '01930000-0000-7000-8000-0000000e8d01', 'startAt' => '2026-11-04T10:00:00+01:00'];
        $path = '/clinic-listings/' . self::LISTING . '/booking/appointments';

        self::assertSame('unauthorized', self::problemType($this->post($path, $body, ['HTTP_IDEMPOTENCY_KEY' => 'k-260']), 401));

        PortalUserFactory::new()->withPhone('+33615008001')->create();
        $token = self::string($this->signIn('+33615008001')['accessToken']);

        self::assertSame('booking_not_open', self::problemType($this->post($path, $body, ['HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_IDEMPOTENCY_KEY' => 'k-260']), 422));
    }

    /**
     * @return list<mixed>
     */
    private static function rowsOf(mixed $rows): array
    {
        self::assertIsArray($rows);

        return array_values(array_filter($rows, \is_array(...)));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        \assert($service instanceof $id);

        return $service;
    }
}
