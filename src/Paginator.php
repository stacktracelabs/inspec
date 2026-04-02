<?php


namespace StackTrace\Inspec;


abstract class Paginator
{
    public readonly string $name;

    public readonly array $object;

    public readonly array $query;

    public readonly string $metaKey;

    public readonly array $meta;

    public readonly ?int $perPageDefault;

    public readonly string $responseDescription;

    public readonly string $responseContentType;

    public readonly array $responseHeaders;

    final public function __construct(
        ?string $name = null,
        ?array $object = null,
        ?array $query = null,
        ?string $metaKey = null,
        array $meta = [],
        ?int $defaultPerPage = null,
        ?string $responseDescription = null,
        ?string $responseContentType = null,
        array $responseHeaders = [],
    ) {
        $this->name = trim($name ?? static::defaultName());
        $this->metaKey = trim($metaKey ?? static::defaultMetaKey());
        $this->responseDescription = trim($responseDescription ?? static::defaultResponseDescription());
        $this->responseContentType = trim($responseContentType ?? static::defaultResponseContentType());
        $this->responseHeaders = $responseHeaders === [] ? static::defaultResponseHeaders() : $responseHeaders;

        if ($this->name === '') {
            throw GeneratorException::withMessage('The pagination schema name cannot be empty.');
        }

        if ($this->metaKey === '') {
            throw GeneratorException::withMessage('The pagination meta key cannot be empty.');
        }

        if ($this->responseDescription === '') {
            throw GeneratorException::withMessage('The pagination response description cannot be empty.');
        }

        if ($this->responseContentType === '') {
            throw GeneratorException::withMessage('The pagination response content type cannot be empty.');
        }

        $this->object = $object ?? static::defaultObject();
        $this->meta = $meta;
        $this->perPageDefault = $defaultPerPage;
        $this->query = $this->applyDefaultPerPage(
            $query ?? static::defaultQuery($defaultPerPage),
            $defaultPerPage,
        );
    }

    public static function default(): static
    {
        return new static();
    }

    public function withSchema(string $name, array $object): static
    {
        return new static(
            name: $name,
            object: $object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withQuery(array $query): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withMetaKey(string $metaKey): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withMeta(array $meta): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: array_replace_recursive($this->meta, $meta),
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function defaultPerPage(?int $defaultPerPage): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $defaultPerPage,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withResponseDescription(string $description): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $description,
            responseContentType: $this->responseContentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withResponseContentType(string $contentType): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $contentType,
            responseHeaders: $this->responseHeaders,
        );
    }

    public function withResponseHeaders(array $headers): static
    {
        return new static(
            name: $this->name,
            object: $this->object,
            query: $this->query,
            metaKey: $this->metaKey,
            meta: $this->meta,
            defaultPerPage: $this->perPageDefault,
            responseDescription: $this->responseDescription,
            responseContentType: $this->responseContentType,
            responseHeaders: $headers,
        );
    }

    public function buildResponse(array $items, array $metaProperties): Response
    {
        return new Response(
            description: $this->responseDescription,
            contentType: $this->responseContentType,
            body: $this->buildResponseBody($items, $metaProperties),
            headers: $this->responseHeaders,
        );
    }

    protected function applyDefaultPerPage(array $query, ?int $defaultPerPage): array
    {
        if ($defaultPerPage === null) {
            return $query;
        }

        foreach ($query as $name => $description) {
            if (! is_string($name)) {
                continue;
            }

            if (Property::compile($name)->name !== 'limit') {
                continue;
            }

            $query[$name] = 'Number of results to return. Defaults to '.$defaultPerPage;
        }

        return $query;
    }

    abstract protected static function defaultName(): string;

    abstract protected static function defaultObject(): array;

    abstract protected static function defaultQuery(?int $defaultPerPage): array;

    abstract protected static function defaultMetaKey(): string;

    protected static function defaultResponseDescription(): string
    {
        return 'Successful response';
    }

    protected static function defaultResponseContentType(): string
    {
        return 'application/json';
    }

    protected static function defaultResponseHeaders(): array
    {
        return [];
    }

    abstract protected function buildResponseBody(array $items, array $metaProperties): ?array;
}
