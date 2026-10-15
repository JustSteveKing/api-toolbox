# API Toolbox

Standards-compliant APIs in Laravel, one RFC at a time.

Every feature in this package implements a published HTTP standard, and each one can be
switched on or off by itself. A feature you leave off registers nothing: no bindings, no
middleware, no exception handling hooks.

Requires PHP 8.3 and Laravel 13.30 or later.

## Install

```bash
composer require juststeveking/api-toolbox
php artisan vendor:publish --tag=api-toolbox-config
```

Features are listed in `config/api-toolbox.php`, and options are passed to them inline:

```php
use JustSteveKing\Toolbox\Features;

'features' => [
    Features::problemDetails([
        'type_base_uri' => 'https://docs.example.com/problems',
    ]),
],
```

If you never publish the config, every stable feature is on with its defaults.

To check a feature at runtime:

```php
use JustSteveKing\Toolbox\Facades\ApiToolbox;
use JustSteveKing\Toolbox\Features;

ApiToolbox::enabled(Features::problemDetails());
```

Or inject `JustSteveKing\Toolbox\Contracts\EnabledFeatures`.

### Stability

A feature built on an IETF draft is marked experimental in its section below and in the
docblock of its `Features::` method. Experimental features can change in a breaking way in a
minor release, because the draft underneath them can. Pin a minor version if you rely on one.
Every other feature follows semantic versioning.

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

## Testing

Assertions for every enabled feature, on Laravel's `TestResponse` and as Pest expectations.
They check the exact syntax each RFC specifies, not only that a header is present. They are
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
| Deprecation, Pagination | `assertLinkHeader(rel, ?uri)` | `toHaveLink(rel, ?uri)` | Every `Link` field parses (RFC 8288 §3), and one has the relation type and target |
| | `assertNoLinkHeader(rel)` | | No `Link` with that relation type |

Dates can be a `DateTimeInterface` or a string. A string without an offset is read as UTC.

## License

MIT. See [LICENSE.md](LICENSE.md).
