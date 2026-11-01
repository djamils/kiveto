<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Application\Query\GetClientConsents;

use App\Context\Client\Application\Port\ClientConsentReaderInterface;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentsView;
use App\Context\Client\Application\Query\GetClientConsents\ClientConsentView;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsents;
use App\Context\Client\Application\Query\GetClientConsents\GetClientConsentsHandler;
use App\Context\Client\Domain\ValueObject\ClinicId;
use PHPUnit\Framework\TestCase;

final class GetClientConsentsHandlerTest extends TestCase
{
    private const string CLINIC_ID = '0192a000-0000-7000-8000-0000000c08c1';
    private const string CLIENT_ID = '0192a000-0000-7000-8000-0000000c0801';

    public function testAsksTheReaderOnceForTheDistinctClients(): void
    {
        $marketing = new ClientConsentView('marketing', ['sms'], new \DateTimeImmutable('2025-01-01'));
        $view      = new ClientConsentsView(self::CLIENT_ID, 'sms', false, ['marketing' => $marketing]);

        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::once())
            ->method('consentsOf')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()), [self::CLIENT_ID])
            ->willReturn([self::CLIENT_ID => $view])
        ;

        $result = (new GetClientConsentsHandler($reader))(new GetClientConsents(self::CLINIC_ID, [self::CLIENT_ID, self::CLIENT_ID]));

        self::assertSame([self::CLIENT_ID => $view], $result);
        self::assertSame($marketing, $view->consentFor('marketing'));
        self::assertNull($view->consentFor('billing'));
    }

    public function testNoClientAsksNothing(): void
    {
        $reader = $this->createMock(ClientConsentReaderInterface::class);
        $reader->expects(self::never())->method('consentsOf');

        self::assertSame([], (new GetClientConsentsHandler($reader))(new GetClientConsents(self::CLINIC_ID, [])));
    }
}
