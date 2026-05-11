<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/lib/uploads.php';

final class ImageReprocessTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('gd') || !function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            self::markTestSkipped('GD image extension is required for image reprocessing tests.');
        }
    }

    public function testPngImageCanBeReprocessed(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'involve-image-test-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $source = $root . DIRECTORY_SEPARATOR . 'source.png';
        $destination = $root . DIRECTORY_SEPARATOR . 'destination.png';

        $image = imagecreatetruecolor(16, 16);
        self::assertInstanceOf(\GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = imagecolorallocatealpha($image, 20, 200, 150, 20);
        imagefilledrectangle($image, 0, 0, 15, 15, $color !== false ? $color : 0);
        imagepng($image, $source);
        cleanupUploadImageResource($image);

        self::assertTrue(reprocessUploadedImage($source, $destination, 'image/png'));
        self::assertFileExists($destination);

        $info = getimagesize($destination);
        self::assertIsArray($info);
        self::assertSame(16, (int) $info[0]);
        self::assertSame(16, (int) $info[1]);
        self::assertSame('image/png', (string) ($info['mime'] ?? ''));

        @unlink($source);
        @unlink($destination);
        @rmdir($root);
    }

    public function testUnsupportedMimeIsRejected(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'involve-image-test-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $source = $root . DIRECTORY_SEPARATOR . 'source.txt';
        $destination = $root . DIRECTORY_SEPARATOR . 'destination.txt';
        file_put_contents($source, 'not an image');

        self::assertFalse(reprocessUploadedImage($source, $destination, 'text/plain'));
        self::assertFileDoesNotExist($destination);

        @unlink($source);
        @rmdir($root);
    }
}
