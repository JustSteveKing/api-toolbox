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

    /**
     * Render exceptions as RFC 9457 problem details.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9457
     *
     * Plain HTTP errors and validation failures use the apiguide.dev error catalogue as their
     * type unless 'apiguide' is false, in which case they use about:blank and the base URI.
     *
     * @param  array{type_base_uri?: string, paths?: list<string>, apiguide?: bool}  $options
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
