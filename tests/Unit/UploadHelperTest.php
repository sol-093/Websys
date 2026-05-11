<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/core/helpers.php';

final class UploadHelperTest extends TestCase
{
    public function testUploadOriginalExtensionIsNormalized(): void
    {
        self::assertSame('jpg', uploadOriginalExtension(['name' => 'Receipt.JPG']));
        self::assertSame('pdf', uploadOriginalExtension(['name' => 'folder/report.final.PDF']));
        self::assertSame('', uploadOriginalExtension(['name' => 'no-extension']));
    }

    public function testUploadExtensionAllowlistRejectsMissingOrDisallowedExtension(): void
    {
        $allowed = ['jpg', 'png', 'pdf'];

        self::assertTrue(uploadExtensionIsAllowed(['name' => 'receipt.jpg'], $allowed));
        self::assertTrue(uploadExtensionIsAllowed(['name' => 'receipt.PDF'], $allowed));
        self::assertFalse(uploadExtensionIsAllowed(['name' => 'receipt.php'], $allowed));
        self::assertFalse(uploadExtensionIsAllowed(['name' => 'receipt'], $allowed));
    }
}
