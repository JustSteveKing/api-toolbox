<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\MessageSignatures;

use Illuminate\Http\Request;

/**
 * The signature a request was accepted on, kept on the request so the application can tell who
 * signed it:
 *
 *     VerifiedSignature::of($request)?->keyId
 */
final readonly class VerifiedSignature
{
    public const string ATTRIBUTE = '_api_toolbox_message_signature';

    /**
     * @param  list<string>  $components
     */
    public function __construct(
        public string $label,
        public string $keyId,
        public ?int $created,
        public array $components,
    ) {}

    public static function of(Request $request): ?self
    {
        $signature = $request->attributes->get(self::ATTRIBUTE);

        return $signature instanceof self ? $signature : null;
    }
}
