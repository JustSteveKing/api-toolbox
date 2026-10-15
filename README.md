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

If you never publish the config, Problem Details is on with its defaults.

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

## License

MIT. See [LICENSE.md](LICENSE.md).
