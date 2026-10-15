<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

it('resolves type slugs against the configured base URI', function (): void {
    Route::get('v1/credit', fn () => throw new OutOfCredit);

    $this->getJson('v1/credit')->assertJsonPath('type', 'https://docs.example.com/problems/out-of-credit');
});

it('always renders problems for the configured paths', function (): void {
    Route::get('v1/credit', fn () => throw new OutOfCredit);

    $this->get('v1/credit')
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('replaces the default paths rather than adding to them', function (): void {
    Route::get('api/credit', fn () => throw new OutOfCredit);

    $response = $this->get('api/credit');

    expect($response->headers->get('Content-Type'))->not->toContain('problem+json');
});
