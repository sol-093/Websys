<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$command = 'git -C ' . escapeshellarg($root) . ' ls-files "*.php"';
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Unable to list tracked PHP files. Is git available?\n");
    exit($exitCode);
}

$files = array_values(array_filter($output, static fn (string $file): bool => trim($file) !== ''));
if ($files === []) {
    echo "No tracked PHP files found.\n";
    exit(0);
}

$failures = [];
foreach ($files as $file) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $lintCommand = PHP_BINARY . ' -l ' . escapeshellarg($path);
    $lintOutput = [];
    $lintExitCode = 0;
    exec($lintCommand, $lintOutput, $lintExitCode);

    if ($lintExitCode !== 0) {
        $failures[] = $file . "\n" . implode("\n", $lintOutput);
        continue;
    }

    echo '.';
}

echo "\n";

if ($failures !== []) {
    fwrite(STDERR, "PHP lint failed:\n\n" . implode("\n\n", $failures) . "\n");
    exit(1);
}

echo 'PHP lint passed for ' . count($files) . " tracked files.\n";
