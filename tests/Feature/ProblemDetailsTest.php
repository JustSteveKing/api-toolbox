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

it('maps validation errors to the errors extension with JSON pointers', function (): void {
    Route::post('api/details', function (Request $request): void {
        Validator::make($request->all(), [
            'age' => ['required', 'integer'],
            'profile.color' => ['required', 'in:green,red,blue'],
        ])->validate();
    });

    $this->postJson('api/details', ['age' => 42.3, 'profile' => ['color' => 'yellow']])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson([
            'type' => '/problems/validation-error',
            'title' => 'Your request is not valid.',
            'status' => 422,
            'detail' => 'The age field must be an integer. (and 1 more error)',
            'errors' => [
                ['detail' => 'The age field must be an integer.', 'pointer' => '#/age'],
                ['detail' => 'The selected profile.color is invalid.', 'pointer' => '#/profile/color'],
            ],
        ]);
});

it('uses about:blank and the status phrase for plain HTTP errors', function (string $uri, Closure $throw, int $status, string $title): void {
    Route::get($uri, $throw);

    $this->getJson($uri)
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('title', $title)
        ->assertJsonPath('status', $status);
})->with([
    'abort' => ['api/gone', fn () => abort(410), 410, 'Gone'],
    'model not found' => ['api/post', fn () => throw new ModelNotFoundException, 404, 'Not Found'],
    'authorization' => ['api/admin', fn () => throw new AuthorizationException, 403, 'Forbidden'],
    'authentication' => ['api/me', fn () => throw new AuthenticationException, 401, 'Unauthorized'],
]);

it('renders unknown routes under the configured paths', function (): void {
    $this->get('api/nothing-here')
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Not Found');
});

it('keeps the headers an HTTP exception carries', function (): void {
    Route::get('api/limited', fn () => throw new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => 30]));

    $this->getJson('api/limited')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '30')
        ->assertJsonPath('title', 'Too Many Requests');
});

it('hides the message and trace of an unexpected exception outside debug mode', function (): void {
    config(['app.debug' => false]);
    Route::get('api/boom', fn () => throw new RuntimeException('database password is hunter2'));

    $response = $this->getJson('api/boom')->assertStatus(500);

    $response->assertExactJson(['type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500]);
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
