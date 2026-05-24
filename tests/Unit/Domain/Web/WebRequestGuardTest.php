<?php

namespace Tests\Unit\Domain\Web;

use App\Domain\Web\Exceptions\WebProviderException;
use App\Domain\Web\Safety\WebRequestGuard;
use PHPUnit\Framework\TestCase;

class WebRequestGuardTest extends TestCase
{
    public function test_rejects_non_http_urls(): void
    {
        $this->expectException(WebProviderException::class);

        (new FakeWebRequestGuard(['example.com' => ['93.184.216.34']]))->assertSafePublicUrl('file:///etc/passwd');
    }

    public function test_rejects_embedded_credentials(): void
    {
        $this->expectException(WebProviderException::class);

        (new FakeWebRequestGuard(['example.com' => ['93.184.216.34']]))->assertSafePublicUrl('https://user:pass@example.com');
    }

    public function test_rejects_localhost(): void
    {
        $this->expectException(WebProviderException::class);

        (new FakeWebRequestGuard)->assertSafePublicUrl('http://localhost/test');
    }

    public function test_rejects_private_resolved_ips(): void
    {
        $this->expectException(WebProviderException::class);

        (new FakeWebRequestGuard(['example.com' => ['10.0.0.5']]))->assertSafePublicUrl('https://example.com');
    }

    public function test_accepts_public_http_urls(): void
    {
        (new FakeWebRequestGuard(['example.com' => ['93.184.216.34']]))->assertSafePublicUrl('https://example.com/docs');

        $this->assertTrue(true);
    }
}

class FakeWebRequestGuard extends WebRequestGuard
{
    public function __construct(private array $records = []) {}

    protected function resolveIpAddresses(string $host): array
    {
        return $this->records[$host] ?? [];
    }
}
