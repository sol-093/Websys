<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ApiRouteTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for API route integration tests.');
        }
    }

    public function testHealthRouteReturnsJson(): void
    {
        $result = $this->runApiScript('api/health.php');

        self::assertSame(0, $result['exit_code'], $result['stderr']);
        $payload = json_decode($result['stdout'], true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok']);
        self::assertSame('healthy', $payload['status']);
    }

    public function testProtectedRouteReturnsUnauthenticatedJson(): void
    {
        $result = $this->runApiScript('api/me.php');
        $payload = json_decode($result['stdout'], true);

        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
        self::assertSame('Unauthenticated.', $payload['error']);
    }

    public function testAdminRouteRejectsUnauthenticatedRequest(): void
    {
        $result = $this->runApiScript('api/admin/audit.php');
        $payload = json_decode($result['stdout'], true);

        self::assertIsArray($payload);
        self::assertFalse($payload['ok']);
        self::assertSame('Unauthenticated.', $payload['error']);
    }

    /**
     * @return array{stdout:string, stderr:string, exit_code:int}
     */
    private function runApiScript(string $script): array
    {
        $root = dirname(__DIR__, 2);
        $dbPath = sys_get_temp_dir() . '/involve-api-test-' . sha1($script) . '.sqlite';
        @unlink($dbPath);

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = array_merge($_ENV, [
            'DB_DRIVER' => 'sqlite',
            'DB_SQLITE_PATH' => $dbPath,
            'DB_BOOTSTRAP_DATABASE' => '1',
            'CACHE_PATH' => sys_get_temp_dir() . '/involve-api-cache',
            'REQUEST_METHOD' => 'GET',
        ]);
        $process = proc_open([PHP_BINARY, $root . DIRECTORY_SEPARATOR . $script], $descriptorSpec, $pipes, $root, $env);
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode,
        ];
    }
}
