<?php


namespace StackTrace\Inspec;


use Attribute;
use Illuminate\Support\Arr;

#[Attribute]
readonly class Route
{
    public array $tags;

    public function __construct(
        array|string  $tags = [],
        public string $summary = '',
        public string $description = '',
        public array $route = [],
        public array $query = [],
        public ?array $request = null,
        public ?array $response = null,
        public array|string|null $paginatedResponse = null,
        public array|string|null $cursorPaginatedResponse = null,
        public ?array $paginatedMeta = null,
        public int    $responseCode = 200,
        public array $responses = [],
        public ?int $defaultPerPage = null,
        public bool $deprecated = false,
        public bool $multipart = false,
    ) {
        $this->tags = Arr::wrap($tags);
    }
}
