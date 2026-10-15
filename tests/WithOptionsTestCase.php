<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests;

use JustSteveKing\Toolbox\Features;

abstract class WithOptionsTestCase extends TestCase
{
    protected function features(): array
    {
        return [
            Features::problemDetails([
                'type_base_uri' => 'https://docs.example.com/problems/',
                'paths' => ['v1/*'],
                'apiguide' => false,
                'www_authenticate' => 'Bearer realm="api", error="invalid_token"',
            ]),
            Features::deprecation(['global' => false]),
            Features::idempotency([
                'ttl' => 60,
                'retry_after' => 5,
                'documentation' => 'https://docs.example.com/idempotency',
            ]),
            Features::rateLimitHeaders(['legacy_headers' => false]),
            Features::negotiation([
                'request_id_header' => 'Correlation-Id',
                'trust_request_id' => false,
                'propagate_trace' => false,
            ]),
        ];
    }
}
