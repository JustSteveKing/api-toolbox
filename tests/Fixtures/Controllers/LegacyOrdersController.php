<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Controllers;

use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

/**
 * The dates are the ones RFC 9745 uses in its own examples.
 */
#[Deprecation(at: '2023-06-30T23:59:59Z', link: 'https://developer.example.com/deprecation')]
#[Sunset(at: '2024-06-30T23:59:59Z', link: 'https://developer.example.com/sunset')]
final class LegacyOrdersController
{
    public function index(): string
    {
        return 'orders';
    }

    #[Deprecation(at: '2024-01-01T00:00:00Z')]
    public function show(): string
    {
        return 'order';
    }

    public function store(): never
    {
        throw ValidationException::withMessages(['total' => 'The total field is required.']);
    }

    public function withLink(): Response
    {
        return new Response('linked', 200, ['Link' => '<https://api.example.com/orders?page=2>; rel="next"']);
    }
}
