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

If you never publish the config, Problem Details and Deprecation are on with their defaults.

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
]),
```

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
| `ValidationException` | 422 | `validation-error`, with an `errors` extension |
| `AuthenticationException` | 401 | `about:blank` |
| Any HTTP exception, including `abort()`, model not found, authorization | its own | `about:blank` |
| Anything else | 500 | `about:blank` |

With `about:blank` the title is the status phrase, as section 4.2.1 asks. Any headers the
exception carries, such as `Retry-After`, are kept.

An unexpected exception gets only `type`, `title` and `status`. Its message and trace are added
only when `app.debug` is on, since section 5 warns against exposing implementation details.

To map an exception of your own to a problem, use Laravel's `$exceptions->map()` in
`bootstrap/app.php` to turn it into a `ProblemException`.

### Validation errors

These follow the shape of the validation example in section 3 of the RFC: one object per
message, each with a `detail` and an RFC 6901 JSON Pointer.

```json
{
  "type": "/problems/validation-error",
  "title": "Your request is not valid.",
  "status": 422,
  "detail": "The age field must be an integer. (and 1 more error)",
  "errors": [
    {"detail": "The age field must be an integer.", "pointer": "#/age"},
    {"detail": "The selected profile.color is invalid.", "pointer": "#/profile/color"}
  ]
}
```

The RFC's pointers locate an error "within the request's content". Laravel validates query
parameters and content through the same keys, so here a pointer locates the field in the
validated input, whichever part of the request it came from.

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
| | `assertLinkHeader(rel, ?uri)` | `toHaveLink(rel, ?uri)` | Every `Link` field parses (RFC 8288 §3), and one has the relation type and target |
| | `assertNoLinkHeader(rel)` | | No `Link` with that relation type |

Dates can be a `DateTimeInterface` or a string. A string without an offset is read as UTC.

## License

MIT. See [LICENSE.md](LICENSE.md).
