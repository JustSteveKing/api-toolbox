<?php

declare(strict_types=1);

/*
 * The detail of every problem this package raises itself. An application translates them by
 * publishing this file (php artisan vendor:publish --tag=api-toolbox-lang) and adding
 * lang/vendor/api-toolbox/{locale}/problems.php. The problem's type and title do not change with
 * the language, so clients can keep branching on the type.
 */
return [
    'conditional_requests' => [
        'precondition_failed' => 'The resource has changed since you last fetched it. Fetch it again and retry with its current ETag.',
        'precondition_required' => 'This request must be conditional. Fetch the resource, then retry with its ETag in If-Match.',
    ],

    'pagination' => [
        'invalid_cursor' => 'The :parameter parameter could not be decoded.',
    ],

    'idempotency' => [
        'conflict' => 'This Idempotency-Key was already used with a different request payload. Resend the original payload, or use a new key for a new request.',
        'in_flight' => 'A request with this Idempotency-Key is still being processed. Retry the same request after it completes.',
        'missing' => 'This operation requires an Idempotency-Key header.',
        'malformed' => 'The Idempotency-Key header must be a string of 1 to 255 visible ASCII characters, such as "8e03978e-40d5-43e8-bc93-6894a57f9324".',
    ],

    'negotiation' => [
        'not_acceptable' => 'This endpoint can only produce :types, which does not match the Accept header.',
        'missing_content_type' => 'The request has content but no Content-Type. Supported content types: :types.',
        'unsupported_media_type' => "The Content-Type ':type' is not supported. Supported content types: :types.",
    ],

    'async_operations' => [
        'not_found' => 'No operation with this ID is known.',
    ],

    'merge_patch' => [
        'malformed' => 'The merge patch could not be parsed as JSON: :reason.',
        'missing_content_type' => 'A PATCH needs a Content-Type of :type.',
        'unsupported_type' => "The patch format ':received' is not supported. Send :type.",
    ],

    'digest' => [
        'missing' => 'This request needs a Content-Digest or Repr-Digest field.',
        'not_a_dictionary' => ':field is not a Structured Field Dictionary (RFC 9651, section 3.2).',
        'unsupported_algorithms' => 'None of the hashing algorithms in the request is supported. Use sha-256 or sha-512.',
        'invalid_values' => 'A digest in the request cannot have come from its hashing algorithm.',
        'mismatched_values' => 'A digest in the request does not match its content. It may have been changed in transit.',
    ],

    'message_signatures' => [
        'malformed' => 'Signature or Signature-Input is not a valid Dictionary: :error',
        'verification_failed' => "The request's signature could not be verified: :reason",
        'reasons' => [
            'not_signed' => 'the request is not signed.',
            'unknown_key' => 'no signature uses a known key.',
            'algorithm_mismatch' => 'the alg parameter does not match the key.',
            'missing_tag' => 'the signature does not carry the required tag.',
            'missing_nonce' => 'the signature has no nonce.',
            'not_covered' => 'the signature does not cover :component.',
            'unresolvable_component' => 'a covered component could not be resolved.',
            'content_digest_mismatch' => 'Content-Digest does not match the content.',
            'mismatch' => 'the signature does not match.',
            'missing_created' => 'the signature has no created time.',
            'created_in_future' => 'the signature was created in the future.',
            'too_old' => 'the signature is too old.',
            'expired' => 'the signature has expired.',
        ],
    ],
];
