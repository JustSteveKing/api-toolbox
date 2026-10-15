<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Digest\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JustSteveKing\Toolbox\Digest\DigestAlgorithm;
use JustSteveKing\Toolbox\Digest\DigestField;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Adds Content-Digest and Repr-Digest to responses (RFC 9530).
 *
 *     ->middleware('digest.respond')          when the request asks with Want-Content-Digest or
 *                                             Want-Repr-Digest
 *     ->middleware('digest.respond:always')   Repr-Digest on every response, with sha-256 unless
 *                                             the request prefers otherwise
 *
 * Want- fields are only hints (section 4), so a request preferring nothing this supports is not an
 * error: it simply gets no digest it asked for.
 *
 * HEAD gets neither. RFC 9530 would allow Repr-Digest on a HEAD response, since it describes the
 * representation a GET would send, but Laravel's router has already emptied a HEAD response's
 * content by the time route middleware sees it, so the digest would be of nothing, and wrong.
 * Responses with no content to hash, streamed, 204 or 304, get neither too.
 */
final readonly class AddDigests
{
    public function handle(Request $request, Closure $next, string ...$options): Response
    {
        $response = $next($request);

        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Expected the next middleware to return a response.');
        }

        $content = $response->getContent();

        if (! is_string($content) || $request->isMethod('HEAD') || in_array($response->getStatusCode(), [204, 304], true)) {
            return $response;
        }

        $always = in_array('always', $options, true);

        $repr = DigestField::preferred($this->field($request, 'Want-Repr-Digest'))
            ?? ($always ? DigestAlgorithm::Sha256 : null);

        $contentAlgorithm = DigestField::preferred($this->field($request, 'Want-Content-Digest'));

        if ($repr !== null) {
            $response->headers->set('Repr-Digest', DigestField::serialize($repr, $content));
        }

        if ($contentAlgorithm !== null) {
            $response->headers->set('Content-Digest', DigestField::serialize($contentAlgorithm, $content));
        }

        return $response;
    }

    /**
     * @return list<string>
     */
    private function field(Request $request, string $name): array
    {
        return array_values(array_filter($request->headers->all(strtolower($name)), is_string(...)));
    }
}
