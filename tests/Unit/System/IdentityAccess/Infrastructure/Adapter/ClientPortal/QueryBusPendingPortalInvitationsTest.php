<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Adapter\ClientPortal;

use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\ListPendingInvitationsForPhone;
use App\Context\ClientPortal\Application\Query\ListPendingInvitationsForPhone\PendingInvitationSummary;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Infrastructure\Adapter\ClientPortal\QueryBusPendingPortalInvitations;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusPendingPortalInvitationsTest extends TestCase
{
    public function testClientPortalsPendingInvitationsOfThePhoneAreTranslated(): void
    {
        $bus = new AnsweringQueryBus()->answer(ListPendingInvitationsForPhone::class, static function (ListPendingInvitationsForPhone $query): array {
            self::assertSame('+33612345678', $query->phone);

            return [new PendingInvitationSummary('01960000-0000-7000-8000-0000000004b1', 'Clinique des Lilas', 'Lyon')];
        });

        $invitations = new QueryBusPendingPortalInvitations($bus)->listForPhone(PhoneNumber::fromString('+33612345678'));

        self::assertCount(1, $invitations);
        self::assertSame(['01960000-0000-7000-8000-0000000004b1', 'Clinique des Lilas', 'Lyon'], [$invitations[0]->invitationId, $invitations[0]->clinicName, $invitations[0]->clinicCity]);
    }
}
