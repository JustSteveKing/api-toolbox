<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Preferences\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Preferences\Preferences;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Honours return=minimal and return=representation (RFC 7240, section 4.2) on a route, and writes
 * Preference-Applied (section 3) for every preference anything honoured while handling it.
 *
 *     ->middleware('prefer')
 *
 * return=minimal empties a successful response to an unsafe method: 200 becomes 204 No Content,
 * other 2xx statuses keep their status and headers, Location included. Safe methods are left
 * alone: section 4.2 is about the result of a modification, and an empty GET is no use to anyone.
 */
final readonly class ApplyPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $preferences = Preferences::fromRequest($request);

        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        if ($response->isSuccessful() && ! $request->isMethodSafe()) {
            if ($preferences->returnMinimal()) {
                $this->minimal($response);
                $preferences->applied('return', 'minimal');
            } elseif ($preferences->returnRepresentation() && $this->hasContent($response)) {
                $preferences->applied('return', 'representation');
            }
        }

        $this->writeApplied($request, $response);

        // Section 2: a server that might vary a response on a preference MUST list Prefer in Vary,
        // whether or not this request used it.
        $response->setVary('Prefer', false);

        return $response;
    }

    private function minimal(Response $response): void
    {
        if ($response->getStatusCode() === 200) {
            $response->setStatusCode(204);
        }

        $response->setContent('');
        $response->headers->remove('Content-Type');
        $response->headers->remove('Content-Length');
    }

    private function hasContent(Response $response): bool
    {
        $content = $response->getContent();

        return $content === false || $content !== '';
    }

    /**
     * Section 3: applied-pref = token [ BWS "=" BWS word ]. A value that is not a token is quoted.
     */
    private function writeApplied(Request $request, Response $response): void
    {
        $applied = $request->attributes->get(Preferences::APPLIED, []);

        if (! is_array($applied) || $applied === []) {
            return;
        }

        $values = [];

        foreach ($applied as $name => $value) {
            $values[] = match (true) {
                ! is_string($value) => (string) $name,
                preg_match("/^[!#$%&'*+\\-.^_`|~0-9A-Za-z]+$/", $value) === 1 => "{$name}={$value}",
                default => $name.'="'.addcslashes($value, '"\\').'"',
            };
        }

        $response->headers->set('Preference-Applied', implode(', ', $values));
    }
}
