<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Each feature maps to one HTTP standard. A feature that is not listed here
    | registers nothing at all. Options are passed inline to the feature.
    |
    */

    'features' => [
        Features::problemDetails([
            // Where problem type slugs resolve to. Point this at your docs.
            'type_base_uri' => '/problems',
            // Paths that always get problem details, whatever the request's Accept header.
            'paths' => ['api/*'],
            // Type plain HTTP errors with the apiguide.dev catalogue rather than about:blank.
            'apiguide' => true,
            // The challenge a 401 carries, which RFC 9110 section 15.5.2 says it MUST.
            'www_authenticate' => 'Bearer',
        ]),
        Features::deprecation([
            // Run on every request; turn off to add the "deprecation" middleware per route instead.
            'global' => true,
        ]),
        Features::conditionalRequests(),
        Features::pagination(),
        Features::idempotency([
            // Seconds a completed response is kept for replay, and so how long a key is valid.
            // The draft (section 2.5.2) says to publish this in your API documentation.
            'ttl' => 86400,
        ]),
        Features::negotiation([
            // No standard defines a request ID header; this is the common convention. false turns
            // request IDs off.
            'request_id_header' => 'X-Request-Id',
            // Keep a well-formed request ID a proxy or client sent, rather than replace it.
            'trust_request_id' => true,
            // W3C Trace Context on every request, and onto outgoing HTTP client requests.
            'trace_context' => true,
            'propagate_trace' => true,
        ]),
        Features::preferences(),
        Features::asyncOperations([
            // Where the status monitor lives, and the middleware it runs behind.
            'path' => 'api/operations',
            'middleware' => ['api'],
            // Seconds an operation's status is kept after its last change.
            'ttl' => 86400,
            // Retry-After, in seconds, while an operation is still running.
            'retry_after' => 5,
        ]),
        Features::mergePatch(),
        Features::digests(),
        // Experimental: built on an IETF draft that can still change.
        // Features::rateLimitHeaders(),
    ],

];
