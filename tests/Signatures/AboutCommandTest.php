<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('lists signature key IDs and algorithms, and never a secret or key path', function (): void {
    Artisan::call('about', ['--only' => 'api_toolbox']);
    $output = Artisan::output();

    expect($output)->toMatch('/Signature Keys[\s.]+webhooks \(hmac-sha256\)/')
        ->not->toContain('base64:')
        ->not->toContain('.pem');
});
