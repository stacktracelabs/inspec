<?php


namespace StackTrace\Inspec;


abstract class PaginationDefinition
{
    public readonly string $name;

    public readonly array $object;

    public readonly array $query;

    public readonly string $metaKey;

    public readonly array $meta;

    public readonly ?int $perPageDefault;

    final public function __construct(
        ?string $name = null,
        ?array $object = null,
        ?array $query = null,
        ?string $metaKey = null,
        array $meta = [],
        ?int $defaultPerPage = null,
    ) {
        $this->name = trim($name ?? static::defaultName());
        $this->metaKey = trim($metaKey ?? static::defaultMetaKey());

        if ($this->name === '') {
            throw GeneratorException::withMessage('The pagination schema name cannot be empty.');
        }

        if ($this->metaKey === '') {
            throw GeneratorException::withMessage('The pagination meta key cannot be empty.');
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
}
