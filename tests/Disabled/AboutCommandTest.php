<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('still shows the section, with every feature off', function (): void {
    Artisan::call('about', ['--only' => 'api_toolbox', '--json' => true]);
    $section = json_decode(Artisan::output(), true)['a_p_i_toolbox'];

    expect(array_filter($section, is_bool(...)))->each->toBeFalse()
        ->and($section)->not->toHaveKey('signature_keys');
});
