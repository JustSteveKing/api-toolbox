<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

function aboutApiToolbox(bool $json = false): string
{
    Artisan::call('about', ['--only' => 'api_toolbox', '--json' => $json]);

    return Artisan::output();
}

it('adds an API Toolbox section to php artisan about', function (): void {
    $output = aboutApiToolbox();

    expect($output)->toContain('API Toolbox')
        ->toMatch('/Version\s*\.+\s*\S+/')
        ->toMatch('/Problem Details\s*\.+\s*ENABLED/')
        ->toMatch('/API Catalog\s*\.+\s*ENABLED/');
});

it('marks experimental features', function (): void {
    expect(aboutApiToolbox())->toMatch('/RateLimit Headers\s*\.+\s*ENABLED \(experimental\)/');
});

it('lists every feature as a boolean in JSON', function (): void {
    $section = json_decode(aboutApiToolbox(json: true), true)['a_p_i_toolbox'];

    expect($section)->toHaveKeys(['version', 'problem_details', 'deprecation', 'conditional_requests', 'pagination', 'idempotency', 'ratelimit_headers', 'negotiation', 'preferences', 'async_operations', 'merge_patch', 'digest_fields', 'message_signatures', 'api_catalog'])
        ->and($section['problem_details'])->toBeTrue()
        ->and($section['signature_keys'])->toBe('none');
});

it('is keyed the way Laravel snake-cases the section name', function (): void {
    // Str::snake('API Toolbox'), as AboutCommand does for every section.
    expect(json_decode(aboutApiToolbox(json: true), true))->toHaveKey('a_p_i_toolbox');
});
