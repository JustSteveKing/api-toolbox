<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;
use JustSteveKing\Toolbox\Preferences\Validation\HonoursHandlingPreference;

final class StoreLead extends FormRequest
{
    use HonoursHandlingPreference;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'items.*.sku' => ['string'],
        ];
    }
}
