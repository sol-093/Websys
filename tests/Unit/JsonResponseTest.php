<?php

declare(strict_types=1);

namespace Tests\Unit;

use Involve\Support\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testEncodeUsesApiJsonFlags(): void
    {
        self::assertSame('{"ok":true,"path":"/api/health.php","label":"INVOLVE"}', JsonResponse::encode([
            'ok' => true,
            'path' => '/api/health.php',
            'label' => 'INVOLVE',
        ]));
    }
}
