# API Toolbox

Standards-compliant APIs in Laravel, one RFC at a time.

Each feature in this package implements a published HTTP standard, and each one is switched on
or off by itself. A feature you leave off registers nothing: no bindings, no middleware aliases,
no macros, no exception handling hooks.

Requires PHP 8.3 and Laravel 13.30 or later.

| Feature | Standard | Status |
|---|---|---|
| [Problem Details](#problem-details) | [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) | Stable |
| [Deprecation and Sunset](#deprecation-and-sunset) | [RFC 9745](https://www.rfc-editor.org/rfc/rfc9745), [RFC 8594](https://www.rfc-editor.org/rfc/rfc8594) | Stable |
| [Conditional Requests](#conditional-requests) | [RFC 9110 §13](https://www.rfc-editor.org/rfc/rfc9110#section-13), [RFC 6585 §3](https://www.rfc-editor.org/rfc/rfc6585#section-3) | Stable |
| [Pagination](#pagination) | [RFC 8288](https://www.rfc-editor.org/rfc/rfc8288) | Stable |
| [Idempotency](#idempotency) | [draft-ietf-httpapi-idempotency-key-header-07](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/) | Stable; the draft has expired |
| [RateLimit headers](#ratelimit-headers-experimental) | [draft-ietf-httpapi-ratelimit-headers-11](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/) | Experimental |
| [Negotiation](#negotiation) | [RFC 9110 §12](https://www.rfc-editor.org/rfc/rfc9110#section-12), [W3C Trace Context](https://www.w3.org/TR/trace-context/); request IDs have no standard | Stable |
| [Testing](#testing) | Assertions for each of the above | Stable |

One part of the package has no standard behind it: request IDs, in Negotiation. No
specification defines a request ID header. They are included because a request ID is how a
support ticket, a log line and a response get tied together, and the
[Negotiation](#request-ids) section says exactly what they do.

Every problem the package raises itself is typed from the
[apiguide.dev error catalogue](https://apiguide.dev/errors), so each `type` resolves to a page
explaining the error and how to recover.

## Install

```bash
composer require juststeveking/api-toolbox
php artisan vendor:publish --tag=api-toolbox-config
```

Features are listed in `config/api-toolbox.php`. Options are passed to each one inline:

```php
use JustSteveKing\Toolbox\Features;

'features' => [
    Features::problemDetails(['type_base_uri' => 'https://docs.example.com/problems']),
    Features::deprecation(),
    Features::conditionalRequests(),
    Features::pagination(),
    Features::idempotency(['ttl' => 86400]),
    Features::negotiation(),
    // Features::rateLimitHeaders(),
],
```

If you never publish the config, every stable feature is on with its defaults. A feature name
the package does not recognise throws when the application boots, so a typo cannot silently
switch something off. Every option is listed in the [configuration reference](#configuration-reference).

To check a feature at runtime:

```php
use JustSteveKing\Toolbox\Facades\ApiToolbox;
use JustSteveKing\Toolbox\Features;

ApiToolbox::enabled(Features::idempotency());
```

Or inject `JustSteveKing\Toolbox\Contracts\EnabledFeatures`.

The config follows Laravel Fortify's `Features` design, and works with `php artisan config:cache`
for the same reason Fortify's does.

### Stability

A feature built on an IETF draft that is still moving is marked experimental, here and in the
docblock of its `Features::` method. **Experimental features can change in a breaking way in a
minor release**, because the draft underneath them can. Pin a minor version if you rely on one.
Every other feature follows semantic versioning.

Idempotency is built on a draft too, but one that expired without changing shape, and the
header is in wide use. It is treated as stable.

## Problem Details

[RFC 9457](https://www.rfc-editor.org/rfc/rfc9457)

Laravel returns errors as `{"message": "..."}`, with a different shape again for validation.
A client has no stable way to tell one error from another, other than matching message
strings. RFC 9457 gives every error a type URI that identifies it, a fixed set of members, and
a media type, `application/problem+json`.

```php
Features::problemDetails([
    'type_base_uri' => 'https://docs.example.com/problems', // default: /problems
    'paths' => ['api/*'],                                   // default: ['api/*']
    'apiguide' => true,                                     // default: true
    'www_authenticate' => 'Bearer',                         // default: Bearer
]),
```

### apiguide.dev

Every problem this package produces itself has a type from the
[apiguide.dev error catalogue](https://apiguide.dev/errors), wherever the catalogue has an entry
for it. Each type URI resolves to a page explaining when the error happens and how a client
recovers, which is what section 3.1.1 asks a type URI to dereference to.

```json
{
  "type": "https://apiguide.dev/errors/precondition-failed",
  "title": "Precondition Failed",
  "status": 412,
  "detail": "The resource has changed since you last fetched it. Fetch it again and retry with its current ETag."
}
```

Your own problem types can use the catalogue too, through `JustSteveKing\Toolbox\Support\ApiGuide`:

```php
#[ProblemType(ApiGuide::ACCOUNT_FROZEN, title: 'Account Frozen', status: 403)]
final class AccountFrozen extends ProblemException {}
```

`ApiGuide` is generated from the catalogue, so a new catalogue entry reaches this package in a
release. Maintainers regenerate it with `composer apiguide`, which reads the catalogue's `main`
branch, checks every type URI resolves, and reports any status that has gained a single entry for
someone to decide whether to map. A weekly workflow runs `composer apiguide -- --check` and fails
when the committed copy has fallen behind.

Set `'apiguide' => false` to use `about:blank` with the status phrase for plain HTTP errors
instead (section 4.2.1), and to resolve the validation type against `type_base_uri`. Types a
feature names for its own errors, such as `invalid-pagination-cursor`, stay on apiguide.dev
either way, because they say more than the status does.

### Typed problems

Describe each problem type once, on its class:

```php
use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use JustSteveKing\Toolbox\ProblemDetails\ProblemException;

#[ProblemType('out-of-credit', title: 'You do not have enough credit.', status: 403)]
final class OutOfCredit extends ProblemException {}
```

Then throw it with the details of this occurrence:

```php
throw new OutOfCredit(
    detail: 'Your current balance is 30, but that costs 50.',
    extensions: ['balance' => 30],
    instance: '/account/12345/msgs/abc',
);
```

```http
HTTP/1.1 403 Forbidden
Content-Type: application/problem+json

{"type":"https://docs.example.com/problems/out-of-credit","title":"You do not have enough credit.","status":403,"detail":"Your current balance is 30, but that costs 50.","instance":"/account/12345/msgs/abc","balance":30}
```

The type, title and status live on the class because section 3.1.3 says a title should not
change between occurrences. Detail, instance and extensions belong to the occurrence. An
extension named after a standard member (`type`, `title`, `status`, `detail`, `instance`) is
rejected, because it would silently replace that member.

A problem class can inherit its `#[ProblemType]` from an abstract parent.

### What else is rendered

| Exception | Status | type |
|---|---|---|
| `ProblemException` | from `#[ProblemType]` | resolved from `#[ProblemType]` |
| `ValidationException` | 422 | `https://apiguide.dev/errors/validation-failed` |
| `AuthenticationException` | 401 | `https://apiguide.dev/errors/unauthorized` |
| Any HTTP exception, including `abort()`, model not found, authorization | its own | the catalogue entry for the status, or `about:blank` |
| Anything else | 500 | `https://apiguide.dev/errors/internal-server-error` |

A status gets a catalogue type only when one entry is the plain meaning of that status: 404 is
`resource-not-found`, 412 is `precondition-failed`, 429 is `rate-limit-exceeded`. Statuses with
several candidates, such as 400 and 422, get `about:blank` with the status phrase, as section
4.2.1 asks, rather than a type that claims more than the status says. Any headers the exception
carries, such as `Retry-After` or `ETag`, are kept.

A 401 carries a `WWW-Authenticate` challenge, because RFC 9110 section 15.5.2 says every 401 MUST.
Laravel's `AuthenticationException` carries none, so the package adds the one in
`www_authenticate`: `Bearer` by default, the scheme Sanctum and Passport tokens use. Set it to
match how your API authenticates, such as `'Bearer realm="api"'` or `'Basic realm="admin"'`. An
exception that already brings a challenge, such as Symfony's `UnauthorizedHttpException`, keeps
its own. `false` sends none; only do that if something else adds the header.

An unexpected exception gets only `type`, `title` and `status`. Its message and trace are added
only when `app.debug` is on, since section 5 warns against exposing implementation details.

To map an exception of your own to a problem, use Laravel's `$exceptions->map()` in
`bootstrap/app.php` to turn it into a `ProblemException`.

### Validation errors

These use the `errors` member that apiguide.dev documents for its `validation-failed` type:
Laravel's messages, keyed by field.

```json
{
  "type": "https://apiguide.dev/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The end date field must be a date after start date.",
  "errors": {
    "end_date": ["The end date field must be a date after start date."]
  }
}
```

RFC 9457's own validation example, in section 3, uses a list of `detail` and JSON Pointer
objects instead. That example belongs to a fictional problem type. Section 3.2 lets each type
define its extensions, and a response has to match the type its `type` member points at, so this
follows the catalogue page.

### Type URIs

A `#[ProblemType]` takes either an absolute URI, used as is, or a slug resolved against
`type_base_uri`. Full paths such as `/types/123` also pass through untouched.

Section 3.1.1 recommends absolute type URIs, and says a relative one should carry the full
path, because a bare relative reference resolves against each request's own URL. So the default
base is the path `/problems`, never nothing. Point `type_base_uri` at your documentation to
follow the recommendation fully: section 3.1.1 also says dereferencing a type URI should give
human-readable documentation.

To resolve types some other way, such as against an external error catalogue, bind your own
`JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver`.

### When problems are rendered

Problem details are rendered when the request expects JSON, or when its path matches one of
`paths`. Everything else is left to Laravel.

Requests that send or accept `application/vnd.api+json` are left alone. JSON:API has its own
error format, and section 4 of the RFC says problem details are not meant to replace
domain-specific formats.

Your own render callbacks in `withExceptions()` run first, so an application can always
override what this package renders.

## Deprecation and Sunset

[RFC 9745](https://www.rfc-editor.org/rfc/rfc9745) (Deprecation),
[RFC 8594](https://www.rfc-editor.org/rfc/rfc8594) (Sunset)

A changelog entry saying an endpoint is deprecated reaches the people who read changelogs.
These two headers reach every client that calls the endpoint, in a form a client library or
gateway can act on.

```php
Features::deprecation([
    'global' => true, // default: true
]),
```

Mark a controller, or one action, with attributes:

```php
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

#[Deprecation(at: '2026-06-30T23:59:59Z', link: 'https://docs.example.com/deprecations/orders-v1')]
#[Sunset(at: '2027-01-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
final class OrderController
{
    // ...
}
```

```http
Deprecation: @1782863999
Sunset: Fri, 01 Jan 2027 00:00:00 GMT
Link: <https://docs.example.com/deprecations/orders-v1>; rel="deprecation"
Link: <https://docs.example.com/sunset-policy>; rel="sunset"
```

- An attribute on an action overrides the same attribute on its controller. The two kinds are
  resolved separately, so a controller-wide `Sunset` can sit beside a per-action `Deprecation`.
- A date without an offset is read as UTC. `at` on `Deprecation` can be in the future: section
  2.1 says the date is when the resource "will be or has been deprecated".
- `#[Deprecation(link: ...)]` with no date sends only the link. Section 3.1 describes this as
  publishing a deprecation policy before anything is deprecated.
- A sunset earlier than the deprecation throws. RFC 9745 section 4 says it MUST NOT be.
- Links are appended to any `Link` the response already has, never replace it.
- Error responses get the headers too, because both describe the resource, not the outcome of
  one request.

The attributes are named after the header fields because `#[Deprecated]` is taken: PHP 8.4's
native `#[\Deprecated]` raises `E_USER_DEPRECATED` every time the method is called, which on a
controller means on every request.

Only controller routes are read, as with Laravel's own controller attributes. Closure routes are
not, because `route:cache` serialises closures and their attributes cannot be relied on.

With `global` on, the middleware runs on every request and does nothing for routes without
attributes. With it off, add the `deprecation` middleware to the routes that need it.

### Sunset dates

RFC 8594 makes `Sunset` an HTTP-date, which RFC 9110 section 5.6.7 fixes as
`Sun, 06 Nov 1994 08:49:37 GMT`. RFC 9745's own section 4 example writes
`Sun, 30 Jun 2024 23:59:59 UTC`, which is not a valid HTTP-date. This package sends `GMT`, and
`assertSunset()` rejects `UTC`.

### Other sources

Attributes are one source of deprecation information. To read it from somewhere else, such as a
manifest shared with services in other languages, bind your own
`JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource`. It returns a `Notice` for a
route, or null:

```php
use JustSteveKing\Toolbox\Deprecation\Notice;

new Notice(
    deprecatedAt: Notice::date('2026-06-30T23:59:59Z'),
    deprecationLink: 'https://docs.example.com/deprecations/orders-v1',
    sunsetAt: Notice::date('2027-01-01'),
);
```

### Field-level deprecation

Not supported. Both RFCs describe a resource, and no published standard puts the deprecation of
a single field in a header. `Notice` has no dependency on routes, so whatever does eventually
describe fields can produce it.

## Conditional Requests

[RFC 9110 section 13](https://www.rfc-editor.org/rfc/rfc9110#section-13),
[RFC 6585 section 3](https://www.rfc-editor.org/rfc/rfc6585#section-3)

Two clients read a document, both edit it, and the second save silently overwrites the first.
Entity tags and preconditions stop that lost update: the client says which version it read, and
the server refuses the write if that is no longer current. The same validators let a client
re-fetch a resource it already has and get a bodiless 304.

```php
Features::conditionalRequests(),
```

It is opt-in per route:

```php
Route::middleware('conditional')->group(function () {
    Route::get('documents/{document}', ShowDocument::class);
    Route::patch('documents/{document}', UpdateDocument::class)->middleware('preconditions.required');
});
```

| Middleware | Does |
|---|---|
| `conditional` | The group of the next two, in order |
| `preconditions` | Evaluates `If-Match`, `If-None-Match`, `If-Modified-Since` and `If-Unmodified-Since` in the order section 13.2.2 sets: 412 or 304 |
| `validators` | Adds `ETag` and `Last-Modified` to successful GET and HEAD responses. `validators:weak` makes a content-hash ETag weak |
| `preconditions.required` | 428 for a PUT, PATCH or DELETE with no precondition. `preconditions.required:PUT,POST` names the methods |

Laravel 13's `#[Middleware('conditional')]` controller attribute works too.

The middleware run after route model binding, and 428 is decided before 412, wherever the route
lists them, because the package adds them to Laravel's middleware priority.

### Where validators come from

A write has to be checked before it happens (section 13.1.1), so the current ETag cannot come
from the response. It comes from the route-bound model, when it implements `HasValidators`:

```php
use JustSteveKing\Toolbox\ConditionalRequests\Concerns\HasModelValidators;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

final class Document extends Model implements HasValidators
{
    use HasModelValidators;
}
```

`HasModelValidators` gives a strong ETag from the model's class, key and `updated_at`, and
`Last-Modified` from `updated_at`. Section 8.8.1 says a strong tag must change whenever the
representation does, and that holds only while every change touches `updated_at` and two writes
never share one. MySQL's default timestamp columns store whole seconds, so two writes in the same
second would get the same tag. For optimistic concurrency, override `entityTagVersion()` to return
a version column that every write increments.

On a GET with no bound model, the ETag is a hash of the response content.

### Behaviour worth knowing

- `If-Match` uses strong comparison (section 13.1.1), so a weak ETag can never satisfy it.
  `If-None-Match` uses weak comparison (section 13.1.2).
- A 412 carries the current `ETag`, so the client knows what to re-fetch.
- A 428 says how to retry, as RFC 6585 asks, and sends `Cache-Control: no-store`, because the
  section says a 428 MUST NOT be stored by a cache.
- `If-Match: *` fails and `If-None-Match: *` passes on a route with no bound resource, which lets
  a PUT create without overwriting.
- Modification times are compared in whole seconds, the resolution of an HTTP-date.
- Validators are only added to GET and HEAD responses. Section 9.3.4 says a successful PUT MUST
  NOT carry one unless the content was stored exactly as received, which a package cannot know.
- GET preconditions are checked against the finished response, because only the finished
  response knows every header a 304 has to repeat (section 15.4.5).
- A 304 has no `Last-Modified`. Laravel's router strips it from every 304, and section 15.4.5 only
  calls it useful when there is no ETag.
- Entity tags are parsed by this package, not Symfony. `Request::getETags()` splits on every comma,
  but a comma is a legal character inside an entity tag.
- `If-Range` and `Range` are not handled: the package does not serve range requests.
- When a precondition fails, the package always sends 412. Sections 13.1.1 and 13.1.4 allow a 2xx
  instead when the change appears to have been applied already, but they also say a server is
  better off sending 412 for every failure on an unsafe method.

## Pagination

[RFC 8288](https://www.rfc-editor.org/rfc/rfc8288)

Laravel's paginators put navigation in the body, as `links` and `meta` beside the data, so every
client has to know that shape. Link headers carry the same navigation in a form any HTTP client
can read, and leave the body as the items alone.

```php
Features::pagination(),
```

```php
use JustSteveKing\Toolbox\Pagination\CursorPage;

return CursorPage::of(Post::query()->cursorPaginate(20), PostResource::class);
```

```http
Link: <https://api.example.com/posts?cursor=eyJpZCI6MjAsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0>; rel="next"
Link: <https://api.example.com/posts>; rel="first"

[{"id": 1, ...}, {"id": 2, ...}]
```

- `next` and `prev` appear when there is a page in that direction. `first` is always there, and
  is the URL with no cursor.
- There is no `last`: a cursor only knows its neighbours.
- The resource class is optional. Without it, the items are serialised as they are.
- Call `withQueryString()` on the paginator to keep filters and sorting in every link.

### Invalid cursors

Laravel treats a cursor it cannot decode as no cursor and serves the first page, which a client
paging through results cannot notice. With this feature on, an undecodable cursor is a 400
instead, typed `https://apiguide.dev/errors/invalid-pagination-cursor`, for every cursor
paginator in the application, before any query runs. A missing or empty cursor is still the first
page.

## Idempotency

[draft-ietf-httpapi-idempotency-key-header-07](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/)

A client posts a payment, the connection drops, and it cannot tell whether the charge went
through. Retrying might charge twice; not retrying might charge never. With an `Idempotency-Key`,
the retry gets the original result instead of running again.

The draft expired on 2026-04-18 without becoming an RFC. This feature is supported anyway, not
experimental: the header has kept the same shape through every revision and is widely used.

```php
Features::idempotency([
    'ttl' => 86400,          // seconds a result is kept, and so how long a key is valid
    'store' => null,         // cache store; it must support atomic locks
    'lock' => 60,            // longest a request is treated as in flight
    'retry_after' => 1,      // Retry-After on a 409
    'documentation' => null, // URL linked from a 400, as the draft's example does
]),
```

```php
Route::post('charges', CreateCharge::class)->middleware('idempotent');           // honoured when sent
Route::post('payouts', CreatePayout::class)->middleware('idempotent:required');  // 400 when missing
```

```http
POST /charges HTTP/1.1
Idempotency-Key: "8e03978e-40d5-43e8-bc93-6894a57f9324"
```

| Situation | Response |
|---|---|
| First request with a key | Runs as normal; the response is stored |
| Retry, same payload | The stored response, unchanged, without running the action again |
| Same key, different payload | 422, `https://apiguide.dev/errors/idempotency-key-conflict` |
| Retry while the first is still running | 409, the same type, with `Retry-After` |
| No key, `idempotent:required` | 400, linked to `documentation` if set |
| Malformed key | 400 |

The draft (section 2.5.2) says an API MUST publish its idempotency policy, including how long
keys last. Put your `ttl` in your API documentation.

- **Both key forms work.** Section 2.1 makes the key a quoted Structured Field String.
  Widely used clients send it bare. `"abc"` and `abc` are the same key.
- **Keys are scoped** to the authenticated user, or to the client address when there is none, so
  two clients cannot collide on the same key. The middleware runs after authentication for this
  reason, wherever the route lists it.
- **The fingerprint** is a hash of the method, path and query, and the whole body. That's one of
  the approaches section 2.4 lists. The same key sent to another endpoint is a different request
  (422), not a retry.
- **Only POST and PATCH.** The other methods are idempotent already.
- **Errors are stored and replayed** too, as section 2.6 says: a retry of a request that failed
  validation fails the same way. Two exceptions: a 5xx is not stored, so a request that failed on
  the server can be retried, which is the point of the key; nor is a 429, because the request
  never ran.
- **A replay is the stored response** with its original status, headers and body, minus `Date`
  and cookies. With Negotiation on, it gets its own request ID and `traceparent`, because a retry
  is a new request. No header marks it as a replay: the draft defines none.
- **The cache store has to support atomic locks**, which is how a concurrent retry is told apart
  from a completed one. Redis, Memcached, database, file, DynamoDB and array do. APC, session and
  storage stores do not, and nor does the null store in practice, since it keeps nothing. The
  package refuses all of those rather than fail open.

## RateLimit headers (experimental)

[draft-ietf-httpapi-ratelimit-headers-11](https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/)

**Experimental.** Built on an IETF draft that can still change. Breaking changes to this
feature are allowed in minor releases. Pin a minor version if you rely on it.

Laravel tells a client its rate limit through `X-RateLimit-*` headers that no standard defines.
A client only finds out the window when it hits a 429. The draft's two fields describe the policy
and the current state in a form any client can parse.

```php
Features::rateLimitHeaders([
    'legacy_headers' => true, // keep Laravel's X-RateLimit-* alongside
]),
```

Nothing changes in your routes. Laravel's `throttle` middleware and rate limiters work as before;
the responses gain two fields:

```php
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
```

```http
RateLimit-Policy: "api";q=60;w=60
RateLimit: "api";r=59;t=60
```

And on a 429:

```http
HTTP/1.1 429 Too Many Requests
Content-Type: application/problem+json
Retry-After: 42
RateLimit-Policy: "api";q=60;w=60
RateLimit: "api";r=0;t=42
Cache-Control: no-store, private

{"type":"https://apiguide.dev/errors/rate-limit-exceeded","title":"Rate Limit Exceeded","status":429,"detail":"Too Many Attempts."}
```

- The policy name is the limiter's name. A limiter that returns several limits gets one policy
  each, numbered: `"layered-1"`, `"layered-2"`. `throttle:60,1` has no name and is `"default"`.
- `q` is the limit, `w` its window in seconds, `r` what is left and `t` the seconds until the
  window resets. `t` is left out before a window has started.
- `pk`, the partition key, is never sent. Section 6.1 says to avoid partition keys that carry
  sensitive information, and Laravel's are user identifiers and IP addresses.
- `Retry-After` is the end of the effective window, never earlier, as section 6 requires.
- A 429 carries `Cache-Control: no-store`, because RFC 6585 section 4 says it MUST NOT be stored
  by a cache.
- The 429 is typed `rate-limit-exceeded`, for a short window that protects capacity.
  apiguide.dev's `quota-exceeded` is for a billing-period allowance. Use it, through a
  `ProblemException`, if that is what your limit is.

This works by binding Laravel's `ThrottleRequests` middleware to a subclass in the container.

### Known gap: Redis throttling

Applications that call `throttleWithRedis()` get no RateLimit headers. Their 429s are still
rendered as problems, but without `RateLimit`, `RateLimit-Policy` or `no-store`.

That is deliberate for now, for three reasons:

- **It is a different middleware.** `throttleWithRedis()` points the `throttle` alias at
  `ThrottleRequestsWithRedis`, so binding `ThrottleRequests` never reaches it.
- **It counts differently.** It does not use Laravel's cache-backed `RateLimiter`. Each limit is a
  `DurationLimiter`, a Lua script run in Redis, and the remaining count and reset time live in
  arrays on the middleware instance, filled as each limit is checked. Supporting it means a second
  subclass that mirrors that class's own `handleRequest()`, a second set of framework internals to
  track.
- **It cannot be tested here.** Proving it works needs a Redis server in the test suite, and this
  feature is experimental already. Untested support for it would be worse than none.

The fix is clear once those are in place: the same `PolicyHeaders` builder, fed from that class's
`remaining` and `decaysAt` instead of the cache.

The draft does not say whether the policy item in `RateLimit` (section 4) must be a String the way
section 3 requires for `RateLimit-Policy`. Every example uses one, and the same name has to
identify the same policy in both, so this package always sends a String.

## Negotiation

[RFC 9110 §12](https://www.rfc-editor.org/rfc/rfc9110#section-12),
[W3C Trace Context Level 1](https://www.w3.org/TR/trace-context/)

Three things that sit between a request and its handler: refusing what a route cannot produce or
consume, joining a request to its distributed trace, and giving it an ID.

```php
Features::negotiation([
    'request_id_header' => 'X-Request-Id', // false turns request IDs off
    'trust_request_id' => true,            // keep a well-formed incoming ID
    'trace_context' => true,
    'propagate_trace' => true,             // onto outgoing HTTP client requests
]),
```

### 406 and 415

An API that only speaks JSON answers an `Accept: application/xml` client with JSON anyway, and
accepts a form post it then fails to read. Declaring what a route produces and consumes turns
both into a clear error.

```php
Route::post('leads', CreateLead::class)
    ->middleware(['produces:application/json', 'consumes:application/json']);

Route::patch('leads/{lead}', UpdateLead::class)
    ->middleware('consumes:application/merge-patch+json');
```

`produces` answers 406, typed `https://apiguide.dev/errors/not-acceptable`, when the `Accept`
header rules out every type listed. `consumes` answers 415, typed
`https://apiguide.dev/errors/unsupported-media-type`, when the request has content in any other
type.

- `Accept` is evaluated as section 12.5.1 says: quality values, wildcards, and the most specific
  matching range deciding a type's quality. `q=0` means not acceptable, even under `*/*`. A
  missing `Accept` accepts anything.
- Section 12.5.1 lets a server ignore an unsatisfiable `Accept` instead. Adding `produces` to a
  route is choosing strict negotiation.
- Responses from a `produces` route carry `Vary: Accept`, since whether they are a 406 depends on
  it.
- The 406 lists what the route can produce in its `detail`, which section 15.5.7 says it SHOULD.
- Error responses are not held to `produces`: RFC 9457 section 3 notes a problem can be sent as
  `application/problem+json` even when the client did not list it.
- The 415 carries an `Accept` header naming the supported types, as section 15.5.16 suggests, and
  a refused PATCH also carries `Accept-Patch`, which RFC 5789 section 2.2 says it SHOULD.
- A request with no content is never refused. One with content and no `Content-Type` is: section
  8.3 lets a server guess, and a guess is not a type the route said it consumes.
- Types match on type and subtype, case-insensitively. `consumes:application/json` accepts
  `application/json; charset=utf-8`, and does not accept `application/merge-patch+json`; list it.

### Trace context

Each request joins the W3C trace its caller started, or starts one. The trace ID and the span ID
for this request are put in Laravel's log context, so every log line written while handling it
carries them, and so do queued jobs it dispatches. Laravel HTTP client requests carry the trace
on in `traceparent`, with `tracestate` forwarded unchanged. That outgoing propagation is the core
of the specification (section 4.3).

```http
GET /orders HTTP/1.1
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01

HTTP/1.1 200 OK
traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-b7ad6b7169203331-01
```

- A valid `traceparent` is continued: same trace ID, a new parent ID for this request, flags
  kept.
- No `traceparent`, or one the specification says to reject (version `ff`, uppercase or
  all-zero IDs, extra fields on version 00), starts a new trace and drops any `tracestate`. A
  higher version is read as far as version 00 goes, as section 3.2.4 describes.
- Flags version 00 does not define are cleared before anything is sent on (section 4.3).
- A response only gets a `traceparent` when the request brought a valid one. Level 1 defines it as
  a request header; section 6.3 notes vendors send it on responses too, and asks that they only
  do so to systems that took part in the trace.
- An outgoing request that already has a `traceparent`, from OpenTelemetry instrumentation for
  example, is left alone.

### Request IDs

**No standard defines this.** It is included deliberately, because a request ID is how a support
ticket, a log line and a response are tied together, and it is the one part of the package not
built on a published specification.

Every request gets an ID, on the response and in the log context as `request_id`. `X-Request-Id`
is the default because it is what most proxies, load balancers and hosting platforms already use;
set `request_id_header` to use another name.

- An incoming ID of 1 to 200 visible ASCII characters is kept, so one ID can follow a request
  through a proxy. Set `trust_request_id` to `false` to always assign your own.
- Otherwise the ID is a UUIDv7, so IDs sort roughly by time in logs.
- Error responses carry it too.

## Testing

Assertions for every enabled feature, on Laravel's `TestResponse` and as Pest expectations.
They check the exact syntax each standard specifies, not only that a header is present. They are
registered only while the application is running its tests (`APP_ENV=testing`), and only for
features that are enabled. The Pest expectations need Pest; the response macros work with
PHPUnit alone.

```php
$this->getJson('/api/orders')
    ->assertProblem(403)
    ->assertProblemType('https://docs.example.com/problems/out-of-credit');

expect($this->getJson('/api/orders'))->toBeProblem(403);
```

| Feature | Response macro | Pest expectation | Checks |
|---|---|---|---|
| Problem Details | `assertProblem(?status)` | `toBeProblem(?status)` | `application/problem+json`; a JSON object; member types; `status` matches the response (RFC 9457 §3.1.2) |
| | `assertProblemType(type, ?status)` | `toHaveProblemType(type, ?status)` | The above, and the type. A missing type counts as `about:blank` (§3.1.1) |
| Deprecation | `assertDeprecated(?at, ?link)` | `toBeDeprecated(?at, ?link)` | Exactly one `Deprecation` field, a Structured Field Date (RFC 9745 §2.1), and a `rel="deprecation"` link if given |
| | `assertSunset(?at, ?link)` | `toHaveSunset(?at, ?link)` | Exactly one `Sunset` field, an IMF-fixdate in GMT (RFC 8594 §3), not earlier than `Deprecation` (RFC 9745 §4) |
| | `assertNotDeprecated()` | | Neither field is present |
| Conditional Requests | `assertETag(?etag, ?weak)` | `toHaveETag(?etag, ?weak)` | Exactly one `ETag` field, valid entity-tag syntax (RFC 9110 §8.8.3), and the tag or strength if given |
| | `assertNotModified()` | `toBeNotModified()` | 304 with no content (RFC 9110 §15.4.5) |
| | `assertPreconditionFailed()` | `toBePreconditionFailed()` | 412 |
| | `assertPreconditionRequired()` | `toBePreconditionRequired()` | 428 that explains itself and carries `no-store` (RFC 6585 §3) |
| Idempotency | `assertIdempotentReplay(original)` | `toBeIdempotentReplayOf(original)` | The same status, content and headers as the original response, other than those that identify the message: `Date`, cookies, `traceparent` and the request ID header (draft §2.6) |
| RateLimit headers | `assertRateLimitHeaders(?policy, ?remaining)` | `toHaveRateLimitHeaders(?policy, ?remaining)` | Both fields parse as RFC 9651 Lists with the draft's parameters and types (draft §3, §4), and describe the policy if given |
| Negotiation | `assertTraceparent(?traceId)` | `toHaveTraceparent(?traceId)` | A valid version 00 `traceparent` (Trace Context §3.2), in the given trace if one is passed |
| | `assertRequestId(?id)` | `toHaveRequestId(?id)` | The configured request ID header is present, with the given value if one is passed |
| Deprecation, Pagination | `assertLinkHeader(rel, ?uri)` | `toHaveLink(rel, ?uri)` | Every `Link` field parses (RFC 8288 §3), and one has the relation type and target |
| | `assertNoLinkHeader(rel)` | | No `Link` with that relation type |

Dates can be a `DateTimeInterface` or a string. A string without an offset is read as UTC.

## Configuration reference

Every option, with its default. Options are passed inline to the feature's method in
`config/api-toolbox.php`.

| Feature | Option | Default | Meaning |
|---|---|---|---|
| `problemDetails` | `type_base_uri` | `/problems` | Base URI for type slugs in `#[ProblemType]` |
| | `paths` | `['api/*']` | Paths that always get problem details, whatever `Accept` says |
| | `apiguide` | `true` | Type plain HTTP errors and validation with the apiguide.dev catalogue |
| | `www_authenticate` | `Bearer` | The challenge sent with a 401; `false` for none |
| `deprecation` | `global` | `true` | Run on every request; otherwise use the `deprecation` middleware |
| `conditionalRequests` | | | No options. Opt in per route with `conditional` |
| `pagination` | | | No options. Use `CursorPage` |
| `idempotency` | `ttl` | `86400` | Seconds a result is kept, and so how long a key is valid |
| | `store` | default store | Cache store; must support atomic locks |
| | `lock` | `60` | Longest a request is treated as in flight |
| | `retry_after` | `1` | `Retry-After` seconds on a 409 |
| | `documentation` | `null` | URL linked from a 400 as `rel="describedby"` |
| `rateLimitHeaders` | `legacy_headers` | `true` | Keep Laravel's `X-RateLimit-*` headers |
| `negotiation` | `request_id_header` | `X-Request-Id` | Request ID header name; `false` turns request IDs off |
| | `trust_request_id` | `true` | Keep a well-formed incoming request ID |
| | `trace_context` | `true` | Join requests to their W3C trace |
| | `propagate_trace` | `true` | Send the trace on in HTTP client requests |

| Middleware | Feature | Does |
|---|---|---|
| `deprecation` | Deprecation | `Deprecation`, `Sunset` and their links, when `global` is off |
| `conditional` | Conditional Requests | `preconditions` then `validators` |
| `preconditions` | Conditional Requests | Evaluates `If-*` headers: 412 or 304 |
| `validators` | Conditional Requests | Adds `ETag` and `Last-Modified`; `validators:weak` for a weak hash |
| `preconditions.required` | Conditional Requests | 428 without a precondition; `preconditions.required:PUT,POST` |
| `idempotent` | Idempotency | Honours `Idempotency-Key`; `idempotent:required` for 400 without one |
| `produces` | Negotiation | 406; `produces:application/json` |
| `consumes` | Negotiation | 415; `consumes:application/json,application/merge-patch+json` |

Request IDs and trace context run as global middleware. RateLimit headers take over Laravel's
`throttle` middleware. Neither needs adding to routes.

## License

MIT. See [LICENSE.md](LICENSE.md).
