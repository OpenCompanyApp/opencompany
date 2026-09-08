<?php

declare(strict_types=1);

use App\Services\MrubySandboxService;
use Bowerbird\RubyEngine\Client;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/**
 * Production-image smoke probe.
 *
 * This script owns no database, provider, queue, or application-daemon work.
 * It proves only that the final image's unprivileged PHP runtime can verify the
 * installed immutable Ruby artifact and execute data-only Ruby through both the
 * release adapter and OpenCompany's sandbox boundary.
 */
if (! function_exists('posix_geteuid') || ! function_exists('posix_getpwuid')) {
    throw new RuntimeException('The container smoke requires POSIX identity support.');
}

$identity = posix_getpwuid(posix_geteuid());
if (($identity['name'] ?? null) !== 'www-data') {
    throw new RuntimeException('The container smoke must run as www-data.');
}

$checksumFile = '/usr/local/share/ruby-engine.sha256';
$binary = '/usr/local/bin/ruby-engine';
$expected = is_readable($checksumFile)
    ? strtok((string) file_get_contents($checksumFile), " \t\r\n")
    : false;
$actual = is_file($binary) ? hash_file('sha256', $binary) : false;
if (! is_string($expected) || $expected === '' || ! is_string($actual) || ! hash_equals($expected, $actual)) {
    throw new RuntimeException('The installed Ruby artifact does not match its checksum file.');
}

$clientExecution = (new Client($binary, $expected))->execute('6 * 7');
if ($clientExecution->error !== null || $clientExecution->validatedOnly || $clientExecution->result !== 42) {
    throw new RuntimeException('The release PHP adapter did not execute the expected Ruby result.');
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sandbox = $app->make(MrubySandboxService::class);
$sandboxExecution = $sandbox->execute('6 * 7', profile: 'console');
if ($sandboxExecution->error !== null || $sandboxExecution->validatedOnly || $sandboxExecution->result !== 42) {
    throw new RuntimeException('MrubySandboxService did not execute the expected Ruby result.');
}
if (! hash_equals($expected, $sandbox->engineDigest()) || ! hash_equals($expected, (string) config('code.engine_sha256'))) {
    throw new RuntimeException('The sandbox or Laravel configuration did not enforce the installed artifact digest.');
}

fwrite(STDOUT, "mruby container execution smoke passed\n");
