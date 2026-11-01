<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\Shared;

use App\Context\ClinicListing\Application\Port\ListingRow;
use App\Context\ClinicListing\Application\Port\OnlineBookingStatusInterface;

/**
 * `onlineBooking` of the directory, computed at read time: the clinic accepts
 * new clients (its listing settings) and Scheduling takes their requests.
 * One call to Scheduling for the whole page, none when no clinic of it
 * accepts new clients.
 */
final class OnlineBookingResolver
{
    /**
     * @param list<ListingRow> $rows
     *
     * @return list<string> the clinic ids open to new-client bookings
     */
    public static function openAmong(OnlineBookingStatusInterface $status, array $rows): array
    {
        $accepting = [];

        foreach ($rows as $row) {
            if ($row->acceptsNewClients) {
                $accepting[] = $row->clinicId;
            }
        }

        return [] === $accepting ? [] : $status->openToNewClients($accepting);
    }
}
