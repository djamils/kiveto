<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Storage;

use App\Shared\Infrastructure\Storage\LocalFileSystemStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalFileSystemStorage::class)]
final class LocalFileSystemStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kiveto-storage-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->root);
    }

    public function testAStoredFileIsReadBackByItsIdentifier(): void
    {
        $storage = new LocalFileSystemStorage($this->root, 'http://clinic.kiveto.local/');

        $id = $storage->store('order.csv', "code,qty\nCATH,50\n");

        self::assertStringEndsWith('/order.csv', $id);
        self::assertSame("code,qty\nCATH,50\n", $storage->read($id));
        self::assertSame('http://clinic.kiveto.local/storage/' . $id, $storage->getUrl($id));

        // A second file of the same day lands in the folder already there.
        self::assertSame('x', $storage->read($storage->store('other.csv', 'x')));
    }

    public function testAMissingFileIsNotRead(): void
    {
        $this->expectException(\RuntimeException::class);

        new LocalFileSystemStorage($this->root, '')->read('2026/08/21/none.csv');
    }

    public function testAnIdentifierCannotLeaveTheStorage(): void
    {
        $this->expectException(\RuntimeException::class);

        new LocalFileSystemStorage($this->root, '')->read('../../etc/passwd');
    }

    public function testAFileIsStoredAtItsExactKeyAndOverwrittenByARetry(): void
    {
        $storage = new LocalFileSystemStorage($this->root, '');

        $storage->storeAt('billing/sent/invoice/42.pdf', 'first');
        $storage->storeAt('billing/sent/invoice/42.pdf', 'second');

        self::assertSame('second', $storage->read('billing/sent/invoice/42.pdf'));
        self::assertFileExists($this->root . '/billing/sent/invoice/42.pdf');
    }

    public function testADeletedFileIsGoneAndDeletingItAgainIsNotAnError(): void
    {
        $storage = new LocalFileSystemStorage($this->root, '');
        $storage->storeAt('notification/attachments/abc/rdv.ics', 'x');

        $storage->delete('notification/attachments/abc/rdv.ics');
        $storage->delete('notification/attachments/abc/rdv.ics');

        self::assertFileDoesNotExist($this->root . '/notification/attachments/abc/rdv.ics');
    }

    public function testAKeyCannotLeaveTheStorageWhenStoringOrDeleting(): void
    {
        $storage = new LocalFileSystemStorage($this->root, '');

        foreach (['../escape.pdf', '/', ''] as $key) {
            try {
                $storage->storeAt($key, 'x');
                self::fail('Stored at ' . $key);
            } catch (\RuntimeException) {
            }
        }

        $this->expectException(\RuntimeException::class);

        $storage->delete('a/../../b');
    }

    public function testAFolderThatCannotBeCreatedIsReported(): void
    {
        $storage = new LocalFileSystemStorage($this->root, '');
        $storage->storeAt('taken', 'a file where a folder is needed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create the folder');

        $storage->storeAt('taken/inside.pdf', 'x');
    }

    public function testAFileThatCannotBeWrittenIsReported(): void
    {
        $storage = new LocalFileSystemStorage($this->root, '');
        $storage->storeAt('folder/file.pdf', 'x');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write');

        // The key names a folder: no file can be written there.
        $storage->storeAt('folder', 'x');
    }
}
