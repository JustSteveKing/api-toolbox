<?php

declare(strict_types=1);

/*
 * Regenerates src/Support/ApiGuide.php from the apiguide.dev error catalogue.
 *
 *     composer apiguide                       regenerate from the catalogue's main branch
 *     composer apiguide -- --check            exit 1 if the committed file is out of date
 *     composer apiguide -- --source=../apiguide.dev/src/content/errors
 *                                             read a local checkout instead of GitHub
 *     composer apiguide -- --no-verify        skip checking each URI resolves
 *
 * Set GITHUB_TOKEN to raise GitHub's rate limit for unauthenticated requests (60 an hour).
 */

use JustSteveKing\Toolbox\Tools\ApiGuideGenerator;

require __DIR__.'/../vendor/autoload.php';

const REPOSITORY = 'JustSteveKing/apiguide.dev';
const DIRECTORY = 'src/content/errors';
const TARGET = __DIR__.'/../src/Support/ApiGuide.php';

$options = getopt('', ['check', 'source:', 'no-verify']);
$check = array_key_exists('check', $options);
$source = is_string($options['source'] ?? null) ? $options['source'] : null;

try {
    $files = $source === null ? fromGitHub() : fromDirectory($source);

    $entries = [];

    foreach ($files as $slug => $markdown) {
        $entries[$slug] = ApiGuideGenerator::parseEntry($slug, $markdown);
    }

    if (! array_key_exists('no-verify', $options)) {
        verify(array_keys($entries));
    }

    $generated = ApiGuideGenerator::render($entries);
} catch (RuntimeException $e) {
    fwrite(STDERR, "error: {$e->getMessage()}\n");

    exit(1);
}

foreach (ApiGuideGenerator::unmappedCandidates($entries) as $status => $slug) {
    fwrite(STDERR, "note: {$status} now has one catalogue entry, [{$slug}], and no mapping. Add it to ApiGuideGenerator::STATUSES if it is the plain meaning of {$status}.\n");
}

$current = is_file(TARGET) ? (string) file_get_contents(TARGET) : '';

if ($check) {
    if ($current !== $generated) {
        fwrite(STDERR, "src/Support/ApiGuide.php is out of date with the catalogue. Run `composer apiguide`.\n");

        exit(1);
    }

    fwrite(STDOUT, 'src/Support/ApiGuide.php matches the catalogue ('.count($entries)." types).\n");

    exit(0);
}

file_put_contents(TARGET, $generated);

fwrite(STDOUT, ($current === $generated ? 'Unchanged' : 'Updated').' src/Support/ApiGuide.php ('.count($entries)." types).\n");

/**
 * @return array<string, string> Markdown keyed by slug.
 */
function fromGitHub(): array
{
    $listing = json_decode(fetch('https://api.github.com/repos/'.REPOSITORY.'/contents/'.DIRECTORY.'?ref=main'), true);

    if (! is_array($listing)) {
        throw new RuntimeException('GitHub did not return a directory listing.');
    }

    $files = [];

    foreach ($listing as $item) {
        if (! is_array($item) || ! is_string($item['name'] ?? null) || ! is_string($item['download_url'] ?? null)) {
            continue;
        }

        if (preg_match('/^(.+)\.mdx?$/', $item['name'], $match) === 1) {
            $files[$match[1]] = fetch($item['download_url']);
        }
    }

    if ($files === []) {
        throw new RuntimeException('The catalogue listing had no Markdown files.');
    }

    return $files;
}

/**
 * @return array<string, string> Markdown keyed by slug.
 */
function fromDirectory(string $directory): array
{
    $files = [];

    foreach (glob(rtrim($directory, '/').'/*.{md,mdx}', GLOB_BRACE) ?: [] as $path) {
        $files[preg_replace('/\.mdx?$/', '', basename($path))] = (string) file_get_contents($path);
    }

    if ($files === []) {
        throw new RuntimeException("No Markdown files in [{$directory}].");
    }

    return $files;
}

/**
 * Every type URI has to resolve, since section 3.1.1 of RFC 9457 says dereferencing it should
 * give documentation. A page in the repository that is not yet deployed fails here.
 *
 * @param  list<string>  $slugs
 */
function verify(array $slugs): void
{
    $missing = [];

    foreach ($slugs as $slug) {
        $headers = @get_headers(ApiGuideGenerator::BASE.$slug, context: stream_context_create([
            'http' => ['method' => 'HEAD', 'header' => 'User-Agent: api-toolbox-generator', 'follow_location' => 0],
        ]));

        if ($headers === false || ! str_contains($headers[0], ' 200')) {
            $missing[] = $slug;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException('These catalogue entries do not resolve on apiguide.dev yet: '.implode(', ', $missing).'. Deploy the site, or pass --no-verify.');
    }
}

function fetch(string $url): string
{
    $headers = ['User-Agent: api-toolbox-generator', 'Accept: application/vnd.github+json'];
    $token = getenv('GITHUB_TOKEN');

    if (is_string($token) && $token !== '') {
        $headers[] = "Authorization: Bearer {$token}";
    }

    $body = @file_get_contents($url, context: stream_context_create(['http' => ['header' => implode("\r\n", $headers)]]));

    if ($body === false) {
        throw new RuntimeException("Could not fetch [{$url}].");
    }

    return $body;
}
