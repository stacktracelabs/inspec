<?php


namespace StackTrace\Inspec;


use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Property
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array  $typeArgs,
        public readonly bool   $nonNullable,
        public readonly bool   $optional,
        public readonly array  $args
    ) { }

    public function isArray(): bool
    {
        return $this->type == 'array';
    }

    public function arrayItemType(): ?string
    {
        return Arr::first($this->typeArgs);
    }

    protected function enumArg(): ?array
    {
        return collect($this->args)->firstWhere(fn ($it) => Arr::first($it) == 'enum');
    }

    public function isEnum(): bool
    {
        return $this->enumArg() != null;
    }

    public function isFile(): bool
    {
        return $this->type == 'file';
    }

    public function enumCases(): array
    {
        $cases = $this->enumArg()['args'];

        if (count($cases) == 1 && enum_exists($cases[0])) {
            return collect($cases[0]::cases())->map->value->all();
        }

        return $cases;
    }

    public static function compile(string $property): static
    {
        $segments = collect(explode('|', $property))
            ->map(function ($segment) {
                $parts = explode(':', $segment);
                $name = Arr::first($parts);
                $args = Arr::last($parts);
                $args = is_string($args) && !empty($args) ? explode(',', $args) : [];

                return [
                    'name' => $name,
                    'args' => $args,
                ];
            });

        // First segment is property name and type definition.
        $typeSegment = $segments->shift();

        if (! is_array($typeSegment)) {
            throw new \LogicException("The first segment must be a property name and type definition.");
        }

        $propName = $typeSegment['name'];

        return new static(
            name: rtrim($propName, '?!'),
            type: Arr::first($typeSegment['args']) ?: 'unknown',
            typeArgs: count($typeSegment['args']) > 1 ? array_slice($typeSegment['args'], 1, count($typeSegment['args']) - 1) : [],
            nonNullable: Str::contains($propName, '!'),
            optional: Str::contains($propName, '?'),
            args: $segments->all()
        );
    }
}
