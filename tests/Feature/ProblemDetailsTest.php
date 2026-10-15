<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver;
use JustSteveKing\Toolbox\ProblemDetails\ProblemDetailsServiceProvider;
use JustSteveKing\Toolbox\Tests\Fixtures\OutOfCredit;

it('registers its provider when enabled', function (): void {
    expect(app()->getProviders(ProblemDetailsServiceProvider::class))->toHaveCount(1);
});

it('renders a typed problem exactly as RFC 9457 section 3 shows it', function (): void {
    Route::post('api/purchase', fn () => throw new OutOfCredit(
        detail: 'Your current balance is 30, but that costs 50.',
        extensions: ['balance' => 30, 'accounts' => ['/account/12345', '/account/67890']],
        instance: '/account/12345/msgs/abc',
    ));

    $response = $this->postJson('api/purchase');

    $response->assertStatus(403)->assertHeader('Content-Type', 'application/problem+json');

    expect($response->getContent())->toBe(
        '{"type":"/problems/out-of-credit","title":"You do not have enough credit.","status":403,'
        .'"detail":"Your current balance is 30, but that costs 50.","instance":"/account/12345/msgs/abc",'
        .'"balance":30,"accounts":["/account/12345","/account/67890"]}'
    );
});

it('maps validation errors to the apiguide.dev validation-failed type and its errors shape', function (): void {
    Route::post('api/leads', function (Request $request): void {
        Validator::make($request->all(), [
            'end_date' => ['required', 'date', 'after:start_date'],
            'start_date' => ['required', 'date'],
        ])->validate();
    });

    $this->postJson('api/leads', ['start_date' => '2026-10-15', 'end_date' => '2026-10-01'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson([
            'type' => 'https://apiguide.dev/errors/validation-failed',
            'title' => 'Validation Failed',
            'status' => 422,
            'detail' => 'The end date field must be a date after start date.',
            'errors' => [
                'end_date' => ['The end date field must be a date after start date.'],
            ],
        ]);
});

it('types plain HTTP errors with their apiguide.dev catalogue entry', function (string $uri, Closure $throw, int $status, string $type, string $title): void {
    Route::get($uri, $throw);

    $this->getJson($uri)
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', $type)
        ->assertJsonPath('title', $title)
        ->assertJsonPath('status', $status);
})->with([
    'abort' => ['api/gone', fn () => abort(410), 410, 'https://apiguide.dev/errors/gone', 'Gone'],
    'model not found' => ['api/post', fn () => throw new ModelNotFoundException, 404, 'https://apiguide.dev/errors/resource-not-found', 'Resource Not Found'],
    'authorization' => ['api/admin', fn () => throw new AuthorizationException, 403, 'https://apiguide.dev/errors/forbidden', 'Forbidden'],
    'authentication' => ['api/me', fn () => throw new AuthenticationException, 401, 'https://apiguide.dev/errors/unauthorized', 'Unauthorized'],
    'precondition failed' => ['api/doc', fn () => abort(412), 412, 'https://apiguide.dev/errors/precondition-failed', 'Precondition Failed'],
    'precondition required' => ['api/doc2', fn () => abort(428), 428, 'https://apiguide.dev/errors/precondition-required', 'Precondition Required'],
]);

it('falls back to about:blank where the catalogue has no single entry for the status', function (int $status, string $title): void {
    Route::get('api/vague', fn () => abort($status));

    $this->getJson('api/vague')
        ->assertStatus($status)
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('title', $title);
})->with([
    [400, 'Bad Request'],
    [402, 'Payment Required'],
    [422, 'Unprocessable Content'],
]);

it('uses the type a feature exception names through HasProblemType', function (): void {
    Route::get('api/cursor', fn () => throw new class extends Symfony\Component\HttpKernel\Exception\BadRequestHttpException implements JustSteveKing\Toolbox\Contracts\HasProblemType
    {
        public function __construct()
        {
            parent::__construct('The cursor parameter could not be decoded.');
        }

        public function problemType(): string
        {
            return JustSteveKing\Toolbox\Support\ApiGuide::INVALID_PAGINATION_CURSOR;
        }

        public function problemTitle(): string
        {
            return 'Invalid Pagination Cursor';
        }
    });

    $this->getJson('api/cursor')->assertStatus(400)->assertExactJson([
        'type' => 'https://apiguide.dev/errors/invalid-pagination-cursor',
        'title' => 'Invalid Pagination Cursor',
        'status' => 400,
        'detail' => 'The cursor parameter could not be decoded.',
    ]);
});

it('renders unknown routes under the configured paths', function (): void {
    $this->get('api/nothing-here')
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'https://apiguide.dev/errors/resource-not-found');
});

it('keeps the headers an HTTP exception carries', function (): void {
    Route::get('api/limited', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 30]));

    $this->getJson('api/limited')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '30')
        ->assertJsonPath('type', 'https://apiguide.dev/errors/rate-limit-exceeded');
});

it('hides the message and trace of an unexpected exception outside debug mode', function (): void {
    config(['app.debug' => false]);
    Route::get('api/boom', fn () => throw new RuntimeException('database password is hunter2'));

    $response = $this->getJson('api/boom')->assertStatus(500);

    $response->assertExactJson([
        'type' => 'https://apiguide.dev/errors/internal-server-error',
        'title' => 'Internal Server Error',
        'status' => 500,
    ]);
});

it('includes the message and trace of an unexpected exception in debug mode', function (): void {
    config(['app.debug' => true]);
    Route::get('api/boom', fn () => throw new RuntimeException('it broke'));

    $this->getJson('api/boom')
        ->assertStatus(500)
        ->assertJsonPath('detail', 'it broke')
        ->assertJsonPath('exception', RuntimeException::class)
        ->assertJsonStructure(['file', 'line', 'trace']);
});

it('leaves an HttpResponseException response alone', function (): void {
    Route::get('api/custom', fn () => throw new HttpResponseException(response('teapot', 418)));

    $this->getJson('api/custom')->assertStatus(418)->assertContent('teapot');
});

it('leaves HTML requests outside the configured paths to Laravel', function (): void {
    Route::get('web/credit', fn () => throw new OutOfCredit);

    $response = $this->get('web/credit');

    expect($response->headers->get('Content-Type'))->not->toContain('problem+json');
});

it('renders for any path when the client asks for JSON', function (): void {
    Route::get('web/credit', fn () => throw new OutOfCredit);

    $this->getJson('web/credit')
        ->assertStatus(403)
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('leaves JSON:API requests to their own error format', function (): void {
    Route::get('api/credit', fn () => throw new OutOfCredit);

    $response = $this->get('api/credit', ['Accept' => 'application/vnd.api+json']);

    expect($response->headers->get('Content-Type'))->not->toContain('problem+json');
});

it('lets the type resolver be replaced, which is the seam for an external catalogue', function (): void {
    app()->instance(TypeResolver::class, new class implements TypeResolver
    {
        public function resolve(string $type): string
        {
            return "https://catalogue.example.com/{$type}";
        }
    });

    Route::get('api/credit', fn () => throw new OutOfCredit);

    $this->getJson('api/credit')->assertJsonPath('type', 'https://catalogue.example.com/out-of-credit');
});

it('sends a WWW-Authenticate challenge with a 401, which RFC 9110 section 15.5.2 requires', function (): void {
    Route::get('api/me', fn () => throw new AuthenticationException);

    $this->getJson('api/me')->assertStatus(401)->assertHeader('WWW-Authenticate', 'Bearer');
});

it('keeps a challenge the exception already carries', function (): void {
    Route::get('api/basic', fn () => throw new Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException('Basic realm="admin"'));

    $this->getJson('api/basic')->assertStatus(401)->assertHeader('WWW-Authenticate', 'Basic realm="admin"');
});

it('sends no challenge with other statuses', function (): void {
    Route::get('api/admin', fn () => throw new AuthorizationException);

    $this->getJson('api/admin')->assertStatus(403)->assertHeaderMissing('WWW-Authenticate');
});
