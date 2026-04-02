<?php


namespace StackTrace\Inspec;


use Attribute;
use Illuminate\Support\Arr;

#[Attribute]
readonly class Route
{
    public array $tags;

    /**
     * @param array|string $tags Group or groups this operation belongs to.
     * @param string $summary Short summary of the endpoint.
     * @param string $description Longer endpoint description. Stored on the attribute, but not currently emitted.
     * @param array $route Route parameter specification using the Property DSL.
     * @param array $query Query parameter specification using the Property DSL.
     * @param array|null $request Request body fields.
     * @param array|null $response Primary success response body.
     * @param array|string|null $paginatedResponse Paginated `data` collection backed by a transformer class.
     * @param array|string|null $cursorPaginatedResponse Cursor-paginated `data` collection backed by a transformer class.
     * @param int $responseCode Status code for the primary success response.
     * @param array $additionalResponses Additional response codes. Values may be null, strings, or Response definitions.
     * @param bool $deprecated Marks the endpoint as deprecated.
     * @param bool $multipart Forces `multipart/form-data` even without `file` fields.
     */
    public function __construct(
        array|string             $tags = [],
        public string            $summary = '',
        public string            $description = '',
        public array             $route = [],
        public array             $query = [],
        public ?array            $request = null,
        public ?array            $response = null,
        public array|string|null $paginatedResponse = null,
        public array|string|null $cursorPaginatedResponse = null,
        public int               $responseCode = 200,
        public array             $additionalResponses = [],
        public bool              $deprecated = false,
        public bool              $multipart = false,
    ) {
        $this->tags = Arr::wrap($tags);
    }
}
