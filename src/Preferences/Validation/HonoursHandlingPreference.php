<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Preferences\Validation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use JustSteveKing\Toolbox\Preferences\Preferences;

/**
 * For a FormRequest: under Prefer: handling=strict (RFC 7240, section 4.4), fields the rules do
 * not know about fail validation instead of being ignored.
 *
 * Section 4.4 leaves what counts as a recoverable error to the server. Laravel already rejects
 * invalid values either way; what it forgives is input it was not asked to validate, so that is
 * what strict handling stops forgiving. Only top-level fields are checked: a rule for
 * "items.*.name" covers everything under "items".
 *
 *     final class StoreOrder extends FormRequest
 *     {
 *         use HonoursHandlingPreference;
 *     }
 *
 * @phpstan-require-extends FormRequest
 */
trait HonoursHandlingPreference
{
    public function withValidator(Validator $validator): void
    {
        // The application's request, not this form request: Laravel builds a form request as a copy,
        // and Preference-Applied is read from the original.
        // With the feature off nothing is bound, so the header is read directly and Preference-Applied
        // goes unwritten, as nothing would write it.
        $preferences = $this->container->bound(Preferences::class)
            ? $this->container->make(Preferences::class)
            : Preferences::fromRequest($this);

        if (! $preferences->handlingStrict()) {
            return;
        }

        $preferences->applied('handling', 'strict');

        $known = [];

        $rules = $this->container->call([$this, 'rules']);

        foreach (is_array($rules) ? array_keys($rules) : [] as $rule) {
            $known[explode('.', (string) $rule)[0]] = true;
        }

        $validator->after(function (Validator $validator) use ($known): void {
            foreach (array_keys($this->all()) as $field) {
                if (! isset($known[(string) $field])) {
                    $validator->errors()->add((string) $field, "The {$field} field is not recognised, and the request asked for strict handling.");
                }
            }
        });
    }
}
