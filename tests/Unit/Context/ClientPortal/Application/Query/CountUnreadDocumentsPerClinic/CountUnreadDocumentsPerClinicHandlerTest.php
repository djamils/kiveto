<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic;

use App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic\CountUnreadDocumentsPerClinic;
use App\Context\ClientPortal\Application\Query\CountUnreadDocumentsPerClinic\CountUnreadDocumentsPerClinicHandler;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\FakePublishedDocumentReadModel;
use PHPUnit\Framework\TestCase;

final class CountUnreadDocumentsPerClinicHandlerTest extends TestCase
{
    public function testTheUnreadCountsOfTheAccount(): void
    {
        $model         = new FakePublishedDocumentReadModel();
        $model->unread = [W::CLINIC => 3];

        $counts = new CountUnreadDocumentsPerClinicHandler($model)(new CountUnreadDocumentsPerClinic(W::USER));

        self::assertSame([W::CLINIC => 3], $counts);
        self::assertSame(['unreadPerClinic:' . W::USER], $model->calls);
    }
}
