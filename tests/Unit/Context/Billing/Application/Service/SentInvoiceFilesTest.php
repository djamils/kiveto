<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Service;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFile;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\EnsureSentInvoiceFileHandler;
use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Service\SentInvoiceFiles;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Billing\Support\FakeInvoicePdfRenderer;
use App\Tests\Unit\Context\Billing\Support\InMemorySentFiles;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;

final class SentInvoiceFilesTest extends TestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000d101';
    private const string INVOICE = '01960000-0000-7000-8000-00000000d102';

    private InMemorySentFiles $store;
    private InMemoryFiles $storage;

    protected function setUp(): void
    {
        $this->store   = new InMemorySentFiles();
        $this->storage = new InMemoryFiles();
    }

    public function testTheFirstSendRendersAndStoresThePdfUnderItsKey(): void
    {
        $renderer = new FakeInvoicePdfRenderer();

        $file = new EnsureSentInvoiceFileHandler($this->files($renderer))(new EnsureSentInvoiceFile(self::CLINIC, self::INVOICE));

        self::assertSame(['billing/sent/invoice/' . self::INVOICE . '.pdf', 'FAC-2026-000012.pdf'], [$file->storageKey, $file->filename]);
        self::assertSame('%PDF-' . self::INVOICE, $this->storage->read($file->storageKey));
        self::assertSame([self::INVOICE], $renderer->rendered);
    }

    public function testLaterSendsReuseTheStoredFile(): void
    {
        $renderer = new FakeInvoicePdfRenderer();
        $files    = $this->files($renderer);

        $first                = $files->ensure(self::CLINIC, self::INVOICE);
        $this->storage->files = [];
        $again                = $files->ensure(self::CLINIC, self::INVOICE);

        self::assertSame($first, $again);
        self::assertSame([self::INVOICE], $renderer->rendered, 'AC 25b: stored once, at the first send.');
        self::assertSame([], $this->storage->files);
    }

    public function testTheLoserOfTwoFirstSendsReusesTheWinnersRow(): void
    {
        $this->store->concurrent = new SentDocumentFile('billing/sent/invoice/' . self::INVOICE . '.pdf', 'FAC-2026-000012.pdf');

        $file = $this->files(new FakeInvoicePdfRenderer('autre.pdf'))->ensure(self::CLINIC, self::INVOICE);

        self::assertSame('FAC-2026-000012.pdf', $file->filename);
    }

    public function testTheAttachmentNameIsSafe(): void
    {
        self::assertSame('FA-2026-0814-copie.pdf', $this->files(new FakeInvoicePdfRenderer('FA 2026/0814 copie.pdf'))->ensure(self::CLINIC, self::INVOICE)->filename);
    }

    public function testANameWithNothingReadableFallsBackOnFacture(): void
    {
        self::assertSame('facture.pdf', $this->files(new FakeInvoicePdfRenderer('éé'))->ensure(self::CLINIC, self::INVOICE)->filename);
    }

    private function files(FakeInvoicePdfRenderer $renderer): SentInvoiceFiles
    {
        return new SentInvoiceFiles($this->store, $renderer, $this->storage, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00')));
    }
}
