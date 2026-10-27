<?php

namespace Tests\Feature\Infrastructure;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NginxConfigurationTest extends TestCase
{
    #[Test]
    public function test_外部LLMの既定タイムアウトより長くFastCGI応答を待つ(): void
    {
        $configuration = file_get_contents(base_path('docker/nginx/default.conf'));

        $this->assertIsString($configuration);
        $matched = preg_match('/fastcgi_read_timeout\s+(\d+)s;/', $configuration, $matches);

        $this->assertSame(1, $matched);
        $this->assertSame(180, (int) $matches[1]);
        $this->assertGreaterThan((int) config('services.illustration_llm.timeout'), (int) $matches[1]);
    }
}
