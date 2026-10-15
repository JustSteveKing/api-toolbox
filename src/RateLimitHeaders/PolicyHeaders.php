<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\RateLimitHeaders;

/**
 * Builds RateLimit and RateLimit-Policy (draft-ietf-httpapi-ratelimit-headers-11) as RFC 9651
 * lists, one item per policy:
 *
 *     RateLimit-Policy: "api";q=60;w=60, "burst";q=10;w=1
 *     RateLimit: "api";r=59;t=60, "burst";r=9;t=1
 *
 * The draft's section 3 requires the policy item to be a String; section 4 only says the
 * RateLimit item "identifies the quota policy". Every example in section 4 uses a String, and
 * the same name has to identify the same policy in both fields, so both use one.
 *
 * pk (partition key) is never sent. Section 6.1 says servers should avoid partition keys that
 * carry sensitive information, and Laravel's are user identifiers and IP addresses.
 */
final class PolicyHeaders
{
    /**
     * @var list<string>
     */
    private array $policies = [];

    /**
     * @var list<string>
     */
    private array $limits = [];

    /**
     * @param  int  $quota  q: section 3.1.1, a non-negative Integer.
     * @param  int  $window  w: section 3.1.3, a non-zero Integer of seconds.
     * @param  int  $remaining  r: section 4.1.1, a non-negative Integer.
     * @param  int|null  $resetsIn  t: section 4.1.2, a non-negative Integer of seconds. Optional.
     */
    public function add(string $policy, int $quota, int $window, int $remaining, ?int $resetsIn = null): void
    {
        $name = self::string($policy);

        $this->policies[] = $name.';q='.max(0, $quota).($window > 0 ? ';w='.$window : '');
        $this->limits[] = $name.';r='.max(0, $remaining).($resetsIn === null ? '' : ';t='.max(0, $resetsIn));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        if ($this->policies === []) {
            return [];
        }

        // RFC 9651 section 4.1.1: list members are joined with ", ".
        return [
            'RateLimit-Policy' => implode(', ', $this->policies),
            'RateLimit' => implode(', ', $this->limits),
        ];
    }

    /**
     * RFC 9651 section 4.1.6: a String is printable ASCII, with DQUOTE and backslash escaped.
     * Anything else is dropped, since a policy name is an identifier, not prose.
     */
    private static function string(string $value): string
    {
        $printable = (string) preg_replace('/[^\x20-\x7E]/', '', $value);

        return '"'.addcslashes($printable, '"\\').'"';
    }
}
