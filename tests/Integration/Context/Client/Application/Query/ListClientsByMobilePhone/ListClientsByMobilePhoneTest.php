<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Application\Query\ListClientsByMobilePhone;

use App\Context\Client\Application\Query\ListClientsByMobilePhone\ListClientsByMobilePhone;
use App\Context\Client\Application\Query\ListClientsByMobilePhone\MobileClientMatch;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Fixtures\Context\Animal\Factory\AnimalEntityFactory;
use App\Fixtures\Context\Animal\Factory\OwnershipEntityFactory;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The cross-clinic mobile lookup of the link suggestions (S7b, AC 75), on
 * the real read model and adapters: label `mobile` only, archived clients
 * and suspended or closed clinics left out, family files of a clinic kept
 * side by side (the Portal groups them).
 */
final class ListClientsByMobilePhoneTest extends KernelTestCase
{
    use Factories;

    private const string PHONE     = '+33612340450';
    private const string CLINIC_A  = '01970000-0000-7000-8000-00000045c001';
    private const string CLINIC_B  = '01970000-0000-7000-8000-00000045c002';
    private const string SUSPENDED = '01970000-0000-7000-8000-00000045c003';
    private const string CLOSED    = '01970000-0000-7000-8000-00000045c004';

    public function testOnlyMobileNumbersOfActiveFilesInActiveClinicsMatch(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC_A)->withName('Clinique A')->create();
        ClinicEntityFactory::new()->withId(self::CLINIC_B)->withName('Clinique B')->create();
        ClinicEntityFactory::new()->withId(self::SUSPENDED)->withName('Clinique suspendue')->suspended()->create();
        ClinicEntityFactory::new()->withId(self::CLOSED)->withName('Clinique fermée')->closed()->create();

        $sophie = $this->client(self::CLINIC_A, 'Sophie', 'Martin', '2026-01-01');
        $paul   = $this->client(self::CLINIC_A, 'Paul', 'Martin', '2026-02-01');
        $other  = $this->client(self::CLINIC_B, 'Sophie', 'Martin', '2026-03-01');
        $this->client(self::CLINIC_A, 'Fixe', 'Martin', '2026-01-02', ContactLabel::HOME);
        $this->client(self::CLINIC_A, 'Archivée', 'Martin', '2026-01-03', archived: true);
        $this->client(self::SUSPENDED, 'Sophie', 'Martin', '2026-01-04');
        $this->client(self::CLOSED, 'Sophie', 'Martin', '2026-01-05');
        $this->client(self::CLINIC_A, 'Autre', 'Numéro', '2026-01-06', phone: '+33612340451');
        $this->pet($sophie, 'Rex');
        $this->pet($sophie, 'Moka');

        $matches = $this->ask(self::PHONE);

        self::assertEqualsCanonicalizing([
            [self::CLINIC_A, $sophie, 'Sophie', 'Martin', ['Moka', 'Rex']],
            [self::CLINIC_A, $paul, 'Paul', 'Martin', []],
            [self::CLINIC_B, $other, 'Sophie', 'Martin', []],
        ], array_map(static fn (MobileClientMatch $m): array => [$m->clinicId, $m->clientId, $m->firstName, $m->lastName, $m->animalNames], $matches));
        // Family files of a clinic come together, oldest first.
        $inA = array_values(array_filter($matches, static fn (MobileClientMatch $m): bool => self::CLINIC_A === $m->clinicId));
        self::assertSame([$sophie, $paul], array_map(static fn (MobileClientMatch $m): string => $m->clientId, $inA));

        self::assertSame([], $this->ask('+33699999999'));
        self::assertSame([], $this->ask(' '));
    }

    private function client(string $clinicId, string $firstName, string $lastName, string $since, ContactLabel $label = ContactLabel::MOBILE, bool $archived = false, string $phone = self::PHONE): string
    {
        $id      = Uuid::v7()->toRfc4122();
        $factory = ClientEntityFactory::new()->withId($id)->withClinicId($clinicId)->withName($firstName, $lastName)->since($since);
        ($archived ? $factory->archived() : $factory->active())->create();
        ContactMethodEntityFactory::new()->forClient($id)->phone($phone)->create(['label' => $label, 'isPrimary' => true]);

        return $id;
    }

    private function pet(string $clientId, string $name): void
    {
        $id = Uuid::v7();
        AnimalEntityFactory::new()->withClinicId(self::CLINIC_A)->withName($name)->dog()->active()->alive()->create(['id' => $id]);
        OwnershipEntityFactory::new()->withClientId($clientId)->active()->primary()->create(['animal' => AnimalEntityFactory::find(['id' => $id])]);
    }

    /**
     * @return list<MobileClientMatch>
     */
    private function ask(string $phone): array
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $matches = $bus->ask(new ListClientsByMobilePhone($phone));
        \assert(\is_array($matches));

        return array_values(array_filter($matches, static fn (mixed $m): bool => $m instanceof MobileClientMatch));
    }
}
