<?php


namespace StackTrace\Inspec;


use Illuminate\Support\Arr;

class Operation
{
    public array $tags;

    public string $summary;

    public string $description;

    public array $route;

    public array $query;

    public ?array $request;

    public array|string|null $response;

    public array|string|null $paginatedResponse;

    public array|string|null $cursorPaginatedResponse;

    public int $responseCode;

    public array $additionalResponses;

    public bool $deprecated;

    public bool $multipart;

    public function __construct(
        array|string $tags = [],
        string $summary = '',
        string $description = '',
        array $route = [],
        array $query = [],
        ?array $request = null,
        array|string|null $response = null,
        array|string|null $paginatedResponse = null,
        array|string|null $cursorPaginatedResponse = null,
        int $responseCode = 200,
        array $additionalResponses = [],
        bool $deprecated = false,
        bool $multipart = false,
    ) {
        $this->tags = Arr::wrap($tags);
        $this->summary = trim($summary);
        $this->description = trim($description);
        $this->route = $route;
        $this->query = $query;
        $this->request = $request;
        $this->response = $response;
        $this->paginatedResponse = $paginatedResponse;
        $this->cursorPaginatedResponse = $cursorPaginatedResponse;
        $this->responseCode = $responseCode;
        $this->additionalResponses = $additionalResponses;
        $this->deprecated = $deprecated;
        $this->multipart = $multipart;
    }

    public static function fromRoute(Route $route): static
    {
        return new static(
            tags: $route->tags,
            summary: $route->summary,
            description: $route->description,
            route: $route->route,
            query: $route->query,
            request: $route->request,
            response: $route->response,
            paginatedResponse: $route->paginatedResponse,
            cursorPaginatedResponse: $route->cursorPaginatedResponse,
            responseCode: $route->responseCode,
            additionalResponses: $route->additionalResponses,
            deprecated: $route->deprecated,
            multipart: $route->multipart,
        );
    }

    public function tags(array|string $tags): static
    {
        $this->tags = Arr::wrap($tags);

        return $this;
    }

    public function summary(string $summary): static
    {
        $this->summary = trim($summary);

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = trim($description);

        return $this;
    }

    public function route(array $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function query(array $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function request(?array $request): static
    {
        $this->request = $request;

        return $this;
    }

    public function response(array|string|null $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function paginatedResponse(array|string|null $paginatedResponse): static
    {
        $this->paginatedResponse = $paginatedResponse;

        return $this;
    }

    public function cursorPaginatedResponse(array|string|null $cursorPaginatedResponse): static
    {
        $this->cursorPaginatedResponse = $cursorPaginatedResponse;

        return $this;
    }

    public function responseCode(int $responseCode): static
    {
        $this->responseCode = $responseCode;

        return $this;
    }

    public function additionalResponses(array $additionalResponses): static
    {
        $this->additionalResponses = $additionalResponses;

        return $this;
    }

    public function deprecated(bool $deprecated = true): static
    {
        $this->deprecated = $deprecated;

        return $this;
    }

    public function multipart(bool $multipart = true): static
    {
        $this->multipart = $multipart;

        return $this;
    }
}
