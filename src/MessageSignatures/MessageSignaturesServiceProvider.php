<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use JustSteveKing\Toolbox\MessageSignatures\Http\Middleware\SignResponse;
use JustSteveKing\Toolbox\MessageSignatures\Http\Middleware\VerifySignature;

final class MessageSignaturesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $options = $this->app->make(EnabledFeatures::class)->options(Features::MESSAGE_SIGNATURES);
        $keys = is_array($options['keys'] ?? null) ? $options['keys'] : [];
        $verify = is_array($options['verify'] ?? null) ? $options['verify'] : [];
        $sign = is_array($options['sign'] ?? null) ? $options['sign'] : [];

        $this->app->singleton(KeyResolver::class, static fn (): KeyResolver => new ConfiguredKeys($keys));
        $this->app->bind(Signer::class, static fn (Application $app): Signer => new Signer($app->make(KeyResolver::class)));

        $this->app->bind(VerificationPolicy::class, static fn (): VerificationPolicy => new VerificationPolicy(
            required: is_array($verify['required'] ?? null) ? array_values(array_filter($verify['required'], is_string(...))) : ['@method', '@authority', '@path'],
            maxAge: is_int($verify['max_age'] ?? null) ? $verify['max_age'] : 300,
            leeway: is_int($verify['leeway'] ?? null) ? $verify['leeway'] : 30,
            coverContent: ($verify['cover_content'] ?? true) === true,
            tag: is_string($verify['tag'] ?? null) ? $verify['tag'] : null,
            requireNonce: ($verify['require_nonce'] ?? false) === true,
        ));

        $this->app->bind(Verifier::class, static fn (Application $app): Verifier => new Verifier(
            $app->make(KeyResolver::class),
            $app->make(VerificationPolicy::class),
        ));

        $this->app->bind(SignResponse::class, static fn (Application $app): SignResponse => new SignResponse(
            $app->make(Signer::class),
            is_string($sign['key'] ?? null) ? $sign['key'] : null,
        ));
    }

    public function boot(): void
    {
        $this->callAfterResolving('router', static function (Router $router): void {
            $router->aliasMiddleware('signature.verify', VerifySignature::class);
            $router->aliasMiddleware('signature.sign', SignResponse::class);
        });

        SignsOutgoingRequests::register();
    }
}
