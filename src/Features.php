<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox;

/**
 * Names the features this package can switch on, for use in the published config file.
 *
 * Modelled on Laravel\Fortify\Features. Each method returns a plain string, and any options are
 * written to a separate config key as a side effect of calling it. That looks odd, and it is
 * deliberate: `php artisan config:cache` writes the config with var_export(), so the features
 * array has to hold scalars, and options cannot live under the package's own key because the
 * config file's return value replaces that key once the file has been evaluated.
 *
 * The side effect uses the config() helper because it runs while config files are being loaded,
 * where there is nothing to inject into. It is the only place in the package that does.
 */
final class Features
{
    public const string CONFIG_KEY = 'api-toolbox';

    public const string OPTIONS_KEY = 'api-toolbox-options';

    public const string PROBLEM_DETAILS = 'problem-details';

    public const string DEPRECATION = 'deprecation';

    public const string CONDITIONAL_REQUESTS = 'conditional-requests';

    public const string PAGINATION = 'pagination';

    public const string IDEMPOTENCY = 'idempotency';

    public const string RATE_LIMIT_HEADERS = 'rate-limit-headers';

    public const string NEGOTIATION = 'negotiation';

    public const string PREFERENCES = 'preferences';

    public const string ASYNC_OPERATIONS = 'async-operations';

    public const string MERGE_PATCH = 'merge-patch';

    public const string DIGESTS = 'digests';

    /**
     * Render exceptions as RFC 9457 problem details.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9457
     *
     * Plain HTTP errors and validation failures use the apiguide.dev error catalogue as their
     * type unless 'apiguide' is false, in which case they use about:blank and the base URI.
     *
     * @param  array{type_base_uri?: string, paths?: list<string>, apiguide?: bool, www_authenticate?: string|false, instance_from_request_id?: bool}  $options
     */
    public static function problemDetails(array $options = []): string
    {
        return self::withOptions(self::PROBLEM_DETAILS, $options);
    }

    /**
     * Send Deprecation (RFC 9745) and Sunset (RFC 8594) headers for routes marked with
     * #[Deprecation] and #[Sunset].
     *
     * @see https://www.rfc-editor.org/rfc/rfc9745
     * @see https://www.rfc-editor.org/rfc/rfc8594
     *
     * @param  array{global?: bool}  $options
     */
    public static function deprecation(array $options = []): string
    {
        return self::withOptions(self::DEPRECATION, $options);
    }

    /**
     * ETags, Last-Modified, 304 Not Modified, 412 Precondition Failed and 428 Precondition
     * Required, opted into per route with the "conditional" middleware group.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9110#section-13
     * @see https://www.rfc-editor.org/rfc/rfc6585#section-3
     */
    public static function conditionalRequests(): string
    {
        return self::CONDITIONAL_REQUESTS;
    }

    /**
     * Cursor pagination that puts next, prev and first in Link headers rather than in the body.
     *
     * @see https://www.rfc-editor.org/rfc/rfc8288
     */
    public static function pagination(): string
    {
        return self::PAGINATION;
    }

    /**
     * Makes POST and PATCH safe to retry with an Idempotency-Key, through the "idempotent"
     * middleware.
     *
     * Built on draft-ietf-httpapi-idempotency-key-header-07, which expired on 2026-04-18 without
     * becoming an RFC. Supported, not experimental: the header's shape has been stable through
     * every revision and is in wide use.
     *
     * @see https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/
     *
     * @param  array{ttl?: int, store?: string, lock?: int, retry_after?: int, documentation?: string}  $options
     */
    public static function idempotency(array $options = []): string
    {
        return self::withOptions(self::IDEMPOTENCY, $options);
    }

    /**
     * Experimental: built on draft-ietf-httpapi-ratelimit-headers-11, which can still change.
     * Breaking changes to this feature are allowed in minor releases.
     *
     * Adds RateLimit and RateLimit-Policy to responses from Laravel's own throttle middleware.
     *
     * @see https://datatracker.ietf.org/doc/draft-ietf-httpapi-ratelimit-headers/
     *
     * @param  array{legacy_headers?: bool}  $options
     */
    public static function rateLimitHeaders(array $options = []): string
    {
        return self::withOptions(self::RATE_LIMIT_HEADERS, $options);
    }

    /**
     * 406 and 415 for routes that declare what they produce and consume, W3C Trace Context on
     * every request, and a request ID.
     *
     * The request ID is the one part of this package that no published standard defines.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9110#section-12
     * @see https://www.w3.org/TR/trace-context/
     *
     * @param  array{request_id_header?: string|false, trust_request_id?: bool, trace_context?: bool, propagate_trace?: bool}  $options
     */
    public static function negotiation(array $options = []): string
    {
        return self::withOptions(self::NEGOTIATION, $options);
    }

    /**
     * The Prefer request field: return=minimal and return=representation through the "prefer"
     * middleware, handling=strict for form requests, and Preference-Applied.
     *
     * @see https://www.rfc-editor.org/rfc/rfc7240
     */
    public static function preferences(): string
    {
        return self::PREFERENCES;
    }

    /**
     * Queued jobs as asynchronous operations: 202 Accepted pointing at a status monitor, which
     * answers 303 See Other to the result once the job is done.
     *
     * RFC 9110 defines 202 and 303, and RFC 7240 the respond-async preference. What happens after
     * the 202 is left to the server by both, so the status monitor's shape is this package's own.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9110#section-15.3.3
     *
     * @param  array{store?: string, ttl?: int, retry_after?: int, path?: string, middleware?: list<string>}  $options
     */
    public static function asyncOperations(array $options = []): string
    {
        return self::withOptions(self::ASYNC_OPERATIONS, $options);
    }

    /**
     * JSON Merge Patch documents for PATCH, through the "merge-patch" middleware and an injectable
     * MergePatch.
     *
     * @see https://www.rfc-editor.org/rfc/rfc7396
     */
    public static function mergePatch(): string
    {
        return self::MERGE_PATCH;
    }

    /**
     * Content-Digest and Repr-Digest: verified on requests with "digest.verify", added to
     * responses with "digest.respond".
     *
     * @see https://www.rfc-editor.org/rfc/rfc9530
     */
    public static function digests(): string
    {
        return self::DIGESTS;
    }

    /**
     * Every feature this version of the package knows about.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PROBLEM_DETAILS,
            self::DEPRECATION,
            self::CONDITIONAL_REQUESTS,
            self::PAGINATION,
            self::IDEMPOTENCY,
            self::RATE_LIMIT_HEADERS,
            self::NEGOTIATION,
            self::PREFERENCES,
            self::ASYNC_OPERATIONS,
            self::MERGE_PATCH,
            self::DIGESTS,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function withOptions(string $feature, array $options): string
    {
        if ($options !== []) {
            config([self::OPTIONS_KEY.'.'.$feature => $options]);
        }

        return $feature;
    }
}
