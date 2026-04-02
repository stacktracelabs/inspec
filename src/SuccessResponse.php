<?php


namespace StackTrace\Inspec;


abstract class SuccessResponse
{
    public readonly string $description;

    public readonly string $contentType;

    public readonly array $headers;

    final public function __construct(
        ?string $description = null,
        ?string $contentType = null,
        array $headers = [],
    ) {
        $this->description = trim($description ?? static::defaultDescription());
        $this->contentType = trim($contentType ?? static::defaultContentType());
        $this->headers = $headers === [] ? static::defaultHeaders() : $headers;

        if ($this->description === '') {
            throw GeneratorException::withMessage('The success response description cannot be empty.');
        }

        if ($this->contentType === '') {
            throw GeneratorException::withMessage('The success response content type cannot be empty.');
        }
    }

    public static function default(): static
    {
        return new static();
    }

    public function withDescription(string $description): static
    {
        return new static(
            description: $description,
            contentType: $this->contentType,
            headers: $this->headers,
        );
    }

    public function withContentType(string $contentType): static
    {
        return new static(
            description: $this->description,
            contentType: $contentType,
            headers: $this->headers,
        );
    }

    public function withHeaders(array $headers): static
    {
        return new static(
            description: $this->description,
            contentType: $this->contentType,
            headers: $headers,
        );
    }

    public function buildResponse(array $schema): Response
    {
        return new Response(
            description: $this->description,
            contentType: $this->contentType,
            body: $this->buildBody($schema),
            headers: $this->headers,
        );
    }

    abstract protected static function defaultDescription(): string;

    abstract protected static function defaultContentType(): string;

    abstract protected function buildBody(array $schema): ?array;

    protected static function defaultHeaders(): array
    {
        return [];
    }
}
