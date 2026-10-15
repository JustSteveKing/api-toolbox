<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use JustSteveKing\Toolbox\Pagination\CursorPage;
use JustSteveKing\Toolbox\Testing\Parsers\LinkHeader;

final class Post extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->resource->id, 'name' => strtoupper((string) $this->resource->title)];
    }
}

beforeEach(function (): void {
    Schema::create('posts', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
    });

    DB::table('posts')->insert(array_map(fn (int $i) => ['title' => "post {$i}"], range(1, 5)));

    Route::get('api/posts', fn () => CursorPage::of(Post::query()->orderBy('id')->cursorPaginate(2)));
    Route::get('api/posts/resource', fn () => CursorPage::of(Post::query()->orderBy('id')->cursorPaginate(2), PostResource::class));
    Route::get('api/posts/filtered', fn () => CursorPage::of(Post::query()->orderBy('id')->cursorPaginate(2)->withQueryString()));
    Route::get('api/posts/plain', fn () => Post::query()->orderBy('id')->cursorPaginate(2));
});

function linkFor(Illuminate\Testing\TestResponse $response, string $rel): ?string
{
    return LinkHeader::withRel($response->headers->all('link'), $rel)[0]['uri'] ?? null;
}

it('puts the items alone in the body', function (): void {
    $this->getJson('api/posts')->assertOk()->assertExactJson([
        ['id' => 1, 'title' => 'post 1'],
        ['id' => 2, 'title' => 'post 2'],
    ]);
});

it('writes next and first as RFC 8288 link-values, with no prev on the first page', function (): void {
    $response = $this->getJson('api/posts');

    expect($response->headers->all('link'))->toHaveCount(2)
        ->and($response->headers->all('link')[0])->toMatch('/^<http:\/\/localhost\/api\/posts\?cursor=[A-Za-z0-9_-]+>; rel="next"$/')
        ->and($response->headers->all('link')[1])->toBe('<http://localhost/api/posts>; rel="first"');

    $response->assertNoLinkHeader('prev');
});

it('walks forward and back through the pages by their links', function (): void {
    $second = $this->getJson(linkFor($this->getJson('api/posts'), 'next'));

    $second->assertJsonPath('0.id', 3)->assertLinkHeader('prev')->assertLinkHeader('next');

    $this->getJson(linkFor($second, 'prev'))->assertJsonPath('0.id', 1);

    $last = $this->getJson(linkFor($second, 'next'));

    $last->assertExactJson([['id' => 5, 'title' => 'post 5']])->assertNoLinkHeader('next')->assertLinkHeader('first', 'http://localhost/api/posts');
});

it('shapes items through a resource', function (): void {
    $this->getJson('api/posts/resource')->assertExactJson([
        ['id' => 1, 'name' => 'POST 1'],
        ['id' => 2, 'name' => 'POST 2'],
    ]);
});

it('keeps the query string in every link when asked to', function (): void {
    $response = $this->getJson('api/posts/filtered?sort=id');

    expect(linkFor($response, 'first'))->toBe('http://localhost/api/posts/filtered?sort=id')
        ->and(linkFor($response, 'next'))->toContain('sort=id');
});

it('rejects a cursor it cannot decode with the apiguide.dev type, rather than serving page one', function (string $query): void {
    $this->getJson("api/posts?{$query}")
        ->assertStatus(400)
        ->assertProblemType('https://apiguide.dev/errors/invalid-pagination-cursor', 400)
        ->assertJsonPath('detail', 'The cursor parameter could not be decoded.');
})->with(['cursor=not-valid-base64', 'cursor[]=x', 'cursor=eyJpZCI6MX0']);

it('treats a missing or empty cursor as the first page', function (string $uri): void {
    $this->getJson($uri)->assertOk()->assertJsonPath('0.id', 1);
})->with(['api/posts', 'api/posts?cursor=']);

it('rejects bad cursors for every cursor paginator, not only CursorPage', function (): void {
    $this->getJson('api/posts/plain?cursor=garbage')->assertStatus(400);
});
