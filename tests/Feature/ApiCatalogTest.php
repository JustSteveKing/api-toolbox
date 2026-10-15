<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\ApiCatalog\ConfiguredCatalog;
use JustSteveKing\Toolbox\ApiCatalog\Contracts\CatalogSource;

function catalogOf(array $apis, array $catalogs = []): void
{
    app()->instance(CatalogSource::class, new ConfiguredCatalog($apis, $catalogs));
}

it('serves an empty but valid catalog at the well-known URI', function (): void {
    $this->get('/.well-known/api-catalog')
        ->assertOk()
        ->assertApiCatalog()
        ->assertExactJson(['linkset' => [['anchor' => 'http://localhost/.well-known/api-catalog']]]);
});

it('labels the document application/linkset+json with the RFC 9727 profile', function (): void {
    // RFC 9727, section 4.2: MUST use application/linkset+json, SHOULD include the profile.
    $this->get('/.well-known/api-catalog')
        ->assertHeader('Content-Type', 'application/linkset+json; profile="https://www.rfc-editor.org/info/rfc9727"');
});

it('answers HEAD with the api-catalog Link field', function (): void {
    // RFC 9727, section 2: HEAD SHALL return a Link header field with the api-catalog relation.
    $response = $this->call('HEAD', '/.well-known/api-catalog');

    $response->assertOk()->assertLinkHeader('api-catalog', 'http://localhost/.well-known/api-catalog');
    expect($response->headers->get('Link'))->toBe('<http://localhost/.well-known/api-catalog>; rel="api-catalog"');
});

it('lists each API with item and gives it its own RFC 8631 entry, as in RFC 9727 Appendix A', function (): void {
    catalogOf([
        [
            'href' => 'https://developer.example.com/apis/foo_api',
            'service-desc' => ['href' => 'https://developer.example.com/apis/foo_api/spec', 'type' => 'application/yaml'],
            'status' => 'https://developer.example.com/apis/foo_api/status',
            'service-doc' => ['href' => 'https://developer.example.com/apis/foo_api/doc', 'type' => 'text/html'],
            'service-meta' => ['href' => 'https://developer.example.com/apis/foo_api/policies', 'type' => 'text/xml'],
        ],
        ['href' => 'https://developer.example.com/apis/bar_api'],
    ]);

    $this->get('/.well-known/api-catalog')
        ->assertApiCatalog('https://developer.example.com/apis/foo_api')
        ->assertApiCatalog('https://developer.example.com/apis/bar_api')
        ->assertExactJson(['linkset' => [
            [
                'anchor' => 'http://localhost/.well-known/api-catalog',
                'item' => [
                    ['href' => 'https://developer.example.com/apis/foo_api'],
                    ['href' => 'https://developer.example.com/apis/bar_api'],
                ],
            ],
            [
                'anchor' => 'https://developer.example.com/apis/foo_api',
                'service-desc' => [['href' => 'https://developer.example.com/apis/foo_api/spec', 'type' => 'application/yaml']],
                'service-doc' => [['href' => 'https://developer.example.com/apis/foo_api/doc', 'type' => 'text/html']],
                'service-meta' => [['href' => 'https://developer.example.com/apis/foo_api/policies', 'type' => 'text/xml']],
                'status' => [['href' => 'https://developer.example.com/apis/foo_api/status']],
            ],
        ]]);
});

it('keeps the relation order and does not escape slashes', function (): void {
    catalogOf([['href' => 'https://api.example.com/v1', 'status' => '/status', 'service-desc' => '/openapi.json']]);

    expect($this->get('/.well-known/api-catalog')->getContent())
        ->toContain('"anchor":"https://api.example.com/v1","service-desc":[{"href":"http://localhost/openapi.json"}],"status"');
});

it('resolves relative references against the application URL', function (): void {
    // RFC 9264, sections 4.2.2 and 4.2.3: anchors and targets SHOULD NOT be relative references.
    catalogOf([['href' => '/api/v1', 'service-doc' => 'docs']]);

    $this->get('/.well-known/api-catalog')->assertJsonPath('linkset.0.item.0.href', 'http://localhost/api/v1')
        ->assertJsonPath('linkset.1.service-doc.0.href', 'http://localhost/docs');
});

it('keeps more than one target for a relation, and hreflang as an array', function (): void {
    // RFC 9264, section 4.2.4.1: hreflang is always an array.
    catalogOf([[
        'href' => 'https://api.example.com',
        'service-doc' => [
            ['href' => 'https://docs.example.com/en', 'hreflang' => 'en', 'title' => 'Docs'],
            ['href' => 'https://docs.example.com/de', 'hreflang' => ['de', 'de-AT']],
        ],
    ]]);

    $this->get('/.well-known/api-catalog')->assertJsonPath('linkset.1.service-doc', [
        ['href' => 'https://docs.example.com/en', 'title' => 'Docs', 'hreflang' => ['en']],
        ['href' => 'https://docs.example.com/de', 'hreflang' => ['de', 'de-AT']],
    ]);
});

it('links nested catalogs with api-catalog', function (): void {
    // RFC 9727, section 4.3.
    catalogOf([], ['https://payments.example.com/.well-known/api-catalog']);

    $this->get('/.well-known/api-catalog')->assertJsonPath('linkset.0.api-catalog', [
        ['href' => 'https://payments.example.com/.well-known/api-catalog'],
    ]);
});

it('refuses an API without an href', function (): void {
    catalogOf([['service-desc' => '/openapi.json']]);

    $this->withoutExceptionHandling()->get('/.well-known/api-catalog');
})->throws(InvalidArgumentException::class, 'API catalog entry 0 needs an href');

it('lets the catalog be cached', function (): void {
    $this->get('/.well-known/api-catalog')->assertCacheControl(['public', 'max-age' => '3600']);
});

it('points to the catalog from other responses', function (): void {
    Route::get('api', fn () => ['ok' => true])->middleware('api-catalog.link');

    $this->getJson('api')->assertLinkHeader('api-catalog', 'http://localhost/.well-known/api-catalog');
});

it('adds to the Link field rather than replacing it', function (): void {
    Route::get('api', fn () => response()->json([], 200, ['Link' => '<https://example.com/terms>; rel="terms-of-service"']))
        ->middleware('api-catalog.link');

    $this->getJson('api')
        ->assertLinkHeader('terms-of-service')
        ->assertLinkHeader('api-catalog');
});

it('offers a Pest expectation', function (): void {
    catalogOf([['href' => 'https://api.example.com']]);

    expect($this->get('/.well-known/api-catalog'))->toBeApiCatalog('https://api.example.com');
});
