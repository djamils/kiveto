<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile;

use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFile;
use App\Context\ClientPortal\Application\Command\DeleteStoredDocumentFile\DeleteStoredDocumentFileHandler;
use App\Shared\Domain\Storage\FileStorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeleteStoredDocumentFileHandlerTest extends TestCase
{
    public function testAPortalFileIsDeleted(): void
    {
        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::once())->method('delete')->with('client_portal/documents/d-v1.pdf');

        new DeleteStoredDocumentFileHandler($storage)(new DeleteStoredDocumentFile('client_portal/documents/d-v1.pdf'));
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

        new DeleteStoredDocumentFileHandler($storage)(new DeleteStoredDocumentFile($key));
    }
}
