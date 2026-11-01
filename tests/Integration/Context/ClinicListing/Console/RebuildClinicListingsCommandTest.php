<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClinicListing\Console;

use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingPage;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\ClinicListingSummary;
use App\Context\ClinicListing\Application\Query\SearchClinicListings\SearchClinicListings;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Context\ClinicListing\Domain\Repository\ClinicListingRepositoryInterface;
use App\Context\ClinicListing\Domain\Repository\ListingSettingsRepositoryInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Tests\Support\MessengerDrain;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;

/**
 * AC 271: on a database with clinics and no projection, the rebuild projects
 * every clinic and the listed ones appear in the search.
 */
final class RebuildClinicListingsCommandTest extends KernelTestCase
{
    use Factories;

    private const string LISTED   = '01960000-0000-7000-8000-0000000b4c01';
    private const string UNLISTED = '01960000-0000-7000-8000-0000000b4c02';

    public function testEveryClinicIsProjectedAndTheListedOnesAreFound(): void
    {
        ClinicEntityFactory::new()->withId(self::LISTED)->withName('Clinique Rebâtie')->withLetterhead(new LetterheadEmbeddable(streetLine1: '3 rue Neuve', postalCode: '33000', city: 'Bordeaux', countryCode: 'FR'))->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::UNLISTED)->withName('Clinique Rebâtie Discrète')->create(['timeZone' => 'Europe/Paris']);

        $settings = self::getContainer()->get(ListingSettingsRepositoryInterface::class);
        \assert($settings instanceof ListingSettingsRepositoryInterface);
        $listed = ListingSettings::defaults(self::LISTED);
        $listed->update(true, true, []);
        $settings->save($listed);

        $kernel = self::bootKernel();
        $tester = new CommandTester(new Application($kernel)->find('clinic-listing:rebuild'));

        self::assertSame(0, $tester->execute([]));
        self::assertMatchesRegularExpression('/\d+ clinic listing\(s\) rebuilt\./', $tester->getDisplay());

        $listings = self::getContainer()->get(ClinicListingRepositoryInterface::class);
        \assert($listings instanceof ClinicListingRepositoryInterface);
        self::assertNotNull($listings->findByClinicId(self::LISTED));
        self::assertNotNull($listings->findByClinicId(self::UNLISTED));

        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $page = $bus->ask(new SearchClinicListings(q: 'rebâtie'));
        \assert($page instanceof ClinicListingPage);
        self::assertSame(['Clinique Rebâtie'], array_map(static fn (ClinicListingSummary $item): string => $item->name, $page->items));

        // Geocoded by the worker: then found around Bordeaux
        MessengerDrain::drain(self::getContainer(), ['async_external']);
        $around = $bus->ask(new SearchClinicListings(q: 'rebâtie', lat: 44.8378, lng: -0.5792));
        \assert($around instanceof ClinicListingPage);
        self::assertSame(['Clinique Rebâtie'], array_map(static fn (ClinicListingSummary $item): string => $item->name, $around->items));
    }
}
