<?php

declare(strict_types=1);

use Illuminate\Foundation\Events\PublishingStubs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\ProblemDetails\Console\MakeApiProblemCommand;

beforeEach(function (): void {
    File::deleteDirectory(app_path('Problems'));
    File::delete(base_path('stubs/'.MakeApiProblemCommand::STUB));
});

afterEach(function (): void {
    File::deleteDirectory(app_path('Problems'));
    File::delete(base_path('stubs/'.MakeApiProblemCommand::STUB));
});

it('writes a problem type with the three things RFC 9457 section 4 requires', function (): void {
    $this->artisan('make:api-problem', ['name' => 'OutOfCredit', '--title' => 'You do not have enough credit.', '--status' => '403'])
        ->assertSuccessful();

    expect(File::get(app_path('Problems/OutOfCredit.php')))->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Problems;

        use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
        use JustSteveKing\Toolbox\ProblemDetails\ProblemException;

        #[ProblemType('out-of-credit', title: 'You do not have enough credit.', status: 403)]
        final class OutOfCredit extends ProblemException {}

        PHP);
});

it('takes the type, title and status from an apiguide.dev entry', function (): void {
    $this->artisan('make:api-problem', ['name' => 'AccountFrozen', '--apiguide' => 'account-frozen'])->assertSuccessful();

    expect(File::get(app_path('Problems/AccountFrozen.php')))->toBe(<<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Problems;

        use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
        use JustSteveKing\Toolbox\ProblemDetails\ProblemException;
        use JustSteveKing\Toolbox\Support\ApiGuide;

        #[ProblemType(ApiGuide::ACCOUNT_FROZEN, title: 'Account Frozen', status: 403)]
        final class AccountFrozen extends ProblemException {}

        PHP);
});

it('takes a type URI or slug when given one, and escapes the title', function (): void {
    $this->artisan('make:api-problem', [
        'name' => 'Billing/CardDeclined',
        '--type' => 'https://docs.example.com/problems/card-declined',
        '--title' => "The card's issuer declined it.",
        '--status' => '402',
    ])->assertSuccessful();

    expect(File::get(app_path('Problems/Billing/CardDeclined.php')))
        ->toContain('namespace App\Problems\Billing;')
        ->toContain("#[ProblemType('https://docs.example.com/problems/card-declined', title: 'The card\\'s issuer declined it.', status: 402)]");
});

it('writes a class that renders as the problem it describes', function (): void {
    $this->artisan('make:api-problem', ['name' => 'QuotaGone', '--title' => 'Your quota is gone.', '--status' => '429'])->assertSuccessful();

    require app_path('Problems/QuotaGone.php');

    Route::get('api/quota', fn () => throw new App\Problems\QuotaGone(detail: 'Come back tomorrow.'));

    $this->getJson('api/quota')
        ->assertJsonPath('type', '/problems/quota-gone')
        ->assertJsonPath('title', 'Your quota is gone.')
        ->assertJsonPath('status', 429)
        ->assertJsonPath('detail', 'Come back tomorrow.');
});

it('prompts for a missing title and status', function (): void {
    $this->artisan('make:api-problem', ['name' => 'Prompted'])
        ->expectsQuestion("What is the problem's title?", 'Prompted for this.')
        ->expectsQuestion('Which status code is it used with?', '409')
        ->assertSuccessful();

    expect(File::get(app_path('Problems/Prompted.php')))->toContain("#[ProblemType('prompted', title: 'Prompted for this.', status: 409)]");
});

it('refuses, writing nothing', function (array $arguments, string $message): void {
    $this->artisan('make:api-problem', ['name' => 'Refused', '--no-interaction' => true, ...$arguments])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(File::exists(app_path('Problems/Refused.php')))->toBeFalse();
})->with([
    'no title' => [['--status' => '403'], 'A problem type needs a title'],
    'no status' => [['--title' => 'Refused.'], 'A problem type needs a status'],
    'a success status' => [['--title' => 'Refused.', '--status' => '200'], 'is not a 4xx or 5xx status'],
    'a redirect' => [['--title' => 'Refused.', '--status' => '302'], 'is not a 4xx or 5xx status'],
    'whitespace in the type' => [['--title' => 'Refused.', '--status' => '400', '--type' => 'two words'], 'cannot contain whitespace'],
    'an unknown apiguide entry' => [['--apiguide' => 'no-such-error'], 'apiguide.dev has no [no-such-error] error. Known errors: account-frozen'],
    'apiguide with a title' => [['--apiguide' => 'gone', '--title' => 'Gone.'], '--apiguide sets the type, title and status'],
]);

it('will not overwrite a class without --force', function (): void {
    $arguments = ['name' => 'Twice', '--title' => 'First.', '--status' => '400'];

    $this->artisan('make:api-problem', $arguments)->assertSuccessful();
    $this->artisan('make:api-problem', [...$arguments, '--title' => 'Second.'])->expectsOutputToContain('already exists');

    expect(File::get(app_path('Problems/Twice.php')))->toContain("'First.'");

    $this->artisan('make:api-problem', [...$arguments, '--title' => 'Second.', '--force' => true])->assertSuccessful();

    expect(File::get(app_path('Problems/Twice.php')))->toContain("'Second.'");
});

it('uses a published stub in place of its own', function (): void {
    File::ensureDirectoryExists(base_path('stubs'));
    File::put(base_path('stubs/'.MakeApiProblemCommand::STUB), str_replace(
        '#[ProblemType',
        "/**\n * Owned by the payments team.\n */\n#[ProblemType",
        File::get(__DIR__.'/../../stubs/make/'.MakeApiProblemCommand::STUB),
    ));

    $this->artisan('make:api-problem', ['name' => 'Custom', '--title' => 'Custom.', '--status' => '400'])->assertSuccessful();

    expect(File::get(app_path('Problems/Custom.php')))->toContain('Owned by the payments team.');
});

it('offers its stub to stub:publish', function (): void {
    $publishing = new PublishingStubs([]);

    app('events')->dispatch($publishing);

    expect($publishing->stubs)->toContain(MakeApiProblemCommand::STUB);
});

it('is listed by artisan', function (): void {
    expect(Artisan::all())->toHaveKey('make:api-problem');
});
