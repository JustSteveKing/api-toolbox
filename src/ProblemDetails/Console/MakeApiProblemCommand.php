<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ProblemDetails\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

/**
 * Creates a problem type class.
 *
 * RFC 9457 section 4 says a new problem type MUST document a type URI, a title and the status
 * code it is used with, so the command will not write a class without all three:
 *
 *     php artisan make:api-problem OutOfCredit --title="You do not have enough credit." --status=403
 *     php artisan make:api-problem AccountFrozen --apiguide=account-frozen
 */
#[AsCommand(name: 'make:api-problem')]
final class MakeApiProblemCommand extends GeneratorCommand
{
    public const string STUB = 'api-problem.stub';

    /**
     * @var string
     */
    protected $name = 'make:api-problem';

    /**
     * @var string
     */
    protected $description = 'Create a new RFC 9457 problem type';

    /**
     * @var string
     */
    protected $type = 'Problem';

    /**
     * Checks the type, title and status before anything is written, so a mistake is an error
     * message rather than a stack trace or a half-made file.
     *
     * @return bool|int|null
     */
    public function handle()
    {
        try {
            $this->problemType($this->qualifyClass($this->getNameInput()));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return parent::handle();
    }

    /**
     * A published copy in the application's stubs directory wins, as with Laravel's own stubs.
     */
    protected function getStub(): string
    {
        $published = $this->laravel->basePath('stubs/'.self::STUB);

        return is_file($published) ? $published : __DIR__.'/../../../stubs/make/'.self::STUB;
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Problems';
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        [$type, $title, $status, $import] = $this->problemType($name);

        return str_replace(
            ['{{ type }}', '{{ title }}', '{{ status }}', "{{ imports }}\n"],
            [$type, var_export($title, true), (string) $status, $import === null ? "\n" : "use {$import};\n\n"],
            parent::buildClass($name),
        );
    }

    /**
     * The three things section 4 requires, as PHP source, with any import the type needs.
     *
     * @return array{0: string, 1: string, 2: int, 3: string|null}
     */
    private function problemType(string $class): array
    {
        $entry = $this->stringOption('apiguide');

        if ($entry !== null) {
            if ($this->stringOption('type') !== null || $this->stringOption('title') !== null || $this->stringOption('status') !== null) {
                throw new InvalidArgumentException('--apiguide sets the type, title and status; do not pass --type, --title or --status with it.');
            }

            $uri = ApiGuide::BASE.$entry;
            $title = ApiGuide::title($uri);
            $status = ApiGuide::status($uri);

            if ($title === null || $status === null) {
                throw new InvalidArgumentException(sprintf(
                    'apiguide.dev has no [%s] error. Known errors: %s.',
                    $entry,
                    implode(', ', array_map(static fn (string $type): string => substr($type, strlen(ApiGuide::BASE)), ApiGuide::types())),
                ));
            }

            return ['ApiGuide::'.strtoupper(str_replace('-', '_', $entry)), $title, $status, ApiGuide::class];
        }

        $type = $this->stringOption('type') ?? Str::kebab(class_basename($class));

        if (preg_match('/\s/', $type) === 1) {
            throw new InvalidArgumentException("The type [{$type}] cannot contain whitespace: it is a URI reference (RFC 9457, section 3.1.1).");
        }

        $title = $this->stringOption('title') ?? throw new InvalidArgumentException('A problem type needs a title (RFC 9457, section 4). Pass --title.');
        $status = $this->stringOption('status') ?? throw new InvalidArgumentException('A problem type needs a status (RFC 9457, section 4). Pass --status.');

        return [var_export($type, true), $title, self::status($status), null];
    }

    /**
     * Prompts for what section 4 requires and was not passed, after Laravel prompts for the name.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($this->stringOption('apiguide') !== null) {
            return;
        }

        if ($this->stringOption('title') === null) {
            $input->setOption('title', text(
                label: 'What is the problem\'s title?',
                placeholder: 'E.g. You do not have enough credit.',
                required: 'A problem type needs a title (RFC 9457, section 4).',
                hint: 'A short summary of the problem type. It should not change between occurrences.',
            ));
        }

        if ($this->stringOption('status') === null) {
            $input->setOption('status', text(
                label: 'Which status code is it used with?',
                placeholder: 'E.g. 403',
                required: true,
                validate: static fn (string $value): ?string => preg_match('/^[45]\d\d$/', $value) === 1 ? null : 'A problem needs a 4xx or 5xx status.',
            ));
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['type', null, InputOption::VALUE_REQUIRED, 'The type URI, or a slug resolved against the configured base (defaults to the class name in kebab case)'],
            ['title', null, InputOption::VALUE_REQUIRED, 'A short, human-readable summary of the problem type'],
            ['status', null, InputOption::VALUE_REQUIRED, 'The HTTP status code the problem is used with'],
            ['apiguide', null, InputOption::VALUE_REQUIRED, 'Use an apiguide.dev catalogue entry, such as account-frozen, for the type, title and status'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => ['What should the problem be named?', 'E.g. OutOfCredit'],
        ];
    }

    private static function status(string $value): int
    {
        if (preg_match('/^[45]\d\d$/', $value) !== 1) {
            throw new InvalidArgumentException("[{$value}] is not a 4xx or 5xx status. A problem describes an error.");
        }

        return (int) $value;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
