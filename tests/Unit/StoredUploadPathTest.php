<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Support\Uploads\StoredUploadPath;
use PHPUnit\Framework\TestCase;

final class StoredUploadPathTest extends TestCase
{
    public function testPublicUploadPathIsResolved(): void
    {
        $path = StoredUploadPath::fromPublicPath('uploads/receipts/sample.pdf', 'C:\\tmp\\uploads');

        self::assertNotNull($path);
        self::assertSame('receipts/sample.pdf', $path->relativePath());
        self::assertSame('uploads/receipts/sample.pdf', $path->publicPath());
        self::assertStringEndsWith('uploads' . DIRECTORY_SEPARATOR . 'receipts' . DIRECTORY_SEPARATOR . 'sample.pdf', $path->absolutePath());
    }

    public function testLegacyPublicUploadPathIsNormalized(): void
    {
        $path = StoredUploadPath::fromPublicPath('public/uploads/users/avatar.png', '/tmp/uploads');

        self::assertNotNull($path);
        self::assertSame('uploads/users/avatar.png', $path->publicPath());
    }

    public function testUnsafeUploadPathsAreRejected(): void
    {
        self::assertNull(StoredUploadPath::fromPublicPath(null));
        self::assertNull(StoredUploadPath::fromPublicPath(''));
        self::assertNull(StoredUploadPath::fromPublicPath('assets/logo.png'));
        self::assertNull(StoredUploadPath::fromPublicPath('uploads/../.env'));
        self::assertNull(StoredUploadPath::fromPublicPath('uploads/receipts/../../config.php'));
        self::assertNull(StoredUploadPath::fromPublicPath('uploads/C:/Windows/win.ini'));
    }

    public function testDeleteIfFileRemovesOnlyResolvedFile(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'involve-upload-test-' . bin2hex(random_bytes(4));
        $receiptDir = $root . DIRECTORY_SEPARATOR . 'receipts';
        mkdir($receiptDir, 0777, true);
        $file = $receiptDir . DIRECTORY_SEPARATOR . 'sample.txt';
        file_put_contents($file, 'receipt');

        $path = StoredUploadPath::fromPublicPath('uploads/receipts/sample.txt', $root);

        self::assertNotNull($path);
        self::assertTrue($path->deleteIfFile());
        self::assertFileDoesNotExist($file);

        @rmdir($receiptDir);
        @rmdir($root);
    }
}
