<?php


namespace StackTrace\Inspec;


abstract class Response
{
    public readonly string $name;

    public readonly string $description;

    public readonly string $contentType;

    public readonly ?array $body;

    public readonly array $headers;

    final public function __construct(
        ?string $name = null,
        ?string $description = null,
        ?string $contentType = null,
        ?array $body = null,
        array $headers = [],
    ) {
        $this->name = trim($name ?? static::defaultName());
        $this->description = trim($description ?? static::defaultDescription());
        $this->contentType = trim($contentType ?? static::defaultContentType());
        $this->body = $body ?? static::defaultBody();
        $this->headers = $headers === [] ? static::defaultHeaders() : $headers;

        if ($this->name === '') {
            throw GeneratorException::withMessage('The response component name cannot be empty.');
        }

        if ($this->description === '') {
            throw GeneratorException::withMessage('The response description cannot be empty.');
        }

        if ($this->contentType === '') {
            throw GeneratorException::withMessage('The response content type cannot be empty.');
        }
    }

    public static function default(): static
    {
        return new static();
    }

    public function withName(string $name): static
    {
        return new static(
            name: $name,
            description: $this->description,
            contentType: $this->contentType,
            body: $this->body,
            headers: $this->headers,
        );
    }

    public function withDescription(string $description): static
    {
        return new static(
            name: $this->name,
            description: $description,
            contentType: $this->contentType,
            body: $this->body,
            headers: $this->headers,
        );
    }

    public function withContentType(string $contentType): static
    {
        return new static(
            name: $this->name,
            description: $this->description,
            contentType: $contentType,
            body: $this->body,
            headers: $this->headers,
        );
    }

    public function withBody(?array $body): static
    {
        return new static(
            name: $this->name,
            description: $this->description,
            contentType: $this->contentType,
            body: $body,
            headers: $this->headers,
        );
    }

    public function withHeaders(array $headers): static
    {
        return new static(
            name: $this->name,
            description: $this->description,
            contentType: $this->contentType,
            body: $this->body,
            headers: $headers,
        );
    }

    abstract protected static function defaultName(): string;

    abstract protected static function defaultDescription(): string;

    abstract protected static function defaultContentType(): string;

    abstract protected static function defaultBody(): ?array;

    protected static function defaultHeaders(): array
    {
        return [];
    }
}
