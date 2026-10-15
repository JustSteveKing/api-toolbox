<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustSteveKing\Toolbox\Preferences\Preferences;
use JustSteveKing\Toolbox\Tests\Fixtures\Requests\StoreLead;

beforeEach(function (): void {
    Route::middleware('prefer')->group(function (): void {
        Route::post('api/items', fn () => response()->json(['id' => 123, 'name' => 'Widget'], 201, ['Location' => '/api/items/123']));
        Route::patch('api/items/123', fn () => ['id' => 123, 'name' => 'Gadget']);
        Route::get('api/items/123', fn () => ['id' => 123]);
        Route::post('api/leads', fn (StoreLead $request) => response()->json($request->validated(), 201));
        Route::post('api/hinted', function (Preferences $preferences) {
            $preferences->applied('wait', '10');

            return response()->noContent();
        });
    });
});

it('empties a 201 for return=minimal, keeping Location, as section 4.2 shows', function (): void {
    $this->postJson('api/items', [], ['Prefer' => 'return=minimal'])
        ->assertStatus(201)
        ->assertContent('')
        ->assertHeader('Location', '/api/items/123')
        ->assertPreferenceApplied('return', 'minimal');
});

it('turns a 200 into 204 No Content for return=minimal', function (): void {
    $this->patchJson('api/items/123', [], ['Prefer' => 'return=minimal'])
        ->assertNoContent()
        ->assertPreferenceApplied('return', 'minimal');
});

it('says when it returned the representation (section 3)', function (): void {
    $this->patchJson('api/items/123', [], ['Prefer' => 'return=representation'])
        ->assertOk()
        ->assertJson(['name' => 'Gadget'])
        ->assertHeader('Preference-Applied', 'return=representation');
});

it('leaves safe methods alone', function (): void {
    $this->getJson('api/items/123', ['Prefer' => 'return=minimal'])
        ->assertOk()
        ->assertJson(['id' => 123])
        ->assertHeaderMissing('Preference-Applied');
});

it('honours neither of a conflicting pair', function (): void {
    $this->postJson('api/items', [], ['Prefer' => 'return=minimal, return=representation'])
        ->assertCreated()
        ->assertJson(['id' => 123])
        ->assertHeaderMissing('Preference-Applied');
});

it('always sends Vary: Prefer, whether or not the client used it (section 2)', function (): void {
    $this->postJson('api/items')->assertHeader('Vary', 'Prefer');
    $this->getJson('api/items/123')->assertHeader('Vary', 'Prefer');
});

it('lists whatever the application honoured', function (): void {
    $this->postJson('api/hinted', [], ['Prefer' => 'wait=10'])->assertHeader('Preference-Applied', 'wait=10');
});

describe('handling=strict', function (): void {
    it('rejects fields the rules do not know about', function (): void {
        $this->postJson('api/leads', ['name' => 'Ada', 'nmae' => 'typo'], ['Prefer' => 'handling=strict'])
            ->assertStatus(422)
            ->assertJsonPath('errors.nmae.0', 'The nmae field is not recognised, and the request asked for strict handling.')
            ->assertPreferenceApplied('handling', 'strict');
    });

    it('counts nested rules as knowing their top-level field', function (): void {
        $this->postJson('api/leads', ['name' => 'Ada', 'items' => [['sku' => 'A1']]], ['Prefer' => 'handling=strict'])
            ->assertCreated();
    });

    it('forgives unknown fields otherwise, as Laravel does', function (?string $prefer): void {
        $this->postJson('api/leads', ['name' => 'Ada', 'nmae' => 'typo'], array_filter(['Prefer' => $prefer]))
            ->assertCreated()
            ->assertExactJson(['name' => 'Ada']);
    })->with([null, 'handling=lenient']);
});
