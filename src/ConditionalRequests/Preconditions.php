<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\ConditionalRequests;

use DateTimeInterface;
use Illuminate\Http\Request;

/**
 * Evaluates a request's preconditions in the order RFC 9110 section 13.2.2 requires.
 *
 * Steps 5 and 6 (If-Range and Range) are out of scope: this package does not serve range requests.
 *
 * Both the "already succeeded" exceptions in steps 1 and 2 are not taken. Sections 13.1.1 and
 * 13.1.4 let a server answer 2xx when a failed precondition's change appears to have been applied
 * already, but they also say a server is better off sending 412 for every failure on an unsafe
 * method, and a package cannot know when the exception is safe.
 */
final class Preconditions
{
    /**
     * @param  bool  $exists  Whether the target resource has a current representation, which is
     *                        what "*" in If-Match and If-None-Match asks about.
     */
    public static function evaluate(Request $request, ?EntityTag $entityTag, ?DateTimeInterface $lastModified, bool $exists = true): Outcome
    {
        $safe = in_array($request->getMethod(), ['GET', 'HEAD'], true);

        // Step 1.
        if (($ifMatch = self::field($request, 'If-Match')) !== null) {
            if (! self::ifMatch($ifMatch, $entityTag, $exists)) {
                return Outcome::PreconditionFailed;
            }
        }

        // Step 2. Section 13.1.4: ignored when If-Match is present, when the value is not a
        // single valid HTTP-date, and when there is no modification date to compare.
        elseif (($since = ReceivedDate::parse(self::field($request, 'If-Unmodified-Since'))) !== null && $lastModified !== null) {
            if (self::seconds($lastModified) > $since->getTimestamp()) {
                return Outcome::PreconditionFailed;
            }
        }

        // Step 3.
        if (($ifNoneMatch = self::field($request, 'If-None-Match')) !== null) {
            if (! self::ifNoneMatch($ifNoneMatch, $entityTag, $exists)) {
                return $safe ? Outcome::NotModified : Outcome::PreconditionFailed;
            }

            return Outcome::Proceed;
        }

        // Step 4. Section 13.1.3: only for GET and HEAD, and ignored for an invalid date or when
        // there is no modification date.
        if ($safe && ($since = ReceivedDate::parse(self::field($request, 'If-Modified-Since'))) !== null && $lastModified !== null) {
            if (self::seconds($lastModified) <= $since->getTimestamp()) {
                return Outcome::NotModified;
            }
        }

        return Outcome::Proceed;
    }

    /**
     * Section 13.1.1, with the strong comparison it requires.
     *
     * @param  '*'|list<EntityTag>  $tags
     */
    private static function ifMatch(string|array $tags, ?EntityTag $current, bool $exists): bool
    {
        if ($tags === '*') {
            return $exists;
        }

        if ($current === null) {
            return false;
        }

        foreach ($tags as $tag) {
            if ($tag->matchesStrongly($current)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Section 13.1.2, with the weak comparison it requires. Returns whether the condition is true.
     *
     * @param  '*'|list<EntityTag>  $tags
     */
    private static function ifNoneMatch(string|array $tags, ?EntityTag $current, bool $exists): bool
    {
        if ($tags === '*') {
            return ! $exists;
        }

        if ($current === null) {
            return true;
        }

        foreach ($tags as $tag) {
            if ($tag->matchesWeakly($current)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every line of a list field, joined as one list (RFC 9110, section 5.3). Null when absent.
     *
     * @return ($name is 'If-Match'|'If-None-Match' ? '*'|list<EntityTag>|null : string|null)
     */
    private static function field(Request $request, string $name): string|array|null
    {
        if (! $request->headers->has($name)) {
            return null;
        }

        $value = implode(', ', array_filter($request->headers->all(strtolower($name)), is_string(...)));

        return in_array($name, ['If-Match', 'If-None-Match'], true) ? EntityTag::parseList($value) : $value;
    }

    /**
     * An HTTP-date has one-second resolution, so a modification time is compared in whole seconds:
     * a resource changed at 12:00:00.4 has not been modified since 12:00:00.
     */
    private static function seconds(DateTimeInterface $date): int
    {
        return $date->getTimestamp();
    }
}
