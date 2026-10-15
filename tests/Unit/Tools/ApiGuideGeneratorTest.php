<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Support\ApiGuide;
use JustSteveKing\Toolbox\Tools\ApiGuideGenerator;

/**
 * Entries for every mapped status, as the catalogue gives them, plus any extras.
 *
 * @return array<string, array{title: string, status: int, deprecated: bool}>
 */
function catalogue(array $extra = []): array
{
    $entries = [];

    foreach (ApiGuideGenerator::STATUSES as $status => $slug) {
        $entries[$slug] = ['title' => ucwords(str_replace('-', ' ', $slug)), 'status' => $status, 'deprecated' => false];
    }

    return [...$entries, ...$extra];
}

it('reads the front matter the catalogue uses, quoted titles included', function (): void {
    $markdown = "---\ntitle: \"Not Acceptable\"\nstatusCode: 406\nstatusText: Not Acceptable\ncategory: client-error\npublishedDate: 2026-07-09\n---\n\n## When to use it\n";

    expect(ApiGuideGenerator::parseEntry('not-acceptable', $markdown))
        ->toBe(['title' => 'Not Acceptable', 'status' => 406, 'deprecated' => false]);
});

it('reads the deprecated flag', function (): void {
    expect(ApiGuideGenerator::parseEntry('old', "---\ntitle: Old\nstatusCode: 400\ndeprecated: true\n---\n")['deprecated'])->toBeTrue();
});

it('refuses entries it cannot read', function (string $markdown): void {
    ApiGuideGenerator::parseEntry('broken', $markdown);
})->with([
    'no front matter' => ['## Just a heading'],
    'no title' => ["---\nstatusCode: 400\n---\n"],
    'no status' => ["---\ntitle: Broken\n---\n"],
])->throws(RuntimeException::class);

it('refuses a catalogue that has dropped a mapped entry', function (): void {
    $entries = catalogue();
    unset($entries['forbidden']);

    ApiGuideGenerator::render($entries);
})->throws(RuntimeException::class, 'Status 403 is mapped to [forbidden], which the catalogue no longer has.');

it('refuses a mapped entry whose status has changed', function (): void {
    ApiGuideGenerator::render(catalogue(['gone' => ['title' => 'Gone', 'status' => 404, 'deprecated' => false]]));
})->throws(RuntimeException::class, 'which the catalogue gives status 404');

it('marks deprecated entries @deprecated', function (): void {
    $source = ApiGuideGenerator::render(catalogue(['old-thing' => ['title' => 'Old Thing', 'status' => 400, 'deprecated' => true]]));

    expect($source)->toContain("     * @deprecated Deprecated in the apiguide.dev catalogue.\n     */\n    public const string OLD_THING = self::BASE.'old-thing';");
});

it('escapes titles safely', function (): void {
    $source = ApiGuideGenerator::render(catalogue(['quoted' => ['title' => "It's \\ odd", 'status' => 400, 'deprecated' => false]]));

    expect($source)->toContain("self::QUOTED => 'It\\'s \\\\ odd',");
});

it('reports statuses that newly have one entry, but not those left unmapped on purpose', function (): void {
    $candidates = ApiGuideGenerator::unmappedCandidates(catalogue([
        'im-a-teapot' => ['title' => "I'm a Teapot", 'status' => 418, 'deprecated' => false],
        'insufficient-funds' => ['title' => 'Insufficient Funds', 'status' => 402, 'deprecated' => false],
    ]));

    expect($candidates)->toBe([418 => 'im-a-teapot']);
});

it('produces exactly the committed class from the committed catalogue', function (): void {
    $reflection = new ReflectionClass(ApiGuide::class);
    $titles = $reflection->getConstant('TITLES');
    $entries = [];

    foreach ($reflection->getConstants() as $name => $uri) {
        if ($name === 'BASE' || ! is_string($uri)) {
            continue;
        }

        $slug = substr($uri, strlen(ApiGuide::BASE));
        $doc = (string) $reflection->getReflectionConstant($name)->getDocComment();

        $entries[$slug] = [
            'title' => $titles[$uri],
            'status' => (int) preg_replace('/\D/', '', strtok($doc, "\n")),
            'deprecated' => str_contains($doc, '@deprecated'),
        ];
    }

    expect(ApiGuideGenerator::render($entries))->toBe(file_get_contents(__DIR__.'/../../../src/Support/ApiGuide.php'));
});
