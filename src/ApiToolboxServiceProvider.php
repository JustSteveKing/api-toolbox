<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox;

use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\ApiCatalog\ApiCatalogServiceProvider;
use JustSteveKing\Toolbox\AsyncOperations\AsyncOperationsServiceProvider;
use JustSteveKing\Toolbox\ConditionalRequests\ConditionalRequestsServiceProvider;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Deprecation\DeprecationServiceProvider;
use JustSteveKing\Toolbox\Digest\DigestServiceProvider;
use JustSteveKing\Toolbox\Idempotency\IdempotencyServiceProvider;
use JustSteveKing\Toolbox\MergePatch\MergePatchServiceProvider;
use JustSteveKing\Toolbox\MessageSignatures\MessageSignaturesServiceProvider;
use JustSteveKing\Toolbox\Negotiation\NegotiationServiceProvider;
use JustSteveKing\Toolbox\Pagination\PaginationServiceProvider;
use JustSteveKing\Toolbox\Preferences\PreferencesServiceProvider;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\RateLimitHeaders\RateLimitHeadersServiceProvider;
use JustSteveKing\Toolbox\Support\Messages;
use JustSteveKing\Toolbox\Testing\TestingServiceProvider;

/**
 * Registers each enabled feature's own provider and nothing else.
 *
 * Fortify registers everything and gates only its routes. This goes further: a disabled feature's
 * provider is never registered, so it contributes no bindings, middleware aliases, macros or
 * exception handling hooks at all.
 */
final class ApiToolboxServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<ServiceProvider>>
     */
    private const array PROVIDERS = [
        Features::PROBLEM_DETAILS => ProblemDetailsServiceProvider::class,
        Features::DEPRECATION => DeprecationServiceProvider::class,
        Features::CONDITIONAL_REQUESTS => ConditionalRequestsServiceProvider::class,
        Features::PAGINATION => PaginationServiceProvider::class,
        Features::IDEMPOTENCY => IdempotencyServiceProvider::class,
        Features::RATE_LIMIT_HEADERS => RateLimitHeadersServiceProvider::class,
        Features::NEGOTIATION => NegotiationServiceProvider::class,
        Features::PREFERENCES => PreferencesServiceProvider::class,
        Features::ASYNC_OPERATIONS => AsyncOperationsServiceProvider::class,
        Features::MERGE_PATCH => MergePatchServiceProvider::class,
        Features::DIGESTS => DigestServiceProvider::class,
        Features::MESSAGE_SIGNATURES => MessageSignaturesServiceProvider::class,
        Features::API_CATALOG => ApiCatalogServiceProvider::class,
    ];

    /**
     * How each feature is named in `php artisan about`.
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        Features::PROBLEM_DETAILS => 'Problem Details',
        Features::DEPRECATION => 'Deprecation',
        Features::CONDITIONAL_REQUESTS => 'Conditional Requests',
        Features::PAGINATION => 'Pagination',
        Features::IDEMPOTENCY => 'Idempotency',
        Features::RATE_LIMIT_HEADERS => 'RateLimit Headers',
        Features::NEGOTIATION => 'Negotiation',
        Features::PREFERENCES => 'Preferences',
        Features::ASYNC_OPERATIONS => 'Async Operations',
        Features::MERGE_PATCH => 'Merge Patch',
        Features::DIGESTS => 'Digest Fields',
        Features::MESSAGE_SIGNATURES => 'Message Signatures',
        Features::API_CATALOG => 'API Catalog',
    ];

    public const string ABOUT_SECTION = 'API Toolbox';

    public function register(): void
    {
        // The merged file carries no options; see stubs/api-toolbox.php for why.
        $this->mergeConfigFrom(__DIR__.'/../config/api-toolbox.php', Features::CONFIG_KEY);

        /** @var Repository $config */
        $config = $this->app->make('config');

        $features = ConfiguredFeatures::fromConfig($config);

        $this->app->instance(EnabledFeatures::class, $features);

        foreach ($features->all() as $feature) {
            $this->app->register(self::PROVIDERS[$feature]);
        }

        if ($this->app->runningUnitTests()) {
            $this->app->register(TestingServiceProvider::class);
        }
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(Messages::PATH, Messages::NAMESPACE);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../stubs/api-toolbox.php' => $this->app->configPath(Features::CONFIG_KEY.'.php'),
            ], Features::CONFIG_KEY.'-config');

            $this->publishes([
                Messages::PATH => $this->app->langPath('vendor/'.Messages::NAMESPACE),
            ], Features::CONFIG_KEY.'-lang');

            self::about($this->app->make(EnabledFeatures::class));
        }
    }

    /**
     * Adds the package to `php artisan about`: its version, and whether each feature is on. The
     * closure is static and holds only the enabled features, not the application, because
     * AboutCommand keeps it in a static registry.
     */
    private static function about(EnabledFeatures $features): void
    {
        if (! class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add(self::ABOUT_SECTION, static function () use ($features): array {
            $rows = [
                'Version' => InstalledVersions::isInstalled('juststeveking/api-toolbox')
                    ? (string) InstalledVersions::getPrettyVersion('juststeveking/api-toolbox')
                    : 'unknown',
            ];

            foreach (Features::all() as $feature) {
                $experimental = in_array($feature, Features::EXPERIMENTAL, true);

                $rows[self::LABELS[$feature]] = AboutCommand::format(
                    $features->enabled($feature),
                    console: static fn (mixed $enabled): string => match (true) {
                        $enabled === true && $experimental => '<fg=yellow;options=bold>ENABLED</> (experimental)',
                        $enabled === true => '<fg=yellow;options=bold>ENABLED</>',
                        default => 'OFF',
                    },
                );
            }

            // Key IDs and algorithms only: never a secret or a key's location.
            if ($features->enabled(Features::MESSAGE_SIGNATURES)) {
                $keys = $features->options(Features::MESSAGE_SIGNATURES)['keys'] ?? [];
                $described = [];

                foreach (is_array($keys) ? $keys : [] as $id => $key) {
                    $algorithm = is_array($key) && is_string($key['algorithm'] ?? null) ? $key['algorithm'] : '?';
                    $described[] = "{$id} ({$algorithm})";
                }

                $rows['Signature Keys'] = $described === [] ? 'none' : implode(', ', $described);
            }

            return $rows;
        });
    }
}
