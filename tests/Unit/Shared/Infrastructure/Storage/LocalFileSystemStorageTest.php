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
}
