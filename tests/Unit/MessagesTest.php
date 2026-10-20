<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use JustSteveKing\Toolbox\Support\Messages;

/*
 * Every problem detail the package raises comes from resources/lang/en/problems.php. A key the
 * code asks for that the file does not have would be sent to clients as the bare key.
 */

/**
 * @return list<string>
 */
function messageKeysUsedInSource(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        preg_match_all("/Messages::get\\('([a-z_.]*[a-z_])'/", $source, $direct);
        preg_match_all("/self::reason\\('([a-z_]+)'/", $source, $reasons);

        $keys = [...$keys, ...$direct[1], ...array_map(static fn (string $key): string => 'message_signatures.reasons.'.$key, $reasons[1])];
    }

    return array_values(array_unique($keys));
}

it('finds the message keys used in the source', function (): void {
    expect(count(messageKeysUsedInSource()))->toBeGreaterThan(25);
});

it('has an English line for every key the source uses', function (string $key): void {
    /** @var array<string, mixed> $lines */
    $lines = require Messages::PATH.'/en/problems.php';

    expect(data_get($lines, $key))->toBeString("resources/lang/en/problems.php has no line for [{$key}].");
})->with(messageKeysUsedInSource());

it('reads lines through the application translator', function (): void {
    expect(Messages::get('idempotency.missing'))->toBe('This operation requires an Idempotency-Key header.');
    expect(Messages::get('negotiation.unsupported_media_type', ['type' => 'text/csv', 'types' => 'application/json']))
        ->toBe("The Content-Type 'text/csv' is not supported. Supported content types: application/json.");
});

it('falls back to the English file without a translator', function (): void {
    $application = Container::getInstance();
    Container::setInstance(new Container);

    try {
        expect(Messages::get('idempotency.missing'))->toBe('This operation requires an Idempotency-Key header.');
        expect(Messages::get('negotiation.unsupported_media_type', ['type' => 'text/csv', 'types' => 'application/json']))
            ->toBe("The Content-Type 'text/csv' is not supported. Supported content types: application/json.");
    } finally {
        Container::setInstance($application);
    }
});
