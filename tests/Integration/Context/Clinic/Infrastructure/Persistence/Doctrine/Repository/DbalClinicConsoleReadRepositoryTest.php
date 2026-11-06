<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\ClinicConsoleReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Console\ConsoleClinicRow;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Context\Clinic\Domain\Account\ClinicAccount;
use App\Context\Clinic\Domain\Account\ClinicAccountRepositoryInterface;
use App\Context\Clinic\Domain\Account\ClinicPlan;
use App\Context\Clinic\Domain\Account\ClinicSuspension;
use App\Context\Clinic\Domain\Account\SuspensionReason;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DbalClinicConsoleReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string ALPHA  = '01960000-0000-7000-8000-00000000e2a1';
    private const string BETA   = '01960000-0000-7000-8000-00000000e2a2';
    private const string CLOSED = '01960000-0000-7000-8000-00000000e2a3';

    private const string CLAIRE = '01960000-0000-7000-8000-00000000e2c1';
    private const string INES   = '01960000-0000-7000-8000-00000000e2c2';
    private const string GONE   = '01960000-0000-7000-8000-00000000e2c3';
    private const string HUGO   = '01960000-0000-7000-8000-00000000e2c4';

    private const string M_CLAIRE      = '01960000-0000-7000-8000-00000000e2d1';
    private const string M_INES        = '01960000-0000-7000-8000-00000000e2d2';
    private const string M_GONE        = '01960000-0000-7000-8000-00000000e2d3';
    private const string M_HUGO        = '01960000-0000-7000-8000-00000000e2d4';
    private const string M_CLAIRE_BETA = '01960000-0000-7000-8000-00000000e2d5';

    protected function setUp(): void
    {
        $at = new \DateTimeImmutable('2026-09-01 08:00:00', new \DateTimeZone('UTC'));

        ClinicEntityFactory::new()->withId(self::ALPHA)->withName('Alpha Vet')->withSlug('alpha-vet')
            ->withLetterhead(new LetterheadEmbeddable(legalName: 'SELARL Alpha', siret: '12345678900012', postalCode: '69003', city: 'Lyon'))
            ->create(['createdAt' => $at, 'updatedAt' => $at])
        ;
        ClinicEntityFactory::new()->withId(self::BETA)->withName('Beta Vet')->withSlug('beta-vet')->suspended()
            ->withLetterhead(new LetterheadEmbeddable(legalName: ' ', city: '  '))
            ->create(['createdAt' => $at->modify('+1 day'), 'updatedAt' => $at])
        ;
        ClinicEntityFactory::new()->withId(self::CLOSED)->withName('Alpha Fermée')->withSlug('alpha-fermee')->closed()->create();

        $account = ClinicAccount::open(ClinicId::fromString(self::ALPHA), ClinicPlan::PILOT, new \DateTimeImmutable('2027-01-31'), null, $at);
        $account->markOnboarded(new \DateTimeImmutable('2026-10-01 08:00:00', new \DateTimeZone('UTC')));
        $account->recordSuspension(ClinicSuspension::of(SuspensionReason::OTHER, '', new \DateTimeImmutable('2026-10-15 08:00:00', new \DateTimeZone('UTC')), null, 'Kiveto'));
        $accounts = self::getContainer()->get(ClinicAccountRepositoryInterface::class);
        \assert($accounts instanceof ClinicAccountRepositoryInterface);
        $accounts->save($account);

        $this->member(self::M_CLAIRE, self::ALPHA, self::CLAIRE, ClinicMemberRole::VETERINARY, $at, ['firstName' => 'Claire', 'lastName' => 'Martin', 'displayName' => 'Dr Martin', 'professionalTitle' => ProfessionalTitle::DR, 'phoneNumber' => '+33612345678', 'registrationNumber' => 'FR-24871']);
        $this->member(self::M_INES, self::ALPHA, self::INES, ClinicMemberRole::RECEPTIONIST, $at, ['firstName' => 'Inès', 'lastName' => 'Benali', 'displayName' => 'Inès'], administrator: true);
        $this->member(self::M_GONE, self::ALPHA, self::GONE, ClinicMemberRole::VETERINARY, $at, ['firstName' => 'Anne', 'lastName' => 'Petit', 'displayName' => 'Anne'], disabled: true);
        $this->member(self::M_HUGO, self::BETA, self::HUGO, ClinicMemberRole::MANAGER, $at, null);
        $this->member(self::M_CLAIRE_BETA, self::BETA, self::CLAIRE, ClinicMemberRole::VETERINARY, $at, ['firstName' => 'Claire', 'lastName' => 'Martin', 'displayName' => 'Claire']);
    }

    public function testTheClinicsAreListedByNameWithTheirAccountAndHeadCounts(): void
    {
        $rows = array_values(array_filter($this->console()->clinics(), static fn (ConsoleClinicRow $row): bool => \in_array($row->id, [self::ALPHA, self::BETA, self::CLOSED], true)));

        self::assertSame([self::ALPHA, self::BETA], array_map(static fn (ConsoleClinicRow $row): string => $row->id, $rows), 'a closed clinic is left out');
        self::assertSame(
            ['Alpha Vet', 'alpha-vet', 'Lyon', '69003', '12345678900012', 'SELARL Alpha', 'active', 'pilot', '2027-01-31 00:00:00', '2026-10-01 08:00:00', '2026-10-15 08:00:00', '2026-09-01 08:00:00', 1, 2],
            self::fields($rows[0]),
        );
        self::assertSame(
            ['Beta Vet', 'beta-vet', null, null, null, null, 'suspended', 'pilot', null, null, null, '2026-09-02 08:00:00', 1, 2],
            self::fields($rows[1]),
            'a clinic without account row reads the defaults; blank letterhead fields are none',
        );
    }

    public function testOneClinicIsReadByItsId(): void
    {
        self::assertSame('Alpha Vet', $this->console()->clinic(self::ALPHA)?->name);
        self::assertNull($this->console()->clinic(self::CLOSED), 'closed');
        self::assertNull($this->console()->clinic('01960000-0000-7000-8000-00000000e2ff'));
    }

    public function testTheClinicsAreSearchedByNameSlugSiretOrLegalName(): void
    {
        $console = $this->console();

        self::assertSame([self::ALPHA], self::ids($console->searchClinics(' alpha ', 50)), 'by name, closed ones excluded');
        self::assertSame([self::BETA], self::ids($console->searchClinics('beta-v', 50)), 'by tenant id');
        self::assertSame([self::ALPHA], self::ids($console->searchClinics('123 456 789 00012', 50)), 'by SIRET typed with spaces');
        self::assertSame([self::ALPHA], self::ids($console->searchClinics('selarl', 50)), 'by legal name');
        self::assertSame([], self::ids($console->searchClinics('%', 50)), 'a wildcard is a literal');
        self::assertSame([self::ALPHA], self::ids($console->searchClinics('vet', 1)), 'limited');
        self::assertSame([self::ALPHA], self::ids($console->searchClinics('vet', 0)), 'at least one');
    }

    public function testTheMembersOfAClinicComeWithTheirProfile(): void
    {
        $rows = $this->console()->members(clinicId: self::ALPHA);

        self::assertSame([self::M_INES, self::M_CLAIRE, self::M_GONE], array_map(static fn (ConsoleMemberRow $row): string => $row->membershipId, $rows), 'the administrator first, then by name');
        self::assertSame(
            [self::ALPHA, 'Alpha Vet', self::CLAIRE, 'VETERINARY', false, 'ACTIVE', '2026-09-01 08:00:00', 'Claire', 'Martin', 'Dr Martin', 'DR', '+33612345678', 'FR-24871'],
            self::memberFields($rows[1]),
        );
        self::assertSame([true, 'RECEPTIONIST', null, null], [$rows[0]->isAdministrator, $rows[0]->role, $rows[0]->professionalTitle, $rows[0]->registrationNumber]);
        self::assertSame('DISABLED', $rows[2]->status);
    }

    public function testTheMembersAreFilteredByAccount(): void
    {
        $console = $this->console();

        self::assertSame([self::M_CLAIRE, self::M_CLAIRE_BETA], array_map(static fn (ConsoleMemberRow $row): string => $row->membershipId, $console->members(userId: self::CLAIRE)));
        self::assertSame([self::M_CLAIRE_BETA], array_map(static fn (ConsoleMemberRow $row): string => $row->membershipId, $console->members(self::BETA, self::CLAIRE)));

        $all  = array_values(array_filter($console->members(), static fn (ConsoleMemberRow $row): bool => \in_array($row->clinicId, [self::ALPHA, self::BETA], true)));
        $hugo = array_values(array_filter($all, static fn (ConsoleMemberRow $row): bool => self::M_HUGO === $row->membershipId))[0] ?? null;
        self::assertCount(5, $all);
        self::assertNotNull($hugo);
        self::assertSame(['', '', '', null, null, null, 'MANAGER'], [$hugo->firstName, $hugo->lastName, $hugo->displayName, $hugo->professionalTitle, $hugo->phone, $hugo->registrationNumber, $hugo->role], 'a membership without profile');
    }

    /**
     * @param list<ConsoleClinicRow> $rows
     *
     * @return list<string>
     */
    private static function ids(array $rows): array
    {
        return array_map(static fn (ConsoleClinicRow $row): string => $row->id, $rows);
    }

    /**
     * @return list<mixed>
     */
    private static function fields(ConsoleClinicRow $row): array
    {
        return [
            $row->name, $row->slug, $row->city, $row->postalCode, $row->siret, $row->legalName, $row->status, $row->plan,
            $row->pilotEndsOn?->format('Y-m-d H:i:s'), $row->onboardedAt?->format('Y-m-d H:i:s'), $row->suspendedAt?->format('Y-m-d H:i:s'),
            $row->createdAt->format('Y-m-d H:i:s'), $row->veterinarians, $row->members,
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function memberFields(ConsoleMemberRow $row): array
    {
        return [
            $row->clinicId, $row->clinicName, $row->userId, $row->role, $row->isAdministrator, $row->status, $row->since->format('Y-m-d H:i:s'),
            $row->firstName, $row->lastName, $row->displayName, $row->professionalTitle, $row->phone, $row->registrationNumber,
        ];
    }

    /**
     * @param array<string, mixed>|null $profile
     */
    private function member(string $id, string $clinicId, string $userId, ClinicMemberRole $role, \DateTimeImmutable $at, ?array $profile, bool $administrator = false, bool $disabled = false): void
    {
        $factory = ClinicMembershipEntityFactory::new()->withId($id)->withClinicId($clinicId)->withUserId($userId)->withRole($role)->with(['createdAt' => $at]);
        $factory = $administrator ? $factory->asClinicAdministrator() : $factory;
        $factory = $disabled ? $factory->disabled() : $factory;
        $factory->create();

        if (null !== $profile) {
            StaffProfileEntityFactory::new()->forMembership(Uuid::fromString($id))->withoutCredentials()->create($profile);
        }
    }

    private function console(): ClinicConsoleReadRepositoryInterface
    {
        $console = self::getContainer()->get(ClinicConsoleReadRepositoryInterface::class);
        \assert($console instanceof ClinicConsoleReadRepositoryInterface);

        return $console;
    }
}
