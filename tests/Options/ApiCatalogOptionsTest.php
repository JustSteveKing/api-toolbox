<?php

declare(strict_types=1);

it('builds the catalog from the configured APIs and catalogs', function (): void {
    $this->get('/.well-known/api-catalog')
        ->assertApiCatalog('http://localhost/v1')
        ->assertJsonPath('linkset.0.api-catalog.0.href', 'https://partners.example.com/.well-known/api-catalog')
        ->assertJsonPath('linkset.1.service-desc.0', ['href' => 'http://localhost/v1/openapi.json', 'type' => 'application/vnd.oai.openapi+json']);
});

it('sends no Cache-Control max-age when max_age is 0', function (): void {
    expect($this->get('/.well-known/api-catalog')->headers->get('Cache-Control'))->not->toContain('max-age');
});

it('runs the catalog behind the configured middleware', function (): void {
    $this->get('/.well-known/api-catalog')->assertOk();
    $this->get('/.well-known/api-catalog')->assertOk();
    $this->get('/.well-known/api-catalog')->assertTooManyRequests();
});
