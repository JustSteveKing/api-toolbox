<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox;

use Illuminate\Contracts\Config\Repository;
use JustSteveKing\Toolbox\Contracts\EnabledFeatures;
use JustSteveKing\Toolbox\Exceptions\UnknownFeature;

final readonly class ConfiguredFeatures implements EnabledFeatures
{
    /**
     * @param  list<string>  $features
     * @param  array<string, array<string, mixed>>  $options
     */
    public function __construct(
        private array $features,
        private array $options = [],
    ) {
        foreach ($this->features as $feature) {
            if (! in_array($feature, Features::all(), true)) {
                throw UnknownFeature::named($feature);
            }
        }
    }

    public static function fromConfig(Repository $config): self
    {
        $configured = $config->get(Features::CONFIG_KEY.'.features', []);
        $features = [];

        foreach (is_array($configured) ? $configured : [] as $feature) {
            if (! is_string($feature)) {
                throw UnknownFeature::named(get_debug_type($feature));
            }

            $features[$feature] = $feature;
        }

        $options = [];

        foreach ((array) $config->get(Features::OPTIONS_KEY, []) as $feature => $featureOptions) {
            foreach (is_array($featureOptions) ? $featureOptions : [] as $name => $value) {
                $options[(string) $feature][(string) $name] = $value;
            }
        }

        return new self(array_values($features), $options);
    }

    public function enabled(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    public function options(string $feature): array
    {
        return $this->options[$feature] ?? [];
    }

    public function all(): array
    {
        return $this->features;
    }
}
