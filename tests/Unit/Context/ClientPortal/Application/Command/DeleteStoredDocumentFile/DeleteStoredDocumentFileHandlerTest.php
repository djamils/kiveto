<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile;

use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFile;
use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFileHandler;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryPublishedDocuments;
use App\Tests\Unit\Context\ClientPortal\Support\PetFakes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeleteStoredDocumentFileHandlerTest extends TestCase
{
    public function testAPortalFileIsDeleted(): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::once())->method('delete')->with('client_portal/documents/d-v1.pdf');

        self::handler($storage)(new DeleteStoredDocumentFile('client_portal/documents/d-v1.pdf'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAnyOtherFileIsRefusedCases(): iterable
    {
        yield 'billing pdf' => ['billing/invoices/f.pdf'];
        yield 'escaping the prefix' => ['client_portal/../x'];
    }

    #[DataProvider('provideAnyOtherFileIsRefusedCases')]
    public function testAnyOtherFileIsRefused(string $key): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::never())->method('delete');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Refusing to delete "%s": not a Client Portal file.', $key));

        self::handler($storage)(new DeleteStoredDocumentFile($key));
    }

    public function testAKeyServedAgainIsKept(): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::never())->method('delete');
        $booklets                     = new PetFakes();
        $booklets->booklets['animal'] = 'client_portal/booklets/animal-hash-a.pdf';
        $world                        = new DocumentWorld();
        $world->document(DocumentType::REPORT, PublicationStatus::AVAILABLE, 2, storageKey: 'client_portal/documents/d-v2.pdf');
        $handler = new DeleteStoredDocumentFileHandler($storage, $booklets, $world->documents);

        // A → B → A: the delayed deletion of A runs once A is current again
        $handler(new DeleteStoredDocumentFile('client_portal/booklets/animal-hash-a.pdf'));
        $handler(new DeleteStoredDocumentFile('client_portal/documents/d-v2.pdf'));
    }

    private static function handler(FileStorageInterface $storage): DeleteStoredDocumentFileHandler
    {
        return new DeleteStoredDocumentFileHandler($storage, new PetFakes(), new InMemoryPublishedDocuments());
    }
}
