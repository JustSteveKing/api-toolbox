<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\Translation\Translator;

/**
 * The text of the problems this package raises, from resources/lang/{locale}/problems.php.
 *
 * Problems are exceptions, constructed with new wherever they are thrown, so this resolves the
 * translator from the container rather than having it injected. Outside an application, or before
 * the translator is bound, it reads the English file directly.
 */
final class Messages
{
    public const string NAMESPACE = 'api-toolbox';

    public const string PATH = __DIR__.'/../../resources/lang';

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $fallback = null;

    /**
     * @param  string  $key  A key in problems.php, such as idempotency.conflict.
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $id = self::NAMESPACE.'::problems.'.$key;
        $container = Container::getInstance();

        if ($container->bound('translator')) {
            $translator = $container->make('translator');

            if ($translator instanceof Translator) {
                $line = $translator->get($id, $replace);

                if (is_string($line) && $line !== $id) {
                    return $line;
                }
            }
        }

        return self::fallback($key, $replace);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function fallback(string $key, array $replace): string
    {
        self::$fallback ??= self::load();

        $line = self::$fallback;

        foreach (explode('.', $key) as $segment) {
            $line = is_array($line) ? ($line[$segment] ?? null) : null;
        }

        if (! is_string($line)) {
            return $key;
        }

        // Longest placeholder first, so :types is not read as :type followed by "s".
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($replace as $name => $value) {
            $line = str_replace(':'.$name, $value, $line);
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        /** @var array<string, mixed> $lines */
        $lines = require self::PATH.'/en/problems.php';

        return $lines;
    }
}
