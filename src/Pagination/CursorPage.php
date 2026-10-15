<?php

declare(strict_types=1);

namespace JustSteveKing\Toolbox\Pagination;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cursor page whose body is the items alone, with navigation in Link headers:
 *
 *     return CursorPage::of(Post::query()->cursorPaginate(20), PostResource::class);
 *
 *     Link: <https://api.example.com/posts?cursor=eyJpZCI6MjB9>; rel="next"
 *     Link: <https://api.example.com/posts>; rel="first"
 *
 *     [{"id": 1, ...}, {"id": 2, ...}]
 */
final readonly class CursorPage implements Responsable
{
    /**
     * @param  CursorPaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     */
    public function __construct(
        private CursorPaginator $paginator,
        private ?string $resource = null,
    ) {}

    /**
     * @param  CursorPaginator<array-key, mixed>  $paginator
     * @param  class-string<JsonResource>|null  $resource
     */
    public static function of(CursorPaginator $paginator, ?string $resource = null): self
    {
        return new self($paginator, $resource);
    }

    /**
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        $items = $this->resource === null
            ? $this->paginator->items()
            : $this->resource::collection($this->paginator->items())->resolve($request);

        $response = new JsonResponse($items, 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach (PaginationLinks::for($this->paginator) as $link) {
            $response->headers->set('Link', $link->toHeaderValue(), false);
        }

        return $response;
    }
}
