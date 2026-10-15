<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\ConfiguredFeatures;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Exceptions\UnknownFeature;
use JustSteveKing\Toolbox\Facades\ApiToolbox;
use JustSteveKing\Toolbox\Features;

it('names each feature with a plain string, so config:cache can var_export it', function (): void {
    expect(Features::problemDetails())->toBe('problem-details');

    $exported = var_export(['features' => [Features::problemDetails()]], true);

    expect(eval("return {$exported};"))->toBe(['features' => ['problem-details']]);
});

it('stores inline options under their own key, not the package key', function (): void {
    Features::problemDetails(['type_base_uri' => 'https://docs.example.com/problems']);

    expect(config(Features::OPTIONS_KEY.'.problem-details'))
        ->toBe(['type_base_uri' => 'https://docs.example.com/problems'])
        ->and(config(Features::CONFIG_KEY.'.problem-details'))->toBeNull();
});

it('does not touch options when a feature is named without them', function (): void {
    Features::problemDetails(['paths' => ['v1/*']]);
    Features::problemDetails();

    expect(config(Features::OPTIONS_KEY.'.problem-details'))->toBe(['paths' => ['v1/*']]);
});

it('rejects a feature it does not know, rather than silently ignoring a typo', function (): void {
    new ConfiguredFeatures(['problem-detials']);
})->throws(UnknownFeature::class, 'Unknown feature [problem-detials]');

it('reads enabled features and their options from config', function (): void {
    config([
        Features::CONFIG_KEY.'.features' => [Features::problemDetails(['paths' => ['v1/*']])],
    ]);

    $features = ConfiguredFeatures::fromConfig(app('config'));

    expect($features->enabled(Features::PROBLEM_DETAILS))->toBeTrue()
        ->and($features->options(Features::PROBLEM_DETAILS))->toBe(['paths' => ['v1/*']])
        ->and($features->all())->toBe([Features::PROBLEM_DETAILS]);
});

it('answers runtime checks through the facade', function (): void {
    expect(ApiToolbox::enabled(Features::problemDetails()))->toBeTrue()
        ->and(app(EnabledFeatures::class))->toBeInstanceOf(ConfiguredFeatures::class);
});

it('survives config:cache with the published stub', function (): void {
    $config = require __DIR__.'/../../stubs/api-toolbox.php';

    $cached = eval('return '.var_export($config, true).';');

    expect($cached['features'])->toBe(['problem-details', 'deprecation', 'conditional-requests', 'pagination', 'idempotency', 'negotiation'])
        ->and(config(Features::OPTIONS_KEY.'.problem-details'))
        ->toBe(['type_base_uri' => '/problems', 'paths' => ['api/*'], 'apiguide' => true, 'www_authenticate' => 'Bearer'])
        ->and(config(Features::OPTIONS_KEY.'.deprecation'))
        ->toBe(['global' => true]);
});

it('merges a default config that carries no options, so it cannot overwrite the application\'s', function (): void {
    config([Features::OPTIONS_KEY => []]);

    require __DIR__.'/../../config/api-toolbox.php';

    expect(config(Features::OPTIONS_KEY))->toBe([]);
});
