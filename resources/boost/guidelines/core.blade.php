## API Toolbox (juststeveking/api-toolbox)

This application uses API Toolbox: HTTP standards for Laravel APIs, one RFC per feature. Errors follow RFC 9457 Problem Details, and there is middleware for ETags and preconditions, idempotency keys, `Prefer`, JSON Merge Patch, digests, HTTP Message Signatures and more. Activate the `api-toolbox-development` skill before using any of these features in detail.

### Check what is enabled first

- Features are switched on in `config/api-toolbox.php` with `Features::problemDetails()`, `Features::idempotency()` and so on. If the config has not been published, every stable feature is on with its defaults; RateLimit headers are experimental and off unless listed.
- A disabled feature registers nothing: no middleware alias, route, binding or test assertion. Before using a middleware alias, an assertion or a class, confirm its feature is enabled.

### Errors are problems, never hand-built JSON

- Do not return `response()->json(['message' => ...], 4xx)` from an API. Throw an exception; the package renders it as `application/problem+json`. `abort()`, validation failures and authentication failures are already rendered correctly.
- For an error your API defines, make a typed problem class with Artisan rather than writing one by hand. The command needs a title and a status, and prompts for them if they are missing, so pass them under `--no-interaction`:

@verbatim
<code-snippet name="Create and throw a problem type" lang="php">
// php artisan make:api-problem OutOfCredit --title="You do not have enough credit." --status=403 --no-interaction
// php artisan make:api-problem AccountFrozen --apiguide=account-frozen --no-interaction

throw new OutOfCredit(
    detail: 'Your balance is 30, but that costs 50.',
    extensions: ['balance' => 30],
);
</code-snippet>
@endverbatim

- The title belongs on the class and must not change per occurrence. Put occurrence details in `detail` and `extensions`. An extension key named `type`, `title`, `status`, `detail` or `instance` throws.
- Prefer a type from the apiguide.dev catalogue when one fits: `JustSteveKing\Toolbox\Support\ApiGuide` has a constant for each entry.

### Use the standard, not a custom header or parameter

- Optimistic locking: use `conditional` and `preconditions.required` middleware with `HasValidators` on the model, not a `version` field in the body.
- Safe retries of POST: use `idempotent` middleware, not a custom request-dedupe table.
- `?return=none` or `?async=1`: use the `Prefer` header (`prefer` middleware, the `Preferences` class).
- Partial updates: use JSON Merge Patch (`merge-patch` middleware, the `MergePatch` class), and validate the merged document, not the patch.
- Webhook signing: use `Http::withSignature('key-id')` to send and the `signature.verify` middleware to receive, not a hand-rolled HMAC header.
- Pagination: return `CursorPage::of($paginator, Resource::class)`. Navigation goes in `Link` headers and the body is the list of items.

### Testing

- Use the package's assertions rather than checking headers by hand. They validate exact header syntax: `assertProblem()`, `assertProblemType()`, `assertETag()`, `assertPreconditionFailed()`, `assertIdempotentReplay()`, `assertLinkHeader()` and others. Each has a Pest expectation too, such as `toBeProblem()`.
- They exist only under `APP_ENV=testing` and only for enabled features.
