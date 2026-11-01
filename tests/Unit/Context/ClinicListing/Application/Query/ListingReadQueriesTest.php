<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicListing\Application\Query;

use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListing;
use App\Context\ClinicListing\Application\Query\GetClinicListing\GetClinicListingHandler;
use App\Context\ClinicListing\Application\Query\GetClinicListing\OpeningHoursView;
use App\Context\ClinicListing\Application\Query\GetListingSettings\GetListingSettings;
use App\Context\ClinicListing\Application\Query\GetListingSettings\GetListingSettingsHandler;
use App\Context\ClinicListing\Domain\ListingSettings;
use App\Tests\Unit\Context\ClinicListing\Support\ClinicListingFakes;
use App\Tests\Unit\Context\ClinicListing\Support\FakeListingReadModel;
use PHPUnit\Framework\TestCase;

final class ListingReadQueriesTest extends TestCase
{
    private const string LISTING = '01960000-0000-7000-8000-0000000a5001';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000a5c01';

    public function testAListingIsReadInFullWithItsClinicIdForTheServerOnly(): void
    {
        $listings       = new FakeListingReadModel();
        $listings->rows = [FakeListingReadModel::row(self::LISTING, self::CLINIC, 'Clinique du Parc')];
        $scheduling     = new ClinicListingFakes();
        $handler        = new GetClinicListingHandler($listings, $scheduling);

        $detail = $handler(new GetClinicListing(self::LISTING));
        self::assertNotNull($detail);
        self::assertSame([self::LISTING, self::CLINIC, 'Clinique du Parc', '+33142551890', 'Europe/Paris', true, false, ['NAC']], [
            $detail->id, $detail->clinicId, $detail->name, $detail->phone, $detail->timezone, $detail->acceptsNewClients, $detail->onlineBooking, $detail->tags,
        ]);
        self::assertSame('Paris', $detail->address?->city);
        self::assertCount(1, $detail->openingHours);
        self::assertInstanceOf(OpeningHoursView::class, $detail->openingHours[0]);
        self::assertSame([2, '08:30', '12:00'], [$detail->openingHours[0]->day, $detail->openingHours[0]->opensAt, $detail->openingHours[0]->closesAt]);

        $scheduling->openToNewClients = [self::CLINIC];
        self::assertTrue($handler(new GetClinicListing(self::LISTING))?->onlineBooking);

        self::assertNull($handler(new GetClinicListing('01960000-0000-7000-8000-0000000a5fff')));
    }

    public function testTheSettingsAreReadWithTheirDefaults(): void
    {
        $fakes = new ClinicListingFakes();
        $fakes->save(ListingSettings::reconstitute(self::CLINIC, true, false, ['NAC']));
        $handler = new GetListingSettingsHandler($fakes);

        self::assertSame([true, false, ['NAC']], array_values((array) $handler(new GetListingSettings(self::CLINIC))));
        self::assertSame([false, true, []], array_values((array) $handler(new GetListingSettings('01960000-0000-7000-8000-0000000a5c02'))));
    }
}
