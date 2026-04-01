<?php


namespace StackTrace\Inspec;


use Attribute;
use Illuminate\Support\Arr;

#[Attribute]
class Route
{
    public readonly array $tags;

    public function __construct(
        array|string  $tags = [],
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly array $route = [],
        public readonly array $query = [],
        public readonly ?array $request = null,
        public readonly ?array $response = null,
        public readonly array|string|null $paginatedResponse = null,
        public readonly array|string|null $cursorPaginatedResponse = null,
        public readonly ?array $paginatedMeta = null,
        public readonly int    $responseCode = 200,
        public readonly array $responses = [],
        public readonly ?int $defaultPerPage = null,
        public readonly bool $deprecated = false,
        public readonly bool $multipart = false,
    ) {
        $this->tags = Arr::wrap($tags);
    }
}
