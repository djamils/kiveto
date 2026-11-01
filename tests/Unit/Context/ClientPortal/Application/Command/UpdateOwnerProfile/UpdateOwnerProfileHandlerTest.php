<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\UpdateOwnerProfile;

use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\ProfileAddress;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\UpdateOwnerProfile;
use App\Context\ClientPortal\Application\Command\UpdateOwnerProfile\UpdateOwnerProfileHandler;
use App\Context\ClientPortal\Domain\Exception\OwnerProfileNotFoundException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Shared\Domain\ValueObject\PostalAddress;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryOwnerProfiles;
use PHPUnit\Framework\TestCase;

final class UpdateOwnerProfileHandlerTest extends TestCase
{
    private const string USER = '01960000-0000-7000-8000-0000000a3401';

    private InMemoryOwnerProfiles $profiles;

    protected function setUp(): void
    {
        $this->profiles = new InMemoryOwnerProfiles();
        $this->profiles->save(OwnerProfile::createFromClient(
            self::USER,
            'Sophie',
            'Martin',
            PostalAddress::create('12 rue des Lilas', 'Lyon', 'FR', 'Bât. B', '69003'),
            'v1',
            new \DateTimeImmutable('2026-10-29'),
        ));
        $this->profiles->saves = 0;
    }

    public function testOnlyTheGivenPartsChange(): void
    {
        $this->handle(new UpdateOwnerProfile(self::USER, firstName: ' Sophia '));

        $profile = $this->profile();
        self::assertSame(['Sophia', 'Martin', '12 rue des Lilas', 'Bât. B', 1], [$profile->firstName(), $profile->lastName(), $profile->address()?->streetLine1, $profile->address()?->streetLine2, $this->profiles->saves]);

        $this->handle(new UpdateOwnerProfile(self::USER, lastName: 'Durand'));
        self::assertSame(['Sophia', 'Durand'], [$this->profile()->firstName(), $this->profile()->lastName()]);
    }

    public function testAnEditedAddressIsStoredAsTypedWithoutSecondLine(): void
    {
        $this->handle(new UpdateOwnerProfile(self::USER, address: new ProfileAddress(' 3 quai Rambaud ', null, 'Lyon', 'fr')));

        $address = $this->profile()->address();
        self::assertSame(['3 quai Rambaud', null, null, 'Lyon', 'FR', 'Sophie'], [$address?->streetLine1, $address?->streetLine2, $address?->postalCode, $address?->city, $address?->countryCode, $this->profile()->firstName()]);
    }

    public function testAnAccountWithoutProfileIsRefused(): void
    {
        $this->expectException(OwnerProfileNotFoundException::class);
        $this->expectExceptionMessage('Owner account "01960000-0000-7000-8000-0000000a3409" has no Portal profile.');

        $this->handle(new UpdateOwnerProfile('01960000-0000-7000-8000-0000000a3409', firstName: 'Paul'));
    }

    private function handle(UpdateOwnerProfile $command): void
    {
        new UpdateOwnerProfileHandler($this->profiles)($command);
    }

    private function profile(): OwnerProfile
    {
        $profile = $this->profiles->findByUserId(self::USER);
        self::assertNotNull($profile);

        return $profile;
    }
}
