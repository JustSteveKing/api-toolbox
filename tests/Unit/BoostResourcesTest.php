<?php

declare(strict_types=1);

use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\Features;
use JustSteveKing\Toolbox\MergePatch\MergePatch;
use JustSteveKing\Toolbox\Preferences\Preferences;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Pest\Expectation;
use PhpParser\ParserFactory;

/*
 * The Laravel Boost guideline and skill teach agents in applications using this package. Every
 * name they mention has to exist, or an agent writes code that cannot run.
 */

/**
 * @return array<string, string>
 */
function boostResources(): array
{
    $root = dirname(__DIR__, 2).'/resources/boost';
    $files = [$root.'/guidelines/core.blade.php', ...glob($root.'/skills/*/SKILL.md'), ...glob($root.'/skills/*/references/*.md')];

    return array_combine(
        array_map(static fn (string $file): string => substr($file, strlen($root) + 1), $files),
        array_map(static fn (string $file): string => (string) file_get_contents($file), $files),
    );
}

function boostText(): string
{
    return implode("\n", boostResources());
}

/**
 * @return list<string>
 */
function boostPhpBlocks(): array
{
    preg_match_all('/```php\n(.*?)```|<code-snippet[^>]*lang="php">\n(.*?)<\/code-snippet>/s', boostText(), $matches, PREG_SET_ORDER);

    return array_map(static fn (array $match): string => $match[1] !== '' ? $match[1] : $match[2], $matches);
}

/**
 * @return list<string>
 */
function boostMatches(string $pattern): array
{
    preg_match_all($pattern, boostText(), $matches);

    return array_values(array_unique($matches[1]));
}

it('ships a guideline and a skill with the frontmatter Boost requires', function (): void {
    $resources = boostResources();

    expect($resources)->toHaveKeys(['guidelines/core.blade.php', 'skills/api-toolbox-development/SKILL.md'])
        ->and($resources['skills/api-toolbox-development/SKILL.md'])
        ->toMatch('/\A---\nname: api-toolbox-development\ndescription: .+\n---\n/');
});

it('renders the guideline with Blade', function (): void {
    $rendered = Blade::render(boostResources()['guidelines/core.blade.php']);

    expect($rendered)->toContain('## API Toolbox')
        ->toContain('<code-snippet name="Create and throw a problem type" lang="php">')
        ->not->toContain('@verbatim');
});

it('links only to reference files that exist', function (): void {
    $skill = dirname(__DIR__, 2).'/resources/boost/skills/api-toolbox-development';

    foreach (boostMatches('/\]\((references\/[^)]+)\)/') as $link) {
        expect(file_exists($skill.'/'.$link))->toBeTrue("{$link} exists");
    }
});

it('names only package classes, interfaces and traits that exist', function (): void {
    $names = boostMatches('/\\\\?(JustSteveKing\\\\Toolbox\\\\[A-Za-z\\\\]+[A-Za-z])/');

    expect($names)->not->toBeEmpty();

    foreach ($names as $name) {
        $namespace = is_dir(dirname(__DIR__, 2).'/src/'.str_replace('\\', '/', substr($name, strlen('JustSteveKing\\Toolbox\\'))));

        expect(class_exists($name) || interface_exists($name) || trait_exists($name) || $namespace)->toBeTrue("{$name} exists");
    }
});

it('uses only ApiGuide constants that exist', function (): void {
    foreach (boostMatches('/ApiGuide::([A-Z_]+)\b/') as $constant) {
        expect(defined(ApiGuide::class.'::'.$constant))->toBeTrue("ApiGuide::{$constant} exists");
    }
});

it('uses only Features methods that exist', function (): void {
    foreach (boostMatches('/Features::([a-zA-Z]+)\(/') as $method) {
        expect(method_exists(Features::class, $method))->toBeTrue("Features::{$method}() exists");
    }
});

it('uses only middleware aliases the package registers, or Laravel\'s own', function (): void {
    $aliases = [...array_keys(app(Router::class)->getMiddleware()), ...array_keys((new Middleware)->getMiddlewareAliases())];
    $groups = array_keys(app(Router::class)->getMiddlewareGroups());

    $used = [];

    foreach (boostPhpBlocks() as $code) {
        preg_match_all('/->middleware\((\[[^\]]*\]|\'[^\']*\')\)/', $code, $calls);

        foreach ($calls[1] as $argument) {
            preg_match_all("/'([^']+)'/", $argument, $names);
            $used = [...$used, ...$names[1]];
        }
    }

    // And every alias in the skill's middleware table.
    preg_match_all('/^\| `([a-z.\-]+)` \|/m', boostResources()['skills/api-toolbox-development/SKILL.md'], $table);

    foreach (array_unique([...$used, ...$table[1]]) as $middleware) {
        $alias = explode(':', $middleware)[0];

        expect(in_array($alias, $aliases, true) || in_array($alias, $groups, true))->toBeTrue("middleware {$alias} is registered");
    }
});

it('uses only response assertions and Pest expectations that exist', function (): void {
    foreach (boostMatches('/->(assert[A-Za-z]+)\(/') as $assertion) {
        expect(TestResponse::hasMacro($assertion) || method_exists(TestResponse::class, $assertion))->toBeTrue("{$assertion}() exists");
    }

    foreach (boostMatches('/`(to(?:Be|Have)[A-Za-z]+)`/') as $expectation) {
        expect(Expectation::hasExtend($expectation) || method_exists(Expectation::class, $expectation))->toBeTrue("{$expectation} exists");
    }
});

it('calls only methods that exist on the classes it injects', function (): void {
    $variables = [
        'preferences' => Preferences::class,
        'patch' => MergePatch::class,
        'operations' => Operations::class,
        'operation' => CurrentOperation::class,
    ];

    foreach ($variables as $variable => $class) {
        preg_match_all('/\$'.$variable.'->([a-zA-Z]+)\(/', boostText(), $calls);

        foreach (array_unique($calls[1]) as $method) {
            expect(method_exists($class, $method))->toBeTrue("{$class}::{$method}() exists");
        }
    }
});

it('has PHP snippets that parse', function (): void {
    $parser = (new ParserFactory)->createForNewestSupportedVersion();

    // A snippet is a file body, statements, class members, array items, or calls chained on the
    // application builder in bootstrap/app.php.
    $wrappers = [
        static fn (string $code): string => "<?php\n{$code}",
        static fn (string $code): string => "<?php\nclass Snippet {\n{$code}\n}",
        static fn (string $code): string => "<?php\n\$snippet = [\n{$code}\n];",
        static fn (string $code): string => "<?php\n\$app{$code};",
    ];

    $blocks = boostPhpBlocks();

    expect(count($blocks))->toBeGreaterThan(20);

    foreach ($blocks as $code) {
        // Uses must come first, so lift them out before wrapping.
        preg_match_all('/^use [^;]+;\n/m', $code, $uses);
        $body = preg_replace('/^use [^;]+;\n/m', '', $code);

        $parses = false;

        foreach ($wrappers as $wrap) {
            try {
                $parser->parse(str_replace('<?php', '<?php '.implode('', $uses[0]), $wrap((string) $body)));
                $parses = true;

                break;
            } catch (PhpParser\Error) {
            }
        }

        expect($parses)->toBeTrue("This snippet parses:\n{$code}");
    }
});
