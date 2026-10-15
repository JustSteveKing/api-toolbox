<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Support;

/**
 * The error catalogue at apiguide.dev, as problem type URIs (RFC 9457, section 3.1.1).
 *
 * Each URI resolves to a page documenting the problem: when to use it, its status and an example,
 * which is what section 3.1.1 asks a type URI to dereference to. Use them in your own problem
 * types as well:
 *
 *     #[ProblemType(ApiGuide::ACCOUNT_FROZEN, title: 'Account Frozen', status: 403)]
 *
 * Generated from the catalogue on 2026-10-15; every URI was checked to resolve.
 *
 * @see https://apiguide.dev/errors
 */
final class ApiGuide
{
    public const string BASE = 'https://apiguide.dev/errors/';

    /** 403 */
    public const string ACCOUNT_FROZEN = self::BASE.'account-frozen';

    /** 502 */
    public const string BAD_GATEWAY = self::BASE.'bad-gateway';

    /** 401 */
    public const string EXPIRED_AUTHENTICATION_TOKEN = self::BASE.'expired-authentication-token';

    /** 403 */
    public const string FORBIDDEN = self::BASE.'forbidden';

    /** 504 */
    public const string GATEWAY_TIMEOUT = self::BASE.'gateway-timeout';

    /** 410 */
    public const string GONE = self::BASE.'gone';

    /** 422 */
    public const string IDEMPOTENCY_KEY_CONFLICT = self::BASE.'idempotency-key-conflict';

    /** 402 */
    public const string INSUFFICIENT_FUNDS = self::BASE.'insufficient-funds';

    /** 403 */
    public const string INSUFFICIENT_SCOPE = self::BASE.'insufficient-scope';

    /** 500 */
    public const string INTERNAL_SERVER_ERROR = self::BASE.'internal-server-error';

    /** 400 */
    public const string INVALID_PAGINATION_CURSOR = self::BASE.'invalid-pagination-cursor';

    /** 400 */
    public const string INVALID_QUERY_PARAMETER = self::BASE.'invalid-query-parameter';

    /** 411 */
    public const string LENGTH_REQUIRED = self::BASE.'length-required';

    /** 400 */
    public const string MALFORMED_REQUEST_BODY = self::BASE.'malformed-request-body';

    /** 405 */
    public const string METHOD_NOT_ALLOWED = self::BASE.'method-not-allowed';

    /** 406 */
    public const string NOT_ACCEPTABLE = self::BASE.'not-acceptable';

    /** 501 */
    public const string NOT_IMPLEMENTED = self::BASE.'not-implemented';

    /** 413 */
    public const string PAYLOAD_TOO_LARGE = self::BASE.'payload-too-large';

    /** 412 */
    public const string PRECONDITION_FAILED = self::BASE.'precondition-failed';

    /** 428 */
    public const string PRECONDITION_REQUIRED = self::BASE.'precondition-required';

    /** 429 */
    public const string QUOTA_EXCEEDED = self::BASE.'quota-exceeded';

    /** 416 */
    public const string RANGE_NOT_SATISFIABLE = self::BASE.'range-not-satisfiable';

    /** 429 */
    public const string RATE_LIMIT_EXCEEDED = self::BASE.'rate-limit-exceeded';

    /** 431 */
    public const string REQUEST_HEADER_FIELDS_TOO_LARGE = self::BASE.'request-header-fields-too-large';

    /** 408 */
    public const string REQUEST_TIMEOUT = self::BASE.'request-timeout';

    /** 409 */
    public const string RESOURCE_CONFLICT = self::BASE.'resource-conflict';

    /** 404 */
    public const string RESOURCE_NOT_FOUND = self::BASE.'resource-not-found';

    /** 503 */
    public const string SERVICE_UNAVAILABLE = self::BASE.'service-unavailable';

    /** 409 */
    public const string STALE_RESOURCE_VERSION = self::BASE.'stale-resource-version';

    /** 401 */
    public const string UNAUTHORIZED = self::BASE.'unauthorized';

    /** 422 */
    public const string UNPROCESSABLE_QUERY = self::BASE.'unprocessable-query';

    /** 400 */
    public const string UNSUPPORTED_API_VERSION = self::BASE.'unsupported-api-version';

    /** 415 */
    public const string UNSUPPORTED_MEDIA_TYPE = self::BASE.'unsupported-media-type';

    /** 414 */
    public const string URI_TOO_LONG = self::BASE.'uri-too-long';

    /** 422 */
    public const string VALIDATION_FAILED = self::BASE.'validation-failed';

    /**
     * Each type's title, as the catalogue gives it.
     */
    private const array TITLES = [
        self::ACCOUNT_FROZEN => 'Account Frozen',
        self::BAD_GATEWAY => 'Bad Gateway',
        self::EXPIRED_AUTHENTICATION_TOKEN => 'Expired Authentication Token',
        self::FORBIDDEN => 'Forbidden',
        self::GATEWAY_TIMEOUT => 'Gateway Timeout',
        self::GONE => 'Gone',
        self::IDEMPOTENCY_KEY_CONFLICT => 'Idempotency Key Conflict',
        self::INSUFFICIENT_FUNDS => 'Insufficient Funds',
        self::INSUFFICIENT_SCOPE => 'Insufficient Scope',
        self::INTERNAL_SERVER_ERROR => 'Internal Server Error',
        self::INVALID_PAGINATION_CURSOR => 'Invalid Pagination Cursor',
        self::INVALID_QUERY_PARAMETER => 'Invalid Query Parameter',
        self::LENGTH_REQUIRED => 'Length Required',
        self::MALFORMED_REQUEST_BODY => 'Malformed Request Body',
        self::METHOD_NOT_ALLOWED => 'Method Not Allowed',
        self::NOT_ACCEPTABLE => 'Not Acceptable',
        self::NOT_IMPLEMENTED => 'Not Implemented',
        self::PAYLOAD_TOO_LARGE => 'Payload Too Large',
        self::PRECONDITION_FAILED => 'Precondition Failed',
        self::PRECONDITION_REQUIRED => 'Precondition Required',
        self::QUOTA_EXCEEDED => 'Quota Exceeded',
        self::RANGE_NOT_SATISFIABLE => 'Range Not Satisfiable',
        self::RATE_LIMIT_EXCEEDED => 'Rate Limit Exceeded',
        self::REQUEST_HEADER_FIELDS_TOO_LARGE => 'Request Header Fields Too Large',
        self::REQUEST_TIMEOUT => 'Request Timeout',
        self::RESOURCE_CONFLICT => 'Resource Conflict',
        self::RESOURCE_NOT_FOUND => 'Resource Not Found',
        self::SERVICE_UNAVAILABLE => 'Service Unavailable',
        self::STALE_RESOURCE_VERSION => 'Stale Resource Version',
        self::UNAUTHORIZED => 'Unauthorized',
        self::UNPROCESSABLE_QUERY => 'Unprocessable Query',
        self::UNSUPPORTED_API_VERSION => 'Unsupported API Version',
        self::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
        self::URI_TOO_LONG => 'URI Too Long',
        self::VALIDATION_FAILED => 'Validation Failed',
    ];

    /**
     * The catalogue entry for a bare status code, where one entry is the plain meaning of that
     * code. Codes with several candidates, 400 and 422 among them, have none: picking one would
     * claim more than the status says.
     */
    private const array STATUSES = [
        401 => self::UNAUTHORIZED,
        403 => self::FORBIDDEN,
        404 => self::RESOURCE_NOT_FOUND,
        405 => self::METHOD_NOT_ALLOWED,
        406 => self::NOT_ACCEPTABLE,
        408 => self::REQUEST_TIMEOUT,
        409 => self::RESOURCE_CONFLICT,
        410 => self::GONE,
        411 => self::LENGTH_REQUIRED,
        412 => self::PRECONDITION_FAILED,
        413 => self::PAYLOAD_TOO_LARGE,
        414 => self::URI_TOO_LONG,
        415 => self::UNSUPPORTED_MEDIA_TYPE,
        416 => self::RANGE_NOT_SATISFIABLE,
        428 => self::PRECONDITION_REQUIRED,
        429 => self::RATE_LIMIT_EXCEEDED,
        431 => self::REQUEST_HEADER_FIELDS_TOO_LARGE,
        500 => self::INTERNAL_SERVER_ERROR,
        501 => self::NOT_IMPLEMENTED,
        502 => self::BAD_GATEWAY,
        503 => self::SERVICE_UNAVAILABLE,
        504 => self::GATEWAY_TIMEOUT,
    ];

    public static function title(string $type): ?string
    {
        return self::TITLES[$type] ?? null;
    }

    public static function forStatus(int $status): ?string
    {
        return self::STATUSES[$status] ?? null;
    }
}
